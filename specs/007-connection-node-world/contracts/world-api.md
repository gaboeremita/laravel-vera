# Connection Node API Contract

All endpoints require the existing authenticated session and scope data to the current user.

| Endpoint | Purpose |
|---|---|
| `GET /api/worlds` | Available world cards. |
| `GET /api/worlds/connection-node` | World configuration, residents, and eligible assistant candidates. |
| `PUT /api/worlds/connection-node` | World settings and approved asset metadata. |
| `PUT /api/worlds/connection-node/residents` | Synchronize selected companions and placement data. |
| `PATCH /api/worlds/connection-node/residents/{resident}` | Update one placement or behavior. |
| `DELETE /api/worlds/connection-node/residents/{resident}` | Remove a companion from the world. |
| `POST /api/worlds/connection-node/npcs` | Create an NPC assistant and resident. |
| `PATCH /api/worlds/connection-node/npcs/{assistant}` | Update an owned NPC. |
| `DELETE /api/worlds/connection-node/npcs/{assistant}` | Delete an owned NPC and its assets. |

The world chat panel continues to use the current assistant conversation endpoints. Responses use `404` for unowned records, `422` for invalid assets or placement, and `409` for occupied/protected layout conflicts.
