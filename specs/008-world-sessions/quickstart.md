# Quickstart: Validating World Sessions

Prerequisites: app running under Laravel Herd, a logged-in user with at least
one existing `World`.

## Backend validation (Pest)

```bash
php artisan test --compact --filter=WorldSessionTest
```

Expected: feature tests cover (per data-model.md and contracts/world-sessions-api.md):
- `index` returns only sessions for the given world, ordered most-recent-first.
- `store` creates a session defaulting to title `'New session'`.
- `update` renames a session (title ≤ 100 chars validated).
- `destroy` permanently removes a session.
- A user cannot list/update/delete a session belonging to another user's world (403/404).

## Manual/browser validation

1. Open a world with no sessions yet → confirm the sessions page shows the
   empty state with a "new session" action (User Story 1, Acceptance #3).
2. Start a new session → confirm it's created and the user enters the world
   within that session (User Story 2, Acceptance #1).
3. Return to the sessions page → confirm both the new and any prior sessions
   are listed, most recent first (User Story 1 & 2).
4. Select a prior session → confirm it resumes into the world (User Story 1,
   Acceptance #2).
5. Delete a session → confirm it disappears from the list and can no longer
   be resumed (User Story 3).
6. As a different user, confirm their sessions page never shows the first
   user's sessions (FR-009, FR-010).

See [contracts/world-sessions-api.md](contracts/world-sessions-api.md) for the
exact endpoints exercised by the above, and [data-model.md](data-model.md) for
the `WorldSession` fields involved.
