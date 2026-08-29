# Frontend Component Contracts: VRMA Avatar Pose Animations

**Feature**: `006-vrma-avatar-poses` | **Date**: 2026-08-29

---

## Modified: `Portrait` component

**File**: `resources/js/components/Portrait.jsx`

Two new props added. All existing props (`emotion`, `getVrmBlendshapes`, `portraitType`, `vrmUrl`, etc. — see `004-vrm-3d-avatar/contracts/frontend-components.md`) unchanged.

| Prop | Type | Required | Default | Description |
|---|---|---|---|---|
| `poseBlendshapes` | `Array<{expression: string, weight: number}>` | no | `[]` | The active pose's blendshape targets, from `useEmotions().getPoseBlendshapes(pose)` |
| `poseAnimationUrl` | `string \| null` | no | `null` | URL to the active pose's `.vrma` file, from `useEmotions().getPoseAnimationUrl(pose)` |

When `portraitType === 'avatar3d'` and `vrmUrl` is non-null: both props are passed through to `<VrmAvatar>` alongside the existing `emotion`/`blendshapes` props. When no pose is currently active, both are their default empty values and `VrmAvatar` behaves exactly as it does today.

---

## Modified: `VrmAvatar` component

**File**: `resources/js/components/VrmAvatar.jsx`

Two new props added. All existing props (`vrmUrl`, `emotion`, `blendshapes`, `assistantId`, `conversationId`) unchanged.

| Prop | Type | Required | Default | Description |
|---|---|---|---|---|
| `poseBlendshapes` | `Array<{expression: string, weight: number}>` | no | `[]` | Facial blendshape targets from the active pose, merged with `blendshapes` (emotion) into the same lerp target map |
| `poseAnimationUrl` | `string \| null` | no | `null` | URL to a `.vrma` clip to play once on the body skeleton |

**New behaviour**:
- When `poseAnimationUrl` changes to a non-null value: loads the clip via `VRMAnimationLoaderPlugin` (`@pixiv/three-vrm-animation`), plays it once through a `THREE.AnimationMixer`, and pauses the existing idle head-sway for the duration (idle blink and blendshape expression continue independently — see [research.md Decision 6](../research.md#decision-6-vrma-playback--interaction-with-existing-idle-expression-animation)). On the mixer's `finished` event, resumes idle head-sway; `poseAnimationUrl` reverting to `null` (or the same clip's completion) marks the pose as no longer active.
- `poseBlendshapes` targets are merged into the same lerp/target map already used for `blendshapes` (emotion) — both are applied concurrently, satisfying spec FR-012 / SC-006 and FR-016 / SC-007 (pose + emotion concurrently).
- Load failures for the animation clip are logged and do not affect the rest of the avatar (facial expression and idle behavior continue normally) — the pose signal is simply dropped, mirroring how a VRM model load failure falls back gracefully rather than crashing.

---

## Modified: `useEmotions` hook

**File**: `resources/js/hooks/useEmotions.js`

Three new values added to the returned object. Existing values unchanged.

| Key | Type | Description |
|---|---|---|
| `poses` | `Array<{id, name, vrm_blendshapes, animation_url}>` | Raw pose list for the currently loaded assistant |
| `getPoseBlendshapes` | `(name: string) => Array<{expression: string, weight: number}>` | Looks up the named pose's `vrm_blendshapes`; returns `[]` if the pose doesn't exist or has none |
| `getPoseAnimationUrl` | `(name: string) => string \| null` | Looks up the named pose's `animation_url`; returns `null` if the pose doesn't exist or has none |

`fetchEmotions(assistantId)` is updated to also populate `poses` from the extended `GET /api/assistants/{assistant}/emotions` response (see [contracts/api.md](api.md)).

---

## Modified: `resources/js/utils/parsers.js`

New export `parsePoseFromResponse(text, validPoseNames)`, mirroring the existing `parseEmotionFromResponse`. Strips a leading `[pose: <name>]` tag (checked after any leading `[emotion]`/`[intimate]` tags are stripped), returning `{ content, pose }` where `pose` is `null` if no tag matched or the matched name isn't in `validPoseNames`.

---

## Modified: `ChatPage` / `AuthenticatedLayout`

**Files**: `resources/js/pages/ChatPage.jsx`, `resources/js/layouts/AuthenticatedLayout.jsx`

`AuthenticatedLayout` gains `currentPose` state (parallel to its existing `currentEmotion` state), passed to `Portrait` as `poseBlendshapes={getPoseBlendshapes(currentPose)}` / `poseAnimationUrl={getPoseAnimationUrl(currentPose)}`, and exposed via `Outlet` context as `setCurrentPose` alongside the existing `setCurrentEmotion`.

`ChatPage` calls `parsePoseFromResponse` alongside `parseEmotionFromResponse` when handling a chat response, then calls `setCurrentPose(pose)`.

---

## New: `PoseEditor` component

**File**: `resources/js/components/PoseEditor.jsx`

Per-assistant pose editor, shown on Create/Edit assistant pages when `portraitType === 'avatar3d'`, below the existing `VrmEmotionEditor` sections. Each pose row holds an optional set of `{expression, weight%}` blendshape sub-rows (reuses the existing `BlendshapeRows` component from `VrmEmotionEditor.jsx`) and an optional `.vrma` file upload/delete control (reuses the upload-button-with-filename pattern already used for the assistant-level VRM upload in `EditAssistantPage.jsx`).

| Prop | Type | Description |
|---|---|---|
| `poses` | `Array<{id?, name, vrm_blendshapes, animation_url}>` | Rows to render — `id` absent for locally-staged (not-yet-created) rows |
| `onAdd` | `(name: string, blendshapes: Array) => void` | Called when a new pose row is added |
| `onDelete` | `(pose) => void` | Called when a pose row is deleted |
| `onUpdateBlendshapes` | `(pose, blendshapes: Array) => void` | Called when an existing row's blendshape Save button is pressed |
| `onUploadAnimation` | `(pose, file: File) => void` | Called when a `.vrma` file is selected for a pose |
| `onDeleteAnimation` | `(pose) => void` | Called when a pose's animation file is deleted |

Unlike `VrmEmotionEditor`, blendshapes and the animation file are both optional and independent — the UI does not force a choice between them; a row can have neither, either, or both.
