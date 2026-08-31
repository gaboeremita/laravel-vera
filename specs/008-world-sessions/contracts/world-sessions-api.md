# Contract: World Sessions API

Nested under the existing `auth:sanctum` middleware group in `routes/api.php`,
following the `worlds/{world}/...` nesting already used for
`worlds.residents.*`.

Sessions are scoped through the requesting user's `WorldUser` row for
`{world}`, exactly as conversations are scoped through `AssistantUser`.

## `GET /worlds/{world}/sessions` — `worlds.sessions.index`

Lists sessions belonging to the authenticated user's `WorldUser` for
`{world}`, ordered by `updated_at desc`.

- **Auth**: the authenticated user MUST have a `WorldUser` row for `{world}`;
  otherwise 403/404 (matches existing `WorldController`/`WorldPolicy` check,
  now pivot-based).
- **Response 200**: `{ "sessions": [{ "id", "title", "updated_at" }, ...] }`
  (field set mirrors `ConversationController::index`'s `id, title, updated_at`
  selection).

## `POST /worlds/{world}/sessions` — `worlds.sessions.store`

Creates a new session under the authenticated user's `WorldUser` for
`{world}`, with the default title `'New session'`.

- **Auth**: same check as above.
- **Response 201**: `{ "session": { "id", "title", "updated_at" } }`.

## `PATCH /worlds/{world}/sessions/{session}` — `worlds.sessions.update`

Renames a session.

- **Body**: `{ "title": string, max:100 }`.
- **Auth**: `{session}` MUST belong to the authenticated user's `WorldUser`
  for `{world}`.
- **Response 200**: `{ "session": { "id", "title", "updated_at" } }`.

## `DELETE /worlds/{world}/sessions/{session}` — `worlds.sessions.destroy`

Permanently deletes a session (FR-006, FR-007).

- **Auth**: same as `update`.
- **Response 204**: no content.

## Error cases

- Requesting a session that does not belong to the authenticated user's
  `WorldUser` for `{world}` (wrong world, wrong user, or no `WorldUser` row
  at all): 404 (not exposing existence of other users'/worlds' data —
  FR-009, FR-010).
