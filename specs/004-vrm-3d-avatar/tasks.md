# Tasks: 3D VRM Avatar Portrait

**Input**: Design documents from `specs/004-vrm-3d-avatar/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/](contracts/)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on each other)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)

---

## Phase 1: Setup

**Purpose**: New frontend dependencies required by all subsequent phases.

- [ ] T001 Install npm packages `three`, `@react-three/fiber`, `@pixiv/three-vrm` in `package.json`

**Checkpoint**: `npm install` completes without errors.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Backend models, migrations, and enum that every user story phase depends on. No user story work can begin until this phase is complete.

- [ ] T002 Create `AssistantPortraitType` enum (`Image = 'image'`, `Avatar3d = 'avatar3d'`) in `app/Enums/AssistantPortraitType.php`
- [ ] T003 [P] Create migration `add_portrait_type_to_assistants_table` — add `portrait_type` string column (default `'image'`) to `assistants` in `database/migrations/`
- [ ] T004 [P] Create migration `create_vrm_files_table` — polymorphic columns (`vrmable_type`, `vrmable_id`), `path`, `disk`, `mime_type`, `size`, `original_name` nullable, unique index on morph columns in `database/migrations/`
- [ ] T005 Create `VrmFile` model — fillable, `url` accessor via `Storage::disk()->url()`, `vrmable()` MorphTo in `app/Models/VrmFile.php` (after T004)
- [ ] T006 [P] Create `VrmFileFactory` in `database/factories/VrmFileFactory.php`
- [ ] T007 Update `Assistant` model — add `portrait_type` to fillable, cast `portrait_type` to `AssistantPortraitType`, add `vrm(): MorphOne` relationship to `VrmFile` in `app/Models/Assistant.php` (after T002, T003, T004, T005)

**Checkpoint**: Run migrations (`php artisan migrate`) and confirm both new tables exist.

---

## Phase 3: User Story 1 — Configure 3D Avatar (Priority: P1) 🎯 MVP

**Goal**: User can set an assistant's portrait type to "3D Avatar", upload a `.vrm` file, save it, and see the 3D avatar rendered in the portrait panel instead of a static image.

**Independent Test**: Configure an assistant for 3D avatar mode, upload a VRM file, navigate to chat — the portrait panel renders the model. Reverting to image mode restores image behaviour.

### Backend — User Story 1

- [ ] T008 [P] [US1] Create `AssistantVrmController` with `store` (upload, validate `.vrm` extension and `max:51200`, replace existing, return `vrm_url`) and `destroy` (delete from storage and DB, 404 if none) in `app/Http/Controllers/Api/AssistantVrmController.php`
- [ ] T009 [P] [US1] Register routes `POST /api/assistants/{id}/vrm` (name: `assistants.vrm.store`) and `DELETE /api/assistants/{id}/vrm` (name: `assistants.vrm.destroy`) inside the authenticated middleware group in `routes/api.php`
- [ ] T010 [US1] Update `AssistantController::update` to accept and validate `portrait_type` (sometimes, enum `AssistantPortraitType`); update `AssistantController::show` to include `portrait_type` and `vrm_url` in the response in `app/Http/Controllers/Api/AssistantController.php`
- [ ] T011 [US1] Update `EmotionController::index` — change flat array response to envelope `{ portrait_type, vrm_url, emotions: [...] }` in `app/Http/Controllers/Api/EmotionController.php`
- [ ] T012 [US1] Write `AssistantVrmTest` (Pest feature test, factory-backed) covering: successful VRM upload returns 201 + vrm_url; file >50 MB rejected with 422; non-VRM file rejected with 422; upload is scoped to owner (other user gets 404); delete removes file and returns 200; delete non-existent returns 404; portrait_type persists via PATCH; emotions index returns new envelope shape in `tests/Feature/Api/AssistantVrmTest.php`

### Frontend — User Story 1

- [ ] T013 [P] [US1] Update `useEmotions` hook — consume new envelope response shape, expose `portraitType` (default `'image'`) and `vrmUrl` (default `null`) alongside existing return values in `resources/js/hooks/useEmotions.js`
- [ ] T014 [US1] Update `AuthenticatedLayout` — pass `portraitType` and `vrmUrl` (from `useEmotions`) as props to `<Portrait>` in `resources/js/layouts/AuthenticatedLayout.jsx` (after T013)
- [ ] T015 [P] [US1] Create `VrmAvatar` component — R3F `<Canvas>` filling the portrait container, load VRM via `GLTFLoader` + `VRMLoaderPlugin`, camera positioned for bust framing, ambient + directional lighting, loading state while fetching, error fallback renders default VERA avatar image and logs the error in `resources/js/components/VrmAvatar.jsx`
- [ ] T016 [US1] Update `Portrait` — accept `portraitType` and `vrmUrl` props (both optional, default to `'image'` / `null`); when `portraitType === 'avatar3d'` and `vrmUrl` is non-null render `<VrmAvatar vrmUrl={vrmUrl} emotion={emotion} />`; when `portraitType === 'avatar3d'` and `vrmUrl` is null render default VERA avatar image; all existing branches unchanged in `resources/js/components/Portrait.jsx` (after T015)
- [ ] T017 [P] [US1] Update `EditAssistantPage` — add portrait type radio/select (image / 3D avatar); when "3D Avatar" selected show VRM file upload input wired to `POST /api/assistants/{id}/vrm`; show current VRM filename and a delete button wired to `DELETE /api/assistants/{id}/vrm`; PATCH assistant with `portrait_type` on save in `resources/js/pages/EditAssistantPage.jsx`
- [ ] T018 [P] [US1] Update `CreateAssistantPage` — add portrait type toggle (defaults to image); when "3D Avatar" selected show optional VRM upload field (file stored via the VRM endpoint after assistant is created, or skip) in `resources/js/pages/CreateAssistantPage.jsx`

**Checkpoint**: Scenarios 1, 2, 3, 7 from [quickstart.md](quickstart.md) pass. `php artisan test --compact --filter=AssistantVrm` is green.

---

## Phase 4: User Story 2 — Emotion-Driven Expressions (Priority: P2)

**Goal**: When the LLM sends an emotion tag, the 3D avatar's face transitions smoothly to the matching expression within ~500 ms.

**Independent Test**: Send a message that produces `[happy]` — avatar transitions to happy expression. Send neutral — face smoothly returns to neutral.

- [ ] T019 [US2] Create `vrmExpressions.js` — export `getBlendshapeTargets(emotionTag)` returning `Array<{ expression: string, weight: number }>` using the mapping from [research.md](research.md); returns empty array for unknown tags in `resources/js/utils/vrmExpressions.js`
- [ ] T020 [US2] Update `VrmAvatar` — consume `emotion` prop; call `getBlendshapeTargets(emotion)` to compute targets; in `useFrame` lerp current `expressionManager` blendshape values toward targets each frame (~300 ms to converge); store current values in a mutable ref (not state) in `resources/js/components/VrmAvatar.jsx` (after T019)

**Checkpoint**: Scenario 4 from [quickstart.md](quickstart.md) passes (visual verification).

---

## Phase 5: User Story 3 — Idle Animations (Priority: P3)

**Goal**: The avatar blinks at randomised intervals of 2–6 seconds while idle, making the portrait feel alive between responses.

**Independent Test**: Leave the chat view open for 10 seconds with no messages — avatar blinks at least once.

- [ ] T021 [US3] Update `VrmAvatar` — add blink accumulator mutable ref in `useFrame`; advance accumulator by `delta`; when it exceeds a randomised threshold (2–6 s), trigger blink by lerping `blink` blendshape to 1.0 then back to 0.0 over ~150 ms, then reset accumulator with a new random threshold; blink must not conflict with expression state (run as a separate blendshape layer) in `resources/js/components/VrmAvatar.jsx`

**Checkpoint**: Scenario 5 from [quickstart.md](quickstart.md) passes (visual verification).

---

## Phase 6: Polish & Lint

- [ ] T022 [P] Run `vendor/bin/pint --dirty --format agent` and fix any violations across all PHP files changed in this feature
- [ ] T023 [P] Run `php artisan test --compact --filter=AssistantVrm` and confirm all tests pass
- [ ] T024 Complete manual verification per [quickstart.md](quickstart.md) scenarios 1–7

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1; blocks all user story phases
- **US1 (Phase 3)**: Depends on Phase 2; backend and frontend subtasks can be parallelised once Phase 2 is done
- **US2 (Phase 4)**: Depends on Phase 3 (needs `VrmAvatar` from T015 and `emotion` prop wired from T016)
- **US3 (Phase 5)**: Depends on Phase 3 (needs `VrmAvatar`); can start after T015, independent of Phase 4
- **Polish (Phase 6)**: Depends on all desired phases complete

### User Story Dependencies

- **US1**: Requires Foundational complete — no dependency on US2 or US3
- **US2**: Requires US1 complete (specifically T015 — `VrmAvatar` exists)
- **US3**: Requires US1 complete (specifically T015 — `VrmAvatar` exists); independent of US2

### Within User Story 1

- T003, T004 can run in parallel (different migration files)
- T005, T006 can run in parallel (different files); T005 requires T004
- T007 requires T002, T003, T004, T005
- T008, T009, T013, T015, T017, T018 can all start in parallel once Phase 2 is done
- T010 and T011 are sequential (same file)
- T014 requires T013; T016 requires T015

---

## Parallel Examples

### User Story 1 — Backend can run alongside Frontend

```
Parallel group A (different files, no deps on each other):
  T008 — AssistantVrmController
  T009 — routes/api.php
  T013 — useEmotions hook
  T015 — VrmAvatar component (loading only)
  T017 — EditAssistantPage
  T018 — CreateAssistantPage

Sequential after group A:
  T010 → T011 (same file, AssistantController)
  T014 (depends on T013)
  T016 (depends on T015)
  T012 (AssistantVrmTest — write last, after API is wired)
```

---

## Implementation Strategy

### MVP (User Story 1 only)

1. Phase 1: Setup (T001)
2. Phase 2: Foundational (T002–T007)
3. Phase 3: US1 backend (T008–T012), then US1 frontend (T013–T018)
4. **Validate**: quickstart scenarios 1, 2, 3, 7 + `AssistantVrmTest` green
5. **Stop here** — 3D avatar configurable and renders; expressions and idle deferred

### Full Delivery

Continue with Phase 4 (US2, expressions) → Phase 5 (US3, idle blink) → Phase 6 (polish).

---

## Notes

- Tests are included for backend API only (`AssistantVrmTest`). 3D rendering, expressions, and idle animation are verified manually per quickstart.md.
- `VrmAvatar.jsx` is touched in T015 (load), T020 (expressions), and T021 (idle) — keep these sequential to avoid merge conflicts.
- The `[P]` marker means tasks can be launched by separate agents concurrently; it does not mean a single agent should skip reading the results of prerequisite tasks.
