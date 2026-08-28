# Quickstart: Validating Archive Entry Hybrid Search

Prerequisites: Herd serving the app, the queue worker running (embeddings are async — see README.md's queue-worker section), and at least one archive with a few entries that have had time to embed.

## 1. Apply the migration

```bash
php artisan migrate
```

Confirms the new `search_vector` generated column, GIN index, and HNSW index on `embedding` were added to `archive_entries` without touching the original migration file.

## 2. Seed data for testing

Use `php artisan tinker` or factories in a test to create an archive with entries covering the states this feature must handle:

- An entry whose title contains an exact word you'll search for (validates instant + keyword-ranked match).
- An entry with no literal keyword overlap but a related topic, with an embedding already generated — wait for the queue worker to process it, or check `archive_entries.embedding IS NOT NULL` directly (validates semantic match, FR-003).
- A freshly created entry with `embedding` still `NULL` (don't wait for the worker) — should remain findable via instant/keyword search only (validates FR-009).

## 3. Exercise the endpoint directly

```bash
curl -H "Accept: application/json" -b "<session cookie>" \
  "https://laravel-vera.test/api/archives/<archive-id>/search?q=<term>"
```

Expected: `{"results": [{"id": ..., "score": ...}, ...]}`, sorted descending by score. Confirm a request for an archive you don't own returns `404`, and `q` under 2 characters returns `422`.

## 4. Exercise the UI

Open the Archive page for the seeded archive in the browser.

- Type the exact-match term → entries should filter **immediately**, no visible delay (User Story 1).
- Type a term with no literal overlap but conceptual relevance → after a brief pause (~400ms), the semantically related entry should appear, visually marked as a semantic match, without displacing the instant matches already shown (User Story 2).
- Clear the search box → full entry list returns, "Add Entry" and existing CRUD actions remain available throughout (spec Edge Cases / brief §8).
- Search for something matching nothing → a clear "no matching entries" message appears, distinguishable from an empty archive.

## 5. Confirm quality gates

```bash
vendor/bin/pint --test
npm run lint
php artisan test --compact --filter=ArchiveSearch
```

## 6. Confirm documentation was updated (FR-012)

README.md and ARCHITECTURE.md should describe the new search capability — check the API route table, the `ArchiveController`/`ArchivePage` prose sections, and README's feature list in both files reflect the shipped behavior, not the pre-feature state.
