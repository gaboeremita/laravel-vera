# Implementation Plan: 3D Avatar Scene Backgrounds

**Branch**: `005-avatar-backgrounds` | **Date**: 2026-08-28 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-avatar-backgrounds/spec.md`

## Summary

Generate a two-image scene (a floor texture and a curved surrounding backdrop) behind a 3D VRM avatar, triggered manually (slash command or agent-mode tool request) or automatically (conversation start, mid-conversation setting change). Generation reuses the assistant's existing image-generation pipeline (`ImageGenerationService`, `PromptDirector` with archive retrieval), runs as a queued job so the conversation is never blocked, and results are held in a per-conversation cache entry (not a database record) with an automatic regenerate-on-cache-miss fallback. The frontend polls for status the same way `AgentProgressIndicator` already does, and cross-fades between backgrounds using the existing `transition-opacity` convention already used by `Portrait.jsx`.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 13) backend; JavaScript (React 19) frontend

**Primary Dependencies**: Existing `ImageGenProviders` stack (`ImageGenManager`, `ImageGenPromptEnhancer`, `ImageGenerationService`), `PromptDirector`/`PromptBuilder`, `LlmManager`; Laravel queues (`QUEUE_CONNECTION=database`) and cache (`CACHE_STORE=database`); `@react-three/fiber` + `@pixiv/three-vrm` (already used in `VrmAvatar.jsx`); Tailwind CSS transitions

**Storage**: No new database tables. Generated floor/surrounding images are written to the existing `public` filesystem disk under `avatar-backgrounds/{conversation_id}/`; the current pair's URLs + source description + timestamp are held in the app's existing cache store under `avatar-background:{conversation_id}`, with a TTL — an intentionally temporary record, per the spec's clarification that backgrounds are cached, not permanently stored

**Testing**: Pest feature tests, following the existing `ImageGenerationTool*Test.php` conventions — `Http::fake()` for LLM and image-gen provider calls, `Queue::fake()` to assert job dispatch, direct job execution to verify cache/file side effects. Existing `Assistant`/`Conversation`/`AssistantUser` factories cover setup; no new factories needed since no new Eloquent model is introduced

**Target Platform**: Existing Laravel Herd-served web app (Chrome/desktop-first browser SPA)

**Project Type**: Web application — single Laravel project with a `resources/js` React frontend (existing structure, not a separate frontend/backend split)

**Performance Goals**: A requested/automatic background is fully generated and displayed within 60s in ≥95% of cases (SC-001); the conversation must remain fully usable (send/receive messages) while generation is in progress (FR-017)

**Constraints**: No ceiling or front-facing geometry (FR-010); must render coherently under `VrmAvatar.jsx`'s existing fixed camera (FR-011); reuse the existing image-generation provider/timeout configuration rather than introducing a new provider abstraction; cached backgrounds are strictly per-conversation, never reused across conversations (clarification, 2026-08-28)

**Scale/Scope**: One active background (floor + surroundings pair) per conversation at a time; applies only to `AssistantPortraitType::Avatar3D` assistants

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style** — PASS. New PHP goes through `vendor/bin/pint --dirty`, new JS/JSX through `npm run lint`, same as every other change.
- **II. Append-Only Migrations** — PASS, trivially. This feature adds **zero** migrations: background state lives in cache + disk files, not a database table, by design (see Storage above and the spec's Assumptions). If a future feature needs to persist backgrounds permanently, that's a new migration then, not a retrofit of this one.
- **III. Comments Justify Only Non-Obvious Decisions** — PASS (applied at implementation time; nothing in this plan requires exempting code from the rule).
- **IV. Data Isolation by Ownership** — PASS, with an explicit design requirement carried into Phase 1: every cache key, file path, and job payload for a background MUST be derived from a `Conversation` resolved through the existing ownership-checked path (`$assistantUser->conversations()->findOrFail($id)`), never a bare `$id` or an assistant-level/global key. This is exactly the class of bug Principle IV was written to prevent (see constitution's issue #44 reference), so `data-model.md` and the job/controller design call it out explicitly.
- **V. Errors Fail Loudly** — PASS, with a design requirement: job failures are logged (mirroring `AgentLoopRunner`'s `Log::warning` on tool failure) and leave the existing cache entry untouched — the "keep the previous background" edge case is an explicit fallback, not a swallowed exception.
- **VI. Feature-Test-First, Factory-Backed** — PASS. Planned Pest coverage: manual command trigger, agent-mode tool trigger, automatic initial background, automatic scene-change detection via tag, cache-hit reuse (no regeneration), cache-miss auto-regeneration on reopen, generation failure fallback, and no-op for non-3D-avatar assistants. All use existing factories.
- **VII. No Speculative Abstraction** — PASS. One job, one prompt-enhancer, one tool class — mirroring the existing `ImageGenerationTool`/`ImageGenPromptEnhancer` shape rather than building a generic "scene provider" abstraction. No new provider interface; the existing `ImageGenManager` is reused as-is.
- **VIII. State Derivation Happens During Render, Not in Effects** — PASS, with a design requirement: the frontend polling component follows `AgentProgressIndicator.jsx`'s existing pattern exactly (active/inactive transitions computed in the render body, not `useEffect`).

No violations requiring justification — Complexity Tracking table is omitted.

## Project Structure

### Documentation (this feature)

```text
specs/005-avatar-backgrounds/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md         # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Contracts/
│   └── ImageGenProvider.php                  # + generateMany(array $prompts): array, for concurrent floor+surroundings generation (research.md §5)
├── Jobs/
│   └── GenerateAvatarBackground.php          # queued, non-blocking generation (FR-017)
├── Services/
│   ├── AvatarBackground/
│   │   ├── AvatarBackgroundPromptEnhancer.php  # mirrors ImageGenPromptEnhancer; shapes floor + surroundings prompts, with archive retrieval (FR-006, FR-007)
│   │   └── AvatarBackgroundService.php         # orchestrates enhance -> generateMany -> store files -> cache
│   └── ImageGenProviders/
│       ├── OpenRouterImageGenProvider.php      # + generateMany() via Http::pool()
│       └── OpenAiCompatibleImageGenProvider.php # + generateMany() via Http::pool()
├── Services/AgentLoop/Tools/
│   └── AvatarBackgroundTool.php               # agent-mode trigger (FR-003), mirrors ImageGenerationTool
└── Http/Controllers/Api/
    └── AvatarBackgroundController.php         # GET status/current background, mirrors AgentProgressController

routes/api.php                                  # + GET .../conversations/{id}/avatar-background

app/Http/Controllers/Api/ConversationController.php
  - store(): dispatch initial background generation for Avatar3D assistants (FR-004)
  - show(): dispatch cache-miss regeneration on conversation reopen (FR-012a, research.md §8)
  - sendMessage(): parse `/change-background <description>` command (FR-002); detect `[scene: ...]` tag in the assistant's reply and dispatch regeneration (FR-005)

resources/
├── images/
│   ├── avatar-background-default-floor.png       # bundled default asset (research.md §9)
│   └── avatar-background-default-surroundings.png # bundled default asset (research.md §9)
└── js/
    ├── hooks/
    │   └── useAvatarBackground.js                 # polling hook, mirrors AgentProgressIndicator's poll loop
    └── components/
        └── VrmAvatar.jsx                          # + floor mesh, curved backdrop mesh, cross-fade transition, default-asset fallback (FR-008–FR-011, FR-018)

tests/Feature/
└── AvatarBackground*Test.php                  # mirrors ImageGenerationTool*Test.php naming/structure
```

**Structure Decision**: Follows the existing single-Laravel-project layout (`app/`, `routes/`, `resources/js/`, `tests/Feature/`) — no new top-level directories. New backend code is grouped under `app/Services/AvatarBackground/` alongside the existing `app/Services/ImageGenProviders/` it depends on, and the agent tool joins the existing `app/Services/AgentLoop/Tools/` directory.

## Complexity Tracking

*No violations — table omitted.*
