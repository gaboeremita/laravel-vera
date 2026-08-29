---

description: "Task list for 3D Avatar Scene Backgrounds"
---

# Tasks: 3D Avatar Scene Backgrounds

**Input**: Design documents from `/specs/005-avatar-backgrounds/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: Included and required for every story — Constitution Principle VI ("Feature-Test-First, Factory-Backed") and CLAUDE.md's Test Enforcement rule make Pest feature tests mandatory for this project, not optional.

**Organization**: Tasks are grouped by user story (from spec.md) to enable independent implementation and testing of each. All three stories share one generation/render pipeline (Phase 2); they differ only in what triggers it.

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

- [ ] T002 [P] Create `AvatarBackgroundPromptEnhancer` in `app/Services/AvatarBackground/AvatarBackgroundPromptEnhancer.php` — mirrors `app/Services/ImageGenProviders/ImageGenPromptEnhancer.php`'s `PromptDirector` + `withRetrieval()` setup (FR-006, FR-007), but its `enhance()` returns a `{floor: string, surroundings: string}` prompt pair from one raw description instead of a single prompt (research.md §5)
- [ ] T003 Create `AvatarBackgroundService` in `app/Services/AvatarBackground/AvatarBackgroundService.php` — orchestrates: call `AvatarBackgroundPromptEnhancer`, generate both images via the assistant's `ImageGenManager` (two calls), delete the conversation's previous `avatar-backgrounds/{conversation_id}/*` files from the `public` disk, write the new pair, and return the cache payload shape from data-model.md. Depends on T002.
- [ ] T004 Create `GenerateAvatarBackground` queued job in `app/Jobs/GenerateAvatarBackground.php` — constructor takes `AssistantUser`, `Conversation`, raw description; on `handle()`, writes `avatar-background-progress:{conversation_id}` while running (mirrors `AgentProgressController`'s key), calls `AvatarBackgroundService`, writes `avatar-background:{conversation_id}` (with the configured TTL from T001) on success, and on failure logs via `Log::error` and leaves the existing `avatar-background:{conversation_id}` entry untouched (Constitution Principle V — FR-013 fallback). Always clears the progress key in a `finally`. Depends on T002, T003.
- [ ] T005 [P] Create `AvatarBackgroundController@show` in `app/Http/Controllers/Api/AvatarBackgroundController.php` — ownership-checks `{assistant}`/`{id}` exactly like `AgentProgressController@show`, returns the shape in `contracts/avatar-background-api.md` (`in_progress`, `status`, `background`)
- [ ] T006 Add `GET /assistants/{assistant}/conversations/{id}/avatar-background` route in `routes/api.php`, next to the existing `conversations.agent-progress` route. Depends on T005.
- [ ] T007 [P] Create `useAvatarBackground.js` hook in `resources/js/hooks/useAvatarBackground.js` — polls the T006 endpoint every 2s while `active` (mirrors `resources/js/components/AgentProgressIndicator.jsx`'s poll loop exactly, including computing the active/inactive transition in the render body per Constitution Principle VIII), returns `{ background, inProgress }`
- [ ] T008 Add floor + curved-backdrop rendering to `resources/js/components/VrmAvatar.jsx` — a flat ground-plane mesh (floor texture) beneath the avatar and an open partial-cylinder mesh (no front/top/bottom caps, surroundings texture) behind it (FR-008–FR-011), consuming `useAvatarBackground` (T007); when the background prop changes, cross-fade the new pair's material opacity 0→1 while fading the previous pair 1→0 before disposing it (FR-018, research.md §7). Depends on T007.

**Checkpoint**: Generation pipeline and rendering are complete and manually triggerable (e.g. via Tinker dispatching the job) — user story work can now begin.

---

## Phase 3: User Story 1 - Change the background on request (Priority: P1) 🎯 MVP

**Goal**: A user can change the background via an explicit `/change-background` command or, in agent mode, by asking naturally — reflecting archive-documented details when the location matches one, and having no effect on non-3D-avatar assistants.

**Independent Test**: Issue a background request (command or agent-mode) in a conversation with an Avatar3D assistant and confirm the scene updates to match; confirm no effect on a non-Avatar3D assistant.

### Tests for User Story 1

- [ ] T009 [P] [US1] Feature test: sending `/change-background a futuristic park` dispatches `GenerateAvatarBackground` with that description, in `tests/Feature/AvatarBackgroundCommandTest.php` (follow `tests/Feature/ImageGenerationToolSingleCallTest.php`'s `Http::fake()`/`Queue::fake()` conventions)
- [ ] T010 [P] [US1] Feature test: an agent-mode assistant asked in natural language to change the background invokes the `change_avatar_background` tool (assert via `tool_calls` in the response, mirroring `ImageGenerationToolSingleCallTest.php`), in `tests/Feature/AvatarBackgroundToolTest.php`
- [ ] T011 [P] [US1] Feature test: requesting a location matching an `ArchiveEntry` (via `setUpAssistantWithArchive()`) results in the enhanced floor/surroundings prompts including that entry's documented content, in `tests/Feature/AvatarBackgroundArchiveGroundingTest.php`
- [ ] T012 [P] [US1] Feature test: `/change-background ...` and the agent tool both have no effect for an assistant whose `portrait_type` is not `Avatar3D` (no job dispatched), in `tests/Feature/AvatarBackgroundNonAvatarNoOpTest.php`
- [ ] T013 [P] [US1] Feature test: requesting a description that closely matches the conversation's current cached `source_description` reuses the cached background instead of dispatching a new job (FR-015), in `tests/Feature/AvatarBackgroundReuseTest.php`

### Implementation for User Story 1

- [ ] T014 [US1] Add `/change-background <description>` slash-command parsing in `ConversationController::sendMessage()` (`app/Http/Controllers/Api/ConversationController.php`), mirroring `extractImageGenPrompt()`/the `/create-image` branch, dispatching `GenerateAvatarBackground` (T004) only when the assistant's `portrait_type === AssistantPortraitType::Avatar3D`
- [ ] T015 [US1] Create `AvatarBackgroundTool` implementing `App\Contracts\AgentTool` in `app/Services/AgentLoop/Tools/AvatarBackgroundTool.php`, per `contracts/avatar-background-tool.md` — `handle()` dispatches `GenerateAvatarBackground` (T004) and returns immediately (no carrier message, unlike `ImageGenerationTool`)
- [ ] T016 [US1] Register `AvatarBackgroundTool` in `ConversationController::sendMessage()`'s agent-mode tool list, gated on `portrait_type === AssistantPortraitType::Avatar3D` (same `if` shape as the existing `ImageGenerationTool` registration). Depends on T015.
- [ ] T017 [US1] Implement the FR-015 reuse check — before dispatching, compare the requested/detected description against the conversation's current `avatar-background:{conversation_id}` cache entry's `source_description`; skip dispatch if they closely match. Add as a small helper used by T014 and T016 (e.g. a method on `AvatarBackgroundService` or a shared trait) — avoid duplicating the comparison logic across the two trigger sites.

**Checkpoint**: User Story 1 is fully functional and independently testable — this is the MVP.

---

## Phase 4: User Story 2 - Automatic scene at conversation start (Priority: P2)

**Goal**: A new conversation with an Avatar3D assistant gets a background automatically, inferred from the opening message (or the user's first message, if there is no opening message).

**Independent Test**: Start a new conversation with an Avatar3D assistant and confirm a background is generated without any manual request.

### Tests for User Story 2

- [ ] T018 [P] [US2] Feature test: creating a conversation for an Avatar3D assistant with a non-empty `opening_message` dispatches `GenerateAvatarBackground` seeded from it, in `tests/Feature/AvatarBackgroundInitialTest.php`
- [ ] T019 [P] [US2] Feature test: creating a conversation for an Avatar3D assistant with an empty `opening_message`, then sending the first user message, dispatches `GenerateAvatarBackground` seeded from that first message (and does not double-dispatch on later messages), in `tests/Feature/AvatarBackgroundInitialFallbackTest.php`
- [ ] T020 [P] [US2] Feature test: creating a conversation for a non-Avatar3D assistant never dispatches a background job, in `tests/Feature/AvatarBackgroundInitialNonAvatarTest.php`

### Implementation for User Story 2

- [ ] T021 [US2] In `ConversationController::store()` (`app/Http/Controllers/Api/ConversationController.php`), dispatch `GenerateAvatarBackground` (T004) using `opening_message` when it's non-empty and `portrait_type === AssistantPortraitType::Avatar3D`
- [ ] T022 [US2] In `ConversationController::sendMessage()`, when the conversation has no `avatar-background:{conversation_id}` cache entry yet and this is its first user message (opening_message was empty), dispatch `GenerateAvatarBackground` seeded from that message's content. Depends on T021.

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
- [ ] T026 [US3] Add `extractSceneTag()` parsing in `ConversationController` (mirrors `extractEmotionTag()`), called after the assistant's reply is received: strips the tag from the visible/persisted content and, when present, dispatches `GenerateAvatarBackground` (subject to the T017 reuse check) with the extracted description. Depends on T025, T017.

**Checkpoint**: All three user stories are independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T027 [P] Feature test: reopening a conversation whose `avatar-background:{conversation_id}` cache entry has expired automatically regenerates a background from current context (FR-012a), in `tests/Feature/AvatarBackgroundCacheMissTest.php`
- [ ] T028 [P] Feature test: an image-gen provider failure during `GenerateAvatarBackground` leaves the previous `avatar-background:{conversation_id}` cache entry (or absence of one) untouched — no exception surfaces to the user (FR-013), in `tests/Feature/AvatarBackgroundFailureTest.php`
- [ ] T029 [P] Feature test: `sendMessage`'s HTTP response returns without waiting on `GenerateAvatarBackground` to finish (`Queue::fake()` + assert response completes), in `tests/Feature/AvatarBackgroundNonBlockingTest.php`
- [ ] T030 Run `vendor/bin/pint --dirty --format agent` and fix any violations across all new/changed PHP files
- [ ] T031 Run `npm run lint` and fix any violations in `resources/js/hooks/useAvatarBackground.js` and `resources/js/components/VrmAvatar.jsx`
- [ ] T032 Walk through every scenario in [quickstart.md](quickstart.md) against a running dev server (`composer run dev`) with a real or fake image-gen provider, confirming the cross-fade transition (T008) looks smooth per SC-006

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational only
- **User Story 2 (Phase 4)**: Depends on Foundational only (independent of US1, though both use `ConversationController::store`/`sendMessage`, which are different methods/branches)
- **User Story 3 (Phase 5)**: Depends on Foundational and on T017 (the reuse-check helper introduced in US1) — this is the one cross-story dependency in this feature, since detected scene changes must go through the same reuse check manual requests do
- **Polish (Phase 6)**: Depends on all three stories being complete

### Within Foundational (Phase 2)

T002 → T003 → T004 (enhancer, then service, then job all build on each other)
T005 → T006 (controller before the route that references it)
T007 → T008 (hook before the scene consumes it)
T005/T006/T007/T008 can proceed in parallel with T002/T003/T004 — they only depend on the cache-key contract already fixed in data-model.md, not on the generation code itself.

### Parallel Opportunities

- T002, T005, T007 can start together (different files, no inter-dependency)
- All Tests within a story phase (marked [P]) can be written together before that phase's implementation tasks
- User Story 1 and User Story 2 can be implemented in parallel by different developers once Foundational is done (User Story 3 needs US1's T017)

---

## Parallel Example: User Story 1

```bash
# Write all US1 tests together first (must fail before implementation):
Task: "Feature test for /change-background command in tests/Feature/AvatarBackgroundCommandTest.php"
Task: "Feature test for agent-mode tool trigger in tests/Feature/AvatarBackgroundToolTest.php"
Task: "Feature test for archive-grounded generation in tests/Feature/AvatarBackgroundArchiveGroundingTest.php"
Task: "Feature test for non-Avatar3D no-op in tests/Feature/AvatarBackgroundNonAvatarNoOpTest.php"
Task: "Feature test for cached-background reuse in tests/Feature/AvatarBackgroundReuseTest.php"
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
5. + Polish → cache-miss recovery, failure fallback, and non-blocking guarantees are explicitly covered, lint/format clean, quickstart fully walked
