# Feature Specification: Items, Inventory and Credits

**Feature Branch**: `142-items-inventory-credits`

**Created**: 2026-09-28

**Status**: Draft

**Input**: User description: "Items, inventory and credits for worlds. An item is defined once per world and can exist in any amount, from one to unlimited. The player and residents hold items and credits. Some things cost money, some give money. Objects in the world can hold items the player takes, and an object's activity can require an item, like a gate that only opens with a certain key. Residents decide in character whether to give what they have, but only the player can take things out of the player's own inventory: the player gives through the interface, and a resident asking for payment needs the player's confirmation."

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

A resident can decide, in character, to give the player (or another character they are talking with) items or credits from their own inventory. A resident can also ask the player to pay a specific amount for a stated reason; the player sees the request with the amount and reason and accepts or declines it. Nothing leaves the player's inventory unless the player gives it or accepts a request.

**Why this priority**: This is what makes trading, rewards and merchants possible while keeping the player's inventory safe from a character's mistaken or invented actions.

**Independent Test**: Configure a resident with 10 bread and ask them for bread; confirm the bread moves to the player. Then have the resident ask for payment, decline once, accept once, and confirm credits move only on acceptance.

**Acceptance Scenarios**:

1. **Given** a resident holds 10 bread, **When** the resident decides to give the player 2, **Then** the resident holds 8, the player holds 2, and the player is notified of what they received.
2. **Given** a resident holds 1 key, **When** the resident tries to give 2 keys, **Then** the handover is refused, nothing moves, and the resident is told they only have 1.
3. **Given** a resident asks the player for 30 credits "for the map", **When** the player accepts, **Then** 30 credits move from the player to the resident and the resident is told the payment was made.
4. **Given** a resident asks for 30 credits, **When** the player declines, **Then** nothing moves and the resident is told the player declined.
5. **Given** the player holds 10 credits, **When** a resident asks for 30, **Then** the player sees the request but cannot accept it, and the resident is told the player can't afford it.
6. **Given** a resident has unlimited credits, **When** they give the player 1,000 credits, **Then** the player gains 1,000 and the resident's credits stay unlimited.

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

### User Story 5 - Activities that require an item (Priority: P2)

In a region's configuration, the user can require an item for an activity of an object, and choose whether using the activity consumes the item. A gate's "open" activity can require a key; a vending machine's activity can require a coin and consume it. Anyone without the required item, player or resident, cannot use that activity.

**Why this priority**: Locked gates and item-gated interactions are the main reason items matter outside conversations.

**Independent Test**: Require a key for an object's activity, try it without the key, receive the key, try again, and confirm it works only with the key and the key is kept or consumed as configured.

**Acceptance Scenarios**:

1. **Given** an activity requires a key, **When** the player without a key tries to use it, **Then** the activity does not start and the player is told which item it needs.
2. **Given** the player holds the key and the requirement keeps the item, **When** the player uses the activity, **Then** it works and the player still holds the key.
3. **Given** the requirement consumes the item and the player holds 2 coins, **When** the player uses the activity, **Then** it works and the player holds 1 coin.
4. **Given** an activity requires a key, **When** a resident without the key considers what to do, **Then** that activity is not offered to them.

---

### User Story 6 - Credit history (Priority: P3)

Every change to a balance is recorded with the amount, who it moved between and why (gift, payment, reward). The player can see their credit history for the session.

**Why this priority**: Useful for the player and needed later for judging how the player spent their money, but trading works without it.

**Independent Test**: Give credits, accept a payment request and receive credits, then open the history and confirm three entries with amounts, counterparts and reasons in order.

**Acceptance Scenarios**:

1. **Given** the player paid a resident 30 credits for a map, **When** the player opens the credit history, **Then** an entry shows -30, the resident's name and "for the map".
2. **Given** a session with no credit changes, **When** the player opens the history, **Then** it shows that nothing has happened yet.

### Edge Cases

- A resident's model cannot hand over items or ask for payment: the resident configuration does not allow giving that resident a starting inventory or credits, and says why.
- The player gives an item to a resident in the middle of that resident's reply: the handover is applied once and appears in the conversation before the next reply.
- Two payment requests arrive before the player answers the first: each is shown and answered separately; accepting one that is no longer affordable is refused.
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
- **FR-003**: Quantities and credit balances MUST be whole numbers of 0 or more; residents' and objects' quantities and residents' credits MAY be unlimited.
- **FR-004**: Every new session MUST start with a copy of the configured starting inventories; changes to configuration MUST NOT affect existing sessions.
- **FR-005**: The player MUST be able to see their credit balance at all times during play and open a view of every held item with its quantity.
- **FR-006**: While in a conversation with a character, the player MUST be able to give that character any held item or any amount of credits up to their balance.
- **FR-007**: A handover from the player MUST be added to the conversation so the receiving character generates their next reply knowing about it.
- **FR-008**: A resident MUST be able to give items or credits from their own inventory to whoever they are talking with; a handover larger than what they hold MUST be refused and the resident told why.
- **FR-009**: A resident MUST be able to ask the player for a specific amount of credits with a stated reason; credits MUST move only when the player accepts, and the resident MUST be told whether the player accepted, declined or could not afford it.
- **FR-010**: Nothing MUST ever leave the player's inventory other than through the player giving it, the player accepting a payment request, or an activity consuming a required item.
- **FR-011**: The player MUST be notified whenever their inventory or balance changes.
- **FR-012**: Users MUST be able to place items with a quantity or unlimited amount on objects in a region's configuration; the player MUST be able to take one of an item from an object while it has any left.
- **FR-013**: Objects' remaining amounts MUST be tracked per session.
- **FR-014**: Users MUST be able to require an item for an object's activity and choose whether using it consumes the item; the requirement MUST apply to the player and to residents.
- **FR-015**: When the player cannot use an activity for lack of an item, the player MUST be told which item it needs.
- **FR-016**: Every balance change MUST be recorded with the amount, the holders involved and the reason; the player MUST be able to see their session's credit history.
- **FR-017**: Inventories, balances and history MUST belong to one session of one player and never be visible from another session or another user's play.

### Key Entities

- **Item**: A thing defined once per world: name, description, optional image, optional base price.
- **Inventory entry**: How many of an item a holder has in one session; the holder is the player, a resident or an object; the quantity is a number or unlimited.
- **Credit balance**: How many credits a holder has in one session; unlimited is possible for residents.
- **Starting inventory**: The items and credits the player or a resident is configured to start each session with.
- **Item requirement**: An item an object's activity needs, and whether using the activity consumes it.
- **Credit transaction**: One recorded balance change: amount, from, to, reason, time.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can define an item and give a resident a starting stock of it in under 2 minutes.
- **SC-002**: In 100% of test runs, no item or credit leaves the player's inventory without the player giving it, accepting a request, or consuming it through a configured requirement.
- **SC-003**: After any handover, both inventories show the change within 1 second, and the receiving character's next reply reflects it.
- **SC-004**: In 100% of attempts, an activity requiring an item is refused to anyone without it, and the player is told which item it needs.
- **SC-005**: A new session always starts with inventories exactly matching the configuration, regardless of what happened in earlier sessions.
- **SC-006**: Every credit change in a session appears in the credit history, with no missing entries.

## Assumptions

- The currency is called "credits" in every world.
- Residents decide whether to give or ask for payment in character; this feature adds no rules about when they should.
- Residents can also hand items and credits to other residents they are talking with, under the same limits as giving to the player.
- The player cannot drop items or place them back on objects.
- Items have no durability, weight, or inventory size limit.
- Base price is informational for residents; it does not force what they charge.
- Creator mode commands for items and credits, secrets that characters hold, and quests are separate features built on top of this one.
- Worlds, regions, residents, sessions and objects with activities already exist as built in earlier features.
