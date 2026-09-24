# Quickstart: Player World Interaction

## Prerequisites

- Herd serving the app, `npm run dev` running.
- The Creator's Penthouse (22 zones, 41 objects), the Renaissance Fair and The Index (3 floors), each with at least one resident; one penthouse resident on a tool-capable model.
- A world session open for each.

## Automated checks

```bash
php artisan test --compact --filter='ResidentWorldStatePromptTest|ResidentDecisionTest|ResidentWorldToolsTest|ResidentObservationTest'
```

Covers `userState` validation and its prompt text, `occupiedSpots` reaching her tools in chat, and the observation endpoint (storage, no model call, isolation) ([contracts/world-requests.md](contracts/world-requests.md)).

```bash
node --test tests/Unit/WorldLocation.test.js tests/Unit/PlayerMotion.test.js tests/Unit/ObjectFocus.test.js tests/Unit/ActivityLines.test.js tests/Unit/PlayerPostures.test.js tests/Unit/Onlookers.test.js
```

Covers zone lookup (parity with the server fixture, nesting, floors, crossing debounce), movement mode (run, crouch, swim thresholds), focus selection, action line wording, posture view placement, and onlooker selection (distance, floor, facing).

## Manual walkthrough (penthouse)

1. **Arrive**: enter the session. The title card announces the starting zone; the readout above the minimap matches.
2. **Crossings**: walk foyer → living room → sunken lounge → pool terrace. Each crossing shows one card; the sunken lounge card says *Living room*. Step back and forth across the lounge steps: no flicker.
3. **Run and crouch**: hold Shift down the living room. Speed roughly doubles, the view widens slightly, and walls still stop you. Press Q: the view drops and you creep at half speed with `Q — STAND UP` shown. Press Q again to stand; crouch again and hold Shift to spring into a run.
4. **Swim**: take the pool steps into the deep end. The view rises to the surface, the edges shimmer, a splash plays, movement slows. Swim to the steps and walk out.
5. **Discover**: approach the bar. A dot breathes at the counter from a distance; within reach, rings appear on all eight stools with the counter's name and `E — INSPECT`. Look toward the back bar: focus moves to it.
6. **Inspect**: press E at the Rhodes. The card shows its description, *Music studio*, and *Play the Rhodes · FREE* highlighted. At the kitchen island, press ↓ and ↑: the glowing highlight slides between *Sit down*, *Eat at the island* and *Cook on the stove*, wrapping at the ends. Walk away: the card closes.
7. **Sit**: at the bar, press E, then Enter on *Sit down*. The view glides onto a stool at seated height, facing the counter, with `SPACE — GET UP`. Look around: the range stops before the view turns fully backward.
8. **Recline and lie down**: repeat at a pool lounger and on the bed. Get up with W each time and end up standing beside the spot.
9. **Standing activity**: at the back counter, highlight *Make coffee* and press Enter. The view turns to the counter, the ring fills, and *makes coffee at the back counter* appears. Start it again and press W halfway: it cancels with no line.
10. **Taken spots**: while a resident reclines on a lounger, open the loungers' card. Her lounger shows `TAKEN · <NAME>`. Recline on another lounger, then ask a resident (in chat) to use yours: she is told it is taken.
11. **Conversation**: open a chat with a resident standing with her back to you. She turns to face you; circle her and she keeps turning. Sit at the bar: `*sits down at the bar counter*` appears in the chat and she replies. Ask "what am I doing?": she says you are sitting at the bar. Ask her to go to the kitchen: she walks facing forward, then turns back to you on arrival. A resident seated on a stool keeps facing the counter.
12. **Onlookers**: with a second resident in plain view across the room and a third in another room, sit down with no chat open. Nobody replies. Open a chat with the second: she knows you sat down. Open a chat with the third: she does not.
13. **Resume**: while seated, exit the world and resume the session. You stand beside the stool and it is free.

## Other worlds

- **The Index**: climb to the second floor. The card and readout include the floor's name; objects downstairs never gain focus.
- **Faire**: run the Lane end to end and compare with walking (SC-002).
- **A world without zones** (the Connection Node): no title card, no readout, no beacons; running and swimming still work.

## Themes and motion

- Switch through default, terminal, grimoire and slate: every element takes the theme's colours and stays legible over bright and dark scenes (SC-009).
- Turn on the system's reduced motion setting: elements fade in and out, and the pulses, scan lines, bob and caustics stop.
