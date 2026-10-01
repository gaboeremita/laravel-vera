# Implementation Plan: Cache-Friendly Prompt Structure

**Branch**: `144-prompt-cache-structure` | **Date**: 2026-10-01 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/023-prompt-cache-structure/spec.md`

## Summary

`PromptDirector` builds a `PromptLayout` with three groups (unchanging, occasional, per turn) instead of a single string. Every top-level section gets an all-caps `# ` heading. A new `ComposeChatRequest` action turns the layout, a server-built history, and the current user message into one provider-neutral request:
- a system message made of text parts, with cache points after the unchanging group and after the memory and Discord parts;
- the history, with a cache point on its last message;
- the current user message, with the per-turn text prepended.

Each `LlmProvider` translates cache points and the conversation identifier into its own wire format, driven only by two new per-model settings (`cache_marks`, `conversation_key_field`). Nothing in the code names a vendor.

History comes from storage on every path, with a jump-ahead limit (100/50, or 60/30 for resident-to-resident turns). Summaries are appended at the end of the memory and sent one part per summary. The two shared rules are stated once.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 13), JavaScript/JSX (React 19)

**Primary Dependencies**: Existing only: `PromptDirector`/`PromptBuilder`, `LlmManager` with `GenericProvider`/`AnthropicProvider`, `AgentLoopRunner`, `LlmResponseTagParser`, Tailwind v4

**Storage**: PostgreSQL. One new migration adds `ai_models.cache_marks` and `ai_models.conversation_key_field`.

**Testing**: Pest 4 feature tests with factories, and `Http::fake` capturing provider request bodies

**Target Platform**: Web app served by Herd. Discord, Telegram, resident turns, and resident decisions share the server logic.

**Project Type**: Web application (Laravel API + React SPA)

**Performance Goals**: About 90% of the unchanging group cached from the second turn on (SC-001). Prompt assembly cost per turn stays as it is today.

**Constraints**:
- Provider-generic: behavior is set only by per-model settings.
- The unchanging group must be byte-identical for the same inputs.
- At most 3 cache points per request.
- The content of the author's prompt is untouched.

**Scale/Scope**: About 15k-token unchanging group, up to 100 history messages, every chat path (web, voice, Telegram, Discord, resident turns, resident decisions, quest rewards, image and scene-change replies)

## Constitution Check

| Principle | Status |
|---|---|
| I. Lint-enforced style | Pint and ESLint run once at push/PR time, per CLAUDE.md |
| II. Append-only migrations | One new migration. No existing migration is edited. |
| III. Comments justify only non-obvious decisions | Comments only on the jump-ahead formula and on why per-turn text travels in the user message |
| IV. Data isolation by ownership | History, memory, and Discord siblings are read through the conversation's own owner, as today. The identifier is an HMAC of the conversation id, so no ids reach the provider. |
| V. Errors fail loudly | Invalid `conversation_key_field` is refused with a 422. Provider errors still throw and log. |
| VI. Feature-test-first, factory-backed | Feature tests through the send-message endpoints and the Telegram command, with `AiModel` factory states and `Http::fake` |
| VII. No speculative abstraction | `PromptLayout`, `ComposeChatRequest`, and `BuildConversationHistory` each have several real callers today. No provider-strategy layer is added, because the two existing providers translate directly. |
| VIII. Render-time derivation | The model settings toggle and input are plain controlled fields. No effects. |

Gate: pass. Re-checked after Phase 1 design: pass.

## Project Structure

### Documentation (this feature)

```text
specs/023-prompt-cache-structure/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/api.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Enums/PromptGroup.php                         # new
├── Enums/TurnSection.php                         # new: cases + heading()
├── DTOs/PromptLayout.php                         # new
├── Directors/PromptDirector.php                  # groups, addOccasional/addToTurn, build(): PromptLayout, memory parts, rule sections
├── Builders/PromptBuilder.php                    # "# TITLE" headings for top-level sections
├── Actions/ComposeChatRequest.php                # new: layout + history + current message -> normalized messages with cache points
├── Actions/BuildConversationHistory.php          # new: stored history, jump-ahead limit, tag stripping
├── Actions/AppendWorldConversationContext.php    # writes into the director's groups instead of returning an array
├── Actions/AppendExpressionTags.php              # static tag format + POSES AND MOVEMENT; posture + poses into CURRENT STATE
├── Actions/BuildResidentWorldPrompt.php          # drop embedded "World awareness:" prefix and the duplicate pose line
├── Actions/SummarizeConversation.php             # append new summary at the end
├── Actions/GenerateResidentConversationTurn.php  # history + compose + identifier
├── Actions/Quests/GiveQuestRewardAction.php      # compose
├── Contracts/LlmProvider.php                     # content parts with cachePoint; ?string $conversationKey
├── Services/LlmProviders/GenericProvider.php     # parts -> arrays with cache_control or joined strings; identifier field
├── Services/LlmProviders/AnthropicProvider.php   # parts -> blocks with cache_control; identifier field; return usage
├── Services/AgentLoop/AgentLoopRunner.php        # pass the identifier every step; instruction into the first system part
├── Models/Conversation.php                       # providerSessionKey()
├── Models/AiModel.php                            # casts for the two columns
├── Http/Controllers/Api/AiModelController.php    # validate and save the two settings
├── Http/Controllers/Api/ConversationController.php   # sendMessage, sendDiscordMessage, image and scene-change replies use history + compose
├── Http/Controllers/Api/ResidentDecisionController.php
├── Console/Commands/TelegramPollCommand.php
├── Services/ImageGenProviders/ImageGenPromptEnhancer.php        # one-shot: layout->fullText()
└── Services/AvatarBackground/AvatarBackgroundPromptEnhancer.php # one-shot: layout->fullText()

database/
├── migrations/xxxx_add_caching_settings_to_ai_models_table.php  # new
└── factories/AiModelFactory.php                  # new: with cacheMarks() and conversationKeyField() states

resources/js/
├── hooks/useConversationChat.js                  # send only the new message
└── components/ModelAccordion.jsx                 # cache-marks toggle, conversation key field input

tests/
├── Feature/PromptLayoutTest.php                  # new: groups, headings, single rules, byte-stability
├── Feature/ComposeChatRequestTest.php            # new: cache points, per-turn placement, history identity across turns
├── Feature/ConversationHistoryLimitTest.php      # new: 100/50, 60/30, Discord merge, tag stripping
├── Feature/ProviderCacheMarksTest.php            # new: wire format per provider and setting, identifier field, Anthropic usage
├── Feature/AiModelCachingSettingsTest.php        # new: validation and persistence
├── Feature/SummarizeConversationOrderTest.php    # new: appended at the end, existing text untouched
└── Unit/PromptDirectorVoiceModeTest.php          # updated for the new voice placement
```

**Structure Decision**: Existing Laravel and React layout. New classes follow the conventions already in use for `app/Actions/`, `app/DTOs/`, and `app/Enums/`.

## Design Notes

- **Director API**:
  - `new PromptDirector($assistant->prompt)` seeds the unchanging group.
  - `append()`, `insertAfter()`, `only()`, and `except()` act on it as today.
  - `addOccasional(key, value)` and `addToTurn(TurnSection, key, value)` fill the other two groups.
  - `withRetrieval()` adds to `RetrievedKnowledge`, with entries only.
  - `withLongTermMemory()` adds the memory parts to the occasional group.
  - `withDiscordEnvironment()` adds to the occasional group.
  - The `REFERENCE MATERIAL RULE` and `POSES AND MOVEMENT` sections are added by `build()` when their subjects are present: retrieval or memory possible, and a 3D avatar.
- **Turn flow (web)**:
  1. Validate `message`.
  2. Store it.
  3. Build the history from storage, excluding the new message.
  4. Build the director as today.
  5. Call `ComposeChatRequest` with the layout, the history, and the current message (after term-rule marking and creator-mode handling).
  6. Call the model with `conversationKey: $conversation->providerSessionKey()`.
  7. `system_prompt` in the response is `$layout->fullText()`.
- **Other paths**: Telegram and Discord follow the same steps. Their current message is the stored trigger message, which becomes the last history entry, so `ComposeChatRequest` takes it as the current message. Image and scene-change replies add their note with `addToTurn(CurrentState, …)` instead of a trailing system message. Resident turns use the 60/30 limit.
- **Providers**: both providers map `content` parts. `cachePoint` is honored only when `cache_marks` is on. Without marks, parts are joined with `\n\n`, so the text is identical whatever the setting. The identifier is added as `[$conversation_key_field => $key]` only when the field is set.
- **Frontend**:
  - `useConversationChat.sendMessage` posts `{message: {content, images?}, …extraParams}`.
  - `ModelAccordion.jsx` adds a checkbox and a text input, using the existing field components and the labels approved below.

## Names

Approved by the owner.

| Proposed name | Refers to |
|---|---|
| `PromptGroup` (`Unchanging`, `Occasional`, `PerTurn`) | The three prompt groups |
| `TurnSection` (`CurrentState`, `RecentActivity`, `RetrievedKnowledge`, `RelationshipState`) | The per-turn headings already approved |
| `PromptLayout` | The built prompt, split by group |
| `ComposeChatRequest` | The action that assembles the request with cache points |
| `BuildConversationHistory` | The action that builds history from storage with the jump-ahead limit |
| `providerSessionKey()` | The opaque per-conversation identifier |
| `cachePoint` | The normalized content-part flag |
| `ai_models.cache_marks` | The per-model setting to send cache points |
| `ai_models.conversation_key_field` | The per-model name of the identifier request field |
| UI label "Send cache marks" | The checkbox for `cache_marks` |
| UI label "Conversation ID field" | The input for `conversation_key_field` |

## Complexity Tracking

No violations.
