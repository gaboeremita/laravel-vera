# Frontend Contract: Configurable Worlds

## Routes

| Route | Page | Purpose |
|---|---|---|
| `/` | `HomePage` | Sibling Assistants/Worlds/NPCs cards; the app's landing page |
| `/worlds` | `WorldsPage` | List the current user's world cards |
| `/worlds/create` | `CreateWorldPage` | Create a world with metadata, environment, context prompts, and resident placement |
| `/worlds/:worldId` | `WorldPage` | Load and explore the selected 3D world |
| `/worlds/:worldId/edit` | `EditWorldPage` | Modify its world configuration |
| `/npcs` | `NpcsPage` | List and manage assistant-backed NPC records, with inline cards |
| `/npcs/create` | `CreateNpcPage` | Renders `CreateAssistantPage` with `kind="world_npc"` |
| `/npcs/:assistantId/edit` | `EditAssistantPage` (`kind="world_npc"`) | Edit or permanently delete an NPC |

No route is reserved for Connection Node. It is ordinary user-created world content.

## World Resource Consumed by UI

```ts
type WorldResource = {
  id: number;
  name: string;
  slug: string;
  description: string;
  environmentUrl: string;
  assistantContextPrompt: string;
  npcContextPrompt: string;
  settings: {
    playerSpawn?: { x: number; y: number; z: number };
    collisionMap?: string[];
  } | null;
  residents: Array<{
    id: number;
    assistant: AssistantResource;
    position: { x: number; y: number; z: number };
    rotation: { x: number; y: number; z: number } | null;
    behavior: 'stationary' | 'roam';
    behaviorSettings: Record<string, unknown> | null;
    openingMessage: string | null;
    customPrompt: string | null;
  }>;
};
```

## Editor Composition

`CreateWorldPage` and `EditWorldPage` reuse the existing assistant editor language:

- Header and card patterns for page structure.
- Accordions for environment, resident selection/placement, and prompt configuration.
- A link or clear path to the dedicated NPC section for NPC model and pose configuration.
- Existing prompt builder/editor components for the world context prompts.
- Existing confirmation modal for deletion.

The prompts are labelled professionally:

- **Companion assistant world context**: context given to selected normal assistant residents during in-world chat.
- **NPC world context**: context given to selected NPC residents during in-world chat.

## Runtime UI States

1. **Loading transition**: world name and progress/error state while configuration and assets load.
2. **Explore**: first-person scene, minimal controls/help affordance, interaction indicator only when relevant.
3. **Resident interaction**: a nearby visible resident presents `C — Chat` (or the configured accessible equivalent).
4. **Chat**: in-world overlay/drawer keeps the canvas alive, identifies the resident, and sends `worldId` with every create/send operation.
5. **Pause/settings**: controls/help, audio and visual-quality toggles where supported, and an explicit Exit World action. World editor settings remain separate from in-world preferences.
6. **Error/empty state**: missing environment, failed asset, no residents, or unauthorized world has a recoverable route back to Worlds.

## Runtime Modules

- `WorldScene`: owns scene lifecycle and asset disposal.
- `FirstPersonController`: keyboard/mouse movement and collision integration.
- `WorldEnvironment`: GLB loading, named collision mesh extraction, lights, and scene setup.
- `ResidentController`: placement, stationary/roam behavior, animation policy, and the distance cutoff that suspends nonessential resident work (no separate `VisibilityPolicy` module).
- `InteractionSystem`: proximity/line-of-sight checks and keyboard activation.
- `WorldChat`: existing conversation UI adapted to pass active `worldId`; no separate provider/chat system.
