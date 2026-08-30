# Frontend Component Contracts: VRMA Avatar Pose Animations

**Feature**: `006-vrma-avatar-poses` | **Date**: 2026-08-29

---

## Modified: `Portrait` component

**File**: `resources/js/components/Portrait.jsx`

New props added. All existing props (`getVrmBlendshapes`, `portraitType`, `vrmUrl`, etc. — see `004-vrm-3d-avatar/contracts/frontend-components.md`) unchanged, except `emotion` is no longer meaningful for `portraitType === 'avatar3d'` — poses are that mode's sole expression/action system, so nothing ever sets `emotion` for an avatar3d assistant.

| Prop | Type | Required | Default | Description |
|---|---|---|---|---|
| `poseBlendshapes` | `Array<{expression: string, weight: number}>` | no | `[]` | The currently triggered pose's blendshape targets, from `useEmotions().getPoseBlendshapes(pose.name)` |
| `poseAnimationUrl` | `string \| null` | no | `null` | URL to the currently triggered pose's animation file (`.vrma` or `.fbx`) |
| `poseTriggerId` | `number \| null` | no | `null` | A fresh identifier per genuine trigger (not just the pose name), so re-signaling the same pose name always retriggers correctly |
| `poseName` | `string \| null` | no | `null` | The currently triggered pose's name, shown in the avatar3d `mood:` label instead of `emotion` |
| `defaultPoseBlendshapes` | `Array<{expression: string, weight: number}>` | no | `[]` | The default pose's blendshape targets — the facial baseline whenever no triggered pose is active |
| `defaultPoseAnimationUrl` | `string \| null` | no | `null` | The default pose's animation URL — loops continuously as the idle baseline when configured |

When `portraitType === 'avatar3d'` and `vrmUrl` is non-null, all of the above pass through to `<VrmAvatar>`. The avatar3d branch's `mood:` label reads `poseName ?? 'default'`, not `emotion`.

---

## Modified: `VrmAvatar` component

**File**: `resources/js/components/VrmAvatar.jsx`

New props added, matching `Portrait`'s new pose props one-for-one (`poseBlendshapes`, `poseAnimationUrl`, `poseTriggerId`, `defaultPoseBlendshapes`, `defaultPoseAnimationUrl`). All existing props (`vrmUrl`, `assistantId`, `conversationId`) unchanged; `emotion`/`blendshapes` are still accepted but are effectively inert for avatar3d callers, since nothing populates them there anymore.

**New behaviour**:
- A file URL's extension selects the loader: `.vrma` via `VRMAnimationLoaderPlugin` (`@pixiv/three-vrm-animation`); `.fbx` via `THREE.FBXLoader` retargeted onto the avatar's humanoid bones through the Mixamo bone-name mapping (see [research.md Decision 9](../research.md#decision-9-fbx-animation-support-via-mixamo-retargeting)). Either path produces a `THREE.AnimationClip` played through a shared `THREE.AnimationMixer`, targeting bones via `getNormalizedBoneNode()`.
- **Triggered pose** (`poseAnimationUrl` + a new `poseTriggerId`): plays once (`LoopOnce`, `clampWhenFinished`), blending in from the current bone pose over ~0.25s. A clip shorter than that blend window (a single-frame pose export) is held for a fixed duration before transitioning back, rather than reverting the instant it technically finishes. Idle head-sway is paused for the duration; idle blink and blendshape expression continue independently, on a separate channel from the animation clip's bone tracks — this is what makes a pose's blendshape expression and body animation combinable without conflict.
- **Default pose** (`defaultPoseAnimationUrl`): loads once and loops (`LoopRepeat`) as the idle baseline whenever no triggered pose is active — the sole idle animation when configured, replacing the previous fixed idle stance. A triggered pose interrupts it (stopping the loop's action so the two don't fight over the same bones) and it resumes, blending back in, once the triggered pose's hold/blend-out completes.
- `poseBlendshapes`/`defaultPoseBlendshapes` are applied to the same lerp/target map already used for `blendshapes` — the triggered pose's own blendshapes take priority while its hold window is active (or for its full body-animation duration, whichever is longer, so a long animation's expression doesn't go blank before the body finishes), falling back to the default pose's blendshapes otherwise, and to pure neutral when neither is configured.
- A `.fbx` clip that fails to retarget cleanly (non-Mixamo bone names) is treated as a load failure — logged, pose animation dropped, rest of the avatar unaffected — same as any other load error.

---

## Modified: `useEmotions` hook

**File**: `resources/js/hooks/useEmotions.js`

New values added to the returned object. Existing `emotions`/`getVrmBlendshapes`/etc. unchanged, but are only meaningfully populated for image-portrait assistants — an avatar3d assistant's `emotions` array is always empty.

| Key | Type | Description |
|---|---|---|
| `poses` | `Array<{id, name, vrm_blendshapes, animation_url, animation_original_name}>` | Raw pose list for the currently loaded assistant, including the always-present `"default"` entry |
| `getPoseBlendshapes` | `(name: string) => Array<{expression: string, weight: number}>` | Looks up the named pose's `vrm_blendshapes`; returns `[]` if the pose doesn't exist or has none |
| `getPoseAnimationUrl` | `(name: string) => string \| null` | Looks up the named pose's `animation_url`; returns `null` if the pose doesn't exist or has none |

`fetchEmotions(assistantId)` populates `poses` from the extended `GET /api/assistants/{assistant}/emotions` response (see [contracts/api.md](api.md)).

---

## Modified: `resources/js/utils/parsers.js`

New export `parsePoseFromResponse(text, validPoseNames)`, mirroring `parseEmotionFromResponse` but matching a bare `[name]` tag (`/^\[([^\]]+)\]/`, not letters-only, so pose names with spaces or underscores round-trip) rather than a colon-qualified form. Returns `{ content, pose }` where `pose` is `null` if no tag matched or the matched (trimmed, lowercased) name isn't in `validPoseNames`.

---

## Modified: `ChatPage` / `AuthenticatedLayout`

**Files**: `resources/js/pages/ChatPage.jsx`, `resources/js/layouts/AuthenticatedLayout.jsx`

`AuthenticatedLayout` gains `currentPose` state (`{name, triggerId} | null`, parallel to its existing `currentEmotion` state), resolves `poseBlendshapes`/`poseAnimationUrl`/`poseTriggerId`/`poseName` from it plus `defaultPoseBlendshapes`/`defaultPoseAnimationUrl` from the always-present `"default"` pose, and exposes `setCurrentPose` via `Outlet` context alongside the existing `setCurrentEmotion`.

`ChatPage` branches on `portraitType` for both parsing and state updates: `avatar3d` assistants call only `parsePoseFromResponse` and only ever call `setCurrentPose({name, triggerId: Date.now()})` (never `setCurrentEmotion`); image-portrait assistants call only `parseEmotionFromResponse` and only ever call `setCurrentEmotion`. A fresh `triggerId` per genuine trigger (not just the pose name) means re-signaling the same pose name always retriggers correctly. This branch applies uniformly across the normal chat-reply path, the `/create-image` reaction, and the `/change-background` reaction — none of the three ever calls `setCurrentEmotion` for an avatar3d assistant.

---

## New: `PoseEditor` component

**File**: `resources/js/components/PoseEditor.jsx`

Per-assistant pose editor for non-default poses, shown on Create/Edit assistant pages when `portraitType === 'avatar3d'`, in place of `VrmEmotionEditor` (never alongside it). Each pose renders as a collapsible accordion row — collapsed to just its name and a delete control, expanding to reveal an editable name field, an optional set of `{expression, weight%}` blendshape sub-rows (reuses `BlendshapeRows` from `VrmEmotionEditor.jsx`), and an optional animation file upload/delete control accepting `.vrma` or `.fbx` via the exported `AnimationFileControl` sub-component (also reused by `DefaultPoseEditor`). The "add new pose" form includes the file picker inline rather than requiring the pose to be saved first.

| Prop | Type | Description |
|---|---|---|
| `poses` | `Array<{id, name, vrm_blendshapes, animation_url, animation_original_name}>` | Rows to render — excludes the `"default"` pose, filtered out by the caller |
| `onAdd` | `(name: string, blendshapes: Array, file: File \| null) => void` | Called when a new pose is added, optionally with an animation file in the same step |
| `onDelete` | `(pose) => void` | Called when a pose is deleted, after confirmation |
| `onUpdateBlendshapes` | `(pose, name: string, blendshapes: Array) => void` | Called when an existing row's Save button is pressed — name and blendshapes save together |
| `onUploadAnimation` | `(pose, file: File) => void` | Called when a `.vrma` or `.fbx` file is selected for an existing pose |
| `onDeleteAnimation` | `(pose) => void` | Called when a pose's animation file is deleted, after confirmation |

Blendshapes and the animation file are both optional and independent — the UI does not force a choice between them; a pose can have neither, either, or both.

---

## New: `DefaultPoseEditor` component

**File**: `resources/js/components/DefaultPoseEditor.jsx`

The assistant's default pose, shown as its own dedicated, always-rendered block above `PoseEditor`'s list — mirroring exactly how image-portrait assistants render a "Default Image" section outside the general emotions grid. Name-locked (`"default"`, not editable) and undeletable; wraps the same collapsible-accordion presentation and reuses `BlendshapeRows`/`AnimationFileControl` from `PoseEditor.jsx`. Left entirely unconfigured, the avatar falls back to its existing hardcoded idle/neutral behavior.

| Prop | Type | Description |
|---|---|---|
| `pose` | `{name: 'default', vrm_blendshapes, animation_url, animation_original_name}` | The default pose's current data |
| `onUpdateBlendshapes` | `(blendshapes: Array) => void` | Called on Save |
| `onUploadAnimation` | `(file: File) => void` | Called when an animation file is selected |
| `onDeleteAnimation` | `() => void` | Called when the animation file is deleted, after confirmation |
