# Data Model: World Music Track

## World (existing entity, extended)

New nullable columns added to the existing `worlds` table:

| Column | Type | Notes |
|---|---|---|
| `track_disk` | string, nullable | Storage disk the track file lives on (`public`) |
| `track_path` | string, nullable | Path to the stored file on that disk |
| `track_original_name` | string, nullable | Original uploaded filename, for display |
| `track_mime_type` | string, nullable | e.g. `audio/mpeg`, `audio/wav` |
| `track_size` | unsigned integer, nullable | Bytes, for display/limits |

All five columns are null together (no track set) or all populated together (track set) — set/replaced atomically by the track controller, matching how `environment_*` columns behave.

**Derived value**: a `track_url` accessor (or resource field) resolves to `Storage::disk($track_disk)->url($track_path)` when `track_path` is set, `null` otherwise — this is what the frontend uses as the `<audio src>`.

**Validation rules** (enforced at upload, not at the DB layer):
- File MUST be MP3 or WAV.
- File MUST be 20 MB or smaller.
- Only the world's owner (per `WorldPolicy`'s `update` ability) may set, replace, or remove the track.

**Deletion behavior**: when a world is deleted, its track file is removed from storage (extending the existing `deleted` model event that already cleans up `environment_path`).

**Lifecycle**:
1. No track → owner uploads → track columns populated, `track_url` becomes non-null.
2. Track set → owner uploads again → old file deleted after new file is stored and columns updated.
3. Track set → owner removes → file deleted from storage, all five columns set back to null.

No new tables, no new models, no relationships beyond the existing `World` entity.
