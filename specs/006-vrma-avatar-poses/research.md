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

**Decision**: A `pose tags` prompt section that mirrors exactly how `emotion tags` already works: the backend only ever appends the assistant's own pose names to it —

```php
$poses = $assistantModel->promptPoseNames();
if (! empty($poses)) {
    $director->append('pose tags', ['available poses' => $poses]);
}
```

— and the instructional wording (what a pose tag means, how to format one, when to use it) lives entirely in the assistant's own stored `prompt` JSON, authored by whoever configures that assistant, the same way `emotion tags`' instructional content is user-authored rather than backend-injected. `PromptDirector::append()` creates the section from scratch if the assistant hasn't written one, or merges `available poses` into whatever `pose tags` content the assistant already has (`app/Directors/PromptDirector.php:76-85`). `Assistant::promptPoseNames(): array<string>` is parallel to `promptEmotionNames()`, but without a regular/intimate split — poses have no restricted concept.

Because a 3D avatar assistant never sees an `emotion tags` section (Decision 6 below), there is nothing to distinguish poses from within the prompt — a pose tag is simply the assistant's expression/action signal, full stop.

**Rationale**: Matching the emotion system's data/config split exactly — dynamic name list from code, instructional wording from the assistant's own prompt — keeps pose configuration exactly as customizable as emotion configuration already is, rather than the pose experience being locked to whatever fixed wording ships in the code.

**Alternatives considered**: A fixed instruction string baked into the backend and injected unconditionally — rejected; it gave assistant creators no way to customize the pose instruction the way they already can for emotions, an inconsistency worth avoiding rather than carrying forward.

---

## Decision 5: Pose Tag Syntax & Parsing Scope

**Decision**: A bare `[name]` tag — the identical grammar the emotion system already uses (`/^\[([^\]]+)\]/`, matching any characters up to the closing bracket so pose names with spaces or underscores round-trip correctly) — not a qualified `[pose: name]` form. Since a 3D avatar assistant is never prompted with emotion tags, there is no ambiguity between the two tag types to disambiguate; only one applies to any given assistant.

Pose signaling is parsed everywhere emotion signaling already was: client-side in the main chat flow (`resources/js/utils/parsers.js:parsePoseFromResponse`, mirroring `parseEmotionFromResponse`), and server-side (`ConversationController::extractExpressionTag`, a single method branching on `portrait_type` that replaced the separate `extractEmotionTag`) for the image-generation reaction, background-change reaction, and Discord reply flows.

**Rationale**: Poses and emotions are mutually exclusive by portrait type, so the tag format doesn't need to distinguish between them the way `[scene: ...]` needs to distinguish itself from an emotion tag within the same response. Reusing the exact emotion-tag grammar keeps the LLM's tag vocabulary uniform and lets a single unified extraction method serve both portrait types.

**Alternatives considered**: A colon-qualified `[pose: <name>]` form, matching `[scene: <description>]` — rejected once poses and emotions became mutually exclusive by portrait type; the qualifier only earns its keep when both tag types could appear in the same response, which never happens here.

---

## Decision 6: VRMA Playback & Interaction with Existing Idle/Expression Animation

**Decision**: Add `@pixiv/three-vrm-animation` (companion package to the already-installed `@pixiv/three-vrm`) to load `.vrma` clips via its `VRMAnimationLoaderPlugin`, and play them with a `THREE.AnimationMixer` inside `VrmScene`'s existing `useFrame` loop, targeting bones via `getNormalizedBoneNode()` (the node the mixer actually drives — `vrm.humanoid.humanBones` returns the raw, pre-normalization rig, which `vrm.update()` overwrites every frame from the normalized bones regardless of what's written to it). A triggered pose's clip plays once (`LoopOnce`, `clampWhenFinished`); the default pose's clip, if configured, plays on `LoopRepeat` as the idle baseline whenever no triggered pose is active, and is the sole idle animation now — the previous fixed idle stance only applies when no default-pose animation is configured. While a body animation is playing, the existing sinusoidal idle head-sway is paused (it directly sets `headBone.rotation`, which would otherwise fight the animation clip's own bone tracks); idle blinking and blendshape-driven facial expression continue unaffected, since those operate on `expressionManager` values, a separate channel from the animation clip's skeletal bone tracks — this is what makes a pose's blendshape expression and uploaded body animation combinable without conflict (per spec Assumptions).

Entering and leaving a triggered pose's animation is a manual bone-quaternion blend (capture the "from" pose, slerp toward the mixer's live output over ~0.25s) rather than a mixer crossfade, since there is no persistent "idle" `AnimationAction` to crossfade against when the idle baseline itself is just the default pose's own action (or nothing, when unconfigured). A triggered clip whose own duration is shorter than that blend window (a single-frame "pose" export, as opposed to a multi-second motion clip, commonly ~0.033s from a 30fps single-frame Mixamo pose) is held for a fixed duration before transitioning back, by leaving its `AnimationAction` running rather than stopping it — `clampWhenFinished` then keeps the mixer re-asserting the clip's true final frame every update on its own, which is more reliable than manually freezing a one-time captured snapshot of a still-converging blend.

Loading a `.vrma`/`.fbx` clip is async, so it is done inside a `useEffect`/`useCallback` with a closure local to that scope — following the same pattern already used for VRM model loading in `VrmScene` (`resources/js/components/VrmAvatar.jsx`) and required by Constitution Principle VIII.

**Rationale**: `@pixiv/three-vrm-animation` is the standard companion library for playing `.vrma` clips against a `@pixiv/three-vrm` avatar (same maintainer, same major-version ecosystem as the already-installed `@pixiv/three-vrm@^3.5.5`), so it introduces no new rendering paradigm — `AnimationMixer` is a standard Three.js primitive already compatible with the existing `useFrame`-driven render loop. Pausing idle head-sway during playback is the simplest way to avoid two systems fighting over the same bone without adding a general-purpose animation priority system the spec doesn't ask for. Leaving a static clip's action running during its hold (rather than manually freezing a captured snapshot) sidesteps a real bug class: the entry blend's own convergence window can outlast an extremely short clip, so a snapshot captured at "finished" time may not yet reflect the clip's true settled pose.

**Alternatives considered**: Building a custom bone-track player instead of `AnimationMixer` — rejected as unnecessary; `AnimationMixer` already does exactly this and is standard Three.js. Manually freezing a captured bone snapshot for the static-pose hold instead of leaving the action running — rejected; a snapshot captured while the entry blend is still converging can be an incomplete capture of the clip's true settled pose, whereas leaving the action active and clamped is unaffected by blend timing.

---

## Decision 7: Shared Blendshape Normalization

**Decision**: Extract `Emotion::normalizeBlendshapes()` (`app/Models/Emotion.php:50-60`) into a shared trait (e.g. `App\Models\Concerns\HasNormalizedBlendshapes`) used by both `Emotion` and the new `Pose` model.

**Rationale**: Constitution Principle VII permits extraction "once a second real caller needs it." `Pose` needs the exact same 0–100% → 0.0–1.0 conversion logic `Emotion` already has — this is that second real caller, not a hypothetical one.

**Alternatives considered**: Duplicate the static method on `Pose` — rejected; the second-caller threshold that justifies extraction has actually been met here, so duplicating known-identical logic would itself be the violation.

---

## Decision 8: Animation File Size Limit

**Decision**: 10 MB (`max:10240` in Laravel's kilobyte-based validation), enforced at the API layer for both `.vrma` and `.fbx` uploads, per the resolved clarification in spec.md.

**Rationale**: Matches the existing image/emotion upload ceiling (`AssistantEmotionController`, `AssistantImageController`) rather than the much larger 50 MB VRM model ceiling — both formats carry only animation curve data (bone tracks, optionally blendshape curves for `.vrma`), not mesh/texture data, so they are expected to be far smaller than a full avatar model. A single ceiling for both formats keeps validation simple; nothing about `.fbx` animation-only exports needs a materially different limit than `.vrma`.

**Alternatives considered**: A separate, larger limit for `.fbx` — rejected; Mixamo-sourced `.fbx` animation exports are typically well under 10 MB (mesh-free), so a shared limit doesn't meaningfully constrain the common case.

---

## Decision 9: `.fbx` Animation Support via Mixamo Retargeting

**Decision**: Accept `.fbx` as a second valid pose animation format alongside `.vrma`. `.fbx` files are parsed with `THREE.FBXLoader` (`three/addons/loaders/FBXLoader.js` — already available via the installed `three` package, no new dependency) and retargeted onto the avatar's VRM humanoid skeleton via a small, in-house bone-name mapping table (Mixamo skeleton names → VRM humanoid bone names), ported from the reference approach in `@pixiv/three-vrm`'s own Mixamo-animation example. The format is detected from the uploaded file's extension at playback time (no new schema field — see [data-model.md](data-model.md)), branching between the `.vrma` (`VRMAnimationLoaderPlugin`) and `.fbx` (`FBXLoader` + retargeter) load paths.

**Rationale**: `@pixiv/three-vrm` does not retarget FBX animations automatically — this was verified directly (searched `pixiv/three-vrm` GitHub discussions and community tooling before committing to this design). The two well-supported paths are: (a) `@pixiv/three-vrm`'s own official example code for loading Mixamo FBX animations, and (b) third-party libraries such as `vrm-mixamo-retargeter`, both of which are scoped to Mixamo's specific bone-naming convention, not arbitrary FBX rigs. Porting the small, official reference mapping in-house avoids taking on an unofficial third-party dependency for a bone-name lookup table that's simple enough to own directly, and keeps the feature dependency-free for this half of the format support (unlike `.vrma`, which does need the new `@pixiv/three-vrm-animation` package per Decision 6).

**Alternatives considered**: (1) Require users to convert `.fbx` to `.vrma` externally before uploading — rejected; this is exactly the extra conversion step the feature request was raised to avoid. (2) Add the third-party `vrm-mixamo-retargeter` package — rejected in favor of porting the mapping directly; the retargeting logic itself is a short, stable bone-name table, not a large surface worth an external dependency. (3) General-purpose retargeting that infers bone mapping from arbitrary FBX skeletons — rejected as out of scope; no reliable general solution exists without the source rig's naming convention, and Mixamo is the dominant source of externally-sourced humanoid FBX animation in practice. Rig conventions other than Mixamo's (e.g. a 3ds Max Biped export) are a real gap this scoping accepts, tracked as a follow-up rather than solved here — each additional convention needs its own bone-name map, and a fully general "map any bone by hand" UI is large enough to be its own feature, not a natural extension of this one.

---

## Decision 10: Poses Replace Emotions for 3D Avatar Mode

**Decision**: Poses are the sole expression/action system for 3D avatar assistants — not an addition alongside emotions. `AssistantEmotionController`, `EmotionController::index`'s response envelope, prompt-tag injection, and every emotion-configuration UI component all branch on `portrait_type`: image-portrait assistants get emotions exactly as before with poses never exposed; 3D avatar assistants get poses exclusively, with the emotion system fully hidden and its endpoints guarded against use. A one-time data migration converts any existing 3D avatar assistant's configured emotions into equivalent poses (name + blendshape weights carried over) and removes the now-redundant emotion records, so no assistant configured before this feature loses its expressive configuration.

A distinguished pose named `"default"` is always present per 3D avatar assistant, created implicitly rather than by user action, name-locked and undeletable at the API level (`AssistantPoseController`/`AssistantPoseAnimationController` reject any create/rename/delete targeting that name through the normal per-pose routes, and expose dedicated `updateDefault`/`storeDefault`/`destroyDefault` actions instead) — mirroring exactly how image-portrait assistants have an undeletable "Default Image". Its blendshape weights are the facial baseline whenever no triggered pose's hold window is active; its animation, if configured, is the sole idle-loop animation, replacing the previous fixed idle stance.

**Rationale**: 3D avatar mode and image mode are genuinely different expression paradigms — poses generalize emotions (they can express mood the same way, plus body action, in one system) rather than being a separate concern layered on top. Running two parallel signal systems for one assistant would require the LLM to choose between two overlapping vocabularies for the same underlying intent (express a mood), which serves no one. Reusing the exact "Default Image" pattern for the default pose keeps the mental model consistent between the two portrait types instead of inventing a new idle-configuration concept.

**Alternatives considered**: Emotions and poses coexisting on 3D avatar assistants, with poses only covering physical actions and emotions still covering mood — rejected; poses already needed to cover mood-like blendshape weights for pose-only body animations that don't include a facial component of their own (a body animation naturally still needs *some* facial baseline), so a separate mood-only emotion system alongside it would be redundant, not complementary.
