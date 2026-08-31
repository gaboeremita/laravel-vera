# Implementation Plan: World Sessions

**Branch**: `009-world-sessions` | **Date**: 2026-08-31 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/008-world-sessions/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Add a per-world sessions page, mirroring the existing per-assistant conversations
page: list a world's past sessions ordered by recency, resume one, start a new
one, or delete one. This requires first restructuring `World` to be shared via a
`WorldUser` pivot exactly like `Assistant`/`AssistantUser`, rather than owned
directly via `worlds.user_id` — so `World` and `Assistant` follow the same
relationship shape end to end. A new `WorldSession` model/table then belongs to
`WorldUser` the same way `Conversation` belongs to `AssistantUser`. Per the
2026-08-31 clarification, resuming a session restores the user's last
recorded position and that session's own conversations with world residents
— so `WorldSession` gains a `position` field, and `Conversation` gains a
nullable `world_session_id` so a resident's conversation is scoped to one
session rather than shared across every visit to a world (as it is today in
`WorldChat.jsx`, which reuses "the assistant's one conversation" regardless
of world or session). A `WorldSessionController` exposes the same
index/store/update/destroy shape as `ConversationController`, nested under
`worlds/{world}/sessions`, plus a lightweight position-update endpoint; a
`WorldSessionsPage.jsx` (list) plus a reusable `WorldSessionList.jsx`
component mirror `ConversationsPage.jsx` / `ConversationList.jsx`.

## Technical Context

**Language/Version**: PHP 8.4, JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13, Sanctum, Inertia-less React SPA via Ziggy routes, Pest 4

**Storage**: PostgreSQL (existing app database)

**Testing**: Pest feature tests (backend), no existing JS test suite for pages — manual/browser verification per project convention

**Target Platform**: Web (Laravel Herd local dev), existing SPA

**Project Type**: Web application (Laravel backend + React frontend, single repo)

**Performance Goals**: N/A — CRUD-scale list/detail operations, no special performance target beyond existing Conversations feature parity

**Constraints**: Must follow existing Assistants/Conversations UI and API conventions; sessions must not leak across worlds or across users

**Scale/Scope**: Two new models (`WorldUser`, `WorldSession`), one restructured
model (`World` drops direct `user_id`), one new controller
(`WorldSessionController`), updates to `WorldController`/`WorldPolicy`/`User`
to route through the pivot, four migrations (create `world_user`, backfill
data, drop `worlds.user_id`, create `world_sessions` with a `position`
column, add nullable `world_session_id` to `conversations`), updates to
`ConversationController`/`WorldChat.jsx` so a resident's conversation is
resolved per session instead of per assistant, ~3 new React
pages/components. Mirrors the existing Assistant/AssistantUser/Conversation
scope, extended for session-scoped position + conversations per the
2026-08-31 clarification.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style**: New PHP/JS files will follow existing formatting; Pint/ESLint are run once at push/PR time per CLAUDE.md, not during planning. PASS (deferred, not a plan-time gate).
- **II. Append-Only Migrations**: Feature adds new migrations for `world_user`, `world_sessions` (with `position`), and a nullable `world_session_id` column on `conversations` (added via a new migration, not by editing `2026_07_11_030329_replace_user_id_with_assistant_user_id_on_conversations_table.php` or any other existing conversations migration). PASS.
- **III. Comments Justify Only Non-Obvious Decisions**: No comments planned beyond what's non-obvious (none anticipated). PASS.
- **IV. Data Isolation by Ownership**: `WorldSession` belongs to `WorldUser`, and `WorldUser` is the same per-user-per-world pivot pattern as `AssistantUser`. All session queries MUST be scoped through the authenticated user's own `WorldUser` row (route-model-bound `World`, then resolve/authorize the requesting user's `WorldUser` for it), never a bare `WorldSession::find()` or a `World::user_id` check. `WorldPolicy::view/update/delete` MUST be updated to check pivot membership (`$world->users->contains($user)`) instead of `$world->user_id === $user->id`. A session-scoped `Conversation` (via `world_session_id`) MUST additionally be checked against the requester's own `AssistantUser` — same as today — so a session's conversations remain scoped by both the requesting user's `WorldUser` and their `AssistantUser`, never resolvable by session ID alone. This is the central design constraint for the controller — see data-model.md. PASS, with this explicit scoping rule carried into Phase 1.
- **V. Errors Fail Loudly**: Controller actions will let exceptions propagate/return proper error responses; no empty catch blocks planned. PASS.
- **VI. Feature-Test-First, Factory-Backed**: A `WorldUserFactory` and `WorldSessionFactory` will be created; Pest feature tests will cover `WorldController`'s and `WorldSessionController`'s index/store/update/destroy/position-update, cross-world/cross-user isolation, and `ConversationController::store`'s per-session conversation resolution, mirroring `AssistantController`/`ConversationController`'s test coverage. Existing `WorldTest`/`WorldPolicyTest`-style coverage (if present) that asserts direct `user_id` ownership MUST be updated to assert pivot-based ownership instead. PASS.
- **VII. No Speculative Abstraction**: Introducing `WorldUser` is not speculative here — it is the explicit, current requirement (World must match Assistant's real sharing model), not a guess about a future caller. Adding `world_session_id` to `Conversation` is likewise a direct requirement (fresh conversations per session), not a guess. No further generalization (e.g. a shared base "ownable pivot" class between `AssistantUser` and `WorldUser`, or a generic "scoped conversation" abstraction) is introduced, since nothing today needs that abstraction beyond these concrete cases. PASS.

No violations; Complexity Tracking section is not needed.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
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
├── Models/
│   ├── World.php              # modified: drop belongsTo(User), add users()/worldUsers()
│   ├── WorldUser.php          # new: pivot, mirrors AssistantUser
│   ├── WorldSession.php       # new: includes `position` field
│   └── Conversation.php       # modified: add belongsTo(WorldSession) via world_session_id
├── Policies/
│   └── WorldPolicy.php        # modified: pivot-membership checks
└── Http/Controllers/Api/
    ├── WorldController.php       # modified: create/list through pivot
    ├── WorldSessionController.php  # new: index/store/update/destroy + position update
    └── ConversationController.php  # modified: store()/index() resolve/create per world_session_id when present

database/
├── factories/
│   ├── WorldUserFactory.php
│   └── WorldSessionFactory.php
└── migrations/
    ├── ..._create_world_user_table.php
    ├── ..._backfill_world_user_from_worlds_user_id.php
    ├── ..._drop_user_id_from_worlds_table.php
    ├── ..._create_world_sessions_table.php   # includes `position`
    └── ..._add_world_session_id_to_conversations_table.php

routes/
└── api.php   # nested under worlds/{world}/sessions; conversations.store/.index gain an optional session-scoped path

resources/js/
├── pages/
│   └── WorldSessionsPage.jsx
├── components/
│   └── WorldSessionList.jsx
├── hooks/
│   └── useWorldSessions.js   # mirrors useWorlds.js
└── components/world/
    └── WorldChat.jsx   # modified: resolves/creates the conversation scoped to the active world_session_id instead of "the assistant's one conversation"

tests/Feature/
├── WorldSessionTest.php
├── WorldTest.php           # modified: assert pivot-based ownership instead of user_id
└── ConversationTest.php    # modified: cover per-session conversation scoping
```

**Structure Decision**: Existing single-repo Laravel + React web application
(`app/`, `database/`, `routes/`, `resources/js/`). This feature adds files only
to those existing directories, following the same layout the Assistants/
Conversations and Worlds features already use — no new top-level directories.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
