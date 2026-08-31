# Research: Connection Node World

## Browser runtime

**Decision**: Build the world in the existing React Three Fiber runtime.

**Rationale**: VERA already has Three.js, VRM, and pose animation support. This keeps authentication, assistant data, chat, archive retrieval, voice, and theme behavior in one application.

**Alternative considered**: Unity WebGL would add a second browser application and integration boundary for every existing service.

## Environment delivery

**Decision**: Ship the approved room as GLB. FBX is accepted as an authoring or animation source format.

**Rationale**: Three.js recommends GLB/glTF for runtime delivery. It is compact and carries meshes, materials, textures, skins, and animation predictably.

**Evidence**: [Three.js model workflow](https://threejs.org/manual/en/loading-3d-models.html), [GLTFLoader](https://threejs.org/docs/pages/GLTFLoader.html).

## NPC model

**Decision**: Add `AssistantKind` and represent an NPC as an `Assistant` with `WorldNpc` kind.

**Rationale**: `Assistant` already owns prompts, archives, VRM files, poses, conversations, provider selection, and user ownership. The NPC editor can expose a smaller configuration surface without creating a second AI or conversation stack.

## World-specific data

**Decision**: Keep resident selection, placement, interaction radius, and roaming configuration in `World` and `WorldResident` records.

**Rationale**: These settings belong to a room. They should not be hardcoded or attached permanently to an assistant.

## Movement and collision

**Decision**: Use authored collision volumes and waypoint roaming in v1.

**Rationale**: One bounded room has known geometry. This is testable, tunable, and avoids a general physics dependency until the product requires dynamic obstacles or complex navigation.

## Resource controls

**Decision**: Reduce pose, idle, and roaming updates for residents outside both the visible view and interaction range. Dispose world-only resources on exit.

**Rationale**: Multiple skinned VRM characters and large textures compete for browser resources. Unseen work does not improve the experience.

**Evidence**: [Three.js cleanup](https://threejs.org/manual/en/cleanup.html), [texture memory](https://threejs.org/manual/en/textures.html).

## Prompt and archive configuration

**Decision**: NPC editing offers one prompt field and archive selection, then stores them through the current structured prompt and retrieval path.

**Rationale**: `PromptDirector` and `PromptBuilder` already provide prompt composition and archive context safely. Hardcoded NPC prompts would bypass user configuration.

## Interfaces and abstractions

**Decision**: Use existing provider contracts and add actions/form requests for world operations. Do not add a fake NPC participant interface when `Assistant` is intentionally the only conversation participant.

**Rationale**: Laravel interfaces should represent an actual swappable boundary. The reusable boundary here is the character renderer and the documented API contracts.

**Evidence**: [Laravel container bindings](https://laravel.com/docs/13.x/container#binding-interfaces-to-implementations), [Eloquent enum casting](https://laravel.com/docs/13.x/eloquent-mutators#enum-casting).
