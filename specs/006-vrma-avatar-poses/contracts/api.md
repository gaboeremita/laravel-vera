# API Contracts: VRMA Avatar Pose Animations

**Feature**: `006-vrma-avatar-poses` | **Date**: 2026-08-29

All routes require `Authorization: Bearer <token>` (Sanctum). All endpoints are scoped to the authenticated user's assistants — no assistant belonging to another user is accessible. All pose endpoints additionally scope the pose to the given assistant (`$assistant->poses()->findOrFail($poseId)`).

---

## Modified Endpoints

### `GET /api/assistants/{assistant}/emotions`

**Change**: Response envelope adds a `poses` array alongside the existing `portrait_type`, `vrm_url`, and `emotions` fields.

**Response** `200 OK`:
```json
{
  "portrait_type": "image | avatar3d",
  "vrm_url": "https://example.com/storage/vrm/1/model.vrm | null",
  "emotions": [ { "...": "unchanged, see 004-vrm-3d-avatar/contracts/api.md" } ],
  "poses": [
    {
      "id": 1,
      "name": "spin",
      "vrm_blendshapes": null,
      "animation_url": "https://example.com/storage/poses/1/1/spin.vrma"
    },
    {
      "id": 2,
      "name": "happy-wave",
      "vrm_blendshapes": [{ "expression": "happy", "weight": 0.8 }],
      "animation_url": "https://example.com/storage/poses/1/2/wave.vrma"
    }
  ]
}
```

`animation_url` is `null` when the pose has no uploaded `.vrma` file. `vrm_blendshapes` is `null` when the pose has no blendshape configuration. A pose may have either, both, or (transiently, before the user configures anything) neither.

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

## Route Names

| Route | Name |
|---|---|
| `POST /api/assistants/{assistantId}/poses` | `assistants.poses.store` |
| `POST /api/assistants/{assistantId}/poses/{pose}` | `assistants.poses.update` |
| `DELETE /api/assistants/{assistantId}/poses/{pose}` | `assistants.poses.destroy` |
| `POST /api/assistants/{assistantId}/poses/{pose}/animation` | `assistants.poses.animation.store` |
| `DELETE /api/assistants/{assistantId}/poses/{pose}/animation` | `assistants.poses.animation.destroy` |

## LLM-Facing Contract (not HTTP)

The system prompt sent to the LLM gains a `pose tags` section (present only when the assistant has ≥1 pose configured — see [research.md Decision 4](../research.md#decision-4-llm-prompt-guidance-for-poses)), listing available pose names and instructing the LLM that they represent physical actions distinct from emotions.

The LLM signals a pose by prefixing its reply with `[pose: <name>]`, following any leading `[emotion]`/`[intimate]` tags — e.g. `[happy][pose: spin] Watch this!`. See [research.md Decision 5](../research.md#decision-5-pose-tag-syntax--parsing-scope) for the full tag grammar and parsing scope.
