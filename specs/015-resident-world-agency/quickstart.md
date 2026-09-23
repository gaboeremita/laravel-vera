# Quickstart: Resident World Agency

## Prerequisites

- Herd serving the app, `npm run dev` running, database migrated (`php artisan migrate`).
- The penthouse environment regenerated with markers (every room as a zone, every seat and lounger as a spot) and uploaded to the Creator's Penthouse world.
- At least one resident in that world whose assistant has a 3D avatar and poses including `check-phone`, `recline`, `sit` and `drink`. Set its behavior to `autonomous` for the idle stories.

## Automated checks

```bash
php artisan test --compact --filter=WorldLayout
```

```bash
php artisan test --compact --filter=ResidentAgency
```

```bash
node --test tests/Unit/WorldNavigation.test.js tests/Unit/WorldCollision.test.js
```

Expected: all pass. They cover marker parsing and warnings, zone resolution across floors, prompt world state, action tag parsing, the decision and outcome endpoints with ownership isolation, the 8-second decision floor, and route-finding on grids with steps, drops and water. See [contracts/](contracts/) and [data-model.md](data-model.md) for what each covers.

## Manual scenarios

Run each in the penthouse unless noted.

1. **World awareness (Story 1)**: Stand on the pool terrace with the resident in the studio. Ask "where are you, and where am I?" She names the music studio and the pool terrace. Ask "is there a pool here?" She says yes, on the terrace.
2. **Talk while moving (Story 2)**: Open a conversation, walk to the kitchen while typing and sending. Movement never pauses, and keys only type while the box is focused. Turn on voice mode and speak while walking; her spoken reply sounds quieter when she's far. Walk away until warned, then past the limit; the conversation closes and the microphone stops. Walk up to a second resident with the conversation open; `C` does not start a new one.
3. **Finding residents (Story 3)**: Every resident shows a name tag through walls; the one in conversation is highlighted, with an edge-of-screen pointer when she's off-screen. Open the full map (`M`) and find each resident by name. In the connection node, climb to the upper floor; the map switches floors, and residents downstairs appear dimmed with their floor name.
4. **Going places (Story 4)**: Ask her to meet you at the bar; she walks from the studio through the doorway to the bar. Ask her to get into the pool; she uses the ledge and steps. Say "follow me" and walk around; she follows. Use the direct follow and stop controls.
5. **Using things (Story 5)**: Ask her to lie on a pool lounger; she ends up aligned and stays. Ask her to get a drink; she plays the drink pose at the bar with nothing in her hand.
6. **Self-chosen activities (Story 6)**: Close the conversation and wait. Within a minute she decides something; the conversation shows a `(reason) *action*` line and the same line floats above her. Within twenty minutes she makes at least three decisions without repeating one back-to-back. Open a conversation mid-activity; she stops and attends to you. Switch browser tabs for a minute and return; nothing happened while the tab was hidden. Leave and re-enter the session; she is where you left her.
