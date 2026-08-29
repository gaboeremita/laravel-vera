# Research: 3D VRM Avatar Portrait

**Feature**: `004-vrm-3d-avatar` | **Date**: 2026-08-28

## Decision 1: VRM File Storage Model

**Decision**: New `VrmFile` model with polymorphic `MorphOne` on `Assistant`, following the existing `Image` / `Video` pattern exactly — same columns (`path`, `disk`, `mime_type`, `size`, `original_name`), same `url` accessor via `Storage::disk()->url()`, same `public` disk.

**Rationale**: The codebase already has this shape for two file types. Reusing it means no new storage abstraction, zero unfamiliar surface, and consistent behaviour everywhere files are served. A direct column on `assistants` (e.g. `vrm_path`) would be simpler but would not carry the computed `url` accessor and disk abstraction the rest of the codebase relies on.

**Alternatives considered**: (1) Reuse `Image` model with a mime-type flag — rejected because VRM is not an image and querying `images` for model files is misleading. (2) Generic polymorphic `File` model — no second caller exists yet, violates No Speculative Abstraction.

---

## Decision 2: Portrait Type Storage

**Decision**: New `portrait_type` string column on `assistants`, backed by a new `AssistantPortraitType` enum (`Image`, `Avatar3d`), default `image`. Cast via `casts()` on the `Assistant` model, same pattern as `mode` / `AssistantMode`.

**Rationale**: Matches how `mode` was added in migration `2026_08_27_214321`. A boolean `has_3d_avatar` was considered but would not generalise cleanly if a third portrait type is ever added; an enum is the right shape here.

**Alternatives considered**: Boolean `is_3d_avatar` — rejected as less expressive and harder to extend.

---

## Decision 3: Emotion → Blendshape Mapping

**Decision**: Per-assistant, database-backed mapping. The `emotions` table (already used for image-mode emotion records) gains a `vrm_blendshapes` JSON column: `array<{expression: string, weight: float}>`, nullable. Each assistant defines its own emotion names and blendshape targets via the assistant settings UI (`VrmEmotionEditor`) — there is no fixed application-level list of emotion names or expression names.

`Assistant::promptEmotionNames()` reads the assistant's actual `Emotion` records for both portrait types (previously it returned a hardcoded list for `avatar3d` assistants, since they had no `Emotion` records to draw from — that fallback is gone now that avatar3d assistants create real `Emotion` rows too). `Emotion` rows are optional for `avatar3d` assistants — creation permits zero emotions. When none exist, `promptEmotionNames()` returns empty `regular` and `intimate` arrays, and `ConversationController` passes those empty arrays to the LLM, so no emotion tags are available or emitted until the user adds emotions via `VrmEmotionEditor`.

On the frontend, `VrmAvatar`'s `useFrame` loop lerps whatever expression names appear in the resolved `blendshapes` prop (sourced from `useEmotions().getVrmBlendshapes(name)`), with no fixed list of expression names to iterate — any name the user's VRM model actually exposes works.

**Rationale**: A fixed JS mapping meant every assistant showed identical expressions regardless of what blendshapes its actual VRM file exposes, and adding or tuning an emotion required a code change. Reusing the `emotions` table (rather than a new one) keeps `image_url` and `vrm_blendshapes` as independent, optional fields on the same row — an emotion can carry an image, a blendshape mapping, both, or neither, consistent with the existing "portrait type and files are independent" rule.

**Alternatives considered**: A dedicated `vrm_expression_targets` table with a `FK` to `emotions` — rejected; a JSON column is sufficient for an ordered list of `{expression, weight}` pairs with no independent query needs.

---

## Decision 4: Portrait Config Delivery to Frontend

**Decision**: Extend the existing `GET /api/assistants/{assistant}/emotions` response to also include `portrait_type` and `vrm_url` at the top level alongside the `emotions` array. This avoids a second HTTP request when a conversation loads.

**Rationale**: `fetchEmotions()` in `useEmotions` is already the call that "loads an assistant's portrait data." Adding `portrait_type` and `vrm_url` to that response keeps portrait setup as a single fetch. The hook's return shape grows by two fields — a small, contained change.

**Alternatives considered**: Second fetch to `GET /api/assistants/{id}` — rejected because it doubles the requests on every assistant load for a field that is naturally co-located with the emotion list.

---

## Decision 5: Lerp Transition & Idle Animation Driver

**Decision**: All per-frame blendshape updates (lerp toward target expression, idle blink timer) run inside R3F's `useFrame` hook inside `VrmAvatar.jsx`. No `useEffect` / `setInterval` involved — the render loop is the timer.

**Rationale**: `useFrame` is the idiomatic R3F place for per-frame work; it runs inside the R3F context where the VRM object is available. Using `setInterval` or `useEffect` timers for frame-rate animation is an anti-pattern in this stack. This also aligns with Constitution Principle VIII (state derivation during render, not in effects) — the blink accumulator is a mutable ref updated in the render loop, not state driven by effects.

**Alternatives considered**: `setInterval` for blink — rejected (wrong driver for animation; fights the render loop).

---

## Decision 6: VRM File Size Limit

**Decision**: 50 MB enforced at the API layer (`max:51200` in Laravel validation, kilobytes).

**Rationale**: VRoid Studio exports typically produce files in the 20–80 MB range; 50 MB covers well-optimised exports and most default-quality exports. Consistent with spec FR-012.
