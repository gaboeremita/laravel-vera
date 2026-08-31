# Implementation Plan: Configurable Worlds

**Branch**: `007-connection-node-world` | **Spec**: [spec.md](./spec.md) | **Date**: 2026-08-30

## Summary

Add a desktop-first Worlds experience beside the existing assistant library. Users can create, configure, enter, edit, and remove single-room 3D worlds. Each world has a supplied GLB environment, selected assistant residents, optional lightweight NPC residents, and two editable context prompts: one for companion assistants and one for NPCs. The Connection Node is the first configured world, not a fixed route or singleton.

The existing React/Three/VRM stack remains the browser runtime. The Laravel application owns world configuration and authorization; the browser owns first-person movement, collision, proximity interaction, and rendering. World-specific context is appended dynamically to a resident's existing prompt only while the user chats from that world.

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 13; JavaScript with React 19

**Primary Dependencies**: Existing Inertia-style React application, Three.js, React Three Fiber, Drei, `@pixiv/three-vrm`, Tailwind CSS 4, Laravel Sanctum

**Storage**: Existing relational database and configured file disks; use existing upload/VRM/pose conventions

**Testing**: Pest feature tests for authorization, validation, resident configuration, NPC reuse, and world-context injection; focused frontend tests only where the existing suite supports them

**Target Platform**: Laptop/desktop browser; keyboard + mouse, no mobile target in this phase

**Performance Goals**: Responsive exploration in a furnished room with an initial limited resident set; non-visible or distant residents must not consume full animation/roaming work

**Constraints**: No new package without approval; no generated production environment art; reuse existing assistant, archive, provider, avatar, animation, pose, prompt, and shared UI patterns

## Architecture and Design Principles

- Keep domain concepts explicit: `World`, `WorldResident`, and `AssistantKind::WorldNpc`.
- Reuse `Assistant` for NPCs. A world NPC is an assistant with a different kind, not a parallel data or conversation model.
- Use PHP enums for persisted state and a `World::contextPromptFor(AssistantKind $kind)` method to centralize prompt selection rather than scattering conditionals.
- Use a concrete `AppendWorldConversationContext` action at the conversation boundary. It validates that the assistant is a resident of the requested world and appends only the matching world context to the existing prompt before `PromptDirector` builds the request. Do not permanently change the assistant prompt or force an interface where no alternate implementation exists.
- Bind interfaces only at genuine variable integrations (for example, existing provider or storage boundaries); retain Laravel's direct concrete injection for application actions and controllers.
- Keep runtime rendering separable from persistence: the API returns a world DTO/resource, and the Three scene receives normalized runtime configuration.
- Export the current portrait preview's pose-loading and bone-quaternion helpers (`loadPoseClip`, `captureBoneQuaternions`, `applyBoneQuaternions`) from `VrmAvatar.jsx` so world resident rendering reuses the same VRM pose behavior directly, without a separate renderer component.
- Reuse existing `Header`, `Accordion`, `ConfirmationModal`, `Toggle`, pose editors, uploader controls, prompt components, and visual language. New world forms follow the assistant editor's structure rather than introducing a separate design system.

## Proposed File-Level Design

### Backend

| Area | Change |
|---|---|
| `app/Models/World.php` | New user-owned model with name, slug, description, environment metadata, assistant context prompt, NPC context prompt, settings, relationships, and `contextPromptFor()` |
| `app/Models/WorldResident.php` | Placement and behavior for an assistant assigned to a world |
| `app/Enums/AssistantKind.php` | Add `WorldNpc`; retain normal assistant behavior unchanged |
| `app/Enums/WorldResidentBehavior.php` | `Stationary` and `Roam` values for runtime behavior |
| migrations | New `worlds` and `world_residents` tables; never alter prior migrations |
| `app/Http/Controllers/Api/WorldController.php` | Authorized Worlds list/create/show/update/delete endpoints, including world-owned environment asset cleanup and environment upload/replace storage |
| `app/Http/Controllers/Api/WorldResidentController.php` | Authorize companion resident selection, placement, and per-resident opening-message/custom-prompt overrides |
| `app/Http/Controllers/Api/NpcController.php` | Dedicated NPC CRUD that persists `AssistantKind::WorldNpc` through existing assistant features |
| Form Requests / API Resources | Validate world fields and present a stable editor/runtime payload |
| `app/Actions/AppendWorldConversationContext.php` | Dynamically append the correct world context to an eligible in-world conversation request |
| Conversation flow | Accept an optional `worldId`, authorize membership, then apply contextual prompt before calling existing `PromptDirector`; ordinary chats skip this action |

### Frontend

| Area | Change |
|---|---|
| `HomePage` | Sibling Assistants/Worlds/NPCs cards and create actions, as the app's landing page |
| `NpcsPage` / `CreateNpcPage` | List with inline cards; `CreateNpcPage` and NPC editing render `CreateAssistantPage`/`EditAssistantPage` with `kind="world_npc"` rather than dedicated NPC page components |
| `WorldsPage` | Lists user-owned world cards with edit and enter actions |
| `CreateWorldPage` / `EditWorldPage` | Assistant-editor-style form for metadata, environment asset, resident selection and placement (including staged residents before a new world is saved), and the two context prompts |
| `WorldPage` | Loading transition, first-person canvas, minimal HUD, close-range interaction affordance, in-world chat panel, pause/settings menu, and exit action |
| `WorldScene` modules | Environment, player controller, collision, resident controller (including its own distance-cutoff visibility policy), interaction system, and world-only asset disposal |
| Shared avatar modules | Export pose-loading and bone-quaternion helpers from `VrmAvatar.jsx`; preserve existing portrait-specific backdrop wrapper |
| Routes | Generic `/worlds`, `/worlds/create`, `/worlds/:worldId`, and `/worlds/:worldId/edit` routes; no fixed Connection Node route |

## Data and API Shape

See [data-model.md](./data-model.md), [world-api.md](./contracts/world-api.md), and [world-frontend.md](./contracts/world-frontend.md).

Key rules:

1. A `World` belongs to one user and has a user-unique slug only for readable routing/display; it is never identified by a hardcoded canonical key.
2. A `WorldResident` references one existing `Assistant`; the `(world_id, assistant_id)` pair is unique.
3. A resident must be an owned normal assistant or `AssistantKind::WorldNpc` with a 3D avatar. NPCs are created in their dedicated library section and worlds only attach or detach their placements.
4. `assistant_context_prompt` applies only when chatting in that world with a companion resident. `npc_context_prompt` applies only when chatting in that world with a world NPC.
5. `worldId` is request context, not a permanent conversation attribute: users may hold ordinary conversations with the same assistant outside a world, without world text bleeding into those messages.

## Runtime Flow

1. The user selects a world card; the app shows a branded loading transition while it fetches world configuration and prepares assets.
2. `WorldPage` loads the room GLB, collision meshes, and nearby resident avatars. The initial player spawn is part of world configuration.
3. The player uses keyboard and mouse to move in first person. Collision prevents passing through configured colliders.
4. When a resident is in interaction range and line of sight, the HUD shows an accessible prompt such as `C — Chat`.
5. Opening chat sends the active `worldId` with the normal conversation operation. The backend verifies the resident and dynamically adds its applicable world context prompt, plus that resident's `customPrompt` override when set, before the existing assistant/archive pipeline runs.
6. Off-screen or sufficiently distant residents suspend roaming and lower animation work. Leaving the world disposes only world-owned scene resources, then returns to the normal application shell.

## Delivery Phases

1. **Foundation**: schema, enums, models, policies, requests, resources, routes, and generic world CRUD.
2. **Editors**: Worlds listing and configuration, plus NPC library CRUD and shared resident selection/placement.
3. **Prompt boundary**: dynamic world-context action plus in-world conversation API integration and coverage proving no ordinary-chat leakage.
4. **Runtime**: shared VRM pose-helper reuse, loading transition, first-person scene, collision, interaction, chat overlay, distance-based culling, and teardown.
5. **Polish and acceptance**: settings/pause behavior, empty states, validation, accessibility, focused test suite, Pint, and user-selected asset handoff verification.

## Risk Register

| Risk | Mitigation |
|---|---|
| Large or inconsistent user assets hurt frame time | Require GLB delivery metadata/collision maps, lazy-load residents, cap active behavior, and dispose scene resources on exit |
| World text leaks into normal chats | Centralize injection in the action; require an authorized `worldId` and test both in-world and ordinary paths |
| NPC feature drift | Store NPCs as assistants with `AssistantKind::WorldNpc`; reuse existing uploader, animation, prompt, archive, and provider code through a dedicated NPC CRUD surface |
| Editor becomes visually inconsistent | Compose existing form, accordion, uploader, pose, modal, and card components; inspect sibling usage before changes |
| Scope expands into multi-room or mobile game systems | Keep one bounded room per configurable world and desktop navigation for this feature |
