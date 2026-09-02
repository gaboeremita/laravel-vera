# API Contract: World Music Track

## POST /api/worlds/{world}/track

Sets or replaces the world's music track.

**Authorization**: caller must pass `WorldPolicy::update` for `{world}` (i.e. be the world's owner). Otherwise `403`.

**Request**: `multipart/form-data`

| Field | Type | Rules |
|---|---|---|
| `track` | file | required, mimes:mp3,wav, max 20480 KB |

**Responses**:

- `201 Created`
  ```json
  { "track_url": "https://.../worlds/1/track/song.mp3" }
  ```
- `403 Forbidden` — caller is not the world's owner.
- `422 Unprocessable Entity` — file missing, wrong format, or over size limit.

**Side effects**: previous track file (if any) is deleted from storage after the new file is successfully stored and the world's `Track` row is created/updated.

---

## DELETE /api/worlds/{world}/track

Removes the world's music track, if one is set.

**Authorization**: caller must pass `WorldPolicy::update` for `{world}`. Otherwise `403`.

**Responses**:

- `200 OK`
  ```json
  { "message": "Track deleted." }
  ```
- `403 Forbidden` — caller is not the world's owner.
- `404 Not Found` — world has no track set.

**Side effects**: track file deleted from storage; the world's `Track` row is deleted.

---

## World resource (existing endpoint, extended)

`GET /api/worlds/{world}` (and any endpoint returning a `WorldResource`) additionally includes:

```json
{
  "track_url": "https://.../worlds/1/track/song.mp3",
  "track_original_name": "song.mp3"
}
```

Both fields are `null` when the world has no track set.
