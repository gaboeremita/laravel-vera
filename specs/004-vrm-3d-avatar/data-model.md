# Data Model: 3D VRM Avatar Portrait

**Feature**: `004-vrm-3d-avatar` | **Date**: 2026-08-28

## Schema Changes

### `assistants` table — new column

```
portrait_type  string  default 'image'   — values: 'image' | 'avatar3d'
```

Migration: `add_portrait_type_to_assistants_table`

---

### `vrm_files` table — new table

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `vrmable_type` | string | no | morph type (e.g. `App\Models\Assistant`) |
| `vrmable_id` | bigint | no | morph id |
| `path` | string | no | storage path |
| `disk` | string | no | storage disk (e.g. `public`) |
| `mime_type` | string | no | e.g. `model/gltf-binary` |
| `size` | integer | no | bytes |
| `original_name` | string | yes | original filename from upload |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

Index: `(vrmable_type, vrmable_id)` — unique (one VRM file per assistant).

Migration: `create_vrm_files_table`

---

## Model Changes

### `Assistant` model

- New cast: `portrait_type` → `AssistantPortraitType`
- New fillable: `portrait_type`
- New relationship: `vrm(): MorphOne` → `VrmFile`

### New `AssistantPortraitType` enum

```
App\Enums\AssistantPortraitType
  Image    = 'image'
  Avatar3d = 'avatar3d'
```

### New `VrmFile` model

```
App\Models\VrmFile
  fillable: path, disk, mime_type, size, original_name
  accessor: url — Storage::disk($this->disk)->url($this->path)
  relationship: vrmable(): MorphTo
```

---

## No Existing Table Changes

The `emotions` table and related models are unchanged. The `images` and `videos` tables are unchanged. The `vrm_files` table is additive.
