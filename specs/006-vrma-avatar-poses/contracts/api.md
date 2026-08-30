# API Contracts: VRMA Avatar Pose Animations

**Feature**: `006-vrma-avatar-poses` | **Date**: 2026-08-29

All routes require `Authorization: Bearer <token>` (Sanctum). All endpoints are scoped to the authenticated user's assistants — no assistant belonging to another user is accessible. All pose endpoints additionally scope the pose to the given assistant (`$assistant->poses()->findOrFail($poseId)`).

---

## Modified Endpoints

### `GET /api/assistants/{assistant}/emotions`

**Change**: Response envelope adds a `poses` array alongside the existing `portrait_type`, `vrm_url`, and `emotions` fields. `emotions` and `poses` are mutually exclusive in practice by `portrait_type` — an image-portrait assistant's `poses` array is always empty (poses aren't configurable for it), and a 3D avatar assistant's `emotions` array is always empty (the emotion system is hidden for it; any pre-existing emotions were converted to poses).

**Response** `200 OK`:
```json
{
  "portrait_type": "image | avatar3d",
  "vrm_url": "https://example.com/storage/vrm/1/model.vrm | null",
  "emotions": [ { "...": "unchanged, see 004-vrm-3d-avatar/contracts/api.md — empty for avatar3d assistants" } ],
  "poses": [
    {
      "id": 1,
      "name": "default",
      "vrm_blendshapes": [{ "expression": "relaxed", "weight": 0.3 }],
      "animation_url": "https://example.com/storage/poses/1/1/idle.fbx",
      "animation_original_name": "idle.fbx"
    },
    {
      "id": 2,
      "name": "spin",
      "vrm_blendshapes": null,
      "animation_url": "https://example.com/storage/poses/1/2/spin.vrma",
      "animation_original_name": "spin.vrma"
    },
    {
      "id": 3,
      "name": "happy-wave",
      "vrm_blendshapes": [{ "expression": "happy", "weight": 0.8 }],
      "animation_url": "https://example.com/storage/poses/1/3/wave.vrma",
      "animation_original_name": "wave.vrma"
    }
  ]
}
```

`animation_url`/`animation_original_name` are `null` when the pose has no uploaded animation file. `vrm_blendshapes` is `null` when the pose has no blendshape configuration. A pose may have either, both, or (transiently, before the user configures anything) neither. The `"default"` pose is always present in this array — it is not optional to include, though its own weights/animation are optional to configure.

---

## New Endpoints

### `POST /api/assistants/{assistantId}/poses`

Creates a new pose for the assistant.

**Request**: JSON or multipart

| Field | Type | Required | Validation |
|---|---|---|---|
| `name` | string | yes | max 255, unique per assistant |
| `vrm_blendshapes` | array | no | see below |
| `vrm_blendshapes.*.expression` | string | required if array present | max 100 |
| `vrm_blendshapes.*.weight` | numeric | required if array present | 0–100, stored normalized to 0.0–1.0 |

**Response** `201 Created`:
```json
{
  "id": 3,
  "name": "dance",
  "vrm_blendshapes": null,
  "animation_url": null
}
```

**Errors**:
- `422` — name missing, or a pose with that name already exists on this assistant
- `404` — assistant not found or not owned by user

---

### `POST /api/assistants/{assistantId}/poses/{pose}` (update)

Updates a pose's `name` and/or `vrm_blendshapes` independently of its animation file. Same validation as store, all fields `sometimes`.

**Response** `200 OK`: same shape as store.

**Errors**:
- `422` — new name collides with another pose on the same assistant
- `404` — assistant or pose not found, or pose not owned by this assistant

---

### `DELETE /api/assistants/{assistantId}/poses/{pose}`

Deletes the pose and, if present, its associated animation file (both the database record and the stored file).

**Response** `200 OK`:
```json
{
  "message": "Pose deleted"
}
```

**Errors**:
- `404` — assistant or pose not found

---

### `POST /api/assistants/{assistantId}/poses/{pose}/animation`

Uploads an animation file for the pose — `.vrma` or `.fbx`. Replaces any existing animation file on that pose. Independent of the pose's `vrm_blendshapes`. `.fbx` uploads are retargeted onto the avatar's skeleton at playback time using a Mixamo bone-naming mapping (see [research.md Decision 9](../research.md#decision-9-fbx-animation-support-via-mixamo-retargeting)) — non-Mixamo `.fbx` rigs are accepted by validation but not guaranteed to animate correctly.

**Request**: `multipart/form-data`

| Field | Type | Required | Validation |
|---|---|---|---|
| `animation` | file | yes | `.vrma` or `.fbx` extension, max 10 240 KB (10 MB) |

**Response** `201 Created`:
```json
{
  "animation_url": "https://example.com/storage/poses/1/3/dance.vrma"
}
```

**Errors**:
- `422` — file missing, wrong extension, or exceeds 10 MB
- `404` — assistant or pose not found

---

### `DELETE /api/assistants/{assistantId}/poses/{pose}/animation`

Removes the pose's animation file from storage and the database. Does not delete the pose itself or its `vrm_blendshapes`.

**Response** `200 OK`:
```json
{
  "message": "Pose animation deleted"
}
```

**Errors**:
- `404` — assistant or pose not found, or the pose has no animation file

---

### `POST /api/assistants/{assistantId}/poses/default` (update)

Updates the assistant's default pose's `vrm_blendshapes`. The default pose always exists per 3D avatar assistant and cannot be targeted by the regular `POST /poses/{pose}` route by name — `name` is not accepted on this route and is never anything other than `"default"`.

**Response** `200 OK`: same shape as a regular pose update.

**Errors**:
- `404` — assistant not found

---

### `POST /api/assistants/{assistantId}/poses/default/animation`

Uploads or replaces the default pose's animation file. Same validation as the regular per-pose animation upload. Unlike a triggered pose's animation, the default pose's animation loops continuously as the idle baseline rather than playing once.

**Response** `201 Created`: same shape as a regular pose animation upload.

**Errors**:
- `422` — file missing, wrong extension, or exceeds 10 MB
- `404` — assistant not found

---

### `DELETE /api/assistants/{assistantId}/poses/default/animation`

Removes the default pose's animation file. The avatar falls back to its hardcoded idle behavior once no default-pose animation is configured.

**Response** `200 OK`:
```json
{
  "message": "Pose animation deleted"
}
```

**Errors**:
- `404` — assistant not found, or the default pose has no animation file

---

## Route Names

| Route | Name |
|---|---|
| `POST /api/assistants/{assistantId}/poses` | `assistants.poses.store` |
| `POST /api/assistants/{assistantId}/poses/{pose}` | `assistants.poses.update` |
| `DELETE /api/assistants/{assistantId}/poses/{pose}` | `assistants.poses.destroy` |
| `POST /api/assistants/{assistantId}/poses/{pose}/animation` | `assistants.poses.animation.store` |
| `DELETE /api/assistants/{assistantId}/poses/{pose}/animation` | `assistants.poses.animation.destroy` |
| `POST /api/assistants/{assistantId}/poses/default` | `assistants.poses.default.update` |
| `POST /api/assistants/{assistantId}/poses/default/animation` | `assistants.poses.default.animation.store` |
| `DELETE /api/assistants/{assistantId}/poses/default/animation` | `assistants.poses.default.animation.destroy` |

The `/default` routes are registered before the `{pose}` wildcard group so the literal `"default"` path segment isn't captured by it.

## LLM-Facing Contract (not HTTP)

The system prompt sent to the LLM gains a `pose tags` section (present only when the assistant has ≥1 non-default pose configured), whose `available poses` list is the only part injected by the backend — the instructional wording is authored in the assistant's own prompt configuration, the same way `emotion tags`' wording is (see [research.md Decision 4](../research.md#decision-4-llm-prompt-guidance-for-poses)). A 3D avatar assistant never receives an `emotion tags` section; an image-portrait assistant never receives a `pose tags` section — the two are mutually exclusive by `portrait_type`.

The LLM signals a pose by prefixing its reply with a bare `[name]` tag — identical grammar to an emotion tag, e.g. `[spin] Watch this!`. See [research.md Decision 5](../research.md#decision-5-pose-tag-syntax--parsing-scope) for the full tag grammar and parsing scope.
