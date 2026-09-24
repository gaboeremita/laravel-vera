# Research: Player World Interaction

## R1. Where the user is (zone lookup on the client)

**Decision**: A new `worldLocation.js` ports `ResolveWorldState::zoneAt`, `floorAt` and `zoneChain` to JavaScript: the innermost zone whose height range and ground outline contain the user's foot position, restricted to the user's floor. A `LocationTracker` inside the canvas evaluates it every 150 ms and reports only changes to the page. A zone crossing shows the title card unless the user left that zone less than 2 s earlier (FR-004).

**Rationale**: The title card must appear within half a second of a crossing (SC-001), so a server round trip per step is out. The server already owns the reference algorithm; the port mirrors it line for line, and a unit test runs both against the same layout fixture so they cannot drift.

**Alternatives considered**: Asking the server on every position save (10 s cadence, far too slow). Precomputing a zone raster per floor from the map renderer (fast lookups, but a second source of truth for zone borders and extra memory for The Index's three floors).

## R2. Player movement: running and swimming

**Decision**: `FirstPersonController` gains a movement mode derived each frame:

- **Running**: Shift doubles speed from 3.5 to 7 m/s. Collision still runs through `WorldCollision.move`, which already subdivides movement into 8 cm steps, so faster movement cannot tunnel through walls. The field of view eases from 70° to 76° while running, for a sense of speed.
- **Swimming**: the body keeps moving along the pool floor through the existing collision world, so pool walls, steps and objects block it exactly as today. Only the view changes: when `waterSurfaceAbove` reports water deeper than 1.1 m over the feet, the eye sits 0.12 m above the surface with a 2 cm, 0.5 Hz bob. The user leaves swimming below 0.9 m. These are the thresholds residents use, moved from `ResidentController.jsx` into `collisionCheck.js` as `SWIM_DEPTH` and `LEAVE_WATER_DEPTH` so both read the same values. Swimming speed is 0.55 × walking, and 0.85 × walking with Shift.

**Rationale**: Keeping the body on the pool floor reuses every collision rule and makes FR-011 hold by construction, since the view only ever rises from the floor-based eye height to the surface. Using the residents' thresholds satisfies the spec's assumption that both enter and leave the water at the same depths.

**Alternatives considered**: A separate floating body with its own collision against pool walls at surface height (duplicates collision, and objects in the water would need a second pass).

## R3. Reporting the user's position while swimming or seated

**Decision**: `PlayerViewTracker` reports the controller's foot position instead of `camera.y − eye height`. The saved session position stays an eye position, but is computed as the foot position plus standing eye height. While the user holds a spot, the saved and reported position is the spot's approach point.

**Rationale**: `getFollowTarget`, conversation range and the server's floor lookup all assume a standing foot position. Deriving it from the camera breaks as soon as the camera moves for swimming or posture. Saving the approach point makes FR-031 (return standing beside the spot) hold with no new session column.

## R4. How an object glows when objects are points

**Finding**: In the penthouse, the faire and The Index, every object marker is an empty node whose only children are spot markers. The furniture itself is merged into one mesh per room or material (`Bar`, `Kitchen`, `Wood_Dark__Gatehouse`…). No mesh belongs to an object, so the furniture cannot be outlined or tinted.

**Decision**: Interactivity is drawn at the marked points. Each spot of the focused object gets a **spot beacon**: a flat ring on the spot's surface (seat, mattress) or on the floor at its approach point for standing activities. The ring is drawn with an additive shader that pulses, rotates a dashed inner arc slowly, and sends a few motes rising. A soft vertical light column marks the object itself. Free spots use the theme accent; taken spots are drawn dimmed in the theme's warning colour. Beacons fade in and out over 300 ms. Objects within 6 m that are not focused show only a faint, slowly breathing dot at their position, so the user can see there is something there before walking up (US3 scenario 1).

**Rationale**: It works for every marked world with no changes to environment files, and it tells the user where exactly they will sit, which an outline would not.

**Alternatives considered**: Extending the marker contract with an object bounding box, or with a reference to its mesh, and changing the generators to split furniture meshes (edits outside this repository for every world, and the merged meshes exist for rendering performance). Screen-space outline post-processing (needs per-object meshes, which do not exist).

## R5. Which object is in focus

**Decision**: An object is within reach when the user is on the same floor and within 2.5 m horizontally of the object's position or of any of its spots' approach points. Among objects within reach, the one with the smallest angle between the view direction and the direction to its nearest point wins, if that angle is under 30°; otherwise the nearest one within reach wins (FR-015). Focus is recomputed every frame inside the canvas, and only changes reach React.

**Rationale**: Large objects (the eight-stool bar counter, the dining table for ten) span more than 2.5 m from their centre, so measuring to spots as well keeps the whole object reachable. The gaze preference lets the user pick between the keyboard stand and the keytar wall, which sit side by side, by looking.

## R6. Where the user's view goes in each posture

**Decision**: A new `playerPostures.js` returns, for a spot and posture, the eye position, the facing yaw, the base pitch and the look-around limits:

| Posture | Eye position | Base pitch | Yaw range | Pitch range |
|---------|-------------|------------|-----------|-------------|
| sitting | spot surface + 0.72 m, 0.1 m behind the spot along its facing | 0° | ±75° | −60° to +50° |
| reclining | spot surface + 0.55 m, 0.45 m behind the spot | +20° | ±55° | −35° to +55° |
| lying | spot surface + 0.28 m, 0.7 m behind the spot | +55° | ±45° | −10° to +80° |

"Behind" is opposite the spot's facing, since a spot marks the hips and the facing points toward the feet or the desk. The view glides from its current pose to the target over 0.8 s with ease-in-out, both for settling and for getting up, and getting up ends at the spot's approach point at standing eye height. While on a spot, WASD, Space and Shift are read as "get up" (FR-025). Holding a spot in the water (the in-water loungers) overrides swimming until the user gets up.

**Rationale**: The numbers follow the resident placement (`SEAT_CLEARANCE`, hips on the surface) and average adult proportions, so the user's view lines up with where a resident's head would be at the same spot. Limits keep the user from looking through their own seat back or the mattress.

## R7. Claiming spots

**Decision**: The user claims spots in the same client-side `occupiedSpots` map residents use, with the holder `'user'`. Resident code already treats any holder that is not herself as taken (`residentActions.js`), and idle decisions already send the map to the server. Chat messages now also send `occupiedSpots`, which the conversation controller passes to `WorldToolbox`. This fixes a gap: during a conversation her `use` and `plan` tools could not see which spots were taken. For a standing activity, the spot is claimed only while the progress ring runs.

**Rationale**: A single occupancy map keeps FR-024 exact in both directions with no new server state, and the cards read the same map to show "taken by Vera".

## R8. Standing activities and action lines

**Decision**: A standing or zone activity turns the view to face the spot (0.4 s; zone activities do not turn) and fills a progress ring at screen centre over 3 s. When it fills, an action line appears at the bottom centre of the screen for 4 s. A movement key cancels it with no line (FR-026). Action lines come from `activityLines.js`: the activity name's first word is conjugated to the third person (`Sit` → `sits`, `Watch` → `watches`, `Have` → `has`, `Try` → `tries`), the rest of the name is kept, and `at the <object name>` is added unless the object's name already appears in the activity name (`Play the Rhodes` → `plays the Rhodes`). Zone activities use the name alone (`looks out at the city`). Getting up gives `gets up from the <object name>`.

**Conversation**: While a conversation is open, the line is sent to the resident as a user message in asterisks through the chat's normal send path, so she replies to it like any message (FR-028). A resting activity sends a line when the user settles and another when they get up; a standing activity sends one line when it completes. Lines produced while her reply is still pending are queued and sent together, as one message, when it arrives.

**Rationale**: A user message in roleplay asterisks is already how she reads actions, so no new message type, prompt section or endpoint is needed. Queueing avoids two replies racing when the user sits and stands up quickly.

## R9. What residents know about the user's activity

**Decision**: Chat messages and idle decisions send `userState: { posture, spotId, activityId }`. The server resolves the spot's object and the activity from the world's layout, and the world state line becomes, for example, `the user is: in Pool terrace, about 3 m away, reclining on the Pool loungers`. Unknown ids are rejected with a 422 (Principle V). Swimming is reported as `swimming in the pool` with the zone name.

**Rationale**: The layout on the server is the source of truth for names, so the client sends ids only. Adding it to the existing `the user is` entry keeps the world-state block under its 2,000-character budget (spec 015 FR-009).

## R10. Residents facing the user

**Decision**: In `ResidentController`'s frame loop, when `inConversation` is true and she is not routing, being placed, holding a resting spot or wandering, her target heading becomes the direction to the user's foot position, and she turns at the existing `TURN_SPEED` with the existing locomotion turning code (`turnTowardsAngle`). The same applies while she treads water. Pose playback is unaffected, since poses animate bones and the heading lives on the scene root, so a pose started during conversation plays facing the user (FR-038). Routing already sets heading from the route, so FR-039 holds, and when the route ends the conversation rule takes over again.

**Rationale**: It uses the heading machinery that roaming and routing already use, with one extra condition.

## R11. The world interface's visual language

**Decision**: One set of HUD styles in `resources/css/app.css`, built only from theme tokens (`--accent`, `--bg-0`, `--fg-*`, `--border-*`, `--font-display`), so each of the four themes restyles it:

- **Frame**: translucent `bg-0` panels with `backdrop-filter: blur`, a 1 px accent-tinted border with corner brackets drawn by pseudo-elements, and a faint animated scan-line texture (`repeating-linear-gradient` drifting slowly).
- **Glow**: `text-shadow` and `box-shadow` from `color-mix(in oklab, var(--accent) …, transparent)`, with a 2.4 s breathing pulse on persistent elements (the prompt and the location readout).
- **Title card**: the zone name in `--font-display`, revealed by a horizontal clip-path wipe with a bright leading edge, letter spacing easing from wide to normal, and a blur-to-sharp settle. It holds 3 s, then dissolves upward. The context line (parent zone, floor) fades in 150 ms after the name.
- **Cards**: slide in from the right with the same wipe, with activity rows staggered 40 ms apart; each row shows its number key, the activity, a posture glyph and its availability.
- **Progress ring**: an SVG circle whose stroke fills over 3 s, with a glowing head and a soft outer halo.
- **Action line**: italic, centred low on screen, typed in over 400 ms and faded out.
- **Swimming**: a thin animated caustic shimmer at the screen edges and a soft tint while swimming, plus a short splash on entering and leaving the water, synthesised with Web Audio from filtered noise (no audio assets).
- **Reduced motion**: under `prefers-reduced-motion: reduce`, every entrance and exit becomes a 150 ms fade, and pulses, scan-line drift, bob and caustics stop (FR-034).

The in-canvas beacons read the same tokens through a shared `themeColor()` helper, moved out of `NameTags.jsx` now that it has a second caller.

**Rationale**: The existing world interface is uppercase, wide-tracked and thin-bordered on dark translucent panels, with an accent glow on name tags. This extends that language with motion. DOM overlays keep text crisp and let CSS animations do the work; per-frame data (label position) is written straight into a ref's style, as `OffscreenIndicator` does, so React renders only on focus or zone changes.

## R12. Keys

**Decision**: Shift runs; E inspects the focused object (again closes the card); 1–9 start the card's activities; G opens the current zone's card; Space, or any movement key, gets up. C, F, X, M and V keep their meanings. Every interface key is ignored while typing (`isTypingTarget`, FR-019), and each is shown where it applies (FR-036).

**Rationale**: E is the conventional "interact" key in first-person games, and none of these keys is bound today.
