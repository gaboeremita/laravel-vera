# Quickstart: The Bridge — One World Made of Connected Regions

## Prerequisites

- Local app served by Herd, database migrated to the commit before this feature, with at least two existing worlds that have environments.
- Two GLB environments containing `passage` markers per [contracts/passage-marker.md](contracts/passage-marker.md), e.g. region A with `lobby-door`, region B with `lua-entrance` and `pier`.

## Automated checks

```bash
php artisan test --compact --filter='Region|Passage|WorldSession|WorldController|WorldResident|RegionsMigration'
node --test tests/Unit/PassageTrigger.test.js
vendor/bin/pint
npm run lint
```

Per CLAUDE.md, Pint, ESLint and the full test suite run once, right before pushing.

## Manual scenarios

1. **Migration** (US1): run `php artisan migrate`. The Worlds page shows The Bridge; its Regions tab lists every former world with its environment, images, music and prompts. The session list is empty. The World tab shows the spawn warning and new sessions cannot be started.
2. **Passages and spawn** (US2, US3): upload the passage GLBs to two regions. Link "Lobby Door" to "Lua Entrance" from region A; region B shows the link back. Choose "Lobby Door" as the spawn passage; the ⚠ on the World tab disappears and region A shows ★.
3. **Travel** (US2): start a session. The player stands 1 m in front of "Lobby Door". Walk away and back into it: region B loads with the player in front of "Lua Entrance". Standing still does not travel back. Walking into the unlinked "Pier" does nothing.
4. **Relinking** (US2): select "Lua Entrance" as the destination of another passage; the confirmation names "Lobby Door", and after confirming "Lobby Door" shows as unlinked.
5. **Residents** (US4): with Luna living in region B, open region A's residents: Luna is grayed out with "lives in B". Use BRING HERE and confirm; she is now listed in A with the default placement.
6. **Followers and visits** (US4): in a session, ask a resident to follow you, walk through a passage, then leave the world and enter the same session again: you and the resident are in the destination region.
7. **Music and prompts** (US5): each region plays its own track on arrival. The resident's context containing the World Prompt, the region's prompt and the region's name is covered by `WorldConversationContextTest`.
8. **Deletion** (edge cases): delete region B while a session is in it; entering that session starts at the spawn passage, and residents who had followed you into B are back in their home regions.
9. **Re-upload** (edge cases): upload a new GLB for region A without `lobby-door`; its link and the spawn are removed and the ⚠ warnings return.
