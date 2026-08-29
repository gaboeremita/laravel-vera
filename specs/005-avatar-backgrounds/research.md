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

**Decision**: One call to `AvatarBackgroundPromptEnhancer` (mirroring `ImageGenPromptEnhancer`) turns the raw setting description into two enhanced prompts in a single LLM call — one framed for a ground/floor texture, one framed for a wide environment backdrop — using the same `PromptDirector` + archive-retrieval setup `ImageGenPromptEnhancer` already uses (FR-006, FR-007). The two resulting prompts are then sent to the assistant's configured image-gen provider **concurrently**, not one after the other: `App\Contracts\ImageGenProvider` gains a second method, `generateMany(array $prompts): array` (returning one `ImageGenResult` per prompt, same order), implemented in both `OpenRouterImageGenProvider` and `OpenAiCompatibleImageGenProvider` using `Http::pool()` instead of two sequential `Http::post()` calls. `AvatarBackgroundService` calls `generateMany([$floorPrompt, $surroundingsPrompt])` rather than calling `generate()` twice.

**Rationale**: Reuses the exact prompt-shaping pipeline the spec's Assumptions call for, extended (not replaced) to produce a pair instead of a single prompt. Two separate generations are necessary because a floor texture and a wide backdrop are different image *compositions* — no single generated image plausibly serves both. Running them concurrently matters for SC-001 (60s/95% target): run sequentially, two calls against a provider with the existing 120s default timeout could easily land the total near or past 60s even on the successful path, not just in failure cases; run concurrently, total latency is bounded by the slower of the two calls instead of their sum.

**Alternatives considered**:
- One generated image, cropped into a "floor" and "background" region — rejected; the spec calls for two distinct images (FR-008), and a single composition can't cleanly serve a top-down floor texture and a forward-facing wide backdrop at once.
- Sequential calls (simpler control flow) — rejected once weighed against SC-001; halving latency by running them concurrently is worth the modest added complexity of a pooled/concurrent HTTP call.

## 6. Placing the two images in the 3D scene

**Decision**: Add two meshes into the existing `VrmScene` (inside `VrmAvatar.jsx`'s `<Canvas>`): a flat ground-plane mesh beneath the avatar textured with the floor image, and an open (no front/top/bottom caps) **partial-arc** cylinder behind the avatar textured with the surroundings image, to read as a curved backdrop under the existing fixed camera. The arc spans 90° (`thetaLength = π/2`), radius 6, height 6, centered on the side of the cylinder actually facing the camera (`thetaStart = π - thetaLength/2`, derived from `CylinderGeometry`'s `x = r·sin(θ), z = r·cos(θ)` convention) — sized so the visible arc's width (`radius × thetaLength ≈ 9.4`) against its height (6) lands close to a normal image's aspect ratio.

**Rationale**: This is a direct implementation of FR-008–FR-011 (a *curved plane*, not a full wraparound), placed into the scene graph that already exists for the VRM model itself. The specific arc size matters, not just "partial vs. full": `CylinderGeometry` maps a texture's full width across whatever `thetaLength` it's given regardless of the image's real aspect ratio, so the arc's width-to-height ratio has to be chosen to roughly match the image or the texture visibly stretches.

**Alternatives considered**: A full 360° cylinder — tried first for simplicity (no arc-facing math to get right), but wrapping a single non-tileable image around a full ~56-unit circumference against a 6–10-unit height stretched it roughly 3x past its native proportions, which read as visible distortion rather than a scene once actually rendered. Rejected in favor of the sized partial arc above.

## 7. Transition effect

**Decision**: Cross-fade: when a new background pair finishes loading, its mesh materials animate opacity from 0→1 while the previous pair's materials animate 1→0 over the same short duration, then the old meshes are disposed.

**Rationale**: Implements FR-018 using the same visual language the app already established — `Portrait.jsx` already cross-fades its own image swaps with a `transition-opacity duration-300` Tailwind class. The 3D scene needs the equivalent effect done via Three.js material opacity (since these are WebGL meshes, not DOM `<img>` elements), but the target feel (smooth 300ms-scale fade, no abrupt pop) is the same as the existing convention.

**Alternatives considered**: An instant swap — explicitly rejected by FR-018/SC-006.

## 8. Trigger point for cache-miss auto-regeneration (FR-012a)

**Decision**: `ConversationController::show()` — the endpoint that loads a conversation's messages, called every time a conversation is opened — dispatches `GenerateAvatarBackground` when: the assistant is `Avatar3D`, this is the first page of messages (no `before` query param, i.e. not a "load older messages" scroll request), no `avatar-background:{conversation_id}` cache entry exists, and no `avatar-background-progress:{conversation_id}` job is already in flight. The description passed to the job is a generic "infer the current setting from the conversation so far" instruction — `AvatarBackgroundPromptEnhancer` already includes recent message history in its LLM call (mirroring `ImageGenPromptEnhancer::recentHistory()`), so the actual inference comes from that history, not from the instruction string itself.

**Rationale**: `show()` already resolves the conversation through the ownership-checked path (Constitution Principle IV) and is the one place in the app that reliably fires exactly when "the user returns to a conversation" (the language FR-012a and the spec's Edge Cases use). It keeps the `GET .../avatar-background` polling endpoint itself free of side effects — consistent with `AgentProgressController`, which is also purely read-only — so `contracts/avatar-background-api.md`'s statement that polling never triggers generation stays true.

**Alternatives considered**:
- Making the `GET .../avatar-background` polling endpoint trigger generation itself when it finds no cached background — rejected; a polled status endpoint performing a side effect on read is surprising (repeated polls could each observe "not cached yet" while the first-triggered job is still running, inviting duplicate-dispatch bugs) and breaks the read-only contract the endpoint was designed around.

## 9. Default background assets (fallback when nothing is cached or generated)

**Decision**: A single bundled pair of static images — a default floor texture and a default surroundings backdrop — shipped with the app (not AI-generated at runtime, not per-conversation) and used as the fallback whenever `useAvatarBackground()` has no cached background to show (brand-new conversation before its first generation completes, or any case where generation hasn't produced a result yet). These are imported directly by the frontend, the same way `resources/js/components/VrmAvatar.jsx` already imports a static fallback (`veraAvatar` from `resources/images/vera-avatar.png`) for its own `loadError` case — no backend/cache/API involvement needed, since the images never change.

**Rationale**: "The default background" is referenced by FR-013 and the spec's Edge Cases but was never concretely defined (flagged in the 2026-08-28 `/speckit-analyze` pass, finding B1). Bundling two fixed images and wiring them in the same static-import pattern the frontend already uses for its avatar-load fallback is the simplest way to make "default" a real, defined thing rather than an implicit "render nothing."

**Alternatives considered**: Generating the two default images once via a throwaway script and discarding the script — considered, then set aside in favor of the user supplying the two images directly, avoiding any one-off generation code entering the codebase at all.
