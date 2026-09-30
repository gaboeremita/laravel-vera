# Implementation Plan: Term Rules Applied From the Assistant's Prompt

**Branch**: `143-prompt-term-rules` | **Date**: 2026-09-30 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/022-prompt-term-rules/spec.md`

## Summary

Term rules stay plain text in one of the assistant's top-level string prompt sections. Three per-assistant settings, plus the picked section, are stored under `agent_config.termRules`. On each turn, code parses the picked section, and depending on the settings: annotates matched terms in the newest user message (`[term -> target]`), replaces invariant terms with `⟦n⟧` placeholders and restores them in the reply, and returns the list of required target terms missing from the reply with their positions in the user's message. The web chat shows those as a warning line and underlines, in memory only. Everything runs inside the existing single model call; saves are refused while a `->` line in the picked section doesn't parse.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 13), JavaScript/JSX (React 19)

**Primary Dependencies**: Existing only: Laravel validation, `PromptDirector`/`PromptBuilder`, `LlmManager` providers, Tailwind v4

**Storage**: PostgreSQL; existing `assistants.agent_config` JSON column (no migration)

**Testing**: Pest 4 feature tests with factories and `Http::fake` for the model call

**Target Platform**: Web app served by Herd; Discord and Telegram paths reuse the server logic

**Project Type**: Web application (Laravel API + React SPA)

**Performance Goals**: Added work per turn under 50 ms with 500 rules (SC-003); in practice one regex pass per message

**Constraints**: One model call per turn; no languages, inflection logic or glossary content in code; unchanged behavior with every setting off

**Scale/Scope**: Up to a few hundred rules per assistant; one message scanned per turn

## Constitution Check

| Principle | Status |
|---|---|
| I. Lint-enforced style | Pint and ESLint run once at push/PR time per CLAUDE.md |
| II. Append-only migrations | No migration needed |
| III. Comments justify only non-obvious decisions | Only the placeholder instruction and the UTF-16 offset conversion warrant a comment |
| IV. Data isolation by ownership | Settings and rules are read from the assistant resolved through the requesting user's own assistants; no cross-assistant reads |
| V. Errors fail loudly | Broken rule lines refuse the save with named lines (FR-014); no swallowed exceptions |
| VI. Feature-test-first, factory-backed | Feature tests through the prompt, assistant and send-message endpoints, with `Assistant` factory states |
| VII. No speculative abstraction | One parser, one matcher, one validation rule; all three callers (web, Discord, Telegram) exist today |
| VIII. Render-time derivation | Underline and warning rendering derive from message props during render; no effects |

Gate: pass. Re-checked after Phase 1 design: pass.

## Project Structure

### Documentation (this feature)

```text
specs/022-prompt-term-rules/
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
├── DTOs/TermRule.php                          # new: one parsed rule
├── DTOs/MarkedMessage.php                     # new: marked text, placeholders, matches; restore() and missingTerms()
├── Actions/TermRules/ParseTermRules.php       # new: section text -> rules + failing lines
├── Actions/TermRules/MarkTermRules.php        # new: message + rules + settings -> MarkedMessage
├── Rules/ValidTermRuleSection.php             # new: 422 per failing line
├── Http/Controllers/Api/AssistantController.php        # store/update/show accept and return agent_config.termRules
├── Http/Controllers/Api/AssistantPromptController.php  # update validates the picked section
├── Http/Controllers/Api/ConversationController.php     # sendMessage + sendDiscordMessage apply marking/swap; missingTerms in response
└── Console/Commands/TelegramPollCommand.php            # apply marking/swap

resources/js/
├── pages/EditAssistantPage.jsx               # dropdown + three checkboxes
├── pages/CreateAssistantPage.jsx             # same controls
├── components/TermRuleSettings.jsx           # new: shared dropdown + checkboxes
├── hooks/useConversationChat.js              # keep missingTerms on in-memory messages
└── components/ChatMessage.jsx                # underline ranges, warning line under reply

database/factories/AssistantFactory.php       # state for termRules settings + a rule section

tests/Feature/Api/
├── TermRulesSettingsTest.php                 # new: save/validation (FR-002, FR-004, FR-014)
└── TermRulesConversationTest.php             # new: marking, swap, missingTerms, history (FR-005..FR-012)
```

**Structure Decision**: Existing Laravel + React layout. New PHP classes follow the `app/Actions/<Area>/`, `app/DTOs/` and `app/Rules/` conventions already in use.

## Design Notes

- **Turn flow (web)**: resolve `termRules`; if every flag is off or `section` is null, skip entirely (FR-005). Otherwise parse the section, build `MarkedMessage` from the newest user message after creator-mode stripping, and replace only that message's content in `$validated['messages']`. The stored user message and `userContent` stay as typed; retrieval uses the unmarked text. When placeholders exist, append the fixed placeholder instruction section to the director before `build()` (research R6). After the model call, `$content = $marked->restore($content)` before TTS parsing and storage. If `highlightMissing`, add `missingTerms` to the response.
- **Discord/Telegram**: same steps without the highlight.
- **Offsets**: matching runs on UTF-8 byte offsets (`PREG_OFFSET_CAPTURE`); ranges returned to the browser are converted to UTF-16 code-unit offsets.
- **Frontend**: the dropdown lists top-level prompt keys whose value is a string. On the edit page it reads the loaded prompt; on the create page, the prompt being built. `ChatMessage` splits the user text by the ranges into plain and underlined spans; the warning line lists `missingTerms[].target` under the reply.

## Complexity Tracking

No violations.
