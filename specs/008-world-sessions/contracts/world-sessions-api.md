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
`{world}`, with the default title `'New session'` and a null `position`.

- **Auth**: same check as above.
- **Response 201**: `{ "session": { "id", "title", "updated_at", "position" } }`.

## `PUT /worlds/{world}/sessions/{session}/position` — `worlds.sessions.position.update`

Updates the session's last recorded position (FR-011). Called by the world
view as the user's position changes meaningfully (e.g. periodically or on
exit) — not part of the sessions-page UI itself.

- **Body**: `{ "position": <JSON value, shape owned by the world view> }`.
- **Auth**: `{session}` MUST belong to the authenticated user's `WorldUser`
  for `{world}`.
- **Response 200**: `{ "session": { "id", "position", "updated_at" } }`.

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

## Change to existing contract: `POST /assistants/{assistant}/conversations`

`ConversationController::store`'s existing `worldId` body parameter is joined
by a new optional `worldSessionId` parameter. When present, the created (or
resolved, if one already exists for this resident within that session)
conversation is scoped to that `world_session_id` instead of being the
assistant's single shared conversation (FR-012). `WorldChat.jsx` passes the
active session's id here once a session is selected/started. Existing calls
without `worldSessionId` are unaffected — direct assistant chat keeps
behaving exactly as it does today.
