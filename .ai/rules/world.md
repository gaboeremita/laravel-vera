---
paths:
  - 'app/Models/World*.php'
  - 'app/Models/Quest*.php'
  - 'app/Actions/Quests/**'
  - 'app/Actions/Activities/**'
  - 'app/Actions/Creator/**'
  - 'app/Services/AgentLoop/Tools/World/**'
  - 'app/Http/Controllers/Api/World*.php'
  - 'app/Http/Controllers/Api/Creator*.php'
  - 'resources/js/components/world/**'
---

# World

## Per-session state
Gameplay state (object states, opened passages, quest progress) belongs to the world session (`WorldSession*` models), never to the world.

## Behaviour through activity responses
Object and world behaviour is configured by the creator as activity responses (conditions plus effects). Express a new behaviour as an effect or condition there, with no table, flag or toggle of its own.

## Residents hold no credits
Only the player has a counted credit balance. Residents give and receive credits without accounting, with no credits field and no balance in prompts (`InventoryHolder::countsCredits()` is false for them).
