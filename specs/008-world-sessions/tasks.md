---

description: "Task list template for feature implementation"
---

# Tasks: World Sessions

**Input**: Design documents from `/specs/008-world-sessions/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/world-sessions-api.md](contracts/world-sessions-api.md), [quickstart.md](quickstart.md)

**Tests**: Included — Constitution Principle VI (Feature-Test-First, Factory-Backed) and CLAUDE.md's Test Enforcement rule require Pest feature test coverage for every change.

**Organization**: Tasks are grouped by user story (from spec.md) to enable independent implementation and testing of each story. A Foundational phase precedes them because `WorldSession` cannot exist until `World` is restructured onto the `WorldUser` pivot, and `Conversation` needs a `world_session_id` before any session can restore position + conversations (per the 2026-08-31 clarification in spec.md).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

Existing single-repo Laravel + React web app: `app/`, `database/`, `routes/`, `resources/js/`, `tests/Feature/` (see plan.md's Project Structure).

---

## Phase 1: Setup

**Purpose**: Establish a safety net before restructuring `World`'s ownership model.

- [ ] T001 Run `php artisan test --compact --filter=WorldTest` and record the current passing baseline, so the World-restructuring tasks in Phase 2 can be checked against it

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Move `World` onto a `WorldUser` pivot (mirroring `Assistant`/`AssistantUser`), and stand up `WorldSession` (with a `position` field) and `Conversation.world_session_id`. No user story can be implemented until this phase is complete.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T002 Create migration `database/migrations/..._create_world_user_table.php`: `world_id` (FK → `worlds`, cascade), `user_id` (FK → `users`, cascade), unique on `(world_id, user_id)`, timestamps — mirrors `2026_07_11_024232_create_assistant_user_table.php`
- [ ] T003 Create migration `database/migrations/..._backfill_world_user_from_worlds_user_id.php`: for every row in `worlds`, insert a `world_user` row from its existing `user_id` (data migration, no-op `down()`, style of `2026_08_18_091207_migrate_discord_settings_json_to_relational_tables.php`) — depends on T002
- [ ] T004 Create migration `database/migrations/..._drop_user_id_from_worlds_table.php`: drop the `worlds.user_id` column and its constraint (with a `down()` that restores it) — depends on T003
- [ ] T005 [P] Create `app/Models/WorldUser.php` pivot model extending `Pivot` with `belongsTo(World)`, `belongsTo(User)`, and `hasMany(WorldSession, 'world_user_id')` — mirrors `app/Models/AssistantUser.php` — depends on T002
- [ ] T006 [P] Create `database/factories/WorldUserFactory.php` — depends on T005
- [ ] T007 Update `app/Models/World.php`: remove `user(): BelongsTo` and its `user_id` fillable reference; add `users(): BelongsToMany` using `WorldUser::class` with timestamps, and `worldUsers(): HasMany` — depends on T005
- [ ] T008 Update `app/Models/User.php`: replace `worlds(): HasMany` with `worlds(): BelongsToMany` using `WorldUser::class` with timestamps, matching `User::assistants()` — depends on T005
- [ ] T009 Update `app/Policies/WorldPolicy.php`: `view`/`update`/`delete` check `$world->users->contains($user)` instead of `$world->user_id === $user->id` — depends on T007
- [ ] T010 Update `app/Http/Controllers/Api/WorldController.php`: `store()` creates the world through `$request->user()->worlds()->create([...])` (attaching via the `WorldUser` pivot instead of setting `user_id` directly); confirm `index()`/`show()`/`update()`/`destroy()` still resolve correctly through the pivot — depends on T007, T008
- [ ] T011 [P] Update the existing World feature tests (e.g. `tests/Feature/WorldTest.php`) to assert pivot-based ownership/creation instead of a direct `user_id` column — depends on T009, T010
- [ ] T012 Run `php artisan test --compact --filter=WorldTest` and confirm it passes, matching or exceeding the T001 baseline — depends on T011
- [ ] T013 Create migration `database/migrations/..._create_world_sessions_table.php`: `world_user_id` (FK → `world_user`, cascade), `title` (string, nullable), `position` (json, nullable), timestamps — depends on T002
- [ ] T014 [P] Create `app/Models/WorldSession.php` with `belongsTo(WorldUser, 'world_user_id')`, `hasMany(Conversation, 'world_session_id')`, and `position` cast to `array` — depends on T013
- [ ] T015 [P] Create `database/factories/WorldSessionFactory.php` defaulting `title` to `'New session'` and `position` to `null` — depends on T014
- [ ] T016 Create an empty `app/Http/Controllers/Api/WorldSessionController.php` class (no actions yet — each story below adds its own method) — depends on T014
- [ ] T017 Create migration `database/migrations/..._add_world_session_id_to_conversations_table.php`: nullable `world_session_id` (FK → `world_sessions`, cascade on delete) — new migration, does not edit any existing conversations migration — depends on T013
- [ ] T018 Update `app/Models/Conversation.php`: add `world_session_id` to the fillable list and a `belongsTo(WorldSession)` relation — depends on T017
- [ ] T019 [P] Add a `forWorldSession(WorldSession $session)` state (or equivalent) to `database/factories/ConversationFactory.php` for setting `world_session_id` in tests — depends on T018

**Checkpoint**: `World` shares the same relationship shape as `Assistant`; `WorldSession` (with `position`) and session-scoped `Conversation` exist. User story implementation can now begin.

---

## Phase 3: User Story 1 - View and resume past sessions in a world (Priority: P1) 🎯 MVP

**Goal**: A user opens a world's sessions page, sees their past sessions listed by recency, and resuming one returns them to their last position with that session's conversations available.

**Independent Test**: Create a session, move in the world and talk to a resident, leave, then reopen the sessions page and resume that session — confirm the same position and conversation are restored, and that the list is ordered by recency.

### Tests for User Story 1

- [ ] T020 [P] [US1] Feature test: `GET /worlds/{world}/sessions` lists only the requester's sessions for that world, ordered by `updated_at` desc, including `position`, in `tests/Feature/WorldSessionTest.php`
- [ ] T021 [P] [US1] Feature test: `GET /worlds/{world}/sessions` returns an empty list for a world with no sessions yet, in `tests/Feature/WorldSessionTest.php`
- [ ] T022 [P] [US1] Feature test: `GET /worlds/{world}/sessions` returns 404 when the requester has no `WorldUser` row for that world, in `tests/Feature/WorldSessionTest.php`
- [ ] T023 [P] [US1] Feature test: `PUT /worlds/{world}/sessions/{session}/position` updates and returns the session's `position`, and returns 404 for a session the requester doesn't own, in `tests/Feature/WorldSessionTest.php`
- [ ] T024 [P] [US1] Feature test: `POST /assistants/{assistant}/conversations` with a `worldSessionId` creates a conversation scoped to that session (and finds it again on a repeat call with the same `worldSessionId`), in `tests/Feature/ConversationTest.php`
- [ ] T025 [P] [US1] Feature test: `GET /assistants/{assistant}/conversations` filtered by `worldSessionId` returns only that session's conversation with the assistant, not conversations from other sessions or direct chat, in `tests/Feature/ConversationTest.php`

### Implementation for User Story 1

- [ ] T026 [US1] Implement `WorldSessionController::index` in `app/Http/Controllers/Api/WorldSessionController.php`: resolve the requester's `WorldUser` for the route-bound `World` (404 if none), return its sessions' `id`/`title`/`updated_at`/`position` ordered by `updated_at` desc — depends on T020, T021, T022
- [ ] T027 [US1] Add `GET /worlds/{world}/sessions` route named `worlds.sessions.index` in `routes/api.php` — depends on T026
- [ ] T028 [US1] Implement `WorldSessionController::updatePosition` and add `PUT /worlds/{world}/sessions/{session}/position` route named `worlds.sessions.position.update` in `routes/api.php` — depends on T023
- [ ] T029 [US1] Update `ConversationController::store` in `app/Http/Controllers/Api/ConversationController.php` to accept an optional `worldSessionId` (validated to belong to the requester's `WorldUser`); when present, find-or-create the conversation scoped to `(assistant_user_id, world_session_id)` instead of the assistant's single shared conversation — depends on T024
- [ ] T030 [US1] Update `ConversationController::index` in `app/Http/Controllers/Api/ConversationController.php` to accept an optional `worldSessionId` filter, returning only that session's conversation(s) with the assistant — depends on T025
- [ ] T031 [P] [US1] Create `resources/js/hooks/useWorldSessions.js` to fetch a world's session list via `route('worlds.sessions.index', { world })`, mirroring `useWorlds.js` — depends on T027
- [ ] T032 [P] [US1] Create `resources/js/components/WorldSessionList.jsx` rendering each session's label + timestamp with an `onSelect` handler, and an empty state (placeholder "start a new session" action, wired in Phase 4) — depends on T027
- [ ] T033 [US1] Create `resources/js/pages/WorldSessionsPage.jsx` using `useWorldSessions` + `WorldSessionList`; selecting a session navigates to `/worlds/:worldId?session=:sessionId` — depends on T031, T032
- [ ] T034 [US1] Add `<Route path="/worlds/:worldId/sessions" element={<WorldSessionsPage />} />` in `resources/js/app.jsx` — depends on T033
- [ ] T035 [US1] Update `resources/js/pages/WorldsPage.jsx` (and/or `WorldCard.jsx`) so opening a world navigates to `/worlds/:worldId/sessions` instead of directly to `/worlds/:worldId` — depends on T034
- [ ] T036 [US1] Update `resources/js/pages/WorldPage.jsx` to read a `session` query parameter (via `useSearchParams`), fetch that session (including its `position`), and pass the session id + position down to `WorldScene`/`WorldChat` — depends on T034
- [ ] T037 [US1] Update `resources/js/components/world/WorldScene.jsx` (or the equivalent avatar/camera controller) to apply the resumed session's `position` on load, and to persist the current position via `worlds.sessions.position.update` periodically and on exit — depends on T028, T036
- [ ] T038 [US1] Update `resources/js/components/world/WorldChat.jsx`'s `resolveConversation` to pass the active `worldSessionId` into `conversations.store`/`conversations.index` calls instead of resolving "the assistant's one conversation" — depends on T029, T030, T036

**Checkpoint**: User Story 1 is fully functional and independently testable — a user can see, resume, and continue a past session's position and conversations.

---

## Phase 4: User Story 2 - Start a new session (Priority: P1)

**Goal**: A user starts a brand-new session for a world from the sessions page, getting a fresh position and fresh conversations with every resident, without disturbing existing sessions.

**Independent Test**: From the sessions page, trigger "new session"; confirm a session is created with no position history, talking to a resident starts a brand-new conversation (not a continuation of any prior session's), and prior sessions remain unaffected and still listed afterward.

### Tests for User Story 2

- [ ] T039 [P] [US2] Feature test: `POST /worlds/{world}/sessions` creates a session defaulting to title `'New session'` and null `position`, under the requester's `WorldUser`, in `tests/Feature/WorldSessionTest.php`
- [ ] T040 [P] [US2] Feature test: `POST /worlds/{world}/sessions` returns 404 when the requester has no `WorldUser` row for that world, in `tests/Feature/WorldSessionTest.php`

### Implementation for User Story 2

- [ ] T041 [US2] Implement `WorldSessionController::store` in `app/Http/Controllers/Api/WorldSessionController.php` — depends on T039, T040
- [ ] T042 [US2] Add `POST /worlds/{world}/sessions` route named `worlds.sessions.store` in `routes/api.php` — depends on T041
- [ ] T043 [US2] Wire a "start new session" action in `resources/js/pages/WorldSessionsPage.jsx` (toolbar button) and the empty-state button added in T032 (`resources/js/components/WorldSessionList.jsx`) that both call `worlds.sessions.store` and navigate into `/worlds/:worldId?session=:newId` (the new session naturally has no position or conversations, since T037/T038 key off the new `world_session_id`) — depends on T042

**Checkpoint**: User Stories 1 and 2 both work independently — resume-with-state and fresh-start are both functional.

---

## Phase 5: User Story 3 - Delete a session (Priority: P2)

**Goal**: A user removes a session they no longer want from the list, along with its position and conversations.

**Independent Test**: Delete a session from the list; confirm it disappears and can no longer be resumed, its conversations are gone, and other sessions remain untouched.

### Tests for User Story 3

- [ ] T044 [P] [US3] Feature test: `DELETE /worlds/{world}/sessions/{session}` permanently deletes the session and cascades its conversations (they no longer appear in a subsequent index call, and the conversation rows are gone), in `tests/Feature/WorldSessionTest.php`
- [ ] T045 [P] [US3] Feature test: `DELETE /worlds/{world}/sessions/{session}` returns 404 for a session not owned by the requester's `WorldUser`, in `tests/Feature/WorldSessionTest.php`

### Implementation for User Story 3

- [ ] T046 [US3] Implement `WorldSessionController::destroy` in `app/Http/Controllers/Api/WorldSessionController.php` — depends on T044, T045
- [ ] T047 [US3] Add `DELETE /worlds/{world}/sessions/{session}` route named `worlds.sessions.destroy` in `routes/api.php` — depends on T046
- [ ] T048 [US3] Add a delete action with confirmation to `resources/js/components/WorldSessionList.jsx`, calling `worlds.sessions.destroy`, removing the session from the list, and falling back to the empty state if it was the last one — depends on T047
- [ ] T049 [US3] Ensure deleting the currently active session from `resources/js/pages/WorldSessionsPage.jsx` (or, if deleted while active, `resources/js/pages/WorldPage.jsx`) exits back to the sessions list — depends on T048

**Checkpoint**: All three user stories are independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: API parity and final validation.

- [ ] T050 [P] Implement `WorldSessionController::update` (rename) in `app/Http/Controllers/Api/WorldSessionController.php` plus `PATCH /worlds/{world}/sessions/{session}` route named `worlds.sessions.update` in `routes/api.php`, validating `title` (string, max:100) — mirrors `ConversationController::update` for API parity (research.md); not required by any user story's UI
- [ ] T051 [P] Feature tests for `WorldSessionController::update` (rename validation, ownership check) in `tests/Feature/WorldSessionTest.php` — depends on T050
- [ ] T052 Execute the manual/browser validation steps in [quickstart.md](quickstart.md) end-to-end
- [ ] T053 Run `vendor/bin/pint --dirty --format agent`, `npm run lint`, and the full `php artisan test --compact` as the single verification pass before push/PR, per CLAUDE.md

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Phase 1 (T001 baseline) — BLOCKS all user stories.
- **User Stories (Phase 3-5)**: All depend on Foundational (Phase 2) completion.
  - US1 and US2 are both P1 and independent at the backend level, but US2's UI wiring (T043) touches files US1 creates (T032, T033), so implement US1 first in practice.
  - US3 depends only on Foundational, not on US1/US2's backend — but its UI task (T048) touches the same `WorldSessionList.jsx` file US1/US2 created, so sequence after them to avoid file conflicts.
- **Polish (Phase 6)**: Depends on all desired user stories being complete.

### Within Each User Story

- Tests MUST be written and FAIL before implementation.
- Model/migration (Foundational) before controller actions.
- Controller actions before routes.
- Routes before frontend wiring.
- Backend session-scoped conversation resolution (T029, T030) before `WorldChat.jsx` is wired to use it (T038).

### Parallel Opportunities

- T005 and T013 can both start as soon as T002 lands; T006, T014, T015 following their respective parents can run in parallel with each other.
- All tests within a single user story phase marked [P] can run in parallel (independent test cases — write them together, then run once).
- T031 and T032 (US1 hook + list component) are different files and can run in parallel.

---

## Parallel Example: User Story 1

```bash
# Launch all backend tests for User Story 1 together:
Task: "Feature test: index lists only the requester's sessions ordered by updated_at desc, including position"
Task: "Feature test: index returns an empty list for a world with no sessions"
Task: "Feature test: index returns 404 for a world the requester has no WorldUser row for"
Task: "Feature test: position endpoint updates and returns position"
Task: "Feature test: conversations.store with worldSessionId creates a session-scoped conversation"
Task: "Feature test: conversations.index filtered by worldSessionId returns only that session's conversation"

# Launch independent frontend pieces for User Story 1 together:
Task: "Create resources/js/hooks/useWorldSessions.js"
Task: "Create resources/js/components/WorldSessionList.jsx"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational (CRITICAL — restructures `World`, stands up `WorldSession` with `position`, and scopes `Conversation` to sessions).
3. Complete Phase 3: User Story 1 (view + resume, with position and conversations restored).
4. **STOP and VALIDATE**: a user can see and resume past sessions, landing back at their last position with their prior conversation intact.

### Incremental Delivery

1. Setup + Foundational → foundation ready (World/WorldUser/WorldSession/session-scoped Conversation in place).
2. Add User Story 1 → resume flow (position + conversations) works → demoable MVP.
3. Add User Story 2 → new-session flow works, with genuinely fresh state.
4. Add User Story 3 → delete flow works.
5. Polish → rename API parity + final lint/test gate per CLAUDE.md.

---

## Notes

- [P] tasks touch different files with no unmet dependencies.
- [Story] labels map tasks to spec.md's user stories for traceability.
- Per CLAUDE.md, Pint/ESLint/full `php artisan test` are run exactly once (T053), at push/PR time — not after each task.
- Every migration is a new file (Constitution Principle II) — none of `worlds`', `conversations`', or any other existing migration is edited.
