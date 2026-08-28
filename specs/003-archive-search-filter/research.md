# Phase 0 Research: Archive Entry Hybrid Search

All unknowns from the Technical Context are resolved below. No `NEEDS CLARIFICATION` markers remain.

## 1. Vector similarity search mechanism

**Decision**: Use Laravel 13's native vector search — `Blueprint::vector()` for the column and `Query\Builder::whereVectorSimilarTo()` for the similarity query — exactly as `RetrievalService` and `PromptDirector` already do. No new package.

**Rationale**: These are confirmed first-party framework methods (`vendor/laravel/framework/src/Illuminate/Database/Schema/Blueprint.php:1535` and `.../Query/Builder.php:1220`), not undefined macros. The live `vera` Postgres database already has the `vector` extension installed and `archive_entries.embedding` is already a genuine `vector(768)` column — verified directly against the database (`psql -d vera`), not assumed. An earlier research pass in this project incorrectly concluded this infrastructure was broken/unimplemented; that conclusion is superseded by this direct verification.

**Alternatives considered**: Installing a third-party pgvector PHP package (`pgvector/pgvector-php`) — rejected, unnecessary now that the native framework support is confirmed working and already in use elsewhere in the codebase.

## 2. Keyword search on the server

**Decision**: Laravel 13's native full-text search — a composite full-text index via `$table->fullText(['title', 'content'])->language('english')` in the migration, queried via `whereFullText(['title', 'content'], $query)`.

**Rationale**: Laravel 13 ships first-party full-text search support (not just vector search) for PostgreSQL, MariaDB, and MySQL — `fullText()` for the index, `whereFullText()` for the query, which Laravel translates to `to_tsvector(...) @@ plainto_tsquery(...)` on PostgreSQL automatically. This supersedes an earlier plan in this document to hand-roll a generated `tsvector` column and raw `ts_rank`/`plainto_tsquery` SQL — that plan predated discovering this native capability. Laravel's own docs note `whereFullText` does not order PostgreSQL results by relevance (unlike MySQL/MariaDB); since RRF only needs a rank *position* per leg, not a numeric score, this is not a blocker — the keyword leg's results are ranked by `id` for a stable, deterministic order before RRF positions are assigned.

**Alternatives considered**: The original raw generated-`tsvector`-column plan — rejected in favor of the native method once discovered, since "do things the Laravel way" outweighs a marginal, currently-unneeded ranking-quality gain from hand-rolled `ts_rank`. Using `ILIKE '%term%'` — rejected, no relevance signal and no index benefit at this granularity.

## 3. Semantic similarity threshold

**Decision**: `minSimilarity: 0.50`, empirically measured — not the `0.3` originally planned.

**Rationale**: The original `0.3` figure came from the pre-implementation technical brief's reasoning ("search should cast a wider net than RAG retrieval's 0.5") without being tested against real data, and it was wrong: manual verification against a real archive (46 entries, `nomic-embed-text` embeddings) showed cosine similarity of **0.48–0.54 for outright gibberish queries** and **0.39–0.40 for a coherent but topically unrelated query** ("what is the weather like on mars"), while genuinely on-topic queries scored **0.61–0.69**. At `0.3`, essentially the entire archive matched any query, including nonsense — confirmed live in the browser, where a random string returned 20 "semantic match" results.

`0.50` — matching RAG retrieval's own threshold — was chosen as the final value after weighing the tradeoff directly: at `0.50`, the gibberish probe query still returns a few matches (up to 4, since its top score of `0.5388` clears this bar) in exchange for wider recall on genuinely related content (17 results vs. 12 at the stricter `0.55`, which fully excludes gibberish). This is an accepted precision/recall tradeoff, not an oversight — `0.55` remains the tightest value that fully excludes the gibberish probe, should that tradeoff need revisiting.

**Alternatives considered**: `0.55` — the strictest value that empirically excludes all gibberish for this dataset; rejected in favor of `0.50`'s wider recall. `0.3` — the original unvalidated figure; rejected, floods results for any input.

## 4. Merging keyword and vector results

**Decision**: Reciprocal Rank Fusion (RRF, k=60): for each result list, add `1 / (60 + rank)` to a running score per entry ID; sum across lists; sort descending.

**Rationale**: Standard, simple technique for combining two differently-scaled ranked lists (`ts_rank` and cosine similarity aren't on comparable scales) without needing to tune a weighting formula between them. k=60 is the standard RRF constant, preventing the #1 result from one list from dominating.

**Alternatives considered**: Weighted linear combination of the two raw scores — rejected, since `ts_rank` and vector similarity aren't on comparable scales and would need ad-hoc normalization that RRF sidesteps entirely.

## 5. Instant client-side layer vs. debounced server layer

**Decision**: Two distinct layers, as scoped by the spec's clarifications. Client-side: filters the already-loaded `entries` array in state on every keystroke, matching title/content/tags/keywords via case-insensitive substring — zero network calls, zero delay. Server-side: fires only after the query reaches 2+ characters and the user pauses for 400ms, running the hybrid (keyword+vector) query described above and returning ranked entry IDs to merge in.

**Rationale**: The client-side layer directly satisfies FR-002 (instant, no network round-trip). The server-side layer is what makes semantic recall (FR-003) possible at all — embeddings can't be compared client-side without shipping every entry's raw vector to the browser on every keystroke, which the small-scale assumption doesn't require solving for.

**Alternatives considered**: Skipping the client-side instant layer and debouncing everything server-side — rejected, it would reintroduce a network-latency floor on the primary "type and see it filter" interaction (User Story 1, P1), which the spec explicitly requires to feel instantaneous.

## 6. Debounce interval

**Decision**: 400ms.

**Rationale**: Long enough to avoid firing an embedding call on every keystroke during active typing, short enough that the semantic layer still feels responsive once the user pauses. Matches SC-002's "roughly half a second" target.

**Alternatives considered**: 500ms (an earlier informal suggestion) — close enough to be interchangeable; 400ms is used as the concrete, decided value throughout this plan and downstream artifacts.

## 7. Minimum query length for the semantic layer

**Decision**: 2 characters, matching the server endpoint's own validation minimum.

**Rationale**: A 1-character query produces a near-meaningless embedding and a near-useless full-text query; gating both the debounce trigger and server-side validation on the same minimum keeps client and server behavior consistent and avoids wasted embedding calls.

## 8. Test coverage prerequisite

**Decision**: Add `HasFactory` to `Archive`, `ArchiveEntry`, and `Tag`, plus new `ArchiveFactory`, `ArchiveEntryFactory`, `TagFactory` classes, before writing the feature test for the new search endpoint.

**Rationale**: None of these three models currently have factories or `HasFactory`, and there is no existing test coverage for the Archive feature at all (`tests/Feature` has no Archive-related test file). Constitution Principle VI requires factory-backed feature tests as the default; this is a genuine, previously-uncovered gap the new test needs, not scope creep.

## 9. Documentation staleness (FR-012)

**Decision**: Update README.md and ARCHITECTURE.md once implementation is complete and verified, as the final task before considering the feature done.

**Rationale**: Both files already describe the Archive feature in specific, identifiable places — the API route table (`ARCHITECTURE.md`), the `ArchiveController`/`ArchivePage` prose descriptions, the project structure trees in both files, and README's top-level feature bullet list. Updating last (rather than speculatively before the implementation is final) avoids describing behavior that might still shift during implementation.
