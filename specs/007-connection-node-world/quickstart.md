# Connection Node Validation Guide

## Prerequisites

- An authenticated user.
- Two owned 3D-avatar assistants with valid VRM files.
- An approved Connection Node GLB environment with collision and placement map.
- One archive for NPC knowledge validation.

## Scenarios

1. Open Assistants, enter Connection Node, and confirm the transition and loader.
2. Configure one stationary and one roaming resident; verify movement, collision, proximity prompt, chat, and return to the same position after closing chat.
3. Create an NPC with name, prompt, archive, VRM, and poses; verify it is absent from the normal Assistants list, present in the world, and uses its archive in chat.
4. Re-enter and leave the world repeatedly; walk residents outside view and return; confirm they become available normally and resources do not degrade interaction.
5. Test a missing environment or character asset and confirm a recoverable error without cross-assistant conversation access.

After implementation, run focused world feature tests, Pint, and ESLint as described in [plan.md](plan.md).
