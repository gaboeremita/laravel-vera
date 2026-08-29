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
- New relationship: `cardImage(): MorphOne` → `Image` (assistants-menu thumbnail)
- New method: `promptEmotionNames(): array{regular: array<string>, intimate: array<string>}` — the assistant's own `Emotion` names, injected into the LLM system prompt so it knows which tags it may emit

### `Emotion` model

- New cast: `vrm_blendshapes` → `array`
- New fillable: `vrm_blendshapes`
- New static method: `normalizeBlendshapes(?array $blendshapes): ?array` — converts UI-collected 0–100 percentages to the 0.0–1.0 scale VRM expects

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

---

### `emotions` table — new column

```
vrm_blendshapes  json  nullable  — array<{expression: string, weight: float}>
```

Migration: `add_vrm_blendshapes_to_emotions_table`. `weight` is stored on a 0.0–1.0 scale; the UI collects it as a 0–100 percentage and `Emotion::normalizeBlendshapes()` converts it on write.

`image` and `vrm_blendshapes` are independent on the same `Emotion` row — either, both, or neither may be set. `image` is no longer required on `AssistantEmotionController::store`.

### `images` table — new use (no schema change)

`Assistant` gains a `cardImage(): MorphOne` → `Image` relationship, reusing the existing polymorphic `images` table (already generic via `imageable_type`/`imageable_id`; no migration needed). Used as the assistants-menu thumbnail; falls back to the `default` emotion's image for image-mode assistants that haven't set one.

## No Existing Table Changes

The `videos` table is unchanged. The `vrm_files` table is additive.
