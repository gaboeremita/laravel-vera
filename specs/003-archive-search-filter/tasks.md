---

description: "Task list for Archive Entry Hybrid Search"
---

# Tasks: Archive Entry Hybrid Search

**Input**: Design documents from `/specs/003-archive-search-filter/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/archives-search.md](./contracts/archives-search.md), [quickstart.md](./quickstart.md)

**Tests**: Included — Constitution Principle VI (Feature-Test-First, Factory-Backed) makes Pest feature tests the default, not optional, for this codebase.

**Organization**: Tasks are grouped by user story. User Story 1 needs no backend or database changes at all and is independently shippable as the MVP; User Story 2 adds the server-side semantic layer on top of it.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2)

---

## Phase 1: Setup

**Purpose**: Make the existing models testable — none currently support factories.

- [ ] T001 [P] Add `HasFactory` trait to `app/Models/Archive.php`
- [ ] T002 [P] Add `HasFactory` trait to `app/Models/ArchiveEntry.php`
- [ ] T003 [P] Add `HasFactory` trait to `app/Models/Tag.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Factories both stories' tests depend on. No Archive/ArchiveEntry/Tag factories currently exist.

**⚠️ CRITICAL**: No test-writing for either user story can begin until this phase is complete.

- [ ] T004 [P] Create `database/factories/ArchiveFactory.php` (`name`, `description`, `user_id`) — depends on T001
- [ ] T005 [P] Create `database/factories/ArchiveEntryFactory.php` (`title`, `content`, `keywords`, `archive_id`; `embedding` defaults to `null` so tests can exercise both the "embedded" and "pending embedding" states per data-model.md) — depends on T002
- [ ] T006 [P] Create `database/factories/TagFactory.php` (`name`, `user_id`) — depends on T003

**Checkpoint**: Foundation ready — user story implementation can now begin.

---

## Phase 3: User Story 1 - Instant search across entries (Priority: P1) 🎯 MVP

**Goal**: Typing into a search box on the Archive page instantly filters the currently loaded entries by title, content, tags, or keywords — no network call, no perceptible delay.

**Independent Test**: Open an archive with several entries with distinct titles, content, tags, and keywords. Type a word matching only one entry's title and confirm only that entry is shown immediately; type a word matching only another entry's content/tags/keywords and confirm that entry appears instead, still immediately.

### Tests for User Story 1

- [ ] T007 [P] [US1] Pest browser feature test in `tests/Feature/ArchiveSearchInstantTest.php`: visit the Archive page for a factory-seeded archive, type into the search input, and assert the visible entry list narrows immediately to title/content/tag/keyword matches, and that clearing the input restores the full list — depends on T004, T005, T006

### Implementation for User Story 1

- [ ] T008 [US1] Add the search input (Search icon, clear/X button when non-empty) to `resources/js/pages/ArchivePage.jsx`, positioned between the archive description and the "Entries (N)" header, using the existing input styling (`bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors`)
- [ ] T009 [US1] Add `searchQuery` state and a render-time-computed (not effect-synced) case-insensitive substring match across `entry.title`, `entry.content`, `entry.keywords`, `entry.tags` in `resources/js/pages/ArchivePage.jsx` — depends on T008
- [ ] T010 [US1] Add a "No entries match your search" empty state in `resources/js/pages/ArchivePage.jsx`, distinct from the existing "No entries yet." empty state — depends on T009
- [ ] T011 [US1] Wire the clear button to reset `searchQuery` and restore the full entry list in `resources/js/pages/ArchivePage.jsx` — depends on T009

**Checkpoint**: User Story 1 is fully functional and independently testable/shippable — no backend or database change was needed.

---

## Phase 4: User Story 2 - Conceptually related results via semantic search (Priority: P2)

**Goal**: A short pause after typing, entries that are conceptually related to the search text — even without shared literal wording — are added to the results, merged with the instant matches and visually distinguished. Includes graceful degradation when the semantic layer is slow or fails (FR-011).

**Independent Test**: Seed an archive with an entry that has a generated embedding but shares no literal words with a thematically related search term. Confirm that entry appears in results a short pause after typing stops, marked as a semantic match, without displacing the already-visible instant matches.

### Tests for User Story 2

- [ ] T012 [P] [US2] Pest feature test in `tests/Feature/Api/ArchiveSearchTest.php` for `GET /api/archives/{id}/search`: asserts a `200` with ranked `{id, score}` results for a valid query, a `404` for an archive not owned by the authenticated user, and a `422` for `q` under 2 characters — per [contracts/archives-search.md](./contracts/archives-search.md); depends on T004, T005, T006
- [ ] T013 [US2] Pest feature test in `tests/Feature/Api/ArchiveSearchTest.php` confirming an `ArchiveEntry` with `embedding = null` still appears in results via the keyword/`tsvector` leg alone (FR-009) — same file as T012, implemented after it
- [ ] T014 [US2] Pest feature test in `tests/Feature/Api/ArchiveSearchTest.php` confirming an entry belonging to a *different* archive owned by the same authenticated user is excluded from the search response (FR-010 cross-archive isolation) — same file as T012/T013, implemented after them
- [ ] T015 [P] [US2] Pest browser feature test in `tests/Feature/ArchiveSearchSemanticTest.php`: confirms a semantically-related-but-not-literally-matching entry appears after the debounce window and is visually marked as a semantic match — depends on T004, T005, T006
- [ ] T016 [US2] Extend the Pest browser test in `tests/Feature/ArchiveSearchSemanticTest.php` to cover an entry that matches both the instant text layer and the semantic layer: confirms it renders exactly once (no duplicate) and ranks at or above single-criterion matches (US2 Acceptance Scenario 2, SC-004) — same file as T015, implemented after it
- [ ] T017 [US2] Extend the Pest browser test in `tests/Feature/ArchiveSearchSemanticTest.php` to cover the search endpoint failing or timing out: confirms the already-visible instant match results remain visible and the page is not blocked or broken (FR-011) — same file as T015/T016, implemented after them

### Implementation for User Story 2

- [ ] T018 [US2] Create migration `database/migrations/xxxx_xx_xx_add_search_to_archive_entries_table.php` adding the generated `search_vector` `tsvector` column (title weighted `A`, content weighted `B`, via raw `DB::statement()`) with a GIN index, and an HNSW index on the existing `embedding` column — per [data-model.md](./data-model.md); does not modify the original `2026_06_25_082004_create_lore_entries_table.php` (Constitution Principle II)
- [ ] T019 [US2] Run `php artisan migrate` and verify the resulting schema against data-model.md — depends on T018
- [ ] T020 [US2] Create `app/Actions/SearchArchiveEntries.php`: runs the `tsvector` keyword query (`ts_rank`, limit 20) and the `whereVectorSimilarTo` vector query (`minSimilarity: 0.3`, limit 20) scoped to the given archive, merges both via Reciprocal Rank Fusion (k=60), returns ranked `{id, score}` pairs — mirrors the single-purpose Action pattern already used by `app/Actions/BuildArchiveFile.php` — depends on T019
- [ ] T021 [US2] Add `search(Request $request, int $id)` to `app/Http/Controllers/Api/ArchiveController.php`: validates `q` (`required|string|min:2`), resolves the archive via `$request->user()->archives()->findOrFail($id)` (Constitution Principle IV — never a bare `Archive::find()`, and the source of FR-010's cross-archive isolation), delegates to `SearchArchiveEntries`, returns the response shape from [contracts/archives-search.md](./contracts/archives-search.md) — depends on T020
- [ ] T022 [US2] Add `Route::get('/archives/{id}/search', [ArchiveController::class, 'search'])->name('archives.search')` to `routes/api.php`, alongside the existing archive routes — depends on T021
- [ ] T023 [US2] Add a 400ms-debounced fetch effect in `resources/js/pages/ArchivePage.jsx`: fires only once `searchQuery.length >= 2`, calls `route('archives.search', { id: archiveId })` via `api.get`, stores the returned `{id, score}[]` in `vectorResults` state; the async work MUST be a closure local to the effect (Constitution Principle VIII) — depends on T009, T022
- [ ] T024 [US2] Wrap the debounced fetch from T023 in try/catch (FR-011): on failure or timeout, leave `vectorResults` unset/`null` rather than throwing or blocking render, and log the error via `console.error` so the failure stays visible for debugging without surfacing a blocking UI error (keeps the spirit of Constitution Principle V's "fail loudly" without making the *frontend* block on a degraded semantic layer, which is a distinct, backend-error-handling-preserving concern) — depends on T023
- [ ] T025 [US2] Add an `isSearching` loading indicator near the search input in `resources/js/pages/ArchivePage.jsx`, shown only while the debounced request is in flight and cleared on both success and the failure path from T024 — depends on T024
- [ ] T026 [US2] Extend the render-time merge in `resources/js/pages/ArchivePage.jsx` to union the instant substring matches with `vectorResults`, deduplicated by entry `id`, ranking entries matched by both above entries matched by only one — computed during render per Constitution Principle VIII, not via a second synced `useState`/`useEffect` pair — depends on T009, T023
- [ ] T027 [US2] Add a semantic-match indicator (Sparkles icon from Lucide React) to `resources/js/components/EntryAccordion.jsx` for entries present only via `vectorResults`, not the instant match — depends on T026

**Checkpoint**: User Stories 1 and 2 both work independently and together — US2 adds semantic recall on top of US1's instant layer without changing it, and degrades gracefully if the semantic layer is unavailable.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Documentation accuracy (FR-012) and final quality gates.

- [ ] T028 [P] Update README.md's Archive feature description (queue-worker section, top-level feature list) to describe hybrid search — per FR-012
- [ ] T029 [P] Update ARCHITECTURE.md's API route table, `ArchiveController`/`ArchivePage` prose descriptions, and project structure trees to describe hybrid search and the new `search_vector`/HNSW additions — per FR-012
- [ ] T030 Run `vendor/bin/pint --test` and fix any violations across all touched PHP files
- [ ] T031 Run `npm run lint` and fix any violations across all touched JS/JSX files
- [ ] T032 Run `php artisan test --compact --filter=ArchiveSearch` and confirm all new tests pass
- [ ] T033 Execute [quickstart.md](./quickstart.md) end-to-end validation steps

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup (factories need the `HasFactory` trait added first) — blocks all test-writing for both stories.
- **User Story 1 (Phase 3)**: Depends on Foundational. No dependency on User Story 2 — ships with zero backend/database changes.
- **User Story 2 (Phase 4)**: Depends on Foundational. Its frontend tasks (T023, T026) build on User Story 1's `searchQuery` state (T009), so in practice US2 is implemented after US1 even though its backend tasks (T018–T022) could start in parallel with US1.
- **Polish (Phase 5)**: Depends on both user stories being complete.

### Within Each User Story

- Tests are written first and must fail before implementation begins.
- Migration → Action → Controller → Route → Frontend effect → Frontend error handling → Frontend merge → Frontend indicator, in that order for User Story 2 (each depends on the previous).

### Parallel Opportunities

- T001, T002, T003 (Setup) — different files.
- T004, T005, T006 (Foundational) — different files, each depending only on its corresponding Setup task.
- T012 and T015 can start in parallel (different files, first task in each). T013 and T014 share a file with T012 and follow it sequentially; T016 and T017 share a file with T015 and follow it sequentially.
- T028, T029 (Polish docs) — different files.

---

## Parallel Example: Foundational Phase

```bash
# After T001–T003 complete, launch all factories together:
Task: "Create database/factories/ArchiveFactory.php"
Task: "Create database/factories/ArchiveEntryFactory.php"
Task: "Create database/factories/TagFactory.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: confirm instant search works end-to-end in the browser — this alone is a shippable increment, since it touches no database or backend code.
5. Deploy/demo if ready.

### Incremental Delivery

1. Setup + Foundational → factories ready.
2. User Story 1 → instant search ships as the MVP.
3. User Story 2 → adds the semantic layer, the new migration, endpoint, merge/indicator logic, and graceful degradation on top.
4. Polish → documentation (FR-012) and quality gates.

---

## Notes

- User Story 1 deliberately has no database or backend tasks — this is what makes it independently shippable as the MVP per the plan's Project Structure.
- Constitution Principle VIII is called out explicitly in T009, T023, and T026 because it's the one pattern most likely to get silently violated by copying the technical brief's suggested `useEffect`-heavy React pattern verbatim.
- Constitution Principle IV (Data Isolation by Ownership) is called out explicitly in T021 since this is a new, easy-to-get-wrong query — it must scope through the authenticated user's archives, not a bare model lookup. T014 is the corresponding test for the cross-archive-isolation half of that same requirement (FR-010).
- T024 exists specifically to give FR-011 (graceful degradation when the semantic layer is slow/unavailable) a concrete implementation task — it was previously unimplemented and untested. T017 is its paired test.
