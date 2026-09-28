# Research: Items, Inventory and Credits

## R1. Who holds things

**Decision**: One inventory per holder per session. An inventory row names its session and its holder (the player, a resident, or an object in a region), carries the credit balance, and owns inventory items (item, quantity). Configuration mirrors it: starting inventories per world, with the same holder kinds and starting inventory items.

**Rationale**: The player, residents and objects all do the same things (hold items, hold credits, give, receive), so one shape serves all three and every transfer is "from inventory A to inventory B". Credits on the inventory row keep a balance change to one row update.

**Alternatives considered**: Separate tables per holder kind, which triples every transfer path; credits as a special item, which mixes a number that can be unlimited with item rows and complicates the history.

## R2. Unlimited amounts

**Decision**: `null` means unlimited, for item quantities and credit balances. Taking from an unlimited amount leaves it `null`. The player's inventory never holds `null`.

**Rationale**: Covers endless merchant stock and endless wells with no extra flag, and every "enough?" check reads as `quantity === null || quantity >= wanted`.

## R3. When session inventories are created

**Decision**: Starting a session copies every starting inventory of the world into the session. A holder with no inventory in a session (a session started before this feature, or a resident added to a region later) gets one copied from its starting inventory the first time it is needed.

**Rationale**: The eager copy satisfies FR-004 (later configuration changes don't touch existing sessions); the lazy copy keeps older sessions and later residents working without a backfill.

## R4. Moving things safely

**Decision**: Every change goes through one `TransferInventory` action: it locks the inventories involved, checks that the giver has enough (unlimited always has enough), moves items and credits, removes item rows that reach 0, and records a credit transaction for any credits moved, all in one database transaction. Nothing outside this action writes inventories.

**Rationale**: FR-010 and SC-002 hold only if every path goes through the same checks; one action gives one place to test them. Row locks stop a double accept or a give during a reply from spending the same credits twice.

## R5. The player's inventory is protected

**Decision**: Things leave the player's inventory only through three endpoints: the player giving, the player accepting a handover request, and the player using an activity or item whose terms consume or cost something. Resident tools can only move things out of the resident's own inventory, or create a handover request.

**Rationale**: A tool call can be mistaken or invented by the model; it can never reach the player's inventory directly.

## R6. How a resident gives and asks

**Decision**: Two world tools, added whenever the resident's model supports tools:
- `give`: items and credits from their own inventory to whoever they are talking with. Refused, with the reason returned to the model, when they don't have enough.
- `ask_for`: credits and items the resident wants from the player, with a reason. Creates a pending handover request and returns "you asked; the player will answer". Only in conversations with the player.

In conversations between residents only `give` exists. Requests for anything that is not credits or items have no tool: the resident says them and judges the answer in conversation.

**Rationale**: Matches FR-008 and FR-009. Keeping requests to what the server can actually move keeps the confirmation step meaningful.

## R7. How the character learns about a handover

**Decision**: Every handover the player makes or answers produces a short line, written by the server, such as `[<player name> hands you 50 credits and the lantern]` or `[<player name> declines to pay 30 credits for the map]`. The endpoint returns it; the client sends it as the player's next message in the conversation, so the character replies to it straight away.

**Rationale**: The world chat already sends the conversation from the client (`sendMessage`). Having the server write the line keeps it accurate to what actually moved.

## R8. Activity terms

**Decision**: Terms are stored per region, object and activity (the ids come from the environment layout): a required item and whether it is consumed, a credit cost, credits and items it gives per use, and a plain-language requirement and outcome. Anything it gives comes from the object's own inventory.

A player's use of an activity with terms goes through a new endpoint before the activity starts: it checks the item and the cost, asks the narrator when there is a plain-language requirement or outcome, applies the transfer, and returns whether the activity may start, the narration, and the changes. Activities without terms start as they do today, with no request.

For residents, `use`, `where_can_i` and `what_is_in` leave out activities whose item or credit terms the resident can't meet; `use` applies the transfer and, when the terms have a plain-language requirement, asks the narrator before the resident sets off.

**Rationale**: Terms belong to the region's configuration, not to the environment file, so they survive re-uploads and stay editable in the UI. Checking on the server before starting keeps SC-004 true regardless of the client.

## R9. Items the player takes from objects

**Decision**: An object's inventory item can be marked as takeable. Every takeable item left on the object appears as a row in the object's inspect card, and choosing it moves one to the player through the transfer action. Items that are not takeable are only given through the object's activities.

**Rationale**: A crate of potions and a vending machine both hold drinks, but only the crate should let the player help themselves.

## R10. The narrator

**Decision**: A `Narrate` action makes one LLM call with a forced `narrate` tool returning `{ "succeeded": bool, "narration": string }`. The prompt includes the world's and region's context prompts, the plain-language requirement and outcome (or the item's contents and use requirement), what the actor holds, where they are, and what the player wrote when trying, if anything. It uses the world's narrator model, chosen in the world's configuration from the user's models, and falls back to the default model in the app's configuration. With neither, the endpoint returns 422 with a message saying no narrator model is set.

**Rationale**: Objects and items have no agent of their own; one small judged call covers all the plain-language terms in FR-014c and FR-014d. The forced tool gives a yes/no the server can act on, plus the text shown to the player.

**Alternatives considered**: Letting the conversation partner judge, which fails for objects used alone; a free-text reply parsed for success, which is unreliable on small local models.

## R11. Trying things with words

**Decision**: Using an activity with a plain-language requirement, and using an item, accepts an optional short text from the player: what they do or say ("I show the signet", "I enter 4471"). Examining an item needs no text.

**Rationale**: The lockbox code and the "show proof" gate depend on what the player does, which only the player can say.

## R12. Vendors

**Decision**: A resident's starting inventory item can be marked for sale. A resident with anything for sale is a vendor. While the player talks with a vendor, the conversation panel shows their items for sale with quantities and base prices. The for-sale mark is copied into sessions with the rest of the inventory, so a vendor who sells out stops showing the item.

**Rationale**: FR-009a. No inventory of a resident is ever sent to the client except the items for sale of the vendor the player is talking with.

## R13. What the resident knows about their inventory

**Decision**: The resident's prompt gets a section listing their own items (with for-sale marks and base prices) and credits. It never includes the player's inventory.

**Rationale**: They need it to describe, haggle and decide what to give; they learn what the player has only by asking or being shown.

## R14. Residents whose model can't call tools

**Decision**: Saving a starting inventory with items or credits for a resident whose model, for the saving user, does not support tools is refused with 422 and a message naming the reason (FR-002a).

**Rationale**: Such a resident could never give or ask, so their stock would be dead weight.

## R15. Credit history

**Decision**: Credit transactions store the session, the two inventories involved (kept even if the holder is later removed, by also storing each side's display name), the amount, the reason and the time. The player's history lists every transaction involving the player's inventory, newest first.

**Rationale**: FR-016 and SC-006; the stored names keep old entries readable after a resident is removed.

## R16. Notifications

**Decision**: Every endpoint that can change the player's inventory, including sending a message in a world conversation (a resident may `give` during their reply), returns the player's inventory after the change. The world page compares it with the previous one and shows a toast for each difference ("+2 bread", "-30 credits").

**Rationale**: FR-011 with no push channel; every change the player's inventory can undergo is triggered by a request from this client.
