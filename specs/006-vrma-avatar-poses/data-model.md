# Data Model: VRMA Avatar Pose Animations

**Feature**: `006-vrma-avatar-poses` | **Date**: 2026-08-29

## Schema Changes

### `poses` table — new table

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `assistant_id` | bigint FK → `assistants.id` | no | cascade delete |
| `name` | string | no | the tag identifier the LLM uses (`[pose: name]`) |
| `vrm_blendshapes` | json | yes | `array<{expression: string, weight: float}>`, 0.0–1.0 scale, same shape as `emotions.vrm_blendshapes` |
| `created_at` / `updated_at` | timestamp | | |

Unique index: `(assistant_id, name)` — a pose name is unique per assistant (mirrors the existing per-assistant emotion name uniqueness check in `AssistantEmotionController::store`).

Migration: `create_poses_table`

---

### `pose_animation_files` table — new table

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `pose_id` | bigint FK → `poses.id`, unique | no | cascade delete; one animation file per pose |
| `path` | string | no | storage path |
| `disk` | string | no | storage disk (`public`) |
| `mime_type` | string | no | `application/octet-stream`, same convention as `vrm_files.mime_type` (covers both `.vrma` and `.fbx`) |
| `size` | unsigned bigint | no | bytes |
| `original_name` | string | yes | original filename from upload |
| `created_at` / `updated_at` | timestamp | | |

Migration: `create_pose_animation_files_table`

No polymorphism — see [research.md Decision 1](research.md#decision-1-pose--pose-animation-file-storage-model) for why this differs from `vrm_files`.

No dedicated `format` column: a pose animation file may be `.vrma` or `.fbx` (see [research.md Decision 9](research.md#decision-9-fbx-animation-support-via-mixamo-retargeting)); the format is read from the stored file's extension (`path`/`original_name`) at load time rather than tracked as separate state.

---

## Model Changes

### New `Pose` model

```
App\Models\Pose
  fillable: name, vrm_blendshapes
  casts: vrm_blendshapes -> array
  relationship: assistant(): BelongsTo -> Assistant
  relationship: animationFile(): HasOne -> PoseAnimationFile
  uses: HasNormalizedBlendshapes trait (see below)
```

### New `PoseAnimationFile` model

```
App\Models\PoseAnimationFile
  fillable: path, disk, mime_type, size, original_name
  relationship: pose(): BelongsTo -> Pose
  accessor: url — Storage::disk($this->disk)->url($this->path)
```

### New `App\Models\Concerns\HasNormalizedBlendshapes` trait

Extracted from `Emotion::normalizeBlendshapes()` (`app/Models/Emotion.php:50-60`). Static method `normalizeBlendshapes(?array $blendshapes): ?array`, identical behavior. Used by both `Emotion` and `Pose`. See [research.md Decision 7](research.md#decision-7-shared-blendshape-normalization).

### `Emotion` model

- Refactor: `use HasNormalizedBlendshapes;` in place of its own `normalizeBlendshapes()` method. No field or behavior change.

### `Assistant` model

- New relationship: `poses(): HasMany` → `Pose`
- New method: `promptPoseNames(): array<string>` — the assistant's own `Pose` names, for LLM prompt injection (no regular/intimate split — poses have no restricted concept)

## No Existing Table Changes

`emotions` and `vrm_files` are unchanged. `poses` and `pose_animation_files` are additive.
