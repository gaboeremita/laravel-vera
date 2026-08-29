# Phase 0 Research: 3D Avatar Scene Backgrounds

No `NEEDS CLARIFICATION` markers remain in the Technical Context — the spec's own clarification session (2026-08-28) already resolved the one open design question (cache scope). The decisions below cover the remaining implementation-level choices needed before design, each grounded in an existing pattern already used in this codebase.

## 1. Non-blocking generation

**Decision**: Dispatch a queued Laravel job (`GenerateAvatarBackground`) on the existing `database` queue connection. The triggering request (conversation creation, `sendMessage`, or the agent tool call) returns immediately; the frontend polls a status endpoint.

**Rationale**: The app already has this exact shape for another async, in-conversation AI operation — `AgentProgressController` + `AgentProgressIndicator.jsx` poll a `Cache::get("agent-progress:{$conversation->id}")` value every 2s. Reusing it satisfies FR-017 without introducing new infrastructure (no websockets/broadcasting exist in this app today).

**Alternatives considered**:
- Synchronous generation in the request — rejected outright, violates FR-017 directly.
- Broadcasting/WebSockets for push updates — rejected; would be the first use of real-time infrastructure in the app for a feature that a 2s poll already serves adequately (image generation takes tens of seconds, not something that needs sub-second push).

## 2. Detecting a mid-conversation setting change

**Decision**: Extend the existing in-band LLM tag convention. The assistant's system prompt gains a new instruction section (alongside the existing "emotion tags" section) telling the model to optionally prefix its reply with a `[scene: <description>]` tag when the narrated setting has just changed. The backend parses it the same way `ConversationController::extractEmotionTag()` already parses `[emotion]`/`[intimate]`, strips it from the visible reply, and dispatches background regeneration with the extracted description.

**Rationale**: The codebase already solves an analogous problem (communicating a structured signal from the main chat reply to the backend) with exactly this pattern for emotions. It costs nothing extra — no second LLM call — because the signal rides on the reply the app is generating anyway.

**Alternatives considered**:
- A dedicated classifier LLM call after every assistant reply, checking "did the setting change?" — rejected; doubles LLM calls/cost for a signal the reply already implicitly carries, and adds latency to every single turn instead of just the ones where the scene actually changes.
- Keyword/regex matching conversation text against archive entry titles — rejected; too brittle for scenes the assistant describes but that aren't documented in the archive (e.g. "a futuristic park" has no archive entry).

## 3. Where generated backgrounds live

**Decision**: No new database table. The two generated images are written to the existing `public` filesystem disk (`Storage::disk('public')`, the same disk `Image::storeFromBase64()` already uses) under `avatar-backgrounds/{conversation_id}/`. The current pair's public URLs, source description, and generation timestamp are held as one cache entry under `avatar-background:{conversation_id}`, with a TTL.

**Rationale**: Directly implements the spec's clarification that backgrounds are cached and temporary, not permanently archived (FR-012). Keeping large binary image data out of the cache store (which is the `database` driver here) and on disk instead avoids bloating the cache table; the cache entry itself stays small (URLs + text).

**Alternatives considered**:
- Reusing the `Image` polymorphic model (as chat-generated images do) — rejected; that model represents a permanent, message-attached asset with its own DB row, which is exactly the "not necessary" permanence the clarification rejected.
- Storing image bytes (base64) directly in the cache value — rejected; unnecessarily large cache payloads for no benefit over a file URL, given files already need to exist for the browser to load them anyway.

## 4. Cache/file lifecycle

**Decision**: Whenever a conversation's background is replaced (manual request, automatic initial, or automatic change), the previous pair of files for that conversation is deleted before the new pair is written, so at most one pair exists on disk per conversation at any time. The cache TTL governs the "already cleared" case from the clarification: if the cache entry for a conversation has expired by the time the user returns, the system treats it as absent and regenerates automatically (FR-012a) — the corresponding on-disk files, if TTL expiry outlived them, are simply overwritten by the regeneration.

**Rationale**: Matches the clarified behavior exactly (per-conversation, temporary, auto-regenerate-on-miss) without needing a scheduled cleanup job for the common path, since every replacement already cleans up after itself.

**Alternatives considered**:
- A scheduled pruning Artisan command (like `emotions:sync`) sweeping orphaned files — deferred as a follow-up, not required for this feature's correctness. It would only matter for a conversation that generates one background and is then abandoned before any replacement or cache expiry cleanup; low-impact enough to not block this feature.

## 5. Producing two images from one description

**Decision**: One call to `AvatarBackgroundPromptEnhancer` (mirroring `ImageGenPromptEnhancer`) turns the raw setting description into two enhanced prompts in a single LLM call — one framed for a ground/floor texture, one framed for a wide environment backdrop — using the same `PromptDirector` + archive-retrieval setup `ImageGenPromptEnhancer` already uses (FR-006, FR-007). Two separate calls are then made to the assistant's configured image-gen provider via the existing `ImageGenManager`, one per prompt.

**Rationale**: Reuses the exact prompt-shaping pipeline the spec's Assumptions call for, extended (not replaced) to produce a pair instead of a single prompt. Two separate generations are necessary because a floor texture and a wide backdrop are different image *compositions* — no single generated image plausibly serves both.

**Alternatives considered**:
- One generated image, cropped into a "floor" and "background" region — rejected; the spec calls for two distinct images (FR-008), and a single composition can't cleanly serve a top-down floor texture and a forward-facing wide backdrop at once.

## 6. Placing the two images in the 3D scene

**Decision**: Add two meshes into the existing `VrmScene` (inside `VrmAvatar.jsx`'s `<Canvas>`): a flat ground-plane mesh beneath the avatar textured with the floor image, and an open (no front cap, no top/bottom cap) partial cylinder behind the avatar textured with the surroundings image, to read as a curved backdrop under the existing fixed camera.

**Rationale**: This is a direct implementation of FR-008–FR-011, placed into the scene graph that already exists for the VRM model itself — no new rendering framework or canvas is needed.

**Alternatives considered**: None meaningfully different — the spec is explicit about the two-plane, no-ceiling/no-front shape (see spec Input), so this is a direct translation rather than an open design choice.

## 7. Transition effect

**Decision**: Cross-fade: when a new background pair finishes loading, its mesh materials animate opacity from 0→1 while the previous pair's materials animate 1→0 over the same short duration, then the old meshes are disposed.

**Rationale**: Implements FR-018 using the same visual language the app already established — `Portrait.jsx` already cross-fades its own image swaps with a `transition-opacity duration-300` Tailwind class. The 3D scene needs the equivalent effect done via Three.js material opacity (since these are WebGL meshes, not DOM `<img>` elements), but the target feel (smooth 300ms-scale fade, no abrupt pop) is the same as the existing convention.

**Alternatives considered**: An instant swap — explicitly rejected by FR-018/SC-006.
