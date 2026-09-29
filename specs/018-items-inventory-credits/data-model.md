# Data Model: Items, Inventory and Credits

All quantities and credits are unsigned integers. `null` means unlimited (research R2).

## New tables

### `items`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_id` | FK `worlds`, cascade | |
| `name` | string | required, unique per world |
| `description` | text | required |
| `base_price` | uint, nullable | shown by vendors; a guide, never enforced |
| `contents` | text, nullable | what examining it reveals, in plain language |
| `use_requirement` | text, nullable | what it takes to use it, in plain language |
| `consumed_on_use` | bool, default false | a successful use removes one from the player |
| `releases_credits` | uint, default 0 | credits a successful use gives the player |
| `releases_items` | json, default `[]` | `[{ "itemId": int, "quantity": uint }]` given on a successful use |

Image: the existing `images` morph, role `card`.

### `starting_inventories`

What a holder starts every session with.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_id` | FK `worlds`, cascade | |
| `holder` | enum `player`, `resident`, `object` | |
| `world_resident_id` | FK `world_residents`, nullable, cascade | set when `holder = resident` |
| `region_id` | FK `regions`, nullable, cascade | set when `holder = object` |
| `object_id` | string, nullable | layout object id, set when `holder = object` |
| `credits` | uint, nullable | `null` = unlimited; never `null` for the player; always `null` for a resident, whose credits are never counted (FR-003a) |

At most one row per world for the player, per resident, and per region object; enforced by the action that saves them.

### `starting_inventory_items`

| Column | Type | Notes |
|---|---|---|
| `starting_inventory_id` | FK, cascade | |
| `item_id` | FK `items`, cascade | |
| `quantity` | uint, nullable | `null` = unlimited; never `null` for the player |
| `for_sale` | bool, default false | residents only; makes the resident a vendor |
| `takeable` | bool, default false | objects only; the player can take one at a time |

Unique `(starting_inventory_id, item_id)`.

### `inventories` and `inventory_items`

The same columns as `starting_inventories` and `starting_inventory_items`, with `world_session_id` (FK `world_sessions`, cascade) in place of `world_id`. Unique `(inventory_id, item_id)`. An item row is deleted when its quantity reaches 0.

### `activity_terms`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `region_id` | FK `regions`, cascade | |
| `object_id` | string | layout object id |
| `activity_id` | string | activity id offered by that object's spots |
| `required_item_id` | FK `items`, nullable, null on delete | |
| `consumes_required` | bool, default false | |
| `cost` | uint, default 0 | credits paid to the object |
| `gives_credits` | uint, default 0 | from the object's inventory |
| `gives_items` | json, default `[]` | `[{ "itemId": int, "quantity": uint }]`, from the object's inventory |
| `requirement` | text, nullable | plain language, judged by the narrator |
| `outcome` | text, nullable | plain language, narrated on success |

Unique `(region_id, object_id, activity_id)`.

### `handover_requests`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_session_id` | FK, cascade | |
| `conversation_id` | FK `conversations`, cascade | the conversation it was asked in |
| `inventory_id` | FK `inventories`, cascade | the asking resident's inventory |
| `credits` | uint, default 0 | |
| `items` | json, default `[]` | `[{ "itemId": int, "quantity": uint }]` |
| `reason` | string | |
| `status` | enum `pending`, `accepted`, `declined`, `unaffordable`, `cancelled` | |
| `answered_at` | timestamp, nullable | |

### `credit_transactions`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_session_id` | FK, cascade | |
| `from_inventory_id` | FK `inventories`, nullable, null on delete | |
| `to_inventory_id` | FK `inventories`, nullable, null on delete | |
| `from_name` | string | display name at the time |
| `to_name` | string | display name at the time |
| `amount` | uint | always positive |
| `reason` | string | e.g. "gift", "for the map", "Vending machine: buy a drink" |
| `created_at` | timestamp | |

## Changed tables

- `worlds.narrator_model_id`: FK `ai_models`, nullable, null on delete. The model the narrator uses in this world (research R10).

## Models and enums

- `Item`, `StartingInventory`, `StartingInventoryItem`, `Inventory`, `InventoryItem`, `ActivityTerms`, `HandoverRequest`, `CreditTransaction`, each with a factory.
- `InventoryHolder` enum: `Player`, `Resident`, `Object`.
- `HandoverRequestStatus` enum: `Pending`, `Accepted`, `Declined`, `Unaffordable`, `Cancelled`.
- `World` gains `items()`, `startingInventories()`, `narratorModel()`. `WorldSession` gains `inventories()`, `creditTransactions()`. `Region` gains `activityTerms()`.

## Validation rules

- Quantities and credits are 0 or more. `null` (unlimited) is refused for the player's starting inventory.
- `for_sale` only on resident rows, `takeable` only on object rows.
- An object's `object_id` must exist in the region's layout; an `activity_terms` `activity_id` must be offered by one of that object's spots.
- Items referenced in `releases_items`, `gives_items`, `required_item_id` and handover requests must belong to the same world.
- A resident's starting inventory and session inventory always have `null` credits; credits sent for a resident's starting inventory are ignored (FR-003a).
- A resident's starting inventory with any items requires their model to support tools (FR-002a).
- `narrator_model_id` must be one of the requesting user's models.

## Lifecycle

- **Session start**: every `starting_inventories` row of the world is copied, with its items, into `inventories` for the new session (research R3).
- **First use in an older session**: a holder with no inventory row gets one copied from its starting inventory, or an empty one if it has none.
- **Transfers**: only through `TransferInventory` (research R4). A `credit_transactions` row is written for any credits moved.
- **Handover request**: created `pending` by the `ask_for` tool; answered once by the player as `accepted`, `declined` or `unaffordable`, or `cancelled` when the player ends the conversation first. Answering a request that is no longer pending returns 409. Resuming a session cancels any request still pending.
- **Item deleted**: its rows cascade out of every inventory and starting inventory; terms that required it lose the requirement; it is removed from every `gives_items` and `releases_items` list by the delete action.
- **Environment re-uploaded**: the existing layout reconciliation also deletes starting inventories and activity terms of objects or activities that no longer exist in the layout.
