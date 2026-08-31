# Implementation Plan: World Sessions

**Branch**: `009-world-sessions` | **Date**: 2026-08-31 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/008-world-sessions/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Add a per-world sessions page, mirroring the existing per-assistant conversations
page: list a world's past sessions ordered by recency, resume one, start a new
one, or delete one. A new `WorldSession` model/table scoped to `World` (which is
already scoped to a single owning user) backs the feature; a `WorldSessionController`
exposes the same index/store/update/destroy shape as `ConversationController`,
nested under `worlds/{world}/sessions`; a `WorldSessionsPage.jsx` (list) plus a
reusable `WorldSessionList.jsx` component mirror `ConversationsPage.jsx` /
`ConversationList.jsx`.

## Technical Context

**Language/Version**: PHP 8.4, JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13, Sanctum, Inertia-less React SPA via Ziggy routes, Pest 4

**Storage**: PostgreSQL (existing app database)

**Testing**: Pest feature tests (backend), no existing JS test suite for pages — manual/browser verification per project convention

**Target Platform**: Web (Laravel Herd local dev), existing SPA

**Project Type**: Web application (Laravel backend + React frontend, single repo)

**Performance Goals**: N/A — CRUD-scale list/detail operations, no special performance target beyond existing Conversations feature parity

**Constraints**: Must follow existing Assistants/Conversations UI and API conventions; sessions must not leak across worlds or across users

**Scale/Scope**: One new model, one new controller, one migration, ~2 new React pages/components, mirrors existing Conversations feature scope

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style**: New PHP/JS files will follow existing formatting; Pint/ESLint are run once at push/PR time per CLAUDE.md, not during planning. PASS (deferred, not a plan-time gate).
- **II. Append-Only Migrations**: Feature adds a new `create_world_sessions_table` migration; no existing migration is edited. PASS.
- **III. Comments Justify Only Non-Obvious Decisions**: No comments planned beyond what's non-obvious (none anticipated). PASS.
- **IV. Data Isolation by Ownership**: `WorldSession` belongs to `World`, and `World` already belongs to exactly one owning `User`. All session queries MUST be scoped through `$request->user()`'s own worlds (e.g., route-model-bound `World` checked against the authenticated user, same as `WorldController` does today), never a bare `WorldSession::find()`. This is the central design constraint for the controller — see data-model.md. PASS, with this explicit scoping rule carried into Phase 1.
- **V. Errors Fail Loudly**: Controller actions will let exceptions propagate/return proper error responses; no empty catch blocks planned. PASS.
- **VI. Feature-Test-First, Factory-Backed**: A `WorldSessionFactory` will be created; Pest feature tests will cover index/store/update/destroy and cross-world/cross-user isolation, mirroring `ConversationController`'s test coverage. PASS.
- **VII. No Speculative Abstraction**: No pivot table (e.g. `WorldUser`) is introduced — `World` already has a direct `user_id`, so `WorldSession` only needs `world_id`. No generalized "session" abstraction shared with `Conversation` is introduced since spec does not require one. PASS.

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
│   └── WorldSession.php
└── Http/Controllers/Api/
    └── WorldSessionController.php

database/
├── factories/
│   └── WorldSessionFactory.php
└── migrations/
    └── ..._create_world_sessions_table.php

routes/
└── api.php   # nested under worlds/{world}/sessions

resources/js/
├── pages/
│   └── WorldSessionsPage.jsx
├── components/
│   └── WorldSessionList.jsx
└── hooks/
    └── useWorldSessions.js   # if list-fetching hook is warranted, mirroring useWorlds.js

tests/Feature/
└── WorldSessionTest.php
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
