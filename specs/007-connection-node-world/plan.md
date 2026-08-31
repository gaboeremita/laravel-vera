# Implementation Plan: Connection Node World

**Branch**: `007-connection-node-world` | **Date**: 2026-08-30 | **Spec**: [spec.md](spec.md)

## Summary

Add a desktop-first, single-room world launched from the Assistants area. It has first-person exploration, collision, configurable assistant residents, world NPCs, proximity-based chat, and a world editor. NPCs are a constrained kind of the existing `Assistant`, retaining its prompts, archives, model resolution, conversations, VRM assets, poses, and voice behavior. One shared React Three Fiber canvas reuses the existing VRM and animation behavior after it is separated from the portrait-only backdrop.

## Technical Context

**Language/Version**: PHP 8.4, Laravel 13, JavaScript ES2023, React 19

**Primary Dependencies**: React Router 7, Tailwind CSS 4, Framer Motion, Three.js 0.185, React Three Fiber 9, `@pixiv/three-vrm` 3.5, `@pixiv/three-vrm-animation` 3.5

**Storage**: PostgreSQL for world and resident configuration; public disk for character and approved room assets

**Testing**: Pest 4 feature tests with factories; ESLint and Pint after implementation

**Target Platform**: Authenticated laptop and desktop browsers with WebGL, keyboard, and mouse

**Performance Goals**: Usable world within 5 seconds on the reference laptop; stable rendering through 15 minutes with up to 10 residents; no nonessential roaming or pose work for residents outside view and interaction range

**Constraints**: One room; no mobile, generated background scene, or open-world traversal; all configuration scoped to the authenticated user; user supplies approved assets

## Constitution Check

| Principle | Status | Plan response |
|---|---|---|
| Lint and tests | Pass | Run final Pint, ESLint, and focused Pest tests. |
| Append-only migrations | Pass | Add migrations only. |
| Data isolation | Pass | Scope every world, resident, NPC, and assistant lookup through user ownership. |
| Feature tests | Pass | Use factories for world, resident, and NPC APIs. |
| No speculative abstraction | Pass | Reuse assistant, VRM, pose, prompt, archive, and conversation systems. |
| Render-state discipline | Pass | Perform synchronous state alignment in render and asynchronous scene loads in local effects. |

## Research Decisions

See [research.md](research.md) for the browser runtime, GLB asset pipeline, assistant-kind NPC model, placement layer, visibility-aware work, and interface-boundary decisions.

## Project Structure

```text
app/
├── Actions/Worlds/
│   ├── CreateWorldNpc.php
│   ├── SyncWorldResidents.php
│   └── UpdateWorldNpc.php
├── Enums/
│   ├── AssistantKind.php
│   └── WorldResidentBehavior.php
├── Http/Controllers/Api/
│   ├── WorldController.php
│   ├── WorldNpcController.php
│   └── WorldResidentController.php
├── Http/Requests/Worlds/
└── Models/
    ├── Assistant.php
    ├── World.php
    └── WorldResident.php

resources/js/
├── components/
│   ├── CharacterVrm.jsx
│   ├── WorldChatPanel.jsx
│   ├── WorldLoader.jsx
│   ├── WorldNpcEditor.jsx
│   └── WorldResidentEditor.jsx
├── hooks/
│   ├── useWorld.js
│   ├── useWorldChat.js
│   └── useWorldControls.js
├── pages/
│   ├── WorldPage.jsx
│   └── EditWorldPage.jsx
└── scenes/
    ├── ConnectionNodeScene.jsx
    ├── WorldCharacter.jsx
    ├── WorldCollisionMap.js
    └── WorldResidentController.js
```

**Structure Decision**: Existing Laravel and React directories remain in place. `CharacterVrm` is extracted because portrait and world scenes both need the same VRM, expression, and pose behavior. World code stays separate from existing chat and assistant-editor pages.

## Implementation Phases

### Phase A — Domain and ownership

1. Add `AssistantKind` (`Companion`, `WorldNpc`) and `WorldResidentBehavior` (`Stationary`, `Roam`).
2. Add `World` and `WorldResident` models, migrations, factories, and user-scoped relations.
3. Create a per-user Connection Node idempotently; do not hardcode a database row.
4. Add actions and form requests. `CreateWorldNpc` stores an NPC as an assistant with `WorldNpc` kind, structured single-prompt data, standard ownership, and optional archive.
5. Preserve `Assistant` as the single source for VRM uploads, poses, archives, conversations, voice, and provider resolution.

### Phase B — APIs and management UI

1. Add endpoints for world summary/configuration, resident synchronization, placement, and NPC lifecycle.
2. Return only eligible 3D-avatar companion assistants; reject cross-user or invalid placement data.
3. Reuse current VRM and pose upload flows for NPC assistants.
4. Exclude `WorldNpc` records from the normal Assistants index but preserve standard conversations and archive retrieval through world APIs.
5. Add a Worlds section and Connection Node card to `AssistantsPage`.
6. Build `EditWorldPage` with existing `Header`, `Accordion`, `ConfirmationModal`, `Toggle`, theme tokens, and compact editor patterns.
7. Extract a reusable VRM file control from the assistant editor; use it in assistant and NPC flows.

### Phase C — Shared world runtime

1. Extract portrait-independent VRM loading, expressions, default poses, triggered poses, and cleanup into `CharacterVrm`.
2. Keep `VrmAvatar` as the portrait wrapper with its existing generated backdrop.
3. Create `ConnectionNodeScene` with one canvas, GLB environment, player camera, collision volumes, resident actors, proximity zones, and HUD.
4. Implement keyboard/mouse controls, cursor release, focus-loss stop, and chat-open movement lock.
5. Implement deterministic waypoint roaming within resident-specific safe bounds; selected residents stop roaming during chat.
6. Add `WorldChatPanel` on the existing conversation/message/voice pipeline.

### Phase D — Performance, assets, and resilience

1. Load the approved room as GLB; use FBX only during asset preparation.
2. Add a graceful loader, readiness/error surface, and disposal on world exit.
3. Pause nonessential animation and roaming outside view and interaction range; restore before visibility or interaction.
4. Cap device pixel ratio and use conservative lighting and shadows.
5. Validate asset license, GLB validity, texture dimensions, scale/orientation, collision map, spawn, stations, and roaming areas.

### Phase E — Verification

1. Add factory-backed feature tests for ownership, eligibility, NPC creation, archive linking, placement, and conversation access.
2. Run Pint, ESLint, focused Pest tests, and the [quickstart](quickstart.md) scenarios after implementation.

## User-Owned Asset Work

1. Source a polished futuristic sci-fi room and props with portfolio-compatible licenses.
2. Supply the source files and license/source records.
3. Approve the converted GLB, room scale, texture quality, and visual direction.
4. Supply or approve a layout map for solid geometry, spawn, resident stations, and roaming areas.

## Complexity Tracking

No constitution violation is required. World entities represent real configuration, while NPCs reuse the assistant system rather than duplicating AI, assets, animation, or conversations.
