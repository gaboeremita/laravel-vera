# Research: Configurable Worlds

## Decision 1: Browser-native renderer, not Unity WebGL

**Decision**: Reuse the existing Three.js, React Three Fiber, Drei, and VRM dependencies for the interactive world.

**Rationale**: The application already renders VRM assistants in React. A browser-native scene shares asset loading, model behavior, authentication, deployment, UI composition, and chat state. Unity WebGL would add a second runtime, bridge layer, build pipeline, and duplicate avatar/chat integration for a single-room proof of concept.

**Alternatives considered**:

- Unity WebGL: strong authoring tooling, but disproportionate integration and payload cost for the current scope.
- Babylon.js: capable, but would duplicate an installed renderer stack without a problem it uniquely solves.

## Decision 2: GLB runtime assets; FBX accepted as source material

**Decision**: Standardize delivered runtime environments and avatars on GLB. Accept FBX source models or animations only when they are converted and validated before runtime use.

**Rationale**: GLB packages geometry, materials, and scene data in one web-friendly asset. It matches Three.js loading and reduces runtime conversion ambiguity. User-selected marketplace assets remain valid acquisition sources.

## Decision 3: Worlds are user-created configuration, not a fixed feature surface

**Decision**: Create a general `World` domain and editor. Connection Node is the first user-configured world.

**Rationale**: The user wants to add spaces as they add assistants. A generalized model allows more rooms later without a migration from a hardcoded endpoint, routing key, or singleton record.

## Decision 4: Reuse Assistant for NPCs

**Decision**: Represent NPCs with `AssistantKind::WorldNpc`, manage them through a dedicated NPC library section, and use `WorldResident` only for world placement and behavior.

**Rationale**: NPCs require the same model uploads, VRM configuration, animations, poses, prompts, archive access, provider behavior, and chat pipeline. A second NPC model would duplicate these capabilities and drift. Separating NPC CRUD from world membership allows one configured NPC to be deliberately reused across worlds and makes deletion semantics unambiguous.

## Decision 5: Dynamic, scoped world prompt injection

**Decision**: Store `assistant_context_prompt` and `npc_context_prompt` on `World`. At an authorized in-world chat boundary, dynamically append the matching prompt to the resident's existing prompt before the existing `PromptDirector` runs.

**Rationale**: The assistant remains knowledgeable through the existing archive path while its world situation is supplied only when relevant. Storing the text in the assistant base prompt would contaminate conversations held elsewhere and make the same assistant harder to reuse across worlds.

## Decision 6: Reuse, then extract shared avatar rendering

**Decision**: Extract a low-level `CharacterVrm` renderer from the existing `VrmAvatar` preview; preserve portrait-only framing/backdrop in the existing wrapper.

**Rationale**: This prevents divergent VRM loading, pose application, animation control, and disposal while retaining the editor's current presentation.

## Decision 7: Collision uses authored collision meshes

**Decision**: User-provided environment deliveries include named collision meshes or an explicit collision map. The runtime hides collision meshes and uses them only for movement blocking.

**Rationale**: Mesh-based collision is more predictable than guessing from visual geometry and lets the asset provider tune walkable space.

## Decision 8: Visibility and distance govern resident work

**Decision**: Visible and nearby residents update at full rate. Distant or off-camera residents suspend roaming and reduce or pause nonessential animation/mixer updates. World exit disposes all world-owned geometry, textures, mixers, and listeners.

**Rationale**: This gives the highest payoff for a contained scene with several VRM characters and avoids retaining heavy state after returning to the application.
