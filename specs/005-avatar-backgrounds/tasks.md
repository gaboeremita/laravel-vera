---

description: "Task list for 3D Avatar Scene Backgrounds"
---

# Tasks: 3D Avatar Scene Backgrounds

**Input**: Design documents from `/specs/005-avatar-backgrounds/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: Included and required for every story — Constitution Principle VI ("Feature-Test-First, Factory-Backed") and CLAUDE.md's Test Enforcement rule make Pest feature tests mandatory for this project, not optional.

**Organization**: Tasks are grouped by user story (from spec.md) to enable independent implementation and testing of each. All three stories share one generation/render pipeline (Phase 2); they differ only in what triggers it.

> Revised 2026-08-28 after `/speckit-analyze`: added T002 (concurrent generation, resolving finding C2/SC-001), T008 (bundled default assets, resolving B1), and T030 (cache-miss-on-reopen trigger, resolving E1/F1); T022's guard condition was reworded (C1), and the original T017's cross-reference corrected (F2). A second revision the same day removed the "reuse cached background when the request closely matches" requirement (FR-015 as originally written) and its task (originally T019) entirely — the scene-tag mechanism (T025/T027) already only fires when the setting has actually changed, so a separate similarity check was solving an already-solved problem. FR-015 now covers only the still-necessary per-conversation cache scoping.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an incomplete task)
- **[Story]**: US1/US2/US3, mapping to spec.md's prioritized user stories
- File paths are exact and relative to the repository root

---

## Phase 1: Setup

- [ ] T001 Add an `avatar_background` config block to `config/ai.php` (cache TTL seconds, storage path prefix `avatar-backgrounds`), following the existing `image_gen` block's `env()`-backed shape

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The generation pipeline and rendering that every user story triggers into. No user story can be implemented until this phase is complete.

- [ ] T002 [P] Add `generateMany(array $prompts): array` to `App\Contracts\ImageGenProvider` (`app/Contracts/ImageGenProvider.php`), returning one `ImageGenResult` per prompt in the same order; implement in `app/Services/ImageGenProviders/OpenRouterImageGenProvider.php` and `app/Services/ImageGenProviders/OpenAiCompatibleImageGenProvider.php` using `Http::pool()` so both prompts are sent concurrently rather than sequentially (research.md §5 — resolves the `/speckit-analyze` C2 finding: sequential calls risked missing SC-001's 60s target)
- [ ] T003 [P] Create `AvatarBackgroundPromptEnhancer` in `app/Services/AvatarBackground/AvatarBackgroundPromptEnhancer.php` — mirrors `app/Services/ImageGenProviders/ImageGenPromptEnhancer.php`'s `PromptDirector` + `withRetrieval()` setup (FR-006, FR-007); its `enhance()` returns a `{floor: string, surroundings: string}` prompt pair from one raw description
- [ ] T004 Create `AvatarBackgroundService` in `app/Services/AvatarBackground/AvatarBackgroundService.php` — orchestrates: call `AvatarBackgroundPromptEnhancer` (T003), call the assistant's `ImageGenManager`-resolved provider's `generateMany()` (T002) with the `[floor, surroundings]` prompts, delete the conversation's previous `avatar-backgrounds/{conversation_id}/*` files from the `public` disk, write the new pair, and return the cache payload shape from data-model.md. Depends on T002, T003.
- [ ] T005 Create `GenerateAvatarBackground` queued job in `app/Jobs/GenerateAvatarBackground.php` — constructor takes `AssistantUser`, `Conversation`, raw description; on `handle()`, writes `avatar-background-progress:{conversation_id}` while running (mirrors `AgentProgressController`'s key), calls `AvatarBackgroundService`, writes `avatar-background:{conversation_id}` (with the configured TTL from T001) on success, and on failure logs via `Log::error` and leaves the existing `avatar-background:{conversation_id}` entry untouched (Constitution Principle V — FR-013 fallback). Always clears the progress key in a `finally`. Depends on T004.
- [ ] T006 [P] Create `AvatarBackgroundController@show` in `app/Http/Controllers/Api/AvatarBackgroundController.php` — ownership-checks `{assistant}`/`{id}` exactly like `AgentProgressController@show`, returns the shape in `contracts/avatar-background-api.md` (`in_progress`, `status`, `background`). Purely a read — never dispatches generation itself (contracts/avatar-background-api.md).
- [ ] T007 Add `GET /assistants/{assistant}/conversations/{id}/avatar-background` route in `routes/api.php`, next to the existing `conversations.agent-progress` route. Depends on T006.
- [x] T008 [P] Add the bundled default background assets to `resources/images/avatar-background-default-floor.png` and `resources/images/avatar-background-default-surroundings.png` (research.md §9 — resolves the `/speckit-analyze` B1 finding: "the default scene" was previously undefined). Done — both files saved from the user-supplied image (the same image used for both, per their direction).
- [ ] T009 [P] Create `useAvatarBackground.js` hook in `resources/js/hooks/useAvatarBackground.js` — polls the T007 endpoint every 2s while `active` (mirrors `resources/js/components/AgentProgressIndicator.jsx`'s poll loop exactly, including computing the active/inactive transition in the render body per Constitution Principle VIII), returns `{ background, inProgress }`
- [ ] T010 Add floor + curved-backdrop rendering to `resources/js/components/VrmAvatar.jsx` — a flat ground-plane mesh (floor texture) beneath the avatar and an open partial-cylinder mesh (no front/top/bottom caps, surroundings texture) behind it (FR-008–FR-011), consuming `useAvatarBackground` (T009); when `background` is null, use the bundled default pair (T008) instead of rendering nothing. When the background prop changes (including from default to generated, or between two generated pairs), cross-fade the new pair's material opacity 0→1 while fading the previous pair 1→0 before disposing it (FR-018, research.md §7). Depends on T008, T009.

**Checkpoint**: Generation pipeline and rendering are complete and manually triggerable (e.g. via Tinker dispatching the job) — user story work can now begin.

---

## Phase 3: User Story 1 - Change the background on request (Priority: P1) 🎯 MVP

**Goal**: A user can change the background via an explicit `/change-background` command or, in agent mode, by asking naturally — reflecting archive-documented details when the location matches one, and having no effect on non-3D-avatar assistants.

**Independent Test**: Issue a background request (command or agent-mode) in a conversation with an Avatar3D assistant and confirm the scene updates to match; confirm no effect on a non-Avatar3D assistant.

### Tests for User Story 1

- [ ] T011 [P] [US1] Feature test: sending `/change-background a futuristic park` dispatches `GenerateAvatarBackground` with that description, in `tests/Feature/AvatarBackgroundCommandTest.php` (follow `tests/Feature/ImageGenerationToolSingleCallTest.php`'s `Http::fake()`/`Queue::fake()` conventions)
- [ ] T012 [P] [US1] Feature test: an agent-mode assistant asked in natural language to change the background invokes the `change_avatar_background` tool (assert via `tool_calls` in the response, mirroring `ImageGenerationToolSingleCallTest.php`), in `tests/Feature/AvatarBackgroundToolTest.php`
- [ ] T013 [P] [US1] Feature test: requesting a location matching an `ArchiveEntry` (via `setUpAssistantWithArchive()`) results in the enhanced floor/surroundings prompts including that entry's documented content, in `tests/Feature/AvatarBackgroundArchiveGroundingTest.php`
- [ ] T014 [P] [US1] Feature test: `/change-background ...` and the agent tool both have no effect for an assistant whose `portrait_type` is not `Avatar3D` (no job dispatched), in `tests/Feature/AvatarBackgroundNonAvatarNoOpTest.php`

### Implementation for User Story 1

- [ ] T015 [US1] Add `/change-background <description>` slash-command parsing in `ConversationController::sendMessage()` (`app/Http/Controllers/Api/ConversationController.php`), mirroring `extractImageGenPrompt()`/the `/create-image` branch, dispatching `GenerateAvatarBackground` (T005) only when the assistant's `portrait_type === AssistantPortraitType::Avatar3D`
- [ ] T016 [US1] Create `AvatarBackgroundTool` implementing `App\Contracts\AgentTool` in `app/Services/AgentLoop/Tools/AvatarBackgroundTool.php`, per `contracts/avatar-background-tool.md` — `handle()` dispatches `GenerateAvatarBackground` (T005) and returns immediately (no carrier message, unlike `ImageGenerationTool`)
- [ ] T017 [US1] Register `AvatarBackgroundTool` in `ConversationController::sendMessage()`'s agent-mode tool list, gated on `portrait_type === AssistantPortraitType::Avatar3D` (same `if` shape as the existing `ImageGenerationTool` registration). Depends on T016.

**Checkpoint**: User Story 1 is fully functional and independently testable — this is the MVP.

---

## Phase 4: User Story 2 - Automatic scene at conversation start (Priority: P2)

**Goal**: A new conversation with an Avatar3D assistant gets a background automatically, inferred from the opening message (or the user's first message, if there is no opening message).

**Independent Test**: Start a new conversation with an Avatar3D assistant and confirm a background is generated without any manual request.

### Tests for User Story 2

- [ ] T018 [P] [US2] Feature test: creating a conversation for an Avatar3D assistant with a non-empty `opening_message` dispatches `GenerateAvatarBackground` seeded from it, in `tests/Feature/AvatarBackgroundInitialTest.php`
- [ ] T019 [P] [US2] Feature test: creating a conversation for an Avatar3D assistant with an empty `opening_message`, then sending the first user message, dispatches `GenerateAvatarBackground` seeded from that first message (and does not double-dispatch on later messages); also covers sending the first message *before* an opening-message-seeded job has finished (no cache entry yet) does **not** double-dispatch when `opening_message` was non-empty, in `tests/Feature/AvatarBackgroundInitialFallbackTest.php`
- [ ] T020 [P] [US2] Feature test: creating a conversation for a non-Avatar3D assistant never dispatches a background job, in `tests/Feature/AvatarBackgroundInitialNonAvatarTest.php`

### Implementation for User Story 2

- [ ] T021 [US2] In `ConversationController::store()` (`app/Http/Controllers/Api/ConversationController.php`), dispatch `GenerateAvatarBackground` (T005) using `opening_message` when it's non-empty and `portrait_type === AssistantPortraitType::Avatar3D`
- [ ] T022 [US2] In `ConversationController::sendMessage()`, dispatch `GenerateAvatarBackground` seeded from the first user message's content when **`assistantModel->opening_message` is empty** and this is the conversation's first user message. Gate on the assistant's `opening_message` value directly, not on cache-entry absence — checking cache absence alone would double-dispatch when `opening_message` was non-empty but T021's job hadn't finished (and hadn't written the cache) yet by the time this message arrives (`/speckit-analyze` finding C1). Depends on T021.

**Checkpoint**: User Stories 1 and 2 both work independently.

---

## Phase 5: User Story 3 - Automatic scene updates as the story moves (Priority: P3)

**Goal**: The background updates automatically when the conversation's narrated setting changes mid-conversation, without a manual request.

**Independent Test**: Run a conversation where the assistant's reply signals a new setting and confirm the background regenerates without any manual command.

### Tests for User Story 3

- [ ] T023 [P] [US3] Feature test: an assistant reply starting with `[scene: a rain-soaked rooftop]` is stripped of that tag before being shown to the user, and dispatches `GenerateAvatarBackground` with `"a rain-soaked rooftop"`, in `tests/Feature/AvatarBackgroundSceneTagTest.php`
- [ ] T024 [P] [US3] Feature test: a normal assistant reply with no `[scene: ...]` tag never dispatches a background job, in `tests/Feature/AvatarBackgroundNoSceneTagTest.php`

### Implementation for User Story 3

- [ ] T025 [US3] In `ConversationController::sendMessage()`, for `Avatar3D` assistants, append a "background tags" system-prompt section instructing the model to optionally prefix its reply with `[scene: <description>]` when the setting has just changed (mirrors the existing `->append('emotion tags', ...)` call, but sets static instructional text unconditionally rather than relying on assistant-authored content — research.md §2)
- [ ] T026 [US3] Add `extractSceneTag()` parsing in `ConversationController` (mirrors `extractEmotionTag()`), called after the assistant's reply is received: strips the tag from the visible/persisted content and, when present, dispatches `GenerateAvatarBackground` with the extracted description. Depends on T025.

**Checkpoint**: All three user stories are independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

### Tests

- [ ] T027 [P] Feature test: reopening a conversation whose `avatar-background:{conversation_id}` cache entry has expired automatically regenerates a background from current context (FR-012a), in `tests/Feature/AvatarBackgroundCacheMissTest.php`
- [ ] T028 [P] Feature test: an image-gen provider failure during `GenerateAvatarBackground` leaves the previous `avatar-background:{conversation_id}` cache entry (or absence of one) untouched — no exception surfaces to the user (FR-013), in `tests/Feature/AvatarBackgroundFailureTest.php`
- [ ] T029 [P] Feature test: `sendMessage`'s HTTP response returns without waiting on `GenerateAvatarBackground` to finish (`Queue::fake()` + assert response completes), in `tests/Feature/AvatarBackgroundNonBlockingTest.php`

### Implementation

- [ ] T030 In `ConversationController::show()` (`app/Http/Controllers/Api/ConversationController.php`), dispatch `GenerateAvatarBackground` — seeded with a generic "infer the current setting from the conversation so far" description, relying on the enhancer's own recent-message-history context (T003) to do the actual inference — when: `portrait_type === Avatar3D`, this is the first page of messages (no `before` query param), no `avatar-background:{conversation_id}` cache entry exists, and no `avatar-background-progress:{conversation_id}` job is already running. Makes T027 pass. Depends on T005. (research.md §8 — resolves the `/speckit-analyze` E1/F1 findings: FR-012a previously had no implementation task, and the polling endpoint's read-only contract is preserved by triggering here instead.)
- [ ] T031 [P] Run `vendor/bin/pint --dirty --format agent` and fix any violations across all new/changed PHP files
- [ ] T032 [P] Run `npm run lint` and fix any violations in `resources/js/hooks/useAvatarBackground.js` and `resources/js/components/VrmAvatar.jsx`
- [ ] T033 Walk through every scenario in [quickstart.md](quickstart.md) against a running dev server (`composer run dev`) with a real or fake image-gen provider, confirming the cross-fade transition (T010) looks smooth per SC-006

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational only
- **User Story 2 (Phase 4)**: Depends on Foundational only (independent of US1, though both use `ConversationController::store`/`sendMessage`, which are different methods/branches)
- **User Story 3 (Phase 5)**: Depends on Foundational only — no cross-story dependency on US1 (the earlier reuse-check dependency was removed along with FR-015's matching requirement)
- **Polish (Phase 6)**: Tests (T027–T029) depend on Foundational; T030 depends on T005 and makes T027 pass; T031–T033 depend on all three stories being complete

### Within Foundational (Phase 2)

T002 [P] and T003 [P] have no dependency on each other and can proceed in parallel.
T004 depends on T002, T003 (service calls both the enhancer and the new `generateMany()`).
T005 depends on T004.
T006 [P] → T007 (controller before the route that references it) — independent of T002–T005, only needs the cache-key contract already fixed in data-model.md.
T008 [P] is fully independent (static assets, blocked only on the user supplying the source images).
T009 [P] → T010, and T010 also depends on T008.

### Parallel Opportunities

- T002, T003, T006, T008, T009 can all start together (different files, no inter-dependency)
- All Tests within a story phase (marked [P]) can be written together before that phase's implementation tasks
- User Stories 1, 2, and 3 can all be implemented in parallel by different developers once Foundational is done — none of the three depends on the others

---

## Parallel Example: User Story 1

```bash
# Write all US1 tests together first (must fail before implementation):
Task: "Feature test for /change-background command in tests/Feature/AvatarBackgroundCommandTest.php"
Task: "Feature test for agent-mode tool trigger in tests/Feature/AvatarBackgroundToolTest.php"
Task: "Feature test for archive-grounded generation in tests/Feature/AvatarBackgroundArchiveGroundingTest.php"
Task: "Feature test for non-Avatar3D no-op in tests/Feature/AvatarBackgroundNonAvatarNoOpTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1: Setup
2. Phase 2: Foundational (generation pipeline + rendering — the bulk of the work)
3. Phase 3: User Story 1 (manual command + agent tool trigger)
4. **STOP and VALIDATE**: run quickstart.md Scenarios 1–4, 6, 7 against US1's trigger paths
5. Demo: a user can change their avatar's background on request

### Incremental Delivery

1. Setup + Foundational → generation/rendering pipeline proven end-to-end via Tinker or a manual job dispatch
2. + User Story 1 → MVP: manual background changes work, archive-grounded, agent-mode included
3. + User Story 2 → new conversations start with a fitting background automatically
4. + User Story 3 → backgrounds follow the story as it moves, with no user action
5. + Polish → cache-miss recovery on reopen, failure fallback, and non-blocking guarantees are explicitly covered, lint/format clean, quickstart fully walked
