# Implementation Plan: Archive Entry Hybrid Search

**Branch**: `003-archive-search-filter` | **Date**: 2026-08-28 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-archive-search-filter/spec.md`

## Summary

Add a single search input to the Archive page that filters the open archive's entries in two layers: an instant, purely client-side substring match across title/content/tags/keywords, and a 400ms-debounced server-side hybrid search (Laravel's native full-text search + native pgvector similarity search) that also surfaces conceptually related entries. Results from both layers are merged and deduplicated with Reciprocal Rank Fusion, semantic-only matches are visually flagged, and README.md/ARCHITECTURE.md are updated to keep documentation accurate.

## Technical Context

**Language/Version**: PHP 8.4, Laravel 13; JavaScript (React 19)

**Primary Dependencies**: Laravel 13's native vector/full-text search support (`Blueprint::vector()`, `whereVectorSimilarTo()` — already used by `RetrievalService`/`PromptDirector`, no new package needed), existing `EmbeddingProvider` contract (Ollama-backed), Ziggy, Tailwind CSS v4, Lucide React icons. No new frontend or backend dependencies are required.

**Storage**: PostgreSQL with the `vector` extension — already installed on the app database, `archive_entries.embedding` is already a native `vector(768)` column.

**Testing**: Pest v4 feature tests, factory-backed (Constitution Principle VI), for backend behavior. No `ArchiveFactory`, `ArchiveEntryFactory`, or `TagFactory` currently exist, and none of `Archive`, `ArchiveEntry`, `Tag` use `HasFactory` — these were added as a prerequisite, since there was previously zero test coverage for the Archive feature at all. Frontend/browser-interaction behavior is verified manually per quickstart.md — Pest's browser-testing plugin was evaluated but its interaction methods proved incompatible with this environment (see tasks.md Notes for detail).

**Target Platform**: Web application, served by Laravel Herd locally

**Project Type**: Web application (Laravel monolith backend + React frontend in `resources/js`, not a separate frontend/backend split)

**Performance Goals**: Instant layer has no perceptible delay (client-side, no network call). Semantic layer results land within roughly 400–600ms of the user pausing (400ms debounce + one local Ollama embedding call + one Postgres query).

**Constraints**: Archives are small-to-medium scale (up to ~100 entries) per spec Assumptions — no pagination, no new indexing strategy required beyond a standard HNSW index. Must pass `vendor/bin/pint --test` and `npm run lint` with zero errors (Constitution Principle I). Frontend state derivation must follow Constitution Principle VIII (compute merged/deduplicated results during render, not via an extra `useEffect` + `setState` chain).

**Scale/Scope**: One archive's entries at a time (already how `ArchivePage` loads data); typically well under 100 entries per archive.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|---|---|---|
| I. Lint-Enforced Code Style | PASS | New PHP/JS code must pass Pint/ESLint before considered done; no exception needed. |
| II. Append-Only Migrations | PASS | The full-text index and the HNSW index on `embedding` are added via a **new** migration; the original `2026_06_25_082004_create_lore_entries_table.php` is not touched. |
| III. Comments Justify Only Non-Obvious Decisions | PASS | Default to no comments; the RRF constant (`60`) is the only candidate for a one-line comment, since it's genuinely non-obvious from the code alone. |
| IV. Data Isolation by Ownership | PASS | The search endpoint MUST scope through `$request->user()->archives()->findOrFail($id)`, mirroring `show()`/`save()` — never a bare `Archive::find()`. Called out explicitly so it isn't dropped during implementation. |
| V. Errors Fail Loudly | PASS | No new swallowed exceptions; embedding-provider failures during the semantic query must surface (e.g. as a normal exception response), not be caught and silently ignored — this is distinct from the *client* tolerating a slow/unavailable semantic layer (FR-011), which is a frontend UX concern (show instant results regardless), not a backend error-suppression concern. |
| VI. Feature-Test-First, Factory-Backed | GATE — see below | Requires adding `HasFactory` + factories for `Archive`, `ArchiveEntry`, `Tag` first, since none exist. This is now a required prerequisite step, not optional cleanup. |
| VII. No Speculative Abstraction | PASS | The merge/rank logic is scoped to this one endpoint. Following the existing `BuildArchiveFile` convention (a single-purpose `app/Actions/*` class used by the controller), the search+merge logic belongs in one new Action class — not a generic "SearchService" built for hypothetical future search use cases. |
| VIII. State Derivation Happens During Render | GATE — see below | The technical brief's suggested pattern (`vectorResults` state set from a debounced fetch effect) is fine as written — that's legitimate async-effect state. But the *merged, deduplicated, ranked display list* must be computed in the render body from `entries` + `searchQuery` + `vectorResults`, not stored via a second `useState` synced through another effect. Flagged explicitly for the tasks phase. |

No violations requiring `Complexity Tracking` — both gated items are additive prerequisites (factories; a render-time derivation instead of an extra effect), not deviations from principle.

**Post-Phase 1 re-check**: Both gated items are now fully specified rather than open — factories are enumerated in [data-model.md](./data-model.md)'s "New Model Factories" section, and the render-time derivation requirement is spelled out in data-model.md's "Ephemeral: Frontend Search State" section. No new violations were introduced by the data model, contract, or quickstart. Gate: PASS.

## Project Structure

### Documentation (this feature)

```text
specs/003-archive-search-filter/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md         # Phase 1 output (/speckit-plan command)
├── quickstart.md         # Phase 1 output (/speckit-plan command)
├── contracts/            # Phase 1 output (/speckit-plan command)
│   └── archives-search.md
├── technical-brief.md    # Reference input captured during clarification
└── tasks.md              # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

This is an existing Laravel + React monolith — no new top-level directories.

```text
database/migrations/
└── xxxx_xx_xx_add_search_to_archive_entries_table.php   # NEW: full-text index (title, content), HNSW index on embedding

database/factories/
├── ArchiveFactory.php          # NEW — required prerequisite (Constitution VI)
├── ArchiveEntryFactory.php     # NEW — required prerequisite
└── TagFactory.php              # NEW — required prerequisite

app/
├── Models/
│   ├── Archive.php              # MODIFY — add HasFactory
│   ├── ArchiveEntry.php         # MODIFY — add HasFactory
│   └── Tag.php                  # MODIFY — add HasFactory
├── Actions/
│   └── SearchArchiveEntries.php # NEW — full-text keyword query + vector query, RRF merge, mirrors BuildArchiveFile's pattern
└── Http/Controllers/Api/
    └── ArchiveController.php    # MODIFY — add search() method, scoped to the authenticated user's archive

routes/api.php                   # MODIFY — add GET /archives/{id}/search → archives.search

resources/js/
├── pages/ArchivePage.jsx        # MODIFY — search input, instant client-side filter, debounced fetch, render-time merge
└── components/EntryAccordion.jsx # MODIFY — optional semantic-match indicator

tests/Feature/Api/
└── ArchiveSearchTest.php        # NEW — Pest feature tests, factory-backed

README.md                        # MODIFY — per FR-012, update the Archive feature description
ARCHITECTURE.md                  # MODIFY — per FR-012, update route table, ArchiveController/ArchivePage descriptions, project structure trees
```

**Structure Decision**: Follow the existing monolith layout exactly — no new directories. Business logic for the merge/rank step is a single-purpose Action class (`app/Actions/SearchArchiveEntries.php`), matching the existing `BuildArchiveFile` convention rather than introducing a new service layer.

## Complexity Tracking

*No entries — no Constitution Check violations require justification.*
