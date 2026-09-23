# Research: Resident World Agency

## R1. Where the idle agent loop runs

- **Decision**: The world page in the browser drives the loop. When a resident's idle wait ends, the page requests one decision from the server. The server makes one model call and returns a single step. The page executes the step and reports its outcome with the next decision request. The server holds no loop state between requests.
- **Rationale**: FR-031 requires that nothing happens while the user is not in the world. A loop driven by the open world page stops by construction when the page closes or is hidden, so no presence tracking, timers or background jobs are needed on the server. Every step is still decided by the model with the previous step's outcome in its context (FR-020, FR-026). Actions execute in the browser anyway, so a server-side loop would need a round trip per step regardless.
- **Alternatives considered**: A queued server-side loop that pushes steps over Reverb presence channels and waits for outcomes. It needs presence bookkeeping, cancellation when the user leaves, and a way to park a job while the browser executes a step, for no user-visible gain. The existing `AgentLoopRunner` runs tools synchronously inside one request, which cannot wait for an action that plays out over seconds in the browser.

## R2. Presence

- **Decision**: The user is "in the world" while the world page is mounted and the browser tab is visible (`document.visibilityState === 'visible'`). Hiding the tab or leaving the page pauses every resident's loop, cancels in-flight steps, and discards unfinished plans (FR-032).
- **Rationale**: This matches the user's rule that nothing happens without them present, including the case where the page is open in a background tab.
- **Alternatives considered**: Tracking presence on the server with a heartbeat. It duplicates what the page already knows, and is only needed if the server drives the loop (see R1).

## R3. Marker format inside the environment file

- **Decision**: Markers are glTF nodes carrying an `extras.vera` object with a `type` of `floor`, `zone`, `object`, `spot` or `entry`. The node's transform gives position and facing. The complete format is in [contracts/environment-markers.md](contracts/environment-markers.md).
- **Rationale**: The loader already copies node `extras` into `userData`; the pool's `passable` flag uses the same mechanism. Blender exports custom properties on empties as `extras`, so markers can be authored by hand in Blender or written by a generator. Namespacing under `vera` keeps markers separate from any other extras an asset carries.
- **Alternatives considered**: A sidecar JSON file uploaded next to the GLB. It can drift from the geometry and needs a second upload field. Encoding markers in node names. Names cannot hold descriptions, activity lists or outlines.

## R4. Parsing markers on upload

- **Decision**: On environment upload or replacement, a PHP action reads the GLB header and JSON chunk, walks the node tree composing each node's translation, rotation and scale into world space, and collects `extras.vera` markers. The result is validated and stored as the world's layout. Invalid markers are skipped and returned as warnings in the upload response (FR-034a).
- **Rationale**: Only the JSON chunk is needed; no mesh or texture processing is involved, so the parse is small and fully testable in Pest with fixture GLBs built in the test. Storing the parsed layout lets the server resolve zones and build prompts without the file.
- **Alternatives considered**: Parsing in the browser and posting the layout to the server. It makes the server trust client-built layout data and cannot be covered by Pest feature tests.

## R5. Storing the layout

- **Decision**: A single JSON `layout` column on `worlds`, holding floors, zones, objects and spots, replaced wholesale on every environment upload.
- **Rationale**: The layout is always written as a whole and read as a whole. Nothing queries individual zones or spots in SQL. Spot occupancy is runtime state held by the world page, not stored data.
- **Alternatives considered**: Separate tables for floors, zones, objects and spots. They add four models and factories for data that is never queried by row.

## R6. Who resolves zones from positions

- **Decision**: The world page sends raw positions (the user's position and each relevant resident's position) with every message and decision request. The server resolves floor and zone from the stored layout and builds the prompt text.
- **Rationale**: Zone containment (floor height range, then point-in-outline, then innermost nested zone) is pure logic over stored data, so it is covered by Pest feature tests, and the prompt text is produced in one place.
- **Alternatives considered**: The page resolving zones and sending names. Simple, but it moves core logic into untested client code and lets prompt text depend on client state.

## R7. Route-finding

- **Decision**: When the environment loads, the page builds a walkable grid on the existing collision octree: 0.25 m cells, sampling every distinct ground level per column within the layout's floor height ranges (or the environment bounds when there are no floors). Neighbouring cells are connected when the existing step and drop limits allow moving between them and the body-clearance check passes. Routes are found with A* and smoothed by skipping waypoints that have a clear straight line. Residents walk routes using the existing `WorldCollision.move`.
- **Rationale**: It reuses the exact walkability rules the user moves by (FR-012), so stairs, pool steps and water behave the same for residents and users, with no new dependency. The penthouse grid is roughly 24,000 columns, which builds in well under two seconds and can be spread over frames during the existing loading screen.
- **Alternatives considered**: Generating a navigation mesh with recast-navigation (a WebAssembly dependency). It produces smoother paths but adds a dependency and a second set of walkability rules that can disagree with the collision system.

## R8. Walking animation

- **Decision**: Ship one walk clip and one idle clip as application assets in VRMA format, used by every resident while moving and standing. An assistant pose named `walk` or `idle` overrides the default for that assistant.
- **Rationale**: FR-013 requires visible walking, and today residents have no locomotion clip at all. Shared defaults make every resident walk without per-assistant setup.
- **Alternatives considered**: Procedural leg animation. It looks mechanical and is more code than playing a clip. Requiring every assistant to upload a walk pose. It leaves residents gliding until someone does.
- **Open item**: Source the clip (for example, a Mixamo walk converted to VRMA). Mixamo's terms allow use of its animations inside projects. The conversion is a one-time asset task in `tasks.md`.

## R9. Poses at spots

- **Decision**: A spot's node position is the root placement for the resident while she performs the activity, and the node's +Z axis is her facing. Each activity names a pose from her library and a mode: `hold` plays the clip and keeps its last frame until she leaves; `once` plays it and returns to idle. She walks to an approach point 0.6 m in front of the spot on walkable ground, then blends to the spot placement over 0.4 s.
- **Rationale**: Separating the approach point from the spot placement lets spots sit on furniture (a lounger, a bar stool) that the walkable grid never reaches. Holding the last frame turns existing one-shot sitting or lying clips into held poses without new animation data.
- **Alternatives considered**: Deriving placement from the furniture's geometry. It needs per-pose knowledge of hip height and fails for arbitrary furniture shapes.

## R10. Action tags

- **Decision**: Actions ride on the existing tag convention, next to `[pose: …]`: `[action: go_to <id>]`, `[action: use <spot-id> <activity-id>]`, `[action: follow]`, `[action: stop]`, `[action: stay]`. Identifiers are the marker ids from the layout. The complete grammar is in [contracts/action-tags.md](contracts/action-tags.md).
- **Rationale**: `LlmResponseTagParser` already strips `[identifier: value]` tags and collects them, and the world chat already reacts to `[pose: …]`. Tags work with every configured model, including ones without tool support.
- **Alternatives considered**: Native tool calls. They are more structured, but only available on models flagged `supports_tools`, and conversation replies in this app are not tool-driven.

## R11. How the resident learns outcomes

- **Decision**: Every executed action is recorded as a resident activity with its outcome. The prompt for her next reply or decision includes a short "recent activity" block: the last 8 activities with zone, reason, outcome and how long ago. The page reports outcomes through a dedicated endpoint.
- **Rationale**: Outcomes must reach her before she continues (FR-020), whether the next model call is an idle decision or a user message. A structured record also produces the recent history the idle decisions depend on (FR-022).
- **Alternatives considered**: Storing outcomes as conversation messages. It clutters the visible conversation, and the history would be lost from the prompt once long-term memory summarizes older messages.

## R12. Idle decision prompt and rate limit

- **Decision**: An idle decision uses the resident's own conversation model (clarification 2026-09-22) with her normal system prompt, the world context, the resolved world state, her recent activity, and a list of available activities: zone activities, spots with their activities and whether each is free, and every pose in her library. She must answer with one line, `(reason) *action*`, followed by exactly one action or pose tag. The server rejects a decision request for the same resident within 8 seconds of the previous one (FR-029). The page staggers residents' idle waits independently from the 10–60 second window.
- **Rationale**: One constrained line per decision keeps calls cheap and parsing reliable. The 8-second floor protects against runaway requests while leaving the 10-second minimum wait untouched.

## R13. Walking and talking

- **Decision**: The world stays active while a conversation is open. The chat panel becomes an overlay. Keys reach movement unless the message box is focused; `Enter` focuses it, and sending or pressing `Esc` blurs it. Closing the conversation uses a close button and the `C` key, which is free while a conversation is open because starting a second one is not allowed (FR-045). Exploration stays enabled with a chat open, and residents are no longer paused by it.
- **Rationale**: The focus rule guarantees keys never both type and move (FR-040), and `Esc` keeps its familiar "leave the text box" meaning.

## R14. Voice in the world

- **Decision**: Reuse the web chat's `useVoiceMode` (VAD-based, hands-free), the `voice.transcribe` route and the `voice.synthesize` route. In the world, the synthesized reply is decoded into a buffer and played through a three.js `PositionalAudio` attached to the speaking resident, with an `AudioListener` on the camera (FR-043).
- **Rationale**: Transcription and synthesis already work; only the playback path changes to make the voice positional.

## R15. Map and name tags

- **Decision**: After the environment loads, render one top-down image per floor with an orthographic camera and a clipping plane at the floor's upper height, so upper floors are removed (FR-057). The minimap and full-screen map are HTML overlays drawing those images with zone labels and live markers. Name tags are sprites with depth testing off (visible through walls, FR-050), scaled to stay readable at any distance. The off-screen indicator is an HTML element placed on the screen edge in the direction of the conversation resident.
- **Rationale**: No new dependency. The render happens once per floor at load, so there is no per-frame cost beyond moving markers.

## R16. Which residents act on their own

- **Decision**: Add an `autonomous` value to the resident behavior setting. Only autonomous residents run idle decisions. `stationary` residents stay put, and `roam` keeps its current behavior.
- **Rationale**: Worlds need residents that stay in place, such as a shopkeeper at the faire, and existing worlds keep their configured behavior.
