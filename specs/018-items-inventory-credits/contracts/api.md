# Contract: HTTP API

All routes are under `/api`, behind the existing `auth:sanctum` group. A world is reachable only through `$request->user()->worlds()`; a region, resident, session, item or request outside that world returns 404. Keys are camelCase.

Shapes used below:
- `ItemEntry`: `{ itemId, name, quantity }`, `quantity` `null` when unlimited.
- `PlayerInventory`: `{ credits, items: ItemEntry[] }`.
- `Changes`: `{ credits: int, items: [{ itemId, name, delta }] }`, signed, from the player's side.

## Configuration

### Items

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | /worlds/{world}/items | worlds.items.index | `[{ id, name, description, basePrice, contents, useRequirement, consumedOnUse, releasesCredits, releasesItems, cardImageUrl, usage }]`; `usage` counts starting inventories, session inventories and terms that use it |
| POST | /worlds/{world}/items | worlds.items.store | item fields; 422 on a duplicate name or an item of another world in `releasesItems` |
| PATCH | /worlds/{world}/items/{item} | worlds.items.update | item fields |
| DELETE | /worlds/{world}/items/{item} | worlds.items.destroy | removes it everywhere (data-model Lifecycle); 204 |
| POST/DELETE | /worlds/{world}/items/{item}/card-image | worlds.items.image.card.* | same as world card images |

### Starting inventories

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | /worlds/{world}/starting-inventories | worlds.starting-inventories.index | `{ player, residents: { [residentId]: … }, objects: { [regionId]: { [objectId]: … } } }`, each `{ credits, items: [{ itemId, quantity, forSale?, takeable? }] }` |
| PUT | /worlds/{world}/starting-inventories/player | worlds.starting-inventories.player.update | `{ credits, items }`; 422 on `null` credits or quantities |
| PUT | /worlds/{world}/starting-inventories/residents/{resident} | worlds.starting-inventories.residents.update | `{ items: [{ itemId, quantity, forSale }] }`, returning `credits: null`; 422 when the resident's model can't call tools and items is non-empty (FR-002a) |
| PUT | /worlds/{world}/regions/{region}/objects/{object}/starting-inventory | worlds.regions.objects.starting-inventory.update | `{ credits, items: [{ itemId, quantity, takeable }] }`; 422 when the object is not in the layout |

### Activity terms

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | /worlds/{world}/regions/{region}/activity-terms | worlds.regions.activity-terms.index | `[{ objectId, activityId, requiredItemId, consumesRequired, cost, givesCredits, givesItems, requirement, outcome }]` |
| PUT | /worlds/{world}/regions/{region}/objects/{object}/activities/{activity}/terms | worlds.regions.activity-terms.update | the terms; 422 when the activity is not offered by that object |
| DELETE | same | worlds.regions.activity-terms.destroy | 204 |

`GET /worlds/{world}/regions/{region}` also returns `activityTerms[]` (each with `requiredItemName`), so the world page knows which activities need a request.

### World

`PATCH /worlds/{world}` accepts `narratorModelId` (one of the user's models, or `null`).

## Play

All under `/worlds/{world}/sessions/{session}`. Every response that can change the player's inventory includes `inventory: PlayerInventory` and `changes: Changes`.

| Method | Path | Name | Body | Result |
|---|---|---|---|---|
| GET | /inventory | worlds.sessions.inventory.show | | `PlayerInventory` |
| GET | /credit-history | worlds.sessions.credit-history.index | | `[{ amount, direction: "in"\|"out", counterpart, reason, createdAt }]`, newest first |
| GET | /residents/{resident}/goods | worlds.sessions.residents.goods.index | | `[{ itemId, name, description, quantity, basePrice, cardImageUrl }]` for items for sale; `[]` for a resident who is not a vendor |
| POST | /handovers | worlds.sessions.handovers.store | `{ residentId, credits, items: [{ itemId, quantity }] }` | `{ line, inventory, changes }`; 422 when the player doesn't have enough |
| POST | /handover-requests/{request}/answer | worlds.sessions.handover-requests.answer | `{ accept: bool }` | `{ status, line, inventory, changes }`; `unaffordable` when accepting fails the balance check; 409 when no longer pending |
| POST | /conversations/{conversation}/handover-requests/cancel | worlds.sessions.conversations.handover-requests.cancel | | `204`; cancels every pending request of that conversation |
| GET | /objects/{object}?regionId= | worlds.sessions.objects.show | | `{ takeable: ItemEntry[] }`, what the player can take from the object now |
| POST | /objects/{object}/take | worlds.sessions.objects.take | `{ regionId, itemId }` | `{ inventory, changes }`; 422 when none is left or the item is not takeable |
| POST | /activity-uses | worlds.sessions.activity-uses.store | `{ regionId, objectId, activityId, attempt? }` | `{ allowed, reason?, narration?, inventory, changes }` |
| GET | /items/{item}/examine | worlds.sessions.items.examine | | `{ narration }`; 422 when the player doesn't hold it |
| POST | /items/{item}/use | worlds.sessions.items.use | `{ attempt? }` | `{ succeeded, narration, inventory, changes }` |

- `line` is the text the client sends as the player's next conversation message (research R7).
- `reason` on a refused activity names the missing item or the cost when the refusal is about items or credits; for a plain-language requirement, `narration` explains it.

### Conversation responses

`POST /assistants/{assistant}/conversations/{id}/messages` in a world conversation additionally returns:
- `inventory` and `changes` when the resident gave the player something during the reply.
- `handoverRequest: { id, credits, items: ItemEntry[], reason, affordable }` when the resident asked for something.

## World tools (LLM)

| Tool | Available | Arguments | Result to the model |
|---|---|---|---|
| `give` | conversations with the player and with residents, when the model supports tools | `{ credits?, items?: [{ item, quantity }] }`, `item` by name; `credits` only with the player, any amount | what was handed over, or why not ("you only have 1 key") |
| `ask_for` | conversations with the player | `{ credits?, items?: [{ item, quantity }], reason }` | "You asked; they will answer." |
| `use` (changed) | as today | as today | leaves out activities whose item or credit terms the resident can't meet; applies the terms, or explains a refusal from the narrator |
| `narrate` (narrator only) | the narrator call | `{ succeeded: bool, narration: string }` | |
