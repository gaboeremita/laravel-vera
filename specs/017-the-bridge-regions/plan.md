# Implementation Plan: The Bridge — One World Made of Connected Regions

**Branch**: `017-the-bridge-regions` | **Date**: 2026-09-27 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/017-the-bridge-regions/spec.md`

## Summary

Today's `World` becomes `Region`, and a new `World` groups regions, holds the World Prompts, images and a spawn passage, and owns residents and sessions.

- **Schema**: the `worlds` table is renamed to `regions`, and a new `worlds` table is created. Residents keep their table and get a home `region_id`, and the existing unique `(world_id, assistant_id)` index now enforces one resident per world. Sessions and resident session states record their current region ([research.md](research.md) R1, R2, R8).
- **Passages**: `ParseEnvironmentLayout` reads a new `passage` marker, stored in `layout.passages` with facing, radius and a precomputed arrival point. Links are two rows in `passage_links`, one per direction, kept consistent by a `LinkPassages` action. A `ReconcilePassages` action removes links and the spawn when a re-uploaded environment drops a passage (R4–R6).
- **Travel**: the client triggers a passage when the player enters its radius after having left it once. A `travel` endpoint resolves the link, moves the session and its followers, and returns the arrival. The world page then unmounts the region and mounts the destination (R9).
- **AI context**: prompt building and world tools receive the resident's current `Region` for layout. Prompts combine the world's and the region's, and location names the region (R11).
- **Configuration UI**:
  - **World tab**: world fields and the spawn passage.
  - **Regions tab**: a region list, and today's world form reused as the region form with a passages editor.
  - **Residents editor**: gains grayed-out rows and "Bring here".
- **Migration**: new migrations carry every existing world into The Bridge as a region and delete old sessions (R12).

## Technical Context

**Language/Version**: PHP 8.4; JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13, Pest 4, Ziggy 2; three.js, @react-three/fiber (existing). No new dependencies.

**Storage**: PostgreSQL in tests, MySQL locally. New `worlds` and `passage_links` tables, `worlds` renamed to `regions`, new columns on `regions`, `world_residents`, `world_sessions` and `world_session_residents` ([data-model.md](data-model.md)).

**Testing**:
- **Pest feature tests** for the API, layout parsing, links, travel, sessions, prompts and the migration.
- **`node --test`** for the pure passage trigger logic.
- **Manually**, following [quickstart.md](quickstart.md): the configuration UI and the travel feel.

**Target Platform**: Desktop browsers via the existing SPA.

**Project Type**: Web application (Laravel backend + React frontend, single repo).

**Performance Goals**: Travel under 5 s for a typical region (SC-003), dominated by the GLB download already needed today. The passage check runs in the existing frame loop over at most a few dozen passages.

**Constraints**:
- Existing environment files keep working without passages.
- The marker convention matches existing markers.
- Session URLs stay stable.

**Scale/Scope**: A handful of worlds, tens of regions per world, up to a few dozen passages per region.
- **Backend**: about 30 files renamed or retyped from `World` to `Region`, about 12 new files, 8 migrations.
- **Frontend**: about 8 new and 12 changed modules.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style**: Pint and ESLint run once at push or PR time per CLAUDE.md. PASS.
- **II. Append-Only Migrations**: every schema and data change is a new migration. No existing migration is edited. PASS.
- **III. Comments Justify Only Non-Obvious Decisions**: comments only where the reason is hidden, for example why a passage must be left before it can trigger, or why links are stored as two rows. PASS.
- **IV. Data Isolation by Ownership**:
  - Worlds are resolved through `$request->user()->worlds()`, and regions through scoped nested route bindings under that world.
  - Residents, links and sessions are resolved inside that world, so cross-world ids return 404.
  - A link target must belong to the same world.
  - Feature tests cover another user's world and a region from another world. PASS.
- **V. Errors Fail Loudly**:
  - An unlinked passage or an invalid follower on travel returns 422. Starting a session without a spawn returns 422. A resident conflict returns 409.
  - The client shows a toast for every failure.
  - Migration steps throw on unexpected state.
  - PASS.
- **VI. Feature-Test-First, Factory-Backed**:
  - `WorldFactory` becomes `RegionFactory` (keeping `withLayout()`, which gains passages).
  - A new `WorldFactory` and `PassageLinkFactory` are added.
  - Client logic uses a node unit test, the established exception.
  - PASS.
- **VII. No Speculative Abstraction**:
  - The images controller logic and `WorldImagesEditor` are shared between world and region because each now has two real callers.
  - Links reuse the region foreign keys for cleanup instead of observers.
  - Residents outside the loaded region are not simulated.
  - PASS.
- **VIII. State Derivation During Render**:
  - The current region, the region's residents and the spawn validity are derived during render from world, session and region data.
  - Region loading uses an effect-local async closure.
  - PASS.

No violations; Complexity Tracking is not needed.

**Post-design re-check**:
- The contracts keep every query world-scoped.
- Deletion rules are enforced by foreign keys plus one explicit spawn clear.
- The only cross-cutting change is the `World` → `Region` type rename in layout code.
- PASS.

## Project Structure

### Documentation (this feature)

```text
specs/017-the-bridge-regions/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api.md
│   └── passage-marker.md
└── tasks.md             # created by /speckit-tasks
```

### Source Code (repository root)

```text
database/
├── migrations/
│   ├── ..._rename_worlds_to_regions.php                 # new
│   ├── ..._create_worlds_table.php                      # new: container + spawn columns
│   ├── ..._add_world_id_to_regions_table.php            # new: creates The Bridge, assigns regions, moves morph types
│   ├── ..._repoint_world_user_to_worlds.php             # new
│   ├── ..._add_region_id_to_world_residents_table.php   # new: home region, repoints world_id
│   ├── ..._delete_world_sessions.php                    # new
│   ├── ..._add_region_id_to_world_sessions_tables.php   # new: world_sessions and world_session_residents
│   └── ..._create_passage_links_table.php               # new
└── factories/
    ├── RegionFactory.php          # renamed from WorldFactory; withLayout() includes passages
    ├── WorldFactory.php           # new: container
    ├── PassageLinkFactory.php     # new
    └── WorldResidentFactory.php   # changed: region

app/
├── Models/
│   ├── Region.php                 # renamed from World; world(), passageLinks()
│   ├── World.php                  # new: regions(), residents(), users(), images, spawnRegion(), spawnPassage()
│   ├── PassageLink.php            # new
│   ├── WorldResident.php          # changed: region()
│   ├── WorldSession.php           # changed: region()
│   ├── WorldSessionResident.php   # changed: region()
│   └── WorldUser.php              # unchanged relations, new target table
├── Actions/
│   ├── ParseEnvironmentLayout.php # changed: passage markers
│   ├── LinkPassages.php           # new: link/unlink both directions
│   ├── ReconcilePassages.php      # new: drop links and spawn for removed passages
│   ├── ResolveResidentRegion.php  # new: a resident's current region in a session
│   ├── TravelThroughPassage.php   # new: session + followers to the linked arrival
│   ├── BuildResidentWorldPrompt.php, AppendWorldConversationContext.php, ResolveWorldState.php,
│   │   ResolveUserActivity.php, ResolveSpotStacking.php, ApplyResidentZoneAccess.php,
│   │   GenerateResidentConversationTurn.php   # changed: Region for layout; world + region prompts
├── Http/
│   ├── Controllers/Api/
│   │   ├── WorldController.php            # rewritten for the container + spawn
│   │   ├── RegionController.php           # new: today's WorldController environment/layout logic
│   │   ├── WorldImageController.php       # changed: world images; shared store/destroy
│   │   ├── RegionImageController.php      # new: region images via the shared logic
│   │   ├── RegionTrackController.php      # renamed from WorldTrackController
│   │   ├── WorldResidentController.php    # changed: nested under region; bring()
│   │   ├── PassageLinkController.php      # new
│   │   ├── WorldSessionController.php     # changed: spawn on store; resume(); travel()
│   │   ├── ConversationController.php, ResidentDecisionController.php, ResidentActivityController.php,
│   │   │   ResidentStateController.php, ResidentConversationController.php,
│   │   │   ResidentObservationController.php   # changed: resident's current region
│   ├── Requests/
│   │   ├── StoreWorldRequest.php, UpdateWorldRequest.php    # changed: container fields, spawn
│   │   ├── StoreRegionRequest.php, UpdateRegionRequest.php  # new: today's world rules
│   │   ├── UpdatePassageLinkRequest.php                     # new
│   │   └── TravelRequest.php                                # new
│   └── Resources/
│       ├── WorldResource.php      # changed: container shape
│       └── RegionResource.php     # new: today's WorldResource shape + links
├── Policies/WorldPolicy.php       # unchanged; regions authorize through their world
├── Services/AgentLoop/Tools/World/*.php  # changed: WorldToolbox and tools take Region
└── Traits/ResolvesWorldUser.php   # unchanged

routes/api.php                     # changed: region routes, passage links, bring, travel, resume
resources/js/ziggy.js              # regenerated

resources/js/
├── pages/
│   ├── WorldsPage.jsx             # changed: card data
│   ├── CreateWorldPage.jsx        # changed: container fields only
│   ├── EditWorldPage.jsx          # changed: WORLD / REGIONS tabs
│   ├── WorldSessionsPage.jsx      # changed: spawn warning, start disabled
│   └── WorldPage.jsx              # changed: loads session region, travel, remount on region change
├── components/
│   ├── WorldForm.jsx              # changed: world fields + spawn PassageSelect
│   ├── RegionForm.jsx             # new: today's WorldForm sections (details, environment, context, theme)
│   ├── RegionsTab.jsx             # new: region list with ★/⚠, selected region editor, add region
│   ├── RegionPassagesEditor.jsx   # new: one row per passage with PassageSelect and confirm-on-relink
│   ├── PassageSelect.jsx          # new: dropdown grouped by region name
│   ├── WorldImagesEditor.jsx      # changed: route names as props
│   ├── WorldTrackEditor.jsx       # changed: region routes
│   ├── WorldResidentsEditor.jsx   # changed: region routes, grayed rows, BRING HERE
│   ├── WorldCard.jsx              # changed: region count, spawn warning
│   ├── WorldSessionList.jsx       # changed: disabled start without spawn
│   └── world/
│       ├── passageTrigger.js      # new: armed-on-exit trigger per passage
│       ├── PassageTracker.jsx     # new: runs passageTrigger in the frame loop, reports a crossing
│       ├── WorldChat.jsx          # changed: sends regionId
│       └── hud/LocationReadout.jsx  # changed: region name before zone
└── hooks/useResidentAgency.js     # changed: only residents in the current region

tests/
├── Feature/Api/
│   ├── WorldControllerTest.php            # rewritten: container CRUD, spawn validation
│   ├── RegionControllerTest.php           # new: from today's world tests; reconcile on re-upload; delete rules
│   ├── WorldLayoutImportTest.php          # extended: passage markers and warnings
│   ├── PassageLinkControllerTest.php      # new: two-way, relink, same region, cross-world 422
│   ├── WorldResidentControllerTest.php    # extended: 409 conflict, bring
│   ├── WorldSessionControllerTest.php     # extended: spawn required, resume, travel with followers
│   ├── WorldConversationContextTest.php   # extended: world + region prompts, region name
│   ├── RegionsMigrationTest.php           # new: former worlds become regions of The Bridge
│   └── (other World* / Resident* tests)   # changed: factories and routes
└── Unit/
    └── PassageTrigger.test.js             # new
```

**Structure Decision**: The existing single-repo layout. Region UI lives beside the world components under `resources/js/components/`, and passage travel under `components/world/`. No new base folders.

## Delivery Slices

1. **Worlds contain regions (US1, US3 without passages)**:
   - Migrations, the model rename, the new `World`, and the region and world controllers and resources.
   - The World and Regions tabs.
   - AI code retyped to `Region`, with world + region prompts.
2. **Passages and spawn (US2, US3)**:
   - Marker parsing, links and reconcile.
   - The passages editor and `PassageSelect`.
   - The spawn setting, and session start requiring the spawn.
3. **Travel (US2)**: `passageTrigger`, `PassageTracker`, the travel and resume endpoints, and region remounting on the world page.
4. **Residents per world (US4)**: the 409 conflict, bring, grayed rows, followers on travel, and resident region in session state.
5. **Region location and music (US5)**: the location readout, the track switch on remount, and the region name in the AI location line.

## Complexity Tracking

Not applicable; no constitution violations.
