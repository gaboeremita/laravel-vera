# Frontend Component Contracts: 3D VRM Avatar Portrait

**Feature**: `004-vrm-3d-avatar` | **Date**: 2026-08-28

---

## Modified: `Portrait` component

**File**: `resources/js/components/Portrait.jsx`

Two new props added. All existing props unchanged.

| Prop | Type | Required | Default | Description |
|---|---|---|---|---|
| `portraitType` | `'image' \| 'avatar3d'` | no | `'image'` | Determines which rendering branch to use |
| `vrmUrl` | `string \| null` | no | `null` | URL to the `.vrm` file; only used when `portraitType === 'avatar3d'` |

When `portraitType === 'avatar3d'` and `vrmUrl` is non-null: renders `<VrmAvatar>` in place of the image/video.
When `portraitType === 'avatar3d'` and `vrmUrl` is null: falls back to the default VERA avatar image (same fallback as a missing image URL today).
When `portraitType === 'image'`: existing behaviour, unchanged.

---

## New: `VrmAvatar` component

**File**: `resources/js/components/VrmAvatar.jsx`

Self-contained 3D avatar renderer. Mounts an R3F canvas, loads the VRM model, manages expression blending, and runs the idle animation loop.

| Prop | Type | Required | Description |
|---|---|---|---|
| `vrmUrl` | `string` | yes | URL to the `.vrm` file to load |
| `emotion` | `string` | yes | Current VERA emotion tag (e.g. `'happy'`, `'neutral'`) — used only to detect when the expression should start its hold/decay timer |
| `blendshapes` | `Array<{expression: string, weight: number}>` | no, default `[]` | The resolved blendshape targets for the current emotion, from `useEmotions().getVrmBlendshapes(emotion)` |

**Behaviour**:
- Loads the VRM file from `vrmUrl`. Shows a loading state while the file is fetching.
- On load error: renders the default VERA avatar image fallback (does not throw; logs the error).
- Lerps current blendshape values toward `blendshapes` on every frame (~300 ms to reach target); any expression name previously active but no longer targeted also lerps down to 0, so switching emotions never leaves a blendshape stuck. No fixed list of expression names — whatever names appear in `blendshapes` (and whatever the loaded VRM model actually exposes) is what animates.
- Holds an expression for ~3.5s after `emotion` changes, then decays back to neutral so the face doesn't freeze in the last emotion indefinitely.
- Runs idle blink at a randomised interval of 2–6 seconds.
- Pauses the render loop when the component is not visible (uses R3F's `frameloop="demand"` or visibility API).

---

## Modified: `useEmotions` hook

**File**: `resources/js/hooks/useEmotions.js`

Three new values added to the returned object. Existing values unchanged.

| Key | Type | Description |
|---|---|---|
| `portraitType` | `'image' \| 'avatar3d'` | Portrait type for the currently loaded assistant |
| `vrmUrl` | `string \| null` | VRM URL for the currently loaded assistant |
| `getVrmBlendshapes` | `(name: string) => Array<{expression: string, weight: number}>` | Looks up the named emotion's `vrm_blendshapes`; returns `[]` if the emotion doesn't exist or has none |

`fetchEmotions(assistantId)` is updated to consume the new response envelope from `GET /api/assistants/{assistant}/emotions` and populate these values. Initial state is `'image'`, `null`, and an empty-array-returning function respectively.

---

## New: `VrmEmotionEditor` component

**File**: `resources/js/components/VrmEmotionEditor.jsx`

Per-assistant emotion → VRM blendshape mapping editor, shown on Create/Edit assistant pages when `portraitType === 'avatar3d'` in place of the image-mode `EmotionGrid`. Each emotion row holds N `{expression, weight%}` sub-rows, added/removed freely, with an explicit Save action (no auto-save per keystroke).

| Prop | Type | Description |
|---|---|---|
| `emotions` | `Array<{id?, name, vrm_blendshapes}>` | Rows to render — `id` absent for locally-staged (not-yet-created) rows |
| `onAdd` | `(name: string, blendshapes: Array) => void` | Called when a new emotion row is added |
| `onDelete` | `(emotion) => void` | Called when an emotion row is deleted |
| `onUpdateBlendshapes` | `(emotion, blendshapes: Array) => void` | Called when an existing row's Save button is pressed |
| `label` | `string`, default `'Emotions'` | Section label (used for the "Restricted Emotions" variant) |

Expression name inputs are free text with a `<datalist>` of common VRM preset names for discoverability — nothing constrains the value to that list, since a VRM model may expose custom blendshape names.
