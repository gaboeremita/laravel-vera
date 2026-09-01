---

description: "Task list for World Music Track feature implementation"
---

# Tasks: World Music Track

**Input**: Design documents from `/specs/010-world-music-tracks/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/world-track-api.md](contracts/world-track-api.md), [quickstart.md](quickstart.md)

**Tests**: Included per Constitution Principle VI (Feature-Test-First, Factory-Backed) — Pest feature tests, no unit tests.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2)

## Phase 1: Setup (Shared Infrastructure)

- [x] T001 Create migration creating the polymorphic `tracks` table (`trackable_type`/`trackable_id`, `path`, `disk`, `mime_type`, `size`, `original_name`, timestamps), structured like the existing `images` table, via `php artisan make:model Track -m --no-interaction`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Model and route scaffolding both user stories build on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T002 Create `app/Models/Track.php` mirroring `app/Models/Image.php`: `trackable(): MorphTo`, fillable `path`/`disk`/`mime_type`/`size`/`original_name`, `getUrlAttribute()` resolving via `Storage::disk($this->disk)->url($this->path)`
- [x] T003 Add `track(): MorphOne` to `app/Models/World.php` (`$this->morphOne(Track::class, 'trackable')`) — `worlds` table itself is unmodified, so no change needed to its `deleted` listener beyond relying on eager-loaded relation cleanup at the controller/observer level
- [x] T004 [P] Create `database/factories/TrackFactory.php` with realistic fake `path`/`disk`/`mime_type`/`size`/`original_name` values (mirrors `ImageFactory` conventions if one exists, else follows `WorldFactory`'s style)
- [x] T005 Add `trackUrl`/`trackOriginalName` fields to `app/Http/Resources/WorldResource.php` sourced from the `track` relation (`$this->whenLoaded('track', fn () => $this->track?->url)`), and eager-load `track` alongside `cardImage`/`portraitImage` wherever those are loaded in `app/Http/Controllers/Api/WorldController.php`

**Checkpoint**: `Track` model, factory, and resource are ready — user story implementation can now begin

---

## Phase 3: User Story 1 - Attach a music track to a world (Priority: P1) 🎯 MVP

**Goal**: A world's owner can upload an MP3/WAV to set, replace, or remove their world's track.

**Independent Test**: Open a world's edit screen, upload an audio file, confirm it's set as the world's track; replace it with another file and confirm it changes; remove it and confirm the world has none.

### Tests for User Story 1 ⚠️

- [x] T006 [P] [US1] Feature test in `tests/Feature/Api/WorldTrackControllerTest.php`: owner uploads a valid MP3 → `201`, `trackUrl` present, world has a `Track` row
- [x] T007 [P] [US1] Feature test in `tests/Feature/Api/WorldTrackControllerTest.php`: owner uploads a valid WAV replacing an existing track (world built with `Track::factory()->for($world, 'trackable')`) → `201`, new `trackUrl` differs from old, old file removed from storage, only one `Track` row exists for the world
- [x] T008 [P] [US1] Feature test in `tests/Feature/Api/WorldTrackControllerTest.php`: upload rejected for unsupported format (e.g. `.flac`) → `422`
- [x] T009 [P] [US1] Feature test in `tests/Feature/Api/WorldTrackControllerTest.php`: upload rejected for file over 20 MB → `422`
- [x] T010 [P] [US1] Feature test in `tests/Feature/Api/WorldTrackControllerTest.php`: non-owner attempting upload or delete → `403`
- [x] T011 [P] [US1] Feature test in `tests/Feature/Api/WorldTrackControllerTest.php`: owner deletes an existing track → `200`, `trackUrl` null afterward, file removed from storage, `Track` row deleted
- [x] T012 [P] [US1] Feature test in `tests/Feature/Api/WorldTrackControllerTest.php`: owner deletes when no track is set → `404`

### Implementation for User Story 1

- [x] T013 [US1] Create `app/Http/Controllers/Api/WorldTrackController.php` with `store(Request $request, World $world)` and `destroy(World $world)` methods, following `app/Http/Controllers/Api/WorldImageController.php`'s pattern exactly: `Gate::authorize('update', $world)`, inline validation (`['track' => ['required', 'file', 'mimes:mp3,wav', 'max:20480']]`), store via `$file->store("worlds/{$world->id}/track", 'public')`, `$world->track()->updateOrCreate([], ['path' => ..., 'disk' => 'public', 'mime_type' => ..., 'size' => ..., 'original_name' => ...])`, delete the previous file only after a successful save, delete-newly-stored-file-and-rethrow on failure
- [x] T014 [US1] Add `POST /worlds/{world}/track` and `DELETE /worlds/{world}/track` routes to `routes/api.php`, named `worlds.track.store` and `worlds.track.destroy`, alongside the existing `worlds.image.*` routes
- [x] T015 [US1] Add an "Add/Replace/Remove track" control to `resources/js/components/WorldForm.jsx` (or a new `resources/js/components/WorldTrackEditor.jsx` used by it), following `resources/js/components/WorldImagesEditor.jsx`'s upload/replace/remove UI pattern, showing `trackOriginalName` when set
- [x] T016 [US1] ~~Add upload/delete calls to `useWorlds.js`~~ — not needed: `WorldTrackEditor.jsx` calls the new routes directly via `api.postForm`/`api.delete`, the same pattern `WorldImagesEditor.jsx` already uses (that component also bypasses `useWorlds.js`)

**Checkpoint**: User Story 1 is fully functional and testable independently — a world's track can be set, replaced, and removed via the UI and API.

---

## Phase 4: User Story 2 - Hear world music during a session (Priority: P1)

**Goal**: Any user in an active world session hears the world's track as background music, with per-user volume/mute control and a fade-out/pause/fade-in cycle on each replay.

**Independent Test**: Enter a session in a world with a track set and confirm background audio plays, fades and restarts after it ends, and responds to volume/mute controls; enter a session in a world with no track and confirm silence.

### Implementation for User Story 2

- [x] T017 [US2] Create `resources/js/components/world/WorldTrackPlayer.jsx`: renders a hidden `<audio>` element sourced from the active world's `trackUrl` (no `loop` attribute), exposes volume/mute controls, and does nothing when `trackUrl` is null
- [x] T018 [US2] In `resources/js/components/world/WorldTrackPlayer.jsx`, implement the fade-out → pause → fade-in restart cycle on the audio element's `ended` event: ramp volume down to 0 over a short interval, `setTimeout` a few seconds, reset `currentTime` to 0, play, then ramp volume back up to the user's current volume setting
- [x] T019 [P] [US2] In `resources/js/components/world/WorldTrackPlayer.jsx`, add a volume slider control that applies directly to the audio element's `volume` on `onChange`, and persist the chosen level in `localStorage` for the user's next session
- [x] T020 [US2] In `resources/js/components/world/WorldTrackPlayer.jsx`, add a `keydown` listener for the `M` key that toggles mute (storing the pre-mute volume and restoring it on unmute), scoped to the component's mount lifetime
- [x] T021 [US2] Mount `WorldTrackPlayer` from `resources/js/pages/WorldPage.jsx` (or `resources/js/components/world/WorldScene.jsx`, whichever renders the active session), passing the current world's `trackUrl`

**Checkpoint**: All user stories are independently functional — tracks can be managed (US1) and are heard with proper fade/volume/mute behavior during sessions (US2).

---

## Phase 5: Polish & Cross-Cutting Concerns

- [x] T022 Run `vendor/bin/pint --dirty --format agent` and `npm run lint` and fix any issues surfaced across all files touched by this feature
- [x] T023 Run `php artisan test --compact --filter=WorldTrackControllerTest` and confirm all new tests pass
- [ ] T024 Walk through [quickstart.md](quickstart.md) manually (backend curl steps + frontend playback/volume/mute/fade checks) and confirm each step behaves as documented

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup (T001's migration) - BLOCKS both user stories
- **User Story 1 (Phase 3)**: Depends on Foundational completion - no dependency on User Story 2
- **User Story 2 (Phase 4)**: Depends on Foundational completion (needs `trackUrl` on `WorldResource`) and functionally needs a track to exist to be observable, but its player component can be built against `Track::factory()->for($world, 'trackable')`-backed data independently of US1's UI
- **Polish (Phase 5)**: Depends on both user stories being complete

### Within Each User Story

- Tests (T006-T012) before implementation (T013-T016) for User Story 1
- Backend (T013, T014) before frontend wiring (T015, T016) for User Story 1
- Core player (T017, T018) before controls layered on top (T019, T020) before mounting (T021) for User Story 2

### Parallel Opportunities

- T004 can run in parallel with T002/T003/T005 (different file)
- T006-T012 (all in the same test file but independent test cases) can be drafted in parallel, then merged into one file
- T019 can be developed in parallel with T020 once T017/T018 exist (different concerns within the same file, low conflict risk — coordinate if worked by different people)

---

## Parallel Example: User Story 1

```bash
# Launch independent test cases for User Story 1 together:
Task: "Feature test: owner uploads a valid MP3 in tests/Feature/Api/WorldTrackControllerTest.php"
Task: "Feature test: upload rejected for unsupported format in tests/Feature/Api/WorldTrackControllerTest.php"
Task: "Feature test: non-owner attempting upload or delete in tests/Feature/Api/WorldTrackControllerTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (migration)
2. Complete Phase 2: Foundational (model, factory, resource)
3. Complete Phase 3: User Story 1 (upload/replace/remove track)
4. **STOP and VALIDATE**: Confirm tracks can be set/replaced/removed via API and UI
5. This alone delivers no audible value yet — pair with User Story 2 before considering the feature demo-ready, since the spec treats both as P1

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → tracks manageable but silent
3. Add User Story 2 → Test independently → tracks now audible with fade/volume/mute → feature complete
4. Run Polish phase → lint, full test run, quickstart walkthrough

---

## Notes

- [P] tasks = different files (or independent test cases), no dependencies
- [Story] label maps task to specific user story for traceability
- Both user stories are Priority P1 per spec.md — deliver both before calling this feature done
- Verify tests fail before implementing (T006-T012 before T013)
- Commit after each task or logical group
- Stop at the Phase 3 checkpoint to validate track management before wiring up playback
