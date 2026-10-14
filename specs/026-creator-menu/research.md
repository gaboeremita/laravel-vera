# Research: Creator Menu

## R1. Unlocking

**Decision**: `POST /worlds/{world}/sessions/{session}/creator/unlock` checks the password with `Hash::check` against `users.creator_password`, as `ConversationController::activateCreatorMode` does, and sets a new `world_sessions.creator_mode_at`. Every other creator endpoint resolves the session through a new `ResolvesCreatorSession` trait, beside `ResolvesWorldUser`, which returns 403 while `creator_mode_at` is null. The session payload of `WorldSessionController::index` carries `creatorUnlocked`.

**Rationale**: Same password, same hashing and same "unlocked from this moment" timestamp as conversations, kept on the session so the two never affect each other (FR-004). A trait mirrors how every session controller already resolves its session.

**Alternatives considered**: A route middleware; the controllers already resolve the session themselves, so a middleware would load it twice.

## R2. Shared creator logic

**Decision**: The bodies of `StartQuestTool`, `EndQuestTool`, `ResetQuestTool`, `SetBeatTool`, `SetQuestFlagTool` and `SetFactKnownTool` move into actions under `app/Actions/Creator/` (`StartQuestAsCreator`, `EndQuestAsCreator`, `ResetQuestAsCreator`, `SetQuestBeat`, `SetQuestFlag`, `SetFactKnown`). Each takes the session and models and throws `RuntimeException` with the same messages. The tools keep their names, parameters and lookups by title, and call the actions. The menu's controllers call the same actions and turn a `RuntimeException` into a 422.

**Rationale**: The menu is the second caller of each piece of logic (constitution VII), and both paths must log `by_creator` identically (FR-022).

**Credits and items** need no extraction: `TransferInventory` is already an action, called with `byCreator: true` and the reasons the tools use ("granted by the creator", "removed by the creator").

## R3. Going to a zone

**Decision**:
- **Same region**: the page computes the landing point with a new pure helper `zoneLanding.js`: among the navigation grid's walkable nodes whose position lies inside the zone's outline and height range, the one closest to the outline's centroid. The player is placed there, and the position is saved through the existing `PUT .../position`, which already announces zones entered (FR-010).
- **Another region**: `POST .../creator/travel` with `regionId` and `zoneId`, `passageId` or `spawn`. A new `TravelAsCreator` action mirrors `TravelThroughPassage`: it updates the session's region and position (the zone's centroid at its `minY`, or the passage arrival), moves the following residents beside that point, dispatches `PlayerEnteredRegion` and runs `AnnounceZonesEntered`. The page loads the region as `travel` does. For a zone, once the region's navigation grid is ready, it lands the player with `zoneLanding.js` and saves the position.
- **No walkable node inside the zone**: the page refuses the jump with "This zone has nowhere to stand". When this is found after a jump to another region, the player stays at the centroid and gets the same message.

**Rationale**: Walkability is known only to the page's navigation grid, so the page picks the exact point. The server owns region changes and followers, as passage travel does.

## R4. Moving residents

**Decision**:
- **Summon**: `POST .../creator/residents/{resident}/summon` with the position and facing the page computed (the grid's standing point nearest a spot one step to the player's right). It writes the `WorldSessionResident` row in the player's region, standing, with no spot or activity.
- **Send to a zone**: `POST .../creator/residents/{resident}/send` with `regionId` and `zoneId`. In the player's region, the page passes the `zoneLanding.js` point. In another region, the server writes the zone's centroid, and the existing stranded check in `ResidentController` moves the resident to the nearest standing point when the region is next loaded.
- **Home**: `DELETE .../creator/residents/{resident}/state` deletes the row, which returns the resident to where they start a session: their own region, at their home spot when they have one (`residentHome.js`).

A resident moved while in a conversation with another resident has that conversation paused through the existing pause path. Moving the resident the player is chatting with closes the chat.

**Rationale**: Writing the existing row keeps every reader of resident location (`ResolveResidentRegion`, the page's resident filter, prompts) working unchanged. Zone access is never consulted on these paths (FR-015).

## R5. Freezing

**Decision**: A new `world_sessions.frozen_resident_ids` JSON list. `PUT .../creator/residents/{resident}/frozen` adds or removes the id. The session payload carries it. `useResidentAgency` skips frozen residents the way it skips busy ones, and `ResidentDecisionController` answers 409 for a frozen resident, so a stale page can't move them. Conversations with them are unaffected.

**Rationale**: Freezing survives sending a resident home, which deletes their state row.

**Alternatives considered**: A column on `world_session_residents`; it would be lost whenever the row is deleted.

## R6. Thoughts and plans

**Decision**: For a resident in the player's region, the menu shows the live thought line the page already keeps for thought bubbles, which includes the current step of a plan. For every resident it shows their last five `ResidentActivity` records (verb, target, reason, outcome), read by the creator overview endpoint.

## R7. Sentiments

**Decision**: `PUT .../creator/residents/{resident}/sentiments` with `{name: value}`, whole numbers from -10 to 10. It calls `Sentiment::inSession(...)->adjust([name => value - current])`, which saves, clamps and dispatches `ResidentSentimentsChanged`, so beats react as in conversation (FR-019).

## R8. Quests, facts, credits, items

**Decision**:
- **Quests**: `POST .../creator/quests/{quest}/start|end|reset|beat|flag`, each calling the R2 action.
- **Facts**: `PUT .../creator/facts/{fact}` with `known`, calling `SetFactKnown`.
- **Credits**: `POST .../creator/credits` with a signed `amount`. A negative amount is limited to the balance, so the balance stops at 0.
- **Items**:
  - `POST .../creator/inventories/grant` gives `quantity` of `itemId` from nothing to a holder.
  - `POST .../creator/inventories/move` moves `quantity` of `itemId` between two holders.
  - A holder is `{type: player|resident|object, residentId?, regionId?, objectId?}`, resolved with `ResolveInventory`. `forObject` creates an object's inventory when it has none.
  - `GET .../creator/inventories?holder=…` lists every item of a holder, takeable or not.
  - Items come from `Item::availableIn($session)`.

## R9. Objects

**Decision**:
- **Unlock and lock**: `PUT .../creator/objects/passable` with `regionId`, `objectId` and `passable` sets `passable` on the `WorldSessionObject` state, which the page already reads for blockers.
- **Use an activity**: `POST .../creator/activity-uses` with `regionId`, `objectId`, `activityId` and the index of one of its responses (default 0). A new `UseActivityAsCreator` builds an `ActivityUse`, applies that response's effects except `takeCredits` and `takeItems`, skips conditions and the narrator, and dispatches `PlayerUsedActivity`. It returns the same shape as `ActivityUseController::store`, so the page applies inventory, facts and object state with its existing code. An activity without responses returns `allowed: true` with nothing to apply.

**Rationale**: Costs are the take effects, and terms are the response conditions; everything else the response does still happens (FR-029).

## R10. Movement toggles and the zone overlay

**Decision**:
- **Flying**: `FirstPersonController` gains a `flying` mode through `playerCommands`. It skips gravity, collisions and blockers; holding Space rises and holding Q descends, and Q toggles crouching only while flying is off. Turning it off makes the player airborne, so they fall to the ground below.
- **Faster walking**: a `speedMultiplier` of 4 on `movementSpeed`.
- **Zone overlay**: a new `ZoneOverlay.jsx` in the scene draws each zone's outline as a line loop at its `minY` plus 5 cm, with its name in a label at the centroid.
- All three are page state and reset on reload.

## R11. Menu and hotkey

**Decision**:
- T toggles the menu (`KeyT`) through the page's capture listener, ignored while an input, textarea or contenteditable has focus.
- The menu is `hud/CreatorMenu.jsx` with five tabs, Go to, Residents, Quests, Items and World, each its own component. The page counts the open menu as paused, as it does for inventory.
- Data comes from one `GET .../creator` overview: regions with zones, passages, objects and activities; residents with region, zone, frozen state, sentiments and recent activities; quests with their latest run; facts with known state; items. It is refetched after each change and on quest broadcasts.
