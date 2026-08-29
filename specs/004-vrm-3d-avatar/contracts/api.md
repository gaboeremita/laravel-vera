# API Contracts: 3D VRM Avatar Portrait

**Feature**: `004-vrm-3d-avatar` | **Date**: 2026-08-28

All routes require `Authorization: Bearer <token>` (Sanctum). All endpoints are scoped to the authenticated user's assistants — no assistant belonging to another user is accessible.

---

## Modified Endpoints

### `GET /api/assistants/{assistant}/emotions`

**Change**: Response envelope changes from a flat array to an object. Adds `portrait_type` and `vrm_url` alongside the existing `emotions` array.

**Response** `200 OK`:
```json
{
  "portrait_type": "image | avatar3d",
  "vrm_url": "https://example.com/storage/vrm/1/model.vrm | null",
  "emotions": [
    {
      "name": "default",
      "image_url": "https://example.com/storage/emotions/1/default.png",
      "video_url": null,
      "vrm_blendshapes": [{ "expression": "happy", "weight": 0.8 }] 
    }
  ]
}
```

`vrm_url` is `null` when no VRM file has been uploaded, regardless of `portrait_type`. `vrm_blendshapes` is `null` for emotions with no blendshape mapping.

---

### `PATCH /api/assistants/{id}`

**Change**: Accepts the new optional `portrait_type` field.

**Request** (partial):
```json
{
  "portrait_type": "image | avatar3d"
}
```

**Validation**:
- `portrait_type`: `sometimes`, must be one of `image`, `avatar3d`

**Response** `200 OK`: existing assistant object shape, now including `portrait_type`.

---

### `GET /api/assistants/{id}`

**Change**: Response now includes `portrait_type`, `vrm_url`, `vrm_original_name`, `image_url` (card image), and each emotion in `emotions`/`restricted_emotions` includes `vrm_blendshapes`.

**Response** `200 OK` (partial — existing fields unchanged):
```json
{
  "id": 1,
  "portrait_type": "image | avatar3d",
  "vrm_url": "https://example.com/storage/vrm/1/model.vrm | null",
  "vrm_original_name": "vera.vrm | null",
  "image_url": "https://example.com/storage/card/1/thumb.png | null"
}
```

---

### `POST /api/assistants/{assistant}/emotions`

**Change**: `image` is now optional (`sometimes` rather than `required`). Accepts an optional `vrm_blendshapes` array, independent of `image`.

**Request** (partial, JSON or multipart):
```json
{
  "name": "happy",
  "vrm_blendshapes": [{ "expression": "happy", "weight": 100 }]
}
```

**Validation**:
- `vrm_blendshapes`: `sometimes`, array
- `vrm_blendshapes.*.expression`: required, string, max 100
- `vrm_blendshapes.*.weight`: required, numeric, 0–100 (stored normalized to 0.0–1.0)

**Response** `201 Created`: adds `vrm_blendshapes` to the existing `{id, name, image_url}` shape.

---

### `POST /api/assistants/{assistant}/emotions/{emotion}` (update)

**Change**: Accepts `vrm_blendshapes`, updatable independently of `image` and `name`. Same validation as store.

---

## New Endpoints

### `POST /api/assistants/{id}/vrm`

Uploads a `.vrm` file for the assistant. Replaces any existing VRM file.

**Request**: `multipart/form-data`

| Field | Type | Required | Validation |
|---|---|---|---|
| `vrm` | file | yes | `.vrm` extension, `model/gltf-binary` MIME type preferred, max 51 200 KB (50 MB) |

**Response** `201 Created`:
```json
{
  "vrm_url": "https://example.com/storage/vrm/1/model.vrm"
}
```

**Errors**:
- `422` — file missing, wrong type, or exceeds 50 MB
- `404` — assistant not found or not owned by user

---

### `DELETE /api/assistants/{id}/vrm`

Removes the VRM file from storage and from the database. Does not change `portrait_type`.

**Response** `200 OK`:
```json
{
  "message": "VRM file deleted"
}
```

**Errors**:
- `404` — assistant not found, not owned by user, or no VRM file exists

---

### `POST /api/assistants/{id}/image`

Uploads a card image for the assistant, used as the assistants-menu thumbnail. Replaces any existing card image. Independent of `portrait_type` and of emotion images.

**Request**: `multipart/form-data`

| Field | Type | Required | Validation |
|---|---|---|---|
| `image` | file | yes | image, max 10 480 KB (~10.2 MB) |

**Response** `201 Created`:
```json
{
  "image_url": "https://example.com/storage/card/1/thumb.png"
}
```

---

### `DELETE /api/assistants/{id}/image`

Removes the card image from storage and the database.

**Response** `200 OK`:
```json
{
  "message": "Card image deleted."
}
```

**Errors**:
- `404` — assistant not found, not owned by user, or no card image exists

---

## Route Names

| Route | Name |
|---|---|
| `POST /api/assistants/{id}/vrm` | `assistants.vrm.store` |
| `DELETE /api/assistants/{id}/vrm` | `assistants.vrm.destroy` |
| `POST /api/assistants/{id}/image` | `assistants.image.store` |
| `DELETE /api/assistants/{id}/image` | `assistants.image.destroy` |
