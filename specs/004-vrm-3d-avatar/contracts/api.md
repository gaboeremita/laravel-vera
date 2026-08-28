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
      "video_url": null
    }
  ]
}
```

`vrm_url` is `null` when no VRM file has been uploaded, regardless of `portrait_type`.

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

**Change**: Response now includes `portrait_type` and `vrm_url`.

**Response** `200 OK` (partial — existing fields unchanged):
```json
{
  "id": 1,
  "portrait_type": "image | avatar3d",
  "vrm_url": "https://example.com/storage/vrm/1/model.vrm | null"
}
```

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

## Route Names

| Route | Name |
|---|---|
| `POST /api/assistants/{id}/vrm` | `assistants.vrm.store` |
| `DELETE /api/assistants/{id}/vrm` | `assistants.vrm.destroy` |
