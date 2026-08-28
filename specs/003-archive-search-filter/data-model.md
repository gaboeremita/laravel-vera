# Data Model: Archive Entry Hybrid Search

No new persisted entity is introduced. This feature adds one derived column and one index to the existing `archive_entries` table, and defines an ephemeral (non-persisted) search-result shape returned by the new endpoint.

## Existing Entity: ArchiveEntry (extended)

Table `archive_entries` (model `app/Models/ArchiveEntry.php`), current columns unchanged:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `archive_id` | bigint FK | |
| `title` | varchar(255) | required |
| `content` | text | required |
| `embedding` | `vector(768)`, nullable | already native pgvector column; populated async by `EmbedArchiveEntry` |
| `keywords` | json, nullable | free-text array |
| `created_at` / `updated_at` | timestamps | |

**New indexes** (via a new migration — the existing `2026_06_25_082004_create_lore_entries_table.php` is not modified, per Constitution Principle II):

- A composite full-text index over `title` and `content`, via Laravel's native `$table->fullText(['title', 'content'])->language('english')` — no new column needed; Laravel/Postgres builds the index directly from the existing columns.
- HNSW index on `embedding` (`->index()` on the vector column) — none currently exists; a similarity query is a sequential scan today, acceptable at the confirmed small scale but worth adding since the migration is already touching this table.

**Relationships**: unchanged — `belongsTo(Archive)`, `morphToMany(Tag)` via `taggables`.

**Validation rules affected by this feature**: none change on the write path (`ArchiveController::save`); this feature is read-only (spec Edge Cases: "Search is read-only").

## New Model Factories (prerequisite, not new entities)

No schema change — these back the feature tests required by Constitution Principle VI, since none currently exist for the Archive feature:

- `ArchiveFactory` — `name`, `description`, `user_id` (relates to an existing `UserFactory`).
- `ArchiveEntryFactory` — `title`, `content`, `keywords` (array of a few words), `archive_id`; `embedding` left `null` by default so tests can exercise both "has embedding" and "pending embedding" (FR-009) states explicitly.
- `TagFactory` — `name`, `user_id`.

Each of `Archive`, `ArchiveEntry`, `Tag` needs the `HasFactory` trait added (none currently have it).

## Ephemeral: Search Result Match

Not a database entity — the shape returned by the new endpoint and held transiently in frontend state. One row per entry that matched via the server-side hybrid query (keyword and/or vector).

| Field | Type | Meaning |
|---|---|---|
| `id` | integer | The matching `ArchiveEntry` id — the frontend already holds full entry data for this id in its loaded `entries` state (spec Assumptions: entries load together, unpaginated). |
| `score` | float | RRF-merged relevance score (k=60), descending. Used only for ordering — not displayed to the user as a raw number. |

## Ephemeral: Frontend Search State (ArchivePage)

Not persisted; describes the state shape `resources/js/pages/ArchivePage.jsx` needs, consistent with Constitution Principle VIII (state derivation happens during render, not via a synced effect):

| State | Type | Set by |
|---|---|---|
| `searchQuery` | string | User typing into the search input (controlled input) |
| `vectorResults` | `{id, score}[] \| null` | The debounced fetch effect, once the server responds; `null` means "no server search in flight/attempted for the current query" |
| `isSearching` | boolean | The debounced fetch effect, for the loading indicator |

The **visible, merged, deduplicated, ranked entry list** is *not* separate state — it is computed in the render body from `entries`, `searchQuery`, and `vectorResults` (client-side substring match ∪ `vectorResults` ids, deduplicated by `id`, entries matched by both ranked above entries matched by one). This is the render-time-derivation pattern already established elsewhere in the codebase (per Constitution Principle VIII).
