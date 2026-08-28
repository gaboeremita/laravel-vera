# Phase 0 Research: Archive Entry Hybrid Search

All unknowns from the Technical Context are resolved below. No `NEEDS CLARIFICATION` markers remain.

## 1. Vector similarity search mechanism

**Decision**: Use Laravel 13's native vector search — `Blueprint::vector()` for the column and `Query\Builder::whereVectorSimilarTo()` for the similarity query — exactly as `RetrievalService` and `PromptDirector` already do. No new package.

**Rationale**: These are confirmed first-party framework methods (`vendor/laravel/framework/src/Illuminate/Database/Schema/Blueprint.php:1535` and `.../Query/Builder.php:1220`), not undefined macros. The live `vera` Postgres database already has the `vector` extension installed and `archive_entries.embedding` is already a genuine `vector(768)` column — verified directly against the database (`psql -d vera`), not assumed. An earlier research pass in this project incorrectly concluded this infrastructure was broken/unimplemented; that conclusion is superseded by this direct verification.

**Alternatives considered**: Installing a third-party pgvector PHP package (`pgvector/pgvector-php`) — rejected, unnecessary now that the native framework support is confirmed working and already in use elsewhere in the codebase.

## 2. Keyword-ranked search on the server

**Decision**: A generated `tsvector` column (`search_vector`) on `archive_entries`, combining `title` (weight `A`) and `content` (weight `B`), with a GIN index. Queried via `search_vector @@ plainto_tsquery('english', ?)`, ranked via `ts_rank`.

**Rationale**: Laravel's schema builder has no first-party generated-tsvector-column helper, so this requires a raw `DB::statement()` in the migration — this is the one piece of genuinely new backend infrastructure this feature needs. Weighting title above content matches how a user thinks about relevance (a title hit is a stronger signal than a content mention).

**Alternatives considered**: Using `ILIKE '%term%'` for the server-side keyword leg instead of `tsvector` — rejected, since it can't rank results by relevance and doesn't benefit from an index at this text-search granularity the way GIN + tsvector does.

## 3. Merging keyword and vector results

**Decision**: Reciprocal Rank Fusion (RRF, k=60): for each result list, add `1 / (60 + rank)` to a running score per entry ID; sum across lists; sort descending.

**Rationale**: Standard, simple technique for combining two differently-scaled ranked lists (`ts_rank` and cosine similarity aren't on comparable scales) without needing to tune a weighting formula between them. k=60 is the standard RRF constant, preventing the #1 result from one list from dominating.

**Alternatives considered**: Weighted linear combination of the two raw scores — rejected, since `ts_rank` and vector similarity aren't on comparable scales and would need ad-hoc normalization that RRF sidesteps entirely.

## 4. Instant client-side layer vs. debounced server layer

**Decision**: Two distinct layers, as scoped by the spec's clarifications. Client-side: filters the already-loaded `entries` array in state on every keystroke, matching title/content/tags/keywords via case-insensitive substring — zero network calls, zero delay. Server-side: fires only after the query reaches 2+ characters and the user pauses for 400ms, running the hybrid (keyword+vector) query described above and returning ranked entry IDs to merge in.

**Rationale**: The client-side layer directly satisfies FR-002 (instant, no network round-trip). The server-side layer is what makes semantic recall (FR-003) possible at all — embeddings can't be compared client-side without shipping every entry's raw vector to the browser on every keystroke, which the small-scale assumption doesn't require solving for.

**Alternatives considered**: Skipping the client-side instant layer and debouncing everything server-side — rejected, it would reintroduce a network-latency floor on the primary "type and see it filter" interaction (User Story 1, P1), which the spec explicitly requires to feel instantaneous.

## 5. Debounce interval

**Decision**: 400ms.

**Rationale**: Long enough to avoid firing an embedding call on every keystroke during active typing, short enough that the semantic layer still feels responsive once the user pauses. Matches SC-002's "roughly half a second" target.

**Alternatives considered**: 500ms (an earlier informal suggestion) — close enough to be interchangeable; 400ms is used as the concrete, decided value throughout this plan and downstream artifacts.

## 6. Minimum query length for the semantic layer

**Decision**: 2 characters, matching the server endpoint's own validation minimum.

**Rationale**: A 1-character query produces a near-meaningless embedding and a near-useless full-text query; gating both the debounce trigger and server-side validation on the same minimum keeps client and server behavior consistent and avoids wasted embedding calls.

## 7. Test coverage prerequisite

**Decision**: Add `HasFactory` to `Archive`, `ArchiveEntry`, and `Tag`, plus new `ArchiveFactory`, `ArchiveEntryFactory`, `TagFactory` classes, before writing the feature test for the new search endpoint.

**Rationale**: None of these three models currently have factories or `HasFactory`, and there is no existing test coverage for the Archive feature at all (`tests/Feature` has no Archive-related test file). Constitution Principle VI requires factory-backed feature tests as the default; this is a genuine, previously-uncovered gap the new test needs, not scope creep.

## 8. Documentation staleness (FR-012)

**Decision**: Update README.md and ARCHITECTURE.md once implementation is complete and verified, as the final task before considering the feature done.

**Rationale**: Both files already describe the Archive feature in specific, identifiable places — the API route table (`ARCHITECTURE.md`), the `ArchiveController`/`ArchivePage` prose descriptions, the project structure trees in both files, and README's top-level feature bullet list. Updating last (rather than speculatively before the implementation is final) avoids describing behavior that might still shift during implementation.
