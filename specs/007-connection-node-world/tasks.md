---

description: "Dependency-ordered implementation tasks for Configurable Worlds"
---

# Tasks: Configurable Worlds

**Input**: Design documents from `/specs/007-connection-node-world/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [world API contract](./contracts/world-api.md), [world frontend contract](./contracts/world-frontend.md), [quickstart.md](./quickstart.md)

**Tests**: Pest feature tests are required by the project constitution for touched behavior. Frontend behavior is validated with the existing supported test tooling where available; the user performs the complete first-person acceptance flow in a desktop browser with the selected assets.

**Organization**: Tasks are grouped by user story so each increment can be implemented and demonstrated independently after the foundational schema and authorization work is complete.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can be completed in parallel because it targets different files and has no unmet dependency within its phase.
- **[US#]**: User story traced from [spec.md](./spec.md).

## Phase 1: Setup and Asset Handoff

**Purpose**: Establish the implementation boundaries and obtain the user-supplied assets required to exercise a real world without introducing generated art or new dependencies.

- [ ] T001 Confirm the existing assistant upload, VRM, pose, archive, and conversation integration points before modification in `app/Models/Assistant.php`, `app/Http/Controllers/Api/AssistantController.php`, `app/Http/Controllers/Api/ConversationController.php`, and `resources/js/pages/EditAssistantPage.jsx`
- [ ] T002 Obtain the user-approved Connection Node room delivery as a runtime GLB plus named collision meshes, spawn position, placement notes, source, and license information per `specs/007-connection-node-world/quickstart.md`
- [ ] T003 Obtain any user-approved NPC VRM, pose, and animation assets to exercise the reused assistant upload flow per `specs/007-connection-node-world/quickstart.md`
- [ ] T004 Verify the existing package lock and imports can support the documented Three/React Three Fiber/VRM runtime without dependency changes in `package.json` and `resources/js/components/VrmAvatar.jsx`

---

## Phase 2: Foundational Domain, Ownership, and API Infrastructure

**Purpose**: Create the additive data model, ownership boundaries, shared serialization, and routes that block all world stories.

**⚠️ CRITICAL**: Complete this phase before beginning the user-story phases.

- [ ] T005 Create an append-only migration for the `assistant_kind` field and update `app/Enums/AssistantKind.php`, `app/Models/Assistant.php`, and `database/factories/AssistantFactory.php` to support normal assistants and `WorldNpc`
- [ ] T006 [P] Create an append-only `worlds` table migration with ownership, user-unique slug, environment metadata, two context prompts, and JSON settings in `database/migrations/*_create_worlds_table.php`
- [ ] T007 [P] Create an append-only `world_residents` table migration with unique world/assistant membership, transforms, behavior enum, and behavior settings in `database/migrations/*_create_world_residents_table.php`
- [ ] T008 Create `app/Enums/WorldResidentBehavior.php` with `Stationary` and `Roam` cases and use enum casts rather than persisted magic strings
- [ ] T009 Create `app/Models/World.php` with owner and resident relationships, guarded/fillable fields matching local conventions, casts, and `contextPromptFor(AssistantKind $kind): ?string`
- [ ] T010 Create `app/Models/WorldResident.php` with typed world/assistant relationships and transform/behavior casts in `app/Models/WorldResident.php`
- [ ] T011 [P] Create factory-backed world test data in `database/factories/WorldFactory.php` and `database/factories/WorldResidentFactory.php`
- [ ] T012 Create ownership policies for worlds and resident changes in `app/Policies/WorldPolicy.php` and register/discover them according to the existing authorization convention
- [ ] T013 [P] Create reusable world validation requests in `app/Http/Requests/StoreWorldRequest.php` and `app/Http/Requests/UpdateWorldRequest.php`
- [ ] T014 [P] Create stable world editor/runtime serialization in `app/Http/Resources/WorldResource.php` and `app/Http/Resources/WorldResidentResource.php`
- [ ] T015 Add authenticated generic world, resident-placement, and NPC-library routes with route-model binding in `routes/api.php`

**Checkpoint**: Migrations, models, factories, ownership rules, requests, resources, and API route placeholders are ready. No route or model may assume a canonical Connection Node record.

---

## Phase 3: User Story 1 - Create and Manage Worlds (Priority: P1) 🎯 MVP

**Goal**: A user can find Worlds next to Assistants and create, edit, view, and delete their own configurable world, including its environment metadata and two context prompts.

**Independent Test**: Create Connection Node, set both prompts, save it, reopen and edit it, confirm it appears as a card, and delete it without affecting a companion assistant or its normal conversations.

### Tests for User Story 1

- [ ] T016 [P] [US1] Write world CRUD, validation, ownership-isolation, environment-asset cleanup, and character-preservation feature coverage in `tests/Feature/Api/WorldControllerTest.php`
- [ ] T017 [P] [US1] Write world resource serialization coverage for context prompts, environment metadata, and resident-safe payloads in `tests/Feature/Api/WorldResourceTest.php`

### Implementation for User Story 1

- [ ] T018 [US1] Implement authorized list, create, show, update, and delete actions, including world-owned environment asset cleanup, in `app/Http/Controllers/Api/WorldController.php`
- [ ] T019 [US1] Implement world environment upload/replace validation and storage using existing file-storage conventions in `app/Http/Controllers/Api/WorldEnvironmentController.php` and `routes/api.php`
- [ ] T020 [P] [US1] Add a world API hook with loading, mutation, and error behavior in `resources/js/hooks/useWorlds.js`
- [ ] T021 [P] [US1] Create a reusable world card matching existing assistant-card styling in `resources/js/components/WorldCard.jsx`
- [ ] T022 [US1] Add the Worlds section, world cards, and create-world entry point to the existing library view in `resources/js/pages/AssistantsPage.jsx`
- [ ] T023 [US1] Create the reusable assistant-style world form with required metadata, environment, and professionally labelled assistant/NPC context prompt fields in `resources/js/components/WorldForm.jsx`
- [ ] T024 [US1] Create the create-world screen using `Header`, `Accordion`, and `WorldForm` in `resources/js/pages/CreateWorldPage.jsx`
- [ ] T025 [US1] Create the edit-world screen with save and confirmation-modal deletion behavior in `resources/js/pages/EditWorldPage.jsx`
- [ ] T026 [US1] Register generic Worlds create/edit routes and preserve existing fallback behavior in `resources/js/app.jsx`

**Checkpoint**: A user can manage worlds through a consistent UI. The app has no hardcoded Connection Node route, record, or prompt.

---

## Phase 4: User Story 2 - Enter and Explore a World (Priority: P1)

**Goal**: A user can enter a ready single-room world through a graceful transition, explore it in first person, respect collision meshes, release world input, and recover from missing assets.

**Independent Test**: Enter a configured world, use keyboard/mouse movement, collide with each supplied collision boundary, lose and regain browser focus, exit cleanly, and return to Worlds.

### Tests for User Story 2

- [ ] T027 [P] [US2] Add feature coverage for authorized runtime-world retrieval, missing environment handling, and empty resident worlds in `tests/Feature/Api/WorldControllerTest.php`

### Implementation for User Story 2

- [ ] T028 [US2] Extract the reusable VRM loading, pose, animation, and disposal layer from the portrait preview into `resources/js/components/CharacterVrm.jsx` and refactor `resources/js/components/VrmAvatar.jsx` to consume it without changing portrait behavior
- [ ] T029 [P] [US2] Implement GLB environment loading, named collision mesh extraction, scene lighting, load errors, and teardown in `resources/js/components/world/WorldEnvironment.jsx`
- [ ] T030 [P] [US2] Implement keyboard/mouse first-person movement, focus-loss stop, pointer-lock release, and collision integration in `resources/js/components/world/FirstPersonController.jsx`
- [ ] T031 [P] [US2] Implement world-only resource disposal and runtime error boundaries in `resources/js/components/world/WorldScene.jsx`
- [ ] T032 [US2] Create loading, empty, failure, pause/settings, and exit-to-Worlds states around the scene in `resources/js/pages/WorldPage.jsx`
- [ ] T033 [US2] Register the generic enter-world route in `resources/js/app.jsx`

**Checkpoint**: A ready GLB world is independently explorable on desktop and returns cleanly to the normal application shell.

---

## Phase 5: User Story 3 - Meet and Chat with Residents (Priority: P1)

**Goal**: Residents use their existing identity, history, archive, and assistant behavior, while chats initiated from an eligible world receive only that world's applicable context prompt.

**Independent Test**: Add eligible residents, approach one, press `C`, exchange messages, close chat, then hold a normal conversation with the same assistant and verify no world context leaks.

### Tests for User Story 3

- [ ] T034 [P] [US3] Write feature coverage proving companion and NPC world prompts are appended only for authorized in-world sends and never for ordinary sends in `tests/Feature/Api/WorldConversationContextTest.php`
- [ ] T035 [P] [US3] Write feature coverage rejecting foreign worlds and assistants that are not residents in `tests/Feature/Api/WorldConversationContextTest.php`
- [ ] T036 [P] [US3] Write feature coverage for eligible assistant/NPC placement and removal without deleting the underlying character record in `tests/Feature/Api/WorldResidentControllerTest.php`

### Implementation for User Story 3

- [ ] T037 [US3] Implement assistant/NPC resident add/update/remove authorization, 3D-avatar eligibility checks, placement validation, and resource responses in `app/Http/Controllers/Api/WorldResidentController.php`
- [ ] T038 [US3] Implement one-request world-context composition and resident/owner validation in `app/Actions/AppendWorldConversationContext.php`
- [ ] T039 [US3] Accept and validate optional `world_id` on conversation creation/send paths and invoke the context action before `PromptDirector` in `app/Http/Controllers/Api/ConversationController.php`
- [ ] T040 [P] [US3] Implement positioned resident VRMs and active-chat movement pause state in `resources/js/components/world/ResidentController.jsx`
- [ ] T041 [P] [US3] Implement proximity, line-of-sight, accessible `C — Chat` prompting, and input activation in `resources/js/components/world/InteractionSystem.jsx`
- [ ] T042 [US3] Adapt the existing conversation UI into an in-world overlay that passes the active `world_id` without creating a second chat/provider pipeline in `resources/js/components/world/WorldChat.jsx`
- [ ] T043 [US3] Integrate resident selection, interaction, chat panel, and exploration resume behavior in `resources/js/pages/WorldPage.jsx`

**Checkpoint**: In-world chat uses the same assistant conversation while applying only the correct world context for that request; ordinary chats stay unchanged.

---

## Phase 6: User Story 4 - Configure NPCs and Resident Behavior (Priority: P2)

**Goal**: A user can manage lightweight assistant-backed NPCs below Assistants, then add existing NPCs to worlds and configure their stationary or bounded roaming behavior there.

**Independent Test**: Create an NPC in the NPC section with model, pose, optional archive, and prompt; add it to a world; configure roam behavior; enter the world; verify bounded movement, chat pause, archive grounding, and context selection.

### Tests for User Story 4

- [ ] T044 [P] [US4] Write factory-backed NPC-library CRUD, permanent deletion, ownership, archive, VRM eligibility, and `WorldNpc` reuse coverage in `tests/Feature/Api/NpcControllerTest.php`
- [ ] T045 [P] [US4] Add roaming behavior validation and resident behavior enum coverage in `tests/Feature/Api/WorldResidentControllerTest.php`

### Implementation for User Story 4

- [ ] T046 [US4] Implement dedicated NPC-library CRUD by reusing assistant persistence, uploads, archive assignment, pose configuration, and confirmed permanent deletion in `app/Http/Controllers/Api/NpcController.php`
- [ ] T047 [P] [US4] Create NPC library pages and cards, reusing the assistant configuration subset and confirmation modal in `resources/js/pages/NpcsPage.jsx`, `resources/js/pages/CreateNpcPage.jsx`, `resources/js/pages/EditNpcPage.jsx`, and `resources/js/components/NpcCard.jsx`
- [ ] T048 [US4] Register NPC library routes and entry points below Assistants in `resources/js/app.jsx` and `resources/js/pages/AssistantsPage.jsx`
- [ ] T049 [US4] Add owned assistant/NPC resident selection and placement accordions to the world editor without duplicating NPC model or pose configuration in `resources/js/components/WorldResidentsEditor.jsx` and `resources/js/components/WorldForm.jsx`
- [ ] T050 [US4] Implement stationary and bounded-roam state, safe-area checks, and chat pause/resume behavior in `resources/js/components/world/ResidentController.jsx`
- [ ] T051 [US4] Add camera-frustum/distance-driven animation and roaming suspension, with pre-interaction readiness thresholds, in `resources/js/components/world/VisibilityPolicy.jsx`
- [ ] T052 [US4] Connect visibility policy and configured resident behavior to the scene lifecycle in `resources/js/components/world/WorldScene.jsx`
**Checkpoint**: NPCs are assistant-backed library records with no parallel conversation or avatar pipeline, and worlds only attach or detach their placements.

---

## Phase 7: Polish, Acceptance, and Quality Gates

**Purpose**: Complete cross-cutting reliability, accessibility, performance, and release checks after all desired stories are integrated.

- [ ] T053 [P] Verify all editable world prompts, empty/error states, and keyboard interaction labels are accessible and visually consistent with existing UI in `resources/js/pages/WorldPage.jsx`, `resources/js/components/WorldForm.jsx`, and `resources/js/components/world/WorldChat.jsx`
- [ ] T054 [P] Verify asset-load failure, scene teardown, and resident visibility transitions fail loudly without leaking another user's data in `resources/js/components/world/WorldScene.jsx` and `app/Http/Controllers/Api/WorldController.php`
- [ ] T055 Ask the user to execute the desktop-browser acceptance and profiling walkthrough with the selected assets in `specs/007-connection-node-world/quickstart.md`
- [ ] T056 Run focused world feature tests with `php artisan test --compact tests/Feature/Api/WorldControllerTest.php tests/Feature/Api/WorldResourceTest.php tests/Feature/Api/WorldResidentControllerTest.php tests/Feature/Api/WorldConversationContextTest.php tests/Feature/Api/NpcControllerTest.php`
- [ ] T057 Run formatting and lint quality gates with `vendor/bin/pint --dirty --format agent`, `vendor/bin/pint --test`, and `npm run lint` from the repository root

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1**: Starts immediately; T002 and T003 are user asset handoffs and do not block backend configuration work, but do block final runtime acceptance with production-like assets.
- **Phase 2**: Depends on the Phase 1 integration review (T001 and T004) and blocks every user story.
- **US1 (Phase 3)**: Depends on Phase 2; establishes the management MVP.
- **US2 (Phase 4)**: Depends on Phase 2 and a world produced by US1.
- **US3 (Phase 5)**: Depends on Phase 2 and uses the World page created in US2; its backend context tests and action can proceed alongside US2 scene work after the foundation.
- **US4 (Phase 6)**: Depends on Phase 2 and adds NPC library CRUD plus world placement integration to the editor/runtime established by US1–US3.
- **Phase 7**: Depends on the stories selected for delivery.

### User Story Completion Order

`Foundation → US1 → US2 + US3 backend work → US3 runtime integration → US4 → Polish`

US1 is the management MVP. US2 and the backend portion of US3 can progress in parallel once the data/API foundation is present; US3's final UI integration requires `WorldPage` from US2.

## Parallel Opportunities

- T006 and T007 can proceed together; T013 and T014 can proceed after the models exist.
- In US1, tests T016–T017 and frontend hook/card work T020–T021 are parallel once their contracts are stable.
- In US2, environment, controller, and scene-lifecycle modules T029–T031 can proceed in parallel after T028's shared VRM extraction is complete.
- In US3, tests T034–T036 and scene modules T040–T041 can proceed in parallel; T038 and T039 remain sequential because the controller consumes the action.
- In US4, tests T044–T045 and NPC page work T047 can proceed in parallel; runtime behavior T050–T051 can proceed in parallel after resident render state is available.

## Parallel Example: User Story 3

```text
Task: "Write scoped world-context feature coverage in tests/Feature/Api/WorldConversationContextTest.php"
Task: "Write resident placement coverage in tests/Feature/Api/WorldResidentControllerTest.php"
Task: "Implement positioned resident VRMs in resources/js/components/world/ResidentController.jsx"
Task: "Implement interaction checks in resources/js/components/world/InteractionSystem.jsx"
```

## Implementation Strategy

### MVP First

1. Complete Phase 1 and Phase 2.
2. Complete US1 and demonstrate user-created world configuration, prompts, and ownership isolation.
3. Validate US1 independently before starting runtime work.

### Incremental Delivery

1. Add US2 for a loaded, navigable room using the user's approved assets.
2. Add US3 for proximity interaction and correctly scoped in-world conversation context.
3. Add US4 for assistant-backed NPCs and resource-aware resident behavior.
4. Complete Phase 7 only after the desired story increments are integrated.

### Guardrails

- Do not change existing migrations or add dependencies without approval.
- Keep prompt selection in `World::contextPromptFor()` and composition in `AppendWorldConversationContext`; never permanently rewrite an assistant's base prompt.
- Reuse existing assistant fields, uploader controls, pose editors, archive behavior, and conversation pipeline before extracting a shared component.
- Use interfaces only at actual variable boundaries; do not create speculative abstractions for one concrete implementation.
