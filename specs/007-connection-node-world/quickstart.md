# Quickstart: Configurable Worlds Acceptance Walkthrough

## Prerequisites

- A normal assistant with a configured 3D avatar.
- User-selected, licensed room assets delivered as a runtime GLB with its collision mesh names, recommended spawn position, and any license/source notes.
- A second 3D avatar and pose/animation assets if creating an NPC.

## Create the First World

1. Open the Assistants area and select the Worlds section.
2. Choose **Create world**.
3. Name it **Connection Node**, provide its description, upload/select the approved room GLB, and save spawn/collision configuration.
4. Add an eligible normal assistant as a companion resident and set its placement.
5. Enter a companion assistant world-context prompt, for example: “You are in the Connection Node, a 3D space where the user can move freely. Ground your response in the room when relevant.”
6. Enter a distinct NPC world-context prompt.
7. Add an NPC through the existing assistant-style model, animation, pose, archive, and base-prompt controls; set its placement and behavior.
8. Save. Confirm the new Connection Node appears as an ordinary world card and can be reopened for editing.

## Explore and Chat

1. Select **Enter world** from the Connection Node card.
2. Confirm a graceful loading transition appears before the world is interactive.
3. Move using keyboard and mouse; verify walls/furniture collision blocks movement.
4. Approach a visible resident. Confirm the contextual chat affordance appears only in range.
5. Open chat and send a message. Confirm the reply follows the resident's base prompt, archive knowledge, and the matching world context prompt.
6. Open the same assistant's normal conversation outside Worlds. Confirm no world context is applied.
7. Move far from or face away from residents, then use profiling/logging appropriate to the implementation to verify roaming/nonessential animation is suspended or reduced.
8. Exit the world, return to the normal app shell, and re-enter to confirm clean lifecycle behavior.
