# Technical Brief: Hybrid Search for Archive Entries

**Status**: Reference input for `/speckit-plan`, not a spec. Captured verbatim from a technical brief provided during clarification on 2026-08-28, so the implementation planning phase has full context on the intended approach without re-deriving it.

## What This Is

A search input on the ArchivePage that filters archive entries using hybrid search: instant client-side keyword matching + debounced server-side vector similarity search, with results merged via deduplication.

## Context You Need

### Existing Infrastructure (DO NOT rebuild — use these)

- `EmbeddingProvider` contract (`app/Contracts/EmbeddingProvider.php`) — `embed(string $text): array` generates a vector embedding. Already bound in `AppServiceProvider`.
- `RetrievalService` (`app/Retrieval/RetrievalService.php`) — has `retrieve(string $text, int $archiveId, int $limit, float $minSimilarity)`. Uses `whereVectorSimilarTo()` scope on `ArchiveEntry`. This is the vector search side — reuse or mirror its query pattern.
- `ArchiveEntry` model (`app/Models/ArchiveEntry.php`) — has `title` (string), `content` (text), `keywords` (JSON array cast), `embedding` (vector 768, nullable). Related `tags()` via `morphToMany`.
- `ArchiveController` (`app/Http/Controllers/Api/ArchiveController.php`) — owns archive CRUD. The search endpoint goes here.
- `ArchivePage` (`resources/js/pages/ArchivePage.jsx`) — loads all entries on mount via `archives.show` route. Entries are stored in React state as objects with `id`, `title`, `content`, `keywords` (comma-separated string), `tags` (comma-separated string), `collapsed` (boolean).
- `EntryAccordion` (`resources/js/components/EntryAccordion.jsx`) — renders each entry. Currently receives `entry`, `index`, `onUpdate`, `onDelete` props.
- Routes are in `routes/api.php`. Archive routes are NOT inside the `assistants/{assistant}` prefix — they're at the top level: `GET /api/archives/{id}`, `POST /api/archives/{id}`, etc.
- `api` utility — `resources/js/utils/api.js` exports `api.get()`, `api.post()`, etc. for fetch calls.
- Ziggy — all routes are referenced via `route('name', params)` from `ziggy-js`.
- No search infrastructure exists yet — no tsvector columns, no search endpoint, no search UI component.

### Tech Stack

- Backend: Laravel 13, PHP 8, PostgreSQL with pgvector extension
- Frontend: React 19, Vite, Tailwind CSS v4, Framer Motion, Lucide React
- Routing: Ziggy (named routes from Laravel available in JS)

## Backend

### 1. Migration: Add tsvector Generated Column + GIN Index

Create a migration that adds a tsvector generated column to `archive_entries`. This column should combine title and content for full-text search. Add a GIN index on it.

```
archive_entries:
  - search_vector: tsvector GENERATED ALWAYS AS (
      setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
      setweight(to_tsvector('english', coalesce(content, '')), 'B')
    ) STORED
  - GIN index on search_vector
```

Title gets weight 'A' (highest priority), content gets weight 'B'. This means title matches rank higher than content matches.

Note: Laravel's schema builder doesn't support generated tsvector columns natively. Use a raw `DB::statement()` in the migration.

### 2. Search Endpoint on ArchiveController

Add a `search` method to `ArchiveController`.

Route: `GET /api/archives/{id}/search?q={query}`
Route name: `archives.search`

Register it in `routes/api.php` alongside the existing archive routes:

```php
Route::get('/archives/{id}/search', [ArchiveController::class, 'search'])->name('archives.search');
```

Behavior:

- Validate `q` is a string, minimum 2 characters.
- Scope to the authenticated user's archive (same ownership check as `show`).
- Run two queries in parallel (or sequentially, both are fine at this scale):

Keyword search — use the `search_vector` column:

```php
$keywordResults = ArchiveEntry::query()
    ->where('archive_id', $archiveId)
    ->whereRaw("search_vector @@ plainto_tsquery('english', ?)", [$query])
    ->selectRaw("*, ts_rank(search_vector, plainto_tsquery('english', ?)) as text_rank", [$query])
    ->orderByDesc('text_rank')
    ->limit(20)
    ->get();
```

Vector search — embed the query, then find similar entries:

```php
$embedding = app(EmbeddingProvider::class)->embed($query);
$vectorResults = ArchiveEntry::query()
    ->where('archive_id', $archiveId)
    ->whereNotNull('embedding')
    ->whereVectorSimilarTo('embedding', $embedding, minSimilarity: 0.3)
    ->limit(20)
    ->get();
```

Use a lower `minSimilarity` (0.3) than RAG retrieval (0.5) because search should cast a wider net.

Merge with RRF (Reciprocal Rank Fusion):

```
For each result in keyword results: rrf_score += 1 / (60 + rank)
For each result in vector results:  rrf_score += 1 / (60 + rank)
Results appearing in both lists get summed scores.
Sort by rrf_score descending.
```

The constant 60 is standard RRF — it prevents top-ranked results from dominating too aggressively.

Return the merged, sorted entry IDs and their scores:

```json
{
  "results": [
    { "id": 12, "score": 0.032 },
    { "id": 5, "score": 0.028 },
    { "id": 19, "score": 0.016 }
  ]
}
```

Return only IDs and scores — the frontend already has the full entry data loaded in state. This keeps the response tiny and avoids duplicating entry content.

## Frontend

### 3. Search Input Component

Add a search input to `ArchivePage`, positioned between the archive description section and the entries list (above the "Entries (N)" header).

Styling must match the existing cyberpunk CRT aesthetic. Use the same input classes as other inputs on the page:

```
bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors
```

Add a Search icon from Lucide React at the left of the input. Add a clear button (X icon) when the input has text.

### 4. Client-Side Keyword Filtering (Instant)

On every keystroke, filter the entries array in state. Match against:

- `entry.title` (case-insensitive includes)
- `entry.keywords` (case-insensitive includes — this is a comma-separated string in the frontend state)
- `entry.tags` (case-insensitive includes — also a comma-separated string)
- `entry.content` (case-insensitive includes)

This gives instant results with zero latency. Store the filtered set separately — do NOT mutate the original entries state. Use something like:

```js
const [searchQuery, setSearchQuery] = useState('');
const [vectorResultIds, setVectorResultIds] = useState(null);
```

Then compute the visible entries from both sources.

### 5. Debounced Vector Search (Server-Side)

After the user stops typing for 400ms, fire a GET request to the search endpoint:

```js
api.get(route('archives.search', { id: archiveId }) + `?q=${encodeURIComponent(query)}`)
```

Use `useEffect` with `setTimeout` and cleanup for debouncing — do NOT install lodash just for this.

Pattern:

```js
useEffect(() => {
    if (searchQuery.length < 2) {
        setVectorResultIds(null);
        return;
    }
    const timer = setTimeout(async () => {
        // Call search endpoint
        // Store returned IDs in vectorResultIds
    }, 400);
    return () => clearTimeout(timer);
}, [searchQuery]);
```

While the vector search is in flight, show a subtle loading indicator (a small spinner or pulsing dot near the search input — not a full-page loader).

### 6. Merging Results

The display logic should work like this:

- If `searchQuery` is empty: show all entries (current behavior).
- If `searchQuery` has text: show entries that match either the client-side keyword filter or appear in the `vectorResultIds` set. Entries matching both rank higher (show them first).
- Use entry `id` for deduplication — an entry should appear once even if both methods found it.
- Entries found only by vector search (not by keyword) should have a subtle visual indicator — a small tag or icon showing they were found by semantic match. Something like a small Sparkles icon from Lucide React next to the accordion label.

### 7. UI States

- Empty search input: Show all entries normally (current behavior). No search active.
- Typing (< 2 chars): Client-side filter runs but vector search does not fire. Show whatever the keyword filter matches.
- Typing (≥ 2 chars, vector in flight): Show client-side keyword matches immediately. Show a loading indicator for vector results.
- Results merged: Show combined results. If nothing matches, show a "No entries match your search" message.
- Clear button clicked: Reset to showing all entries.

### 8. Things NOT to Do

- Do NOT move entry data fetching to the search endpoint. The page already loads all entries on mount — keep that. The search endpoint returns only IDs and scores.
- Do NOT change how entries are saved, created, or deleted. Search is read-only.
- Do NOT hide the "Add Entry" button during search. User should still be able to add entries.
- Do NOT change the `EntryAccordion` component's core functionality. Only add the semantic match indicator.
- Do NOT install lodash or any external debounce library.
- Do NOT add `pg_trgm` extension — tsvector full-text search is sufficient for this first pass. Trigram fuzzy matching can be added later if needed.

## File Checklist

| File | Action |
|---|---|
| `database/migrations/xxxx_add_search_vector_to_archive_entries.php` | Create — adds tsvector generated column + GIN index |
| `app/Http/Controllers/Api/ArchiveController.php` | Modify — add `search()` method |
| `routes/api.php` | Modify — add search route |
| `resources/js/pages/ArchivePage.jsx` | Modify — add search input, client-side filtering, debounced vector search, result merging |
| `resources/js/components/EntryAccordion.jsx` | Modify — add optional semantic match indicator |

## Testing Considerations

- Search with a term that appears literally in an entry title → should appear instantly via client-side filter.
- Search with a conceptual query that doesn't match any literal text → should appear after the debounce via vector search.
- Search with a term that matches both keyword and vector → entry should appear once, ranked high.
- Empty the search input → should return to showing all entries.
- Entries without embeddings (newly created, queue not yet processed) → should still appear in keyword results, just not vector results.
- Archive with zero entries → search input should still render, show "no entries" state.

## Known Prerequisite Gap

The pgvector Postgres extension and its `whereVectorSimilarTo()` scope are referenced by existing code (`RetrievalService`, `PromptDirector`) but are not currently installed or defined anywhere in this codebase — those call sites would throw at runtime today. Wiring up pgvector for this feature also fixes that pre-existing gap in chat retrieval.
