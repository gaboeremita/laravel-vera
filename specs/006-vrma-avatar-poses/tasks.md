---

description: "Task list for VRMA avatar pose animations"
---

# Tasks: VRMA Avatar Pose Animations

**Input**: Design documents from `/specs/006-vrma-avatar-poses/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: Included — Constitution Principle VI (Feature-Test-First, Factory-Backed) makes Pest feature tests mandatory for any Pest-covered behavior this feature touches. 3D rendering/playback (US2) is excluded from automated tests per established project convention (WebGL/VRM rendering is verified manually — see `quickstart.md`), matching how `004-vrm-3d-avatar` scoped its own visual-only scenarios.

**Organization**: Tasks are grouped by user story (from spec.md) to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no unmet dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- Exact file paths are included in every description

---

## Phase 1: Setup

**Purpose**: Dependency the frontend playback work (US2) needs before it can start.

- [X] T001 Add `@pixiv/three-vrm-animation` to `package.json` and run `npm install` — requires explicit user approval first per CLAUDE.md's dependency-change policy (research.md Decision 6)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, models, and shared data-delivery changes every user story builds on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T002 [P] Migration: create `poses` table (`assistant_id` FK, `name`, `vrm_blendshapes` json nullable, unique `(assistant_id, name)`) in `database/migrations/xxxx_xx_xx_xxxxxx_create_poses_table.php` (data-model.md)
- [X] T003 [P] Migration: create `pose_animation_files` table (`pose_id` FK unique, `path`, `disk`, `mime_type`, `size`, `original_name`) in `database/migrations/xxxx_xx_xx_xxxxxx_create_pose_animation_files_table.php` (data-model.md)
- [X] T004 [P] Extract `HasNormalizedBlendshapes` trait from `Emotion::normalizeBlendshapes()` (`app/Models/Emotion.php:50-60`) into `app/Models/Concerns/HasNormalizedBlendshapes.php`; update `Emotion` to `use` it (research.md Decision 7)
- [X] T005 [P] Create `Pose` model in `app/Models/Pose.php` (fillable `name`, `vrm_blendshapes`; cast `vrm_blendshapes` → array; `assistant(): BelongsTo`; `animationFile(): HasOne`; uses `HasNormalizedBlendshapes`) + `PoseFactory` in `database/factories/PoseFactory.php` (depends on T002, T004)
- [X] T006 [P] Create `PoseAnimationFile` model in `app/Models/PoseAnimationFile.php` (fillable `path`, `disk`, `mime_type`, `size`, `original_name`; `pose(): BelongsTo`; `url` accessor) + `PoseAnimationFileFactory` in `database/factories/PoseAnimationFileFactory.php` (depends on T003)
- [X] T007 Update `Assistant` model in `app/Models/Assistant.php`: add `poses(): HasMany` relation and `promptPoseNames(): array` method (depends on T005)
- [X] T008 Update `EmotionController::index` in `app/Http/Controllers/Api/EmotionController.php`: add a `poses` array (`{id, name, vrm_blendshapes, animation_url}`) to the response envelope alongside the existing `portrait_type`, `vrm_url`, `emotions` (contracts/api.md) (depends on T005, T006)

**Checkpoint**: Foundation ready — user story implementation can now begin.

---

## Phase 3: User Story 1 - Configure a Pose for an Assistant (Priority: P1) 🎯 MVP

**Goal**: A user can add, rename, and delete named poses on a 3D-avatar assistant, each with blendshape weights and/or an uploaded `.vrma`/`.fbx` animation file, combinable rather than exclusive.

**Independent Test**: Open an assistant's edit page, add a pose, configure blendshape weights and/or upload an animation file, save, and confirm the pose is listed with the configured data persisted.

### Tests for User Story 1 ⚠️

> Write these first — they should FAIL until the corresponding implementation tasks land.

- [X] T009 [P] [US1] `AssistantPoseTest` — create/rename/delete a pose, reject duplicate names on the same assistant, ownership scoping (404 for another user's assistant) in `tests/Feature/Api/AssistantPoseTest.php` (exercises T011, T013)
- [X] T010 [P] [US1] `AssistantPoseAnimationTest` — upload/delete a pose's animation file, accept `.vrma` and `.fbx`, reject non-matching extensions and files over 10 MB, ownership scoping in `tests/Feature/Api/AssistantPoseAnimationTest.php` (exercises T012, T013)

### Implementation for User Story 1

- [X] T011 [US1] Create `AssistantPoseController` (`store`, `update`, `destroy`) in `app/Http/Controllers/Api/AssistantPoseController.php`, scoped via `$request->user()->assistants()->findOrFail($assistantId)->poses()`, mirroring `AssistantEmotionController` (contracts/api.md) (depends on T005)
- [X] T012 [US1] Create `AssistantPoseAnimationController` (`store`, `destroy`) in `app/Http/Controllers/Api/AssistantPoseAnimationController.php` with `['required','file','extensions:vrma,fbx','max:10240']` validation, mirroring `AssistantVrmController` (contracts/api.md) (depends on T006)
- [X] T013 [US1] Register 5 pose routes in `routes/api.php`: `assistants.poses.store`, `assistants.poses.update`, `assistants.poses.destroy`, `assistants.poses.animation.store`, `assistants.poses.animation.destroy` (contracts/api.md) (depends on T011, T012) — T009/T010 should pass after this lands
- [X] T014 [US1] Add `poses`, `getPoseBlendshapes(name)`, `getPoseAnimationUrl(name)` to the `useEmotions` hook in `resources/js/hooks/useEmotions.js`, populated from the extended `GET /api/assistants/{assistant}/emotions` envelope (contracts/frontend-components.md) (depends on T008)
- [X] T015 [US1] Create `PoseEditor.jsx` in `resources/js/components/PoseEditor.jsx` — pose rows with optional `BlendshapeRows` (reused from `VrmEmotionEditor.jsx`) and an optional animation file (`.vrma`/`.fbx`) upload/delete control (contracts/frontend-components.md) (depends on T014)
- [X] T016 [P] [US1] Render `<PoseEditor>` in `resources/js/pages/EditAssistantPage.jsx` when `portraitType === 'avatar3d'`, below the existing `VrmEmotionEditor` sections, wired to the pose CRUD/animation endpoints (depends on T015, T013)
- [X] T017 [P] [US1] Render `<PoseEditor>` in `resources/js/pages/CreateAssistantPage.jsx` when `portraitType === 'avatar3d'`, wired the same way (depends on T015, T013)

**Checkpoint**: User Story 1 is fully functional and testable independently — poses can be configured and persisted, even though nothing triggers them in chat yet.

---

## Phase 4: User Story 2 - LLM-Triggered Pose Playback in Chat (Priority: P2)

**Goal**: When a chat response signals a configured pose, the 3D avatar plays the uploaded animation and/or applies the pose's blendshape expression, concurrently with any active emotion.

**Independent Test**: Configure a pose (Story 1), send a chat message containing a `[pose: name]` tag, and observe the avatar perform the associated animation and/or expression.

**Note**: No automated tests — 3D rendering/playback is verified manually per `quickstart.md` Scenarios 5–6, consistent with `004-vrm-3d-avatar`'s own scope for VRM rendering behavior.

### Implementation for User Story 2

- [X] T018 [P] [US2] Add `parsePoseFromResponse(text, validPoseNames)` to `resources/js/utils/parsers.js`, mirroring `parseEmotionFromResponse` — strips a leading `[pose: name]` tag after any `[emotion]`/`[intimate]` tags (research.md Decision 5)
- [X] T019 [P] [US2] Create `mixamoRetargeting.js` in `resources/js/utils/mixamoRetargeting.js` — Mixamo skeleton → VRM humanoid bone-name mapping, ported from `@pixiv/three-vrm`'s official Mixamo-animation example (research.md Decision 9) (depends on T001)
- [X] T020 [US2] Update `VrmAvatar.jsx` (`resources/js/components/VrmAvatar.jsx`): accept `poseBlendshapes`/`poseAnimationUrl` props; branch by file extension — `.vrma` via `VRMAnimationLoaderPlugin`, `.fbx` via `THREE.FBXLoader` + `mixamoRetargeting.js`; play once via `AnimationMixer` inside the existing `useFrame` loop; pause/resume idle head-sway around playback; merge `poseBlendshapes` into the existing blendshape lerp target map (contracts/frontend-components.md, research.md Decision 6) (depends on T001, T019)
- [X] T021 [US2] Update `Portrait.jsx` (`resources/js/components/Portrait.jsx`): pass `poseBlendshapes`/`poseAnimationUrl` props through to `VrmAvatar` (depends on T020)
- [X] T022 [US2] Update `AuthenticatedLayout.jsx` (`resources/js/layouts/AuthenticatedLayout.jsx`): add `currentPose` state, resolve `poseBlendshapes`/`poseAnimationUrl` via `useEmotions`, pass to `Portrait`, expose `setCurrentPose` via `Outlet` context alongside the existing `setCurrentEmotion` (depends on T021, T014)
- [X] T023 [US2] Update `ChatPage.jsx` (`resources/js/pages/ChatPage.jsx`): call `parsePoseFromResponse` alongside `parseEmotionFromResponse` when handling a chat response, then `setCurrentPose(pose)` (depends on T018, T022)

**Checkpoint**: User Stories 1 AND 2 both work independently — poses configured in Story 1 now visibly animate/express when signaled in chat.

---

## Phase 5: User Story 3 - Pose-Aware Prompt Guidance for the LLM (Priority: P3)

**Goal**: The system prompt sent to the LLM describes each assistant's configured poses as physical actions distinct from emotions, so the LLM knows to signal them.

**Independent Test**: Configure one or more poses on an assistant, inspect the constructed system prompt, and confirm it lists the pose names with guidance distinguishing them from emotions — and confirm the section is absent for a pose-free assistant.

### Tests for User Story 3 ⚠️

> Write first — should FAIL until T025 lands.

- [X] T024 [P] [US3] Test verifying the `pose tags` prompt section is present (with correct pose names) when poses are configured and absent when they are not, in `tests/Feature/Api/ConversationPosePromptTest.php` (exercises T025)

### Implementation for User Story 3

- [X] T025 [US3] Add a `POSE_TAG_INSTRUCTION` constant and a conditional `pose tags` prompt section (`$director->append('pose tags', [...])`, guarded on `promptPoseNames()` being non-empty) in `app/Http/Controllers/Api/ConversationController.php`, mirroring the existing `background tags` section (research.md Decision 4) (depends on T007) — T024 should pass after this lands

**Checkpoint**: All three user stories are independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Quality gates that span all stories.

- [X] T026 [P] Run `vendor/bin/pint --dirty --format agent` and fix any violations across all new/modified PHP files
- [X] T027 [P] Run `npm run lint` and fix any violations across all new/modified JS files
- [X] T028 Run `php artisan test --compact --filter=AssistantPose` and `php artisan test --compact --filter=ConversationPosePrompt`; confirm all pass
- [ ] T029 Manually validate [quickstart.md](quickstart.md) Scenarios 1–8 in the browser (3D rendering/playback verification cannot be automated)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately (needs user approval to install the npm package)
- **Foundational (Phase 2)**: No dependency on Setup (backend-only); BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational — no dependency on Setup or other stories
- **User Story 2 (Phase 4)**: Depends on Foundational and Setup (T001); depends on US1's `useEmotions` extension (T014) and pose data existing to animate
- **User Story 3 (Phase 5)**: Depends on Foundational only — independent of US1/US2, could be built in parallel with either
- **Polish (Phase 6)**: Depends on all desired user stories being complete

### Within Each User Story

- Tests are written first and should fail until their paired implementation task lands (Constitution Principle VI)
- Backend data delivery (`useEmotions` extension) before the frontend UI that consumes it
- Story complete before moving to the next priority (or run US3 in parallel with US1/US2 — it has no shared files with either)

### Parallel Opportunities

- T002, T003, T004 (Foundational) can run in parallel — different files, no shared dependencies
- T005 and T006 can run in parallel once their respective migration/trait dependencies land
- T009 and T010 (US1 tests) can be written in parallel — different files
- T016 and T017 (US1 page wiring) can run in parallel — different files
- T018 and T019 (US2 parsing/retargeting utilities) can run in parallel — different files, no shared dependency
- US3 (T024, T025) can be built in parallel with US1 or US2 by a second developer — no file overlap with either

---

## Parallel Example: Foundational Phase

```bash
# Launch independent foundational tasks together:
Task: "Migration: create poses table in database/migrations/..._create_poses_table.php"
Task: "Migration: create pose_animation_files table in database/migrations/..._create_pose_animation_files_table.php"
Task: "Extract HasNormalizedBlendshapes trait from Emotion::normalizeBlendshapes() into app/Models/Concerns/HasNormalizedBlendshapes.php"
```

## Parallel Example: User Story 1 Tests

```bash
# Write both test files together (before the controllers/routes they exercise):
Task: "AssistantPoseTest in tests/Feature/Api/AssistantPoseTest.php"
Task: "AssistantPoseAnimationTest in tests/Feature/Api/AssistantPoseAnimationTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 2: Foundational (CRITICAL — blocks everything)
2. Complete Phase 3: User Story 1
3. **STOP and VALIDATE**: Configure poses via the UI/API, confirm persistence and combinable weights+file
4. This is a usable increment even though nothing in chat triggers a pose yet — content authors can pre-configure poses ahead of Story 2 landing

### Incremental Delivery

1. Foundational → Foundation ready
2. Add User Story 1 → Test independently → poses configurable (MVP)
3. Add User Story 2 (needs Setup/T001 done first) → Test independently → poses visibly animate/express in chat
4. Add User Story 3 → Test independently → LLM reliably knows to signal configured poses
5. Polish → lint, full test suite, manual quickstart pass

### Suggested Team Split

- Developer A: Foundational → US1 (backend: T009–T013) → US1 (frontend: T014–T017)
- Developer B: Setup (T001) → US2 (T018–T023), starting once Foundational + US1's T014 land
- Developer C: US3 (T024–T025), fully parallel to A/B once Foundational lands

---

## Notes

- [P] tasks = different files, no unmet dependencies
- [Story] label maps task to specific user story for traceability
- T001 (npm dependency) requires explicit user approval before running, per CLAUDE.md
- Commit after each task or logical group
- Stop at any checkpoint to validate a story independently
