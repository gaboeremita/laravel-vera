# Research: VRMA Avatar Pose Animations

**Feature**: `006-vrma-avatar-poses` | **Date**: 2026-08-29

## Decision 1: Pose & Pose Animation File Storage Model

**Decision**: New `Pose` model, `belongsTo` an `Assistant` directly (`assistant_id` FK), not polymorphic. Its optional uploaded animation lives on a new `PoseAnimationFile` model in a `hasOne`/`belongsTo` pair (`pose_id` FK, unique), also not polymorphic.

**Rationale**: `VrmFile` (004) and `Image`/`Video` are polymorphic because they have (or anticipate) more than one real owner type. A pose animation file only ever belongs to a `Pose` — there is no second caller today. A direct foreign key is simpler and satisfies Constitution Principle VII (No Speculative Abstraction); polymorphism here would be built for a hypothetical second owner that doesn't exist.

**Alternatives considered**: Mirror `VrmFile`'s polymorphic shape for consistency — rejected; consistency with a precedent that itself has only one caller is not a strong enough reason to add an unused abstraction.

---

## Decision 2: No "Configuration Mode" Field

**Decision**: A `Pose` has two independent, optional pieces of data — `vrm_blendshapes` (json, nullable) and an associated `PoseAnimationFile` (present or absent). There is no `mode` or `type` column. Presence/absence of each is the only state that exists.

**Rationale**: The spec's clarified design (combinable, not an exclusive toggle) means there is nothing to store beyond the two independent optional values — no third state to track. An earlier draft considered a `config_mode` enum for an exclusive toggle; that field is now unnecessary given the combinable model and would misrepresent the data (implying exclusivity that doesn't exist).

**Alternatives considered**: `config_mode` enum (`preset` / `upload` / `both`) — rejected as redundant; it would just restate what `vrm_blendshapes !== null` and `animationFile !== null` already say.

---

## Decision 3: Pose Data Delivery to Frontend

**Decision**: Extend the existing `GET /api/assistants/{assistant}/emotions` envelope response (`EmotionController::index`) to also include a `poses` array alongside `portrait_type`, `vrm_url`, and `emotions`. Each pose entry: `{id, name, vrm_blendshapes, animation_url}`.

**Rationale**: Matches Decision 4 from `004-vrm-3d-avatar/research.md` — this endpoint is already "load an assistant's portrait config," and poses are part of that config for 3D avatar assistants. Adding a field is cheaper than a second round trip on every conversation load.

**Alternatives considered**: New `GET /api/assistants/{id}/poses` endpoint — rejected; would double the requests needed to render a 3D avatar assistant's chat page for no isolation benefit, since poses are only ever consumed alongside the rest of the portrait config.

---

## Decision 4: LLM Prompt Guidance for Poses

**Decision**: A new `pose tags` prompt section, built entirely in code (not pre-seeded in any assistant's stored `prompt` JSON), following the exact precedent set by `background tags` in `ConversationController` (005-avatar-backgrounds): a `POSE_TAG_INSTRUCTION` constant plus the assistant's pose names, appended conditionally:

```php
$poses = $assistantModel->promptPoseNames();
if (! empty($poses)) {
    $director->append('pose tags', [
        'instruction' => self::POSE_TAG_INSTRUCTION,
        'available poses' => $poses,
    ]);
}
```

`PromptDirector::append()` already creates a section from scratch when the key doesn't exist in the assistant's stored config (confirmed at `app/Directors/PromptDirector.php:76-85`), so no migration/backfill of existing assistants' prompt JSON is needed. `Assistant::promptPoseNames(): array<string>` is a new method (parallel to `promptEmotionNames()`, but without a regular/intimate split — poses have no restricted concept per the spec).

`POSE_TAG_INSTRUCTION` explicitly tells the LLM that poses are physical actions/gestures distinct from emotions (per spec FR-008, US3), satisfying the "prompt needs to let the LLM know what this is" requirement from the original feature request.

**Rationale**: `background tags` is the closest existing precedent for introducing a brand-new prompt section for a brand-new signal type, and it already proves the append-creates-if-missing mechanism works without a data migration. Reusing the same shape as `emotion tags` (instruction + named list) keeps prompt structure predictable for future maintainers.

**Alternatives considered**: Piggyback pose names onto the existing `emotion tags` section — rejected; the spec explicitly requires poses to read as a separate, non-overlapping signal set to the LLM (US3 Scenario 3), and merging sections would blur that distinction in the prompt itself.

---

## Decision 5: Pose Tag Syntax & Parsing Scope

**Decision**: `[pose: <name>]`, following the exact convention already used for `[scene: <description>]` (005-avatar-backgrounds) — a colon-qualified bracket tag, distinguishing it from the bare `[emotionname]` tag syntax so a pose name can never be mistaken for an emotion tag by the parser. In the main chat flow (`ConversationController::sendMessage` → `ChatPage.jsx`), where emotion tags are already parsed client-side (`resources/js/utils/parsers.js:parseEmotionFromResponse`) rather than server-side, pose tags follow the same client-side pattern: a new `parsePoseFromResponse(text, validPoseNames)` strips a leading `[pose: name]` tag (checked after any leading `[emotion]`/`[intimate]` tags, so `[happy][pose: spin] ...` parses both from the same message, satisfying spec FR-016 / US2 Scenario 5).

Pose signaling is scoped to the main chat interface for v1. The image-generation reaction, background-change reaction, and Discord reply flows (which parse emotion server-side via `ConversationController::extractEmotionTag`) are out of scope — they either have no 3D avatar view to animate (Discord, image-gen) or are already a narrow side-flow (background-change reaction) where adding pose support offers little value relative to the added parsing surface.

**Rationale**: Reusing an established tag convention (`[scene: ...]`) rather than inventing a new bracket format keeps the LLM's tag vocabulary consistent and reduces the chance the model confuses pose and emotion tags. Keeping pose parsing client-side in the one flow that already renders a `VrmAvatar` avoids adding server-side parsing to flows that have no visual surface to apply it to.

**Alternatives considered**: Bare `[posename]` matching the same regex as emotion tags — rejected; a pose and an emotion could share a name (per spec Assumptions), which would make the tags ambiguous without the `pose:` qualifier.

---

## Decision 6: VRMA Playback & Interaction with Existing Idle/Expression Animation

**Decision**: Add `@pixiv/three-vrm-animation` (companion package to the already-installed `@pixiv/three-vrm`) to load `.vrma` clips via its `VRMAnimationLoaderPlugin`, and play them with a `THREE.AnimationMixer` inside `VrmScene`'s existing `useFrame` loop. When a pose's `animation_url` becomes active: load (or reuse a cached) clip, play it once, and on the mixer's `finished` event return to idle — matching spec FR-015. While a body animation is playing, the existing sinusoidal idle head-sway is paused (it directly sets `headBone.rotation`, which would otherwise fight the animation clip's own bone tracks); idle blinking and blendshape-driven facial expression continue unaffected, since those operate on `expressionManager` values, a separate channel from the animation clip's skeletal bone tracks — this is what makes a pose's blendshape expression and uploaded body animation combinable without conflict (per spec Assumptions).

Loading a `.vrma` clip is async, so it is done inside a `useEffect` with a closure local to that effect — following the same pattern already used for VRM model loading in `VrmScene` (`resources/js/components/VrmAvatar.jsx:53-114`) and required by Constitution Principle VIII.

**Rationale**: `@pixiv/three-vrm-animation` is the standard companion library for playing `.vrma` clips against a `@pixiv/three-vrm` avatar (same maintainer, same major-version ecosystem as the already-installed `@pixiv/three-vrm@^3.5.5`), so it introduces no new rendering paradigm — `AnimationMixer` is a standard Three.js primitive already compatible with the existing `useFrame`-driven render loop. Pausing idle head-sway during playback is the simplest way to avoid two systems fighting over the same bone without adding a general-purpose animation priority system the spec doesn't ask for.

**Alternatives considered**: Building a custom bone-track player instead of `AnimationMixer` — rejected as unnecessary; `AnimationMixer` already does exactly this and is standard Three.js.

---

## Decision 7: Shared Blendshape Normalization

**Decision**: Extract `Emotion::normalizeBlendshapes()` (`app/Models/Emotion.php:50-60`) into a shared trait (e.g. `App\Models\Concerns\HasNormalizedBlendshapes`) used by both `Emotion` and the new `Pose` model.

**Rationale**: Constitution Principle VII permits extraction "once a second real caller needs it." `Pose` needs the exact same 0–100% → 0.0–1.0 conversion logic `Emotion` already has — this is that second real caller, not a hypothetical one.

**Alternatives considered**: Duplicate the static method on `Pose` — rejected; the second-caller threshold that justifies extraction has actually been met here, so duplicating known-identical logic would itself be the violation.

---

## Decision 8: `.vrma` File Size Limit

**Decision**: 10 MB (`max:10240` in Laravel's kilobyte-based validation), enforced at the API layer, per the resolved clarification in spec.md.

**Rationale**: Matches the existing image/emotion upload ceiling (`AssistantEmotionController`, `AssistantImageController`) rather than the much larger 50 MB VRM model ceiling — `.vrma` files carry only animation curve data, not mesh/texture data, so they are expected to be far smaller than a full avatar model.
