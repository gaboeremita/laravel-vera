# Quickstart: Creator Menu

## Prerequisites

- A creator password set in Settings.
- A world with at least two linked regions, nested zones, a resident with a home spot, a blocking object with a passable effect, a paid activity, a quest with two beats, a fact, and an object with a starting inventory.

## Automated checks

```bash
php artisan test --compact --filter=Creator
```

```bash
node --test tests/Unit/ZoneLanding.test.js tests/Unit/PlayerMotion.test.js
```

## Manual scenarios

1. **Unlock** (US1): Press T in a new session; a wrong password keeps it locked; the right one opens it and pauses the world. Reload and press T: no prompt. Type T in the chat box: the menu stays closed.
2. **Go to** (US2): Jump to a nested zone in the same region and check the location readout names it. Have a resident follow you, then jump to a zone in another region; both arrive. Jump to a passage and to the spawn.
3. **Flying and speed** (US2): Fly through a wall, hold Space and Q to rise and descend, turn flying off and land. Turn on faster walking and compare.
4. **Residents** (US3): Summon a resident from another region and talk to them. Send them to a zone in another region, go there, and find them inside it. Freeze one, wait a minute, and check they haven't moved; reload, still frozen; unfreeze. Send one home. Read a resident's thought and recent activities.
5. **Sentiments** (US4): Set trust to 5 and check that a trust ≥ 5 beat finishes.
6. **Quests** (US5): Start a quest, finish beat 2, undo beat 1 (beat 2 undoes too), fail it, reopen it. The quest log shows each change as CREATOR.
7. **Credits and items** (US6): Add 100 credits; take 1000 (balance 0). Grab 3 of an item. Open a chest in another region and take an item. Give a resident an item from nothing.
8. **World state** (US7): Unlock a blocking door and walk through; lock it. Reveal a fact and forget it. Use the paid activity from far away: effects apply, no credits taken. Turn on the zone overlay.

See [contracts/api.md](contracts/api.md) for request and response shapes and [data-model.md](data-model.md) for stored state.
