# Data Model: World Music Track

## Track (new entity, polymorphic — mirrors `Image`)

A new `tracks` table, structured exactly like the existing polymorphic `images` table, but attached via a `trackable` morph instead of `imageable`:

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `trackable_type` / `trackable_id` | morphs | The owning model — a `World`, for this feature |
| `path` | string | Path to the stored file on `disk` |
| `disk` | string, default `public` | Storage disk |
| `mime_type` | string, nullable | e.g. `audio/mpeg`, `audio/wav` |
| `size` | unsigned big integer, nullable | Bytes, for display/limits |
| `original_name` | string, nullable | Original uploaded filename, for display |
| `timestamps` | | |

**Derived value**: `Track::getUrlAttribute()` resolves to `Storage::disk($this->disk)->url($this->path)`, the same pattern as `Image`.

**Relationship**: `World::track(): MorphOne` — a world has at most one `Track` via `morphOne(Track::class, 'trackable')`. The `worlds` table itself is unchanged.

**Validation rules** (enforced at upload, not at the DB layer):
- File MUST be MP3 or WAV.
- File MUST be 20 MB or smaller.
- Only the world's owner (per `WorldPolicy`'s `update` ability) may set, replace, or remove the track.

**Deletion behavior**:
- Deleting a world's track row also deletes its file from storage.
- Deleting a world deletes its `Track` row (and file) along with it, the same way `cardImage`/`portraitImage` are cleaned up.

**Lifecycle**:
1. No track → owner uploads → a `Track` row is created via `$world->track()->create([...])`.
2. Track set → owner uploads again → `updateOrCreate` replaces the row's file/metadata; old file deleted after the new one is stored, mirroring `WorldImageController`.
3. Track set → owner removes → file deleted from storage, `Track` row deleted.

No columns are added to `worlds`. This reuses the same polymorphic shape as `Image`, just for audio instead of pictures.
