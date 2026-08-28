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
| `emotion` | `string` | yes | Current VERA emotion tag (e.g. `'happy'`, `'neutral'`) |

**Behaviour**:
- Loads the VRM file from `vrmUrl`. Shows a loading state while the file is fetching.
- On load error: renders the default VERA avatar image fallback (does not throw; logs the error).
- Maps `emotion` to blendshape values via `vrmExpressions.js`.
- Lerps current blendshape values toward targets on every frame (~300 ms to reach target).
- Runs idle blink at a randomised interval of 2–6 seconds.
- Pauses the render loop when the component is not visible (uses R3F's `frameloop="demand"` or visibility API).

---

## New: `vrmExpressions.js` utility

**File**: `resources/js/utils/vrmExpressions.js`

Exports a pure function `getBlendshapeTargets(emotionTag)` that returns an array of `{ expression: string, weight: number }` pairs. Returns all-zero weights for unknown tags.

```js
// Example outputs
getBlendshapeTargets('happy')    // [{ expression: 'happy', weight: 0.8 }]
getBlendshapeTargets('flustered') // [{ expression: 'surprised', weight: 0.3 }, { expression: 'happy', weight: 0.2 }]
getBlendshapeTargets('unknown')  // []  (neutral — caller treats missing as 0.0)
```

---

## Modified: `useEmotions` hook

**File**: `resources/js/hooks/useEmotions.js`

Two new values added to the returned object. Existing values unchanged.

| Key | Type | Description |
|---|---|---|
| `portraitType` | `'image' \| 'avatar3d'` | Portrait type for the currently loaded assistant |
| `vrmUrl` | `string \| null` | VRM URL for the currently loaded assistant |

`fetchEmotions(assistantId)` is updated to consume the new response envelope from `GET /api/assistants/{assistant}/emotions` and populate these two new values. Initial state is `'image'` and `null` respectively.
