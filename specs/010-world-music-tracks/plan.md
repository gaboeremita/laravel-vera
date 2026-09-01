# Implementation Plan: World Music Track

**Branch**: `010-world-music-tracks` | **Date**: 2026-08-31 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/010-world-music-tracks/spec.md`

## Summary

A world's owner can upload a single audio track (MP3 or WAV, max 20 MB) for their world, replacing or removing it as needed. Any user in an active world session hears that track as background music that fades out, pauses briefly, and fades back in each time it finishes, with a local volume slider and an M-key mute toggle that don't affect other users.

## Technical Context

**Language/Version**: PHP 8.4, JavaScript (React 19 / JSX)

**Primary Dependencies**: Laravel 13 (Eloquent, Storage), React 19, existing world session/audio components (`WorldScene.jsx`, `useVoiceMode.js` audio patterns)

**Storage**: Track file stored on the `public` disk under `worlds/{world}/track`; file metadata (disk, path, mime type, size, original name) stored as columns on the existing `worlds` table, mirroring how `environment_disk`/`environment_path`/`environment_original_name` already work for the `.glb` environment file

**Testing**: Pest feature tests (backend), following existing `WorldImageControllerTest`/`WorldControllerTest` conventions

**Target Platform**: Existing web application (Laravel + React SPA)

**Project Type**: Web application (single Laravel + React codebase, no separate frontend/backend projects)

**Performance Goals**: Background music starts within 2s of entering a session (SC-002)

**Constraints**: 20 MB max upload size; MP3/WAV only; one track per world (replace-on-upload, no playlist)

**Scale/Scope**: One new nullable track field-set on `World`; one new controller for upload/replace/delete; frontend playback + volume/mute control added to the existing world session view

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style**: New PHP/JSX code will be run through Pint/ESLint before the task is considered done (per project cadence rules, not per-file during development). PASS.
- **II. Append-Only Migrations**: Track columns are added via a new migration on the existing `worlds` table; no existing migration is edited. PASS.
- **III. Comments Justify Only Non-Obvious Decisions**: No speculative commenting planned. PASS.
- **IV. Data Isolation by Ownership**: Track mutations (`store`/`destroy`) are authorized against the specific `World` model via the existing `WorldPolicy`, mirroring `WorldImageController`'s `Gate::authorize('update', $world)` — never inferred from account-level defaults. PASS.
- **V. Errors Fail Loudly**: File store/delete failures are not swallowed; the existing `WorldImageController` pattern (delete the newly stored file and rethrow on failure) is reused. PASS.
- **VI. Feature-Test-First, Factory-Backed**: New behavior gets Pest feature tests using `WorldFactory`; no unit tests planned since the behavior is fully exercisable through HTTP requests. PASS.
- **VII. No Speculative Abstraction**: No playlist/ordering/multi-track abstraction is built since the spec explicitly scopes to a single track per world. PASS.
- **VIII. State Derivation Happens During Render, Not in Effects**: Frontend volume/mute state changes will be derived in event handlers (key press, slider change), not synced from props via effects; any audio-element lifecycle effect (e.g. play/pause on session enter) will be scoped to that effect only. PASS.

No violations requiring justification.

## Project Structure

### Documentation (this feature)

```text
specs/010-world-music-tracks/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Models/World.php                          # add track_* fillable attributes + accessor
├── Http/Controllers/Api/WorldTrackController.php   # new: store/destroy track
└── Policies/WorldPolicy.php                  # reused as-is (update ability)

database/
├── migrations/                                # new migration adding track_* columns to worlds
└── factories/WorldFactory.php                # optional withTrack() state for tests

routes/api.php                                 # new /worlds/{world}/track routes

resources/js/
├── components/world/WorldScene.jsx            # or a new WorldTrackPlayer component: looping <audio>, volume/mute, M key handler
├── components/WorldForm.jsx or WorldTrackEditor.jsx  # upload/replace/remove UI on world edit screen
└── hooks/useWorlds.js                         # extend with track upload/delete calls if needed

tests/
└── Feature/Api/WorldTrackControllerTest.php   # new
```

**Structure Decision**: Single Laravel + React codebase (existing structure). This feature adds one controller, one migration, and frontend additions to the existing world edit and world session views — no new top-level directories.

## Complexity Tracking

*No constitution violations — table not needed.*
