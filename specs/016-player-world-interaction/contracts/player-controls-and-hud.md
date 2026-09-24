# Contract: Player Controls and World Interface

## Keys

All keys are ignored while the message box or any text field has focus.

| Key | Where | Action |
|-----|-------|--------|
| W A S D | exploring | Move (existing). |
| W A S D, Space | on a spot | Get up. |
| W A S D | standing activity in progress | Cancel it. |
| Shift (hold) | moving | Run; swim faster in the water. Stands up from a crouch. |
| Q | exploring, not swimming or on a spot | Toggle crouching. |
| E | an object in focus, or its card open | Open its card; press again to close. |
| G | inside a zone | Open the zone's card; press again to close. |
| Esc | a card is open | Close it. |
| ↑ / ↓ | a card is open | Move the highlight through its activities, wrapping at the ends. |
| Enter | a card is open | Start the highlighted activity. |
| C, F, X, M, V | unchanged | Chat, follow, stop, map, voice. |

## Interface elements

| Element | Position | Appears | Leaves | Content |
|---------|----------|---------|--------|---------|
| Zone title card | upper centre, below the conversation warning | on a zone crossing (not within 2 s of leaving that zone) and when the world becomes ready | after 3 s, or when replaced | Zone name; context line with parent zone and, in multi-floor worlds, floor. |
| Location readout | bottom right, above the minimap | while exploring a world with zones | never | `ZONE · FLOOR`, or the world's name outside zones. Breathing glow. |
| Object dots | in the world, at objects within 6 m | on entering 6 m | on leaving | Faint breathing dot. |
| Spot beacons | in the world, at each spot of the focused object | on focus | on losing focus | Ring per spot (accent = free, dimmed warning = taken), light column at the object. |
| Focus prompt | in the world, above the object | on focus | on losing focus | Object name and `E — INSPECT`. |
| Object card | right side, just left of the minimap column, clear of the conversation panel (left) | E | E, Esc, leaving its object's reach, or starting an activity; looking at another object does not close it | Name; zone chain; description; activity list: name, posture glyph, availability (`FREE`, `2 OF 8 FREE`, `TAKEN · VERA`), with the highlighted row glowing; hint `↑ ↓ — CHOOSE · ENTER — START`. Objects without activities say `NOTHING TO DO HERE`. |
| Zone card | right side, same slot | G | G, Esc, leaving the zone, or starting an activity | Name; parent zone; floor; description; zone activity list, chosen the same way, with no availability (zone activities have no spots). |
| Posture hint | bottom centre | on settling on a spot, or crouching | on getting up, or standing | `SPACE — GET UP`, or `Q — STAND UP` while crouched. |
| Progress ring | screen centre | on starting a standing or zone activity | when filled or cancelled | Filling ring with glowing head; activity name beneath. |
| Action line | bottom centre, above the posture hint | when an activity is started (resting), completed (standing) or left | after 4 s | The line, italic, e.g. *sits down at the bar counter*. |
| Notice | bottom centre | when no spot is free for the chosen activity | after 3 s | e.g. `NO FREE SEAT — ALL 8 ARE TAKEN`. |
| Swim overlay | screen edges | while swimming | on leaving the water | Caustic shimmer and tint. |

Only one card is open at a time; opening one replaces the other. The title card and notices never cover the conversation panel, a card or the full map; while the full map is open, the title card waits and shows the latest crossing when the map closes.

## Motion

Every element enters and leaves with an animation (wipe, slide or fade), and persistent elements carry a slow ongoing effect (glow pulse, scan-line drift, beacon rotation). Under `prefers-reduced-motion: reduce`, entrances and exits become 150 ms fades, and ongoing effects, the swimming bob and the running field-of-view change stop. The view still glides into postures and crouches, since a jump cut there disorients more than it helps.

## Theming

Colours, fonts and borders come only from the active theme's tokens (`--accent`, `--bg-*`, `--fg-*`, `--border-*`, `--warning`, `--font-display`); nothing is hard-coded per theme.
