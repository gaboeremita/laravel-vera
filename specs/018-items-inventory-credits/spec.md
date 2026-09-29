# Feature Specification: Items, Inventory and Credits

**Feature Branch**: `142-items-inventory-credits`

**Created**: 2026-09-28

**Status**: Draft

**Input**: User description: "Items, inventory and credits for worlds. An item is defined once per world and can exist in any amount, from one to unlimited. The player and residents hold items and credits. Some things cost money, some give money. Objects in the world can hold items the player takes, and an object's activity can require an item, like a gate that only opens with a certain key. Residents decide in character whether to give what they have, but only the player can take things out of the player's own inventory: the player gives through the interface, and a resident asking for payment needs the player's confirmation."

## Clarifications

### Session 2026-09-28

- Q: Should an object's activity be able to cost credits, as well as require an item? → A: An activity can require an item, cost credits, or both, and it can give credits, items, or both.
- Q: Should residents also be able to ask the player for an item, not just credits? → A: A request can be for anything: credits, items, information, a certain response. Activities and items likewise can require or give anything, including information, described in plain language and judged by the LLM.
- Q: What happens to a request still unanswered when the conversation ends? → A: It is cancelled.
- Q: Can the player see what a resident is carrying? → A: Vendors show the player their items for sale; every other resident's inventory stays hidden, and they may tell the player what they carry, or not.
- Q: Do residents pay credits when they trade with each other or use a paid activity? → A: No. Between residents credits are only played at: they may name a price and act out paying, but only items change hands, for free, and residents are never charged an activity's cost.
- Q: How far do residents look for vendors? → A: Within their own region.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Define items and starting stock (Priority: P1)

In a world's configuration, the user defines the world's items: a name, a description, an optional image and an optional base price. The user sets what the player starts every session with (items and credits), and, on each resident, what that resident starts with. A quantity can be a number or unlimited for residents, so a merchant can have endless bread or endless credits.

**Why this priority**: Nothing else in the feature has anything to act on until items exist and someone holds them.

**Independent Test**: Define two items, give the player a starting amount of one item and some credits, give a resident an unlimited amount of the other, start a new session and confirm both inventories match the configuration.

**Acceptance Scenarios**:

1. **Given** a world, **When** the user adds an item with a name, description and image, **Then** the item is listed in the world's items and can be picked anywhere items are chosen for that world.
2. **Given** the world defines a starting inventory of 3 of an item and 100 credits for the player, **When** the player starts a new session, **Then** the player holds exactly 3 of that item and 100 credits.
3. **Given** a resident is configured with an unlimited amount of an item, **When** a session starts, **Then** that resident holds the item with an unlimited quantity.
4. **Given** sessions already exist, **When** the user changes any starting inventory, **Then** existing sessions keep their current inventories and only new sessions start with the new configuration.
5. **Given** an item is held in some sessions or configured as starting stock, **When** the user deletes the item, **Then** the user is warned how many places use it, and on confirmation it is removed from every inventory and every configuration.

---

### User Story 2 - See and give what the player holds (Priority: P1)

During play, the player sees their credits and opens their inventory to see every item they hold and how many. While talking with a character, the player can give that character any item they hold or any amount of their credits. The character sees the handover as part of the conversation and reacts to it in character.

**Why this priority**: Giving money and items is how the player bribes, pays and trades; without it credits have no use in conversation.

**Independent Test**: Start a session with credits and an item, open the inventory, talk to a resident, give them part of the credits and the item, and confirm both inventories changed and the resident's reply acknowledges the gift.

**Acceptance Scenarios**:

1. **Given** the player holds items and credits, **When** the player opens the inventory, **Then** every held item is shown with its quantity, and the credit balance is shown.
2. **Given** the player is talking with a resident and holds 100 credits, **When** the player gives them 50 credits, **Then** the player holds 50, the resident's balance grows by 50, and the resident's next reply is generated knowing they received 50 credits.
3. **Given** the player holds 1 key, **When** the player gives the key to the resident, **Then** the key leaves the player's inventory and appears in the resident's.
4. **Given** the player holds 20 credits, **When** the player tries to give 50, **Then** the interface does not allow it and nothing changes.

---

### User Story 3 - Residents give and ask for payment (Priority: P1)

A resident can decide, in character, to give the player (or another character they are talking with) items or credits from their own inventory. A resident can also ask the player for anything: credits, an item, a piece of information, a certain answer. When the request includes credits or items, the player sees it with what is asked and why, and accepts or declines it; anything else is simply said and answered in conversation, and the resident judges whether the player delivered. Nothing leaves the player's inventory unless the player gives it or accepts a request.

**Why this priority**: This is what makes trading, rewards and merchants possible while keeping the player's inventory safe from a character's mistaken or invented actions.

**Independent Test**: Configure a resident with 10 bread and ask them for bread; confirm the bread moves to the player. Then have the resident ask for payment, decline once, accept once, and confirm credits move only on acceptance.

**Acceptance Scenarios**:

1. **Given** a resident holds 10 bread, **When** the resident decides to give the player 2, **Then** the resident holds 8, the player holds 2, and the player is notified of what they received.
2. **Given** a resident holds 1 key, **When** the resident tries to give 2 keys, **Then** the handover is refused, nothing moves, and the resident is told they only have 1.
3. **Given** a resident asks the player for 30 credits "for the map", **When** the player accepts, **Then** 30 credits move from the player to the resident and the resident is told the payment was made.
4. **Given** a resident asks for 30 credits, **When** the player declines, **Then** nothing moves and the resident is told the player declined.
5. **Given** the player holds 10 credits, **When** a resident asks for 30, **Then** the player sees the request but cannot accept it, and the resident is told the player can't afford it.
6. **Given** a resident has unlimited credits, **When** they give the player 1,000 credits, **Then** the player gains 1,000 and the resident's credits stay unlimited.
7. **Given** a resident is a vendor with 3 of their items marked for sale, **When** the player talks with them, **Then** the player can see those 3 items with their base prices, and nothing else the vendor carries.
8. **Given** a resident is not a vendor, **When** the player talks with them, **Then** their inventory is never shown; the resident may describe what they carry, in character, or keep it to themselves.
9. **Given** the player agrees on a price with a vendor, **When** the vendor asks for the credits and the player accepts, **Then** the vendor hands over the item, and the price paid may differ from the base price.
10. **Given** a resident asks for 10 credits and the lantern, **When** the player accepts, **Then** both move to the resident together.
11. **Given** a resident asks the player where the harbor master lives, **When** the player answers in conversation, **Then** no confirmation is shown and the resident judges the answer in character.

---

### User Story 4 - Take items from objects (Priority: P2)

In a region's configuration, the user places items on objects of the environment, each with a quantity or unlimited (a crate with 3 potions, a well with endless water). During play, the player uses the object to take one of each item it offers, until it runs out.

**Why this priority**: Items in the world give the player a way to find things without a character involved, but trading already works without it.

**Independent Test**: Put 2 of an item on an object, start a session, take from it three times, and confirm the player ends with 2 and the object shows as empty.

**Acceptance Scenarios**:

1. **Given** an object holds 2 potions, **When** the player takes from it, **Then** the player gains 1 potion and the object holds 1.
2. **Given** the object holds no potions left, **When** the player looks at it, **Then** taking is no longer offered.
3. **Given** an object holds an unlimited amount of water, **When** the player takes from it any number of times, **Then** each time adds 1 water and the object never runs out.
4. **Given** the player emptied an object in one session, **When** the player starts a new session, **Then** the object holds its configured amount again.

---

### User Story 5 - Activities that cost and give (Priority: P2)

In a region's configuration, the user can set, for an activity of an object, an item it requires (and whether using the activity consumes it), a credit cost, or both. The user can also set credits and items the activity gives each time it is used, drawn from the object's own stock. A gate's "open" activity can require a key; a vending machine can cost 5 credits and give a drink; a job board can give 20 credits. Anyone who lacks the required item cannot use that activity, and the player cannot use it without the credits; residents are never charged the cost.

Beyond items and credits, an activity can have a requirement and an outcome written in plain language: "opens for anyone who can show they work for the Guild", "the terminal shows the last message sent from it". The LLM judges whether the player (or resident) meets the requirement from what they hold and the situation, and narrates the outcome, including any information it reveals.

**Why this priority**: Locked gates, paid services and rewards from the world are the main reason items and credits matter outside conversations.

**Independent Test**: Require a key and a credit cost for an activity that gives an item, try it without the key, then without enough credits, then with both, and confirm it works only with both, the key is kept or consumed as configured, the credits are paid and the item is received.

**Acceptance Scenarios**:

1. **Given** an activity requires a key, **When** the player without a key tries to use it, **Then** the activity does not start and the player is told which item it needs.
2. **Given** the player holds the key and the requirement keeps the item, **When** the player uses the activity, **Then** it works and the player still holds the key.
3. **Given** the requirement consumes the item and the player holds 2 coins, **When** the player uses the activity, **Then** it works and the player holds 1 coin.
4. **Given** an activity requires a key, **When** a resident without the key considers what to do, **Then** that activity is not offered to them.
4a. **Given** an activity costs 5 credits, **When** a resident with no credits uses it, **Then** it works and the resident's credits do not change.
5. **Given** an activity costs 5 credits and gives 1 drink, **When** the player with 12 credits uses it, **Then** the player holds 7 credits and 1 more drink, and the object holds 1 drink less.
6. **Given** an activity costs 5 credits, **When** the player with 3 credits tries to use it, **Then** the activity does not start and the player is told it costs 5 credits.
7. **Given** an activity gives 20 credits and its object has 30 credits, **When** the player uses it twice, **Then** the first use gives 20 and the second is not offered, because the object has only 10 left.
8. **Given** an activity's requirement reads "opens for anyone carrying proof of Guild membership", **When** the player holding a Guild signet uses it, **Then** the LLM judges the requirement met and narrates the gate opening.
9. **Given** the same activity, **When** the player holds nothing that could pass as proof, **Then** the activity does not happen and the narration says why in the world's terms.
10. **Given** an activity's outcome reads "the terminal shows the last message sent from it", **When** the player uses it, **Then** the narration tells the player what that message says.

---

### User Story 6 - Items that hold or need something (Priority: P2)

An item's definition can say, in plain language, what examining it reveals ("a letter signed only with an initial, asking to meet at the old pier") and what it takes to use it ("the lockbox opens with the four-digit code its owner chose"). The player examines or tries to use an item from the inventory, and the LLM narrates what happens, judging any requirement from what the player holds and says.

**Why this priority**: Items that carry information or need something to work are how items become part of the story rather than tokens, but trading and gated objects work without it.

**Independent Test**: Define a letter with contents and a lockbox that needs a code, examine the letter, then try the lockbox with a wrong and a right code.

**Acceptance Scenarios**:

1. **Given** the player holds a letter whose definition describes its contents, **When** the player examines it, **Then** the narration tells the player what the letter says.
2. **Given** the player holds a lockbox that opens with its owner's code, **When** the player tries a code they made up, **Then** it stays shut and the narration says so.
3. **Given** the player learned the code in conversation, **When** the player tries that code, **Then** the LLM judges it correct and narrates the lockbox opening, and any items or credits the definition says it holds move to the player.

---

### User Story 7 - Credit history (Priority: P3)

Every change to a balance is recorded with the amount, who it moved between and why (gift, payment, reward). The player can see their credit history for the session.

**Why this priority**: Useful for the player and needed later for judging how the player spent their money, but trading works without it.

**Independent Test**: Give credits, accept a handover request and receive credits, then open the history and confirm three entries with amounts, counterparts and reasons in order.

**Acceptance Scenarios**:

1. **Given** the player paid a resident 30 credits for a map, **When** the player opens the credit history, **Then** an entry shows -30, the resident's name and "for the map".
2. **Given** a session with no credit changes, **When** the player opens the history, **Then** it shows that nothing has happened yet.

---

### User Story 8 - Residents trade among themselves (Priority: P3)

Residents know which other residents of their region sell something, what they sell and where they serve. When a resident feels like something a vendor sells, like tacos from the taco stand, they go to the vendor and talk to them, and the vendor hands it over in character. Between residents nothing really costs credits: they may name a price and play along with paying, but only the items move. An activity can name a resident as its vendor; while that vendor is in the same room, other residents get what the activity offers from the vendor instead of using it themselves.

**Why this priority**: Residents going about their own errands, buying and sharing things, makes the world feel alive without touching the player's economy.

**Independent Test**: Give a vendor tacos for sale and name them the vendor of the taco stand's activity; let another resident of the region decide what to do, confirm they are told the vendor sells tacos, then have the vendor hand them tacos in conversation and confirm the tacos moved and no credits did.

**Acceptance Scenarios**:

1. **Given** a resident of the region sells tacos and serves at the taco stand, **When** another resident of that region decides what to do, **Then** they are told who sells tacos, where they serve and where they are now.
2. **Given** a vendor also carries items not for sale, **When** another resident is told what the vendor sells, **Then** only the items for sale are named.
3. **Given** two residents are talking, **When** one hands the other 2 tacos, **Then** the tacos move, and neither is offered credits to hand over nor told their credit balance.
4. **Given** an activity names a vendor, **When** the vendor is in the same room as another resident, **Then** that activity is not offered to the other resident; **When** the vendor is elsewhere, **Then** it is offered as usual.

---

### Edge Cases

- A resident's model cannot hand over items or ask for payment: the resident configuration does not allow giving that resident a starting inventory or credits, and says why.
- The player gives an item to a resident in the middle of that resident's reply: the handover is applied once and appears in the conversation before the next reply.
- Two handover requests arrive before the player answers the first: each is shown and answered separately; accepting one that is no longer affordable is refused.
- The player ends a conversation while a request from that character is still unanswered: the request is cancelled; nothing moves, and the character must ask again in a later conversation.
- A resident is removed from a region while holding items from the player: the items are lost with the resident's session state.
- An item's quantity reaches 0 in an inventory: it disappears from that inventory; unlimited quantities never reach 0.
- Credits never go below 0 for any holder.
- The same item is placed on several objects: each object keeps its own count.
- An object's activity requires an item that was deleted: the requirement is removed along with the item.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Users MUST be able to create, edit and delete items per world, each with a name, description, optional image and optional base price.
- **FR-002**: Users MUST be able to set a starting inventory of items and credits for the player per world, and for each resident.
- **FR-002a**: Users MUST NOT be able to give a starting inventory or credits to a resident whose model cannot hand over items or ask for payment; the configuration MUST say why.
- **FR-003**: Quantities and credit balances MUST be whole numbers of 0 or more; residents' and objects' quantities and credits MAY be unlimited.
- **FR-004**: Every new session MUST start with a copy of the configured starting inventories; changes to configuration MUST NOT affect existing sessions.
- **FR-005**: The player MUST be able to see their credit balance at all times during play and open a view of every held item with its quantity.
- **FR-006**: While in a conversation with a character, the player MUST be able to give that character any held item or any amount of credits up to their balance.
- **FR-007**: A handover from the player MUST be added to the conversation so the receiving character generates their next reply knowing about it.
- **FR-008**: A resident MUST be able to give items or credits from their own inventory to whoever they are talking with; a handover larger than what they hold MUST be refused and the resident told why.
- **FR-009**: A resident MUST be able to ask the player for credits, items, or both, with a stated reason; they MUST move only when the player accepts, and the resident MUST be told whether the player accepted, declined or could not provide them. Requests for anything else (information, an answer, an action) happen in conversation and are judged by the resident.
- **FR-009a**: Users MUST be able to mark items in a resident's inventory as for sale, which makes that resident a vendor; while talking with a vendor, the player MUST be able to see the items for sale, with quantities and base prices. The inventory of a resident who is not a vendor, and a vendor's items not for sale, MUST never be shown to the player.
- **FR-010**: Nothing MUST ever leave the player's inventory other than through the player giving it, the player accepting a handover request, or the player using an activity or item whose terms cost credits, consume a required item, or consume the item itself.
- **FR-011**: The player MUST be notified whenever their inventory or balance changes.
- **FR-012**: Users MUST be able to place items with a quantity or unlimited amount on objects in a region's configuration; the player MUST be able to take one of an item from an object while it has any left.
- **FR-013**: Objects' remaining amounts MUST be tracked per session.
- **FR-008a**: Between residents, handovers MUST be items only; credits MUST NOT move between residents, and a resident talking with another resident MUST NOT be told their credit balance.
- **FR-008b**: When a resident decides what to do on their own, they MUST be told which other residents of their region sell something, what they sell (never items not for sale), where they serve and where they are.
- **FR-014**: Users MUST be able to set, per object activity, a required item (kept or consumed), a credit cost, or both; the required item MUST apply to the player and to residents, and the cost MUST apply to the player only.
- **FR-014e**: Users MUST be able to name a resident as the vendor of an object activity; while the vendor is in the same room as another resident, that activity MUST NOT be offered to the other resident.
- **FR-014a**: Users MUST be able to set, per object activity, credits and items it gives on each use; what it gives MUST come from the object's stock, and the activity MUST NOT be offered once the stock cannot cover it.
- **FR-014b**: Users MUST be able to give objects a stock of credits, as a number or unlimited, tracked per session like their items.
- **FR-014c**: Users MUST be able to give an object activity a requirement and an outcome in plain language; the LLM MUST judge the requirement from what the user of the activity holds and the situation, and narrate the outcome, including information it reveals.
- **FR-014d**: Users MUST be able to give an item a plain-language description of what examining it reveals and what it takes to use it, plus credits and items it releases when used; the player MUST be able to examine and try to use held items, with the LLM judging and narrating the result.
- **FR-015**: When the player cannot use an activity for lack of an item or credits, the player MUST be told which item it needs or how much it costs; when a plain-language requirement is not met, the narration MUST say why in the world's terms.
- **FR-016**: Every balance change MUST be recorded with the amount, the holders involved and the reason; the player MUST be able to see their session's credit history.
- **FR-017**: Inventories, balances and history MUST belong to one session of one player and never be visible from another session or another user's play.
- **FR-018**: Every new screen, panel and control MUST follow the app's current theme and styling and look sleek and polished, consistent with the existing HUD and configuration screens.

### Key Entities

- **Item**: A thing defined once per world: name, description, optional image, optional base price, and optional plain-language contents, use requirement, and credits and items it releases.
- **Inventory entry**: How many of an item a holder has in one session; the holder is the player, a resident or an object; the quantity is a number or unlimited; a resident's entry can be marked for sale.
- **Credit balance**: How many credits a holder has in one session; unlimited is possible for residents and objects.
- **Starting inventory**: The items and credits the player or a resident is configured to start each session with.
- **Activity terms**: For one object activity: the item it requires and whether it is consumed, its credit cost, the credits and items it gives per use, and a plain-language requirement and outcome.
- **Credit transaction**: One recorded balance change: amount, from, to, reason, time.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can define an item and give a resident a starting stock of it in under 2 minutes.
- **SC-002**: In 100% of test runs, no item or credit leaves the player's inventory without the player giving it, accepting a handover request, or choosing to use an activity or item whose terms cost or consume it.
- **SC-003**: After any handover, both inventories show the change within 1 second, and the receiving character's next reply reflects it.
- **SC-004**: In 100% of attempts, an activity is refused to anyone lacking its required item or credits, and the player is told what is missing.
- **SC-005**: A new session always starts with inventories exactly matching the configuration, regardless of what happened in earlier sessions.
- **SC-006**: Every credit change in a session appears in the credit history, with no missing entries.

## Assumptions

- The game is an open role-playing sim in the spirit of a tabletop RPG, not focused on combat; mechanics stay open and are judged by the LLM wherever rules are not about moving credits and items.
- The currency is called "credits" in every world.
- Residents decide whether to give or ask for payment in character; this feature adds no rules about when they should.
- Residents can also hand items to other residents they are talking with, under the same limits as giving to the player; credits between residents are pretend.
- The player cannot drop items or place them back on objects.
- Items have no durability, weight, or inventory size limit.
- Base price is a guide for residents and what vendors display; it does not force what they charge.
- Information revealed by narration is part of the conversation and narration history; tracking which pieces of information the player knows, for secrets and quests, belongs to the secrets feature.
- Creator mode commands for items and credits, secrets that characters hold, and quests are separate features built on top of this one.
- Worlds, regions, residents, sessions and objects with activities already exist as built in earlier features.
