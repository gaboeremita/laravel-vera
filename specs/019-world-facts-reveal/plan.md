# Implementation Plan: Facts and Reveal Safeguards

**Branch**: `claude/cool-hopper-uqk44z` | **Date**: 2026-09-29 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/019-world-facts-reveal/spec.md`

## Summary

Residents hold facts, secrets they share in character, and the server makes sure a secret is earned in the story.

- **Schema**: `facts` on resident placements, `fact_relays`, and per session `known_facts`, `fact_acknowledgements` and `reveal_attempts`; flags on worlds, conversations, users, credit transactions, items and activity terms ([data-model.md](data-model.md)).
- **Prompts**: a `facts` section shows a holder only the topic and disclosure prose until the player knows the fact; relay residents get the content once the player knows it (research R2).
- **Tools**: `reveal` returns the content only after a review by the world's narrator model, which judges the disclosure prose against the stored conversation with OOC spans removed; `acknowledge` is refused unless the player knows the fact. Every attempt is logged (R3–R6).
- **OOC turns**: holders get the content and reveals skip the review; OOC text stays in the conversation (R7).
- **Creator mode**: a hashed per-user password checked by the server, kept on per conversation, removed from every stored and sent message. The hardcoded frontend trigger is deleted. Creator turns get unscoped tools plus `set_fact_known`, `grant` and `remove` (R8–R10).
- **Player and owner UI**: facts editor in each resident's section of the region editor, relay picker, review toggle on the world, fact links on items and activity terms, creator password in Settings, a learned-facts HUD panel with toasts, and a reveal log on the sessions page.

## Technical Context

**Language/Version**: PHP 8.4; JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13, Pest 4, Ziggy 2; the existing agent loop, LLM providers, narrator and inventory transfer. No new dependencies.

**Storage**: PostgreSQL in tests, MySQL locally. Five new tables and six new columns ([data-model.md](data-model.md)).

**Testing**:
- **Pest feature tests** for the configuration and play endpoints, the prompt contents per turn mode, the tools, the review and creator mode, with factories and a faked LLM.
- **Manually**, following [quickstart.md](quickstart.md): the editors, HUD panel, toasts, reveal log and the password never reaching the bundle.

**Target Platform**: Desktop browsers via the existing SPA.

**Project Type**: Web application (Laravel backend + React frontend, single repo).

**Performance Goals**: A reviewed reveal adds one LLM call (SC-006, under 5 s on the narrator model); prompts gain one short section.

**Constraints**:
- A fact's content reaches a holder's prompt only when the player knows it, or on OOC and creator turns (FR-005, SC-002).
- The review reads the conversation stored on the server, never the history the client sends.
- The creator password never appears in the bundle, the repository, stored messages or prompts (SC-007).
- OOC and creator bypasses exist only in `sendMessage`; resident turns and decisions never get them (FR-012).
- Existing worlds, sessions and conversations keep working with no facts and creator mode off.
- Every new UI piece follows the UI standard of [feature 1's tasks.md](../018-items-inventory-credits/tasks.md) (FR-020).

**Scale/Scope**: A handful of facts per resident, tens per world.
- **Backend**: about 30 new files (migrations, models, factories, enum, actions, tools, controllers, requests) and about 15 changed.
- **Frontend**: about 5 new components and hooks, about 8 changed.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style**: Pint and ESLint run once at push or PR time per CLAUDE.md. PASS.
- **II. Append-Only Migrations**: every schema change is a new migration. PASS.
- **III. Comments Justify Only Non-Obvious Decisions**: comments only for hidden constraints, such as why the review reads stored messages or why a rejected reveal is a normal result. PASS.
- **IV. Data Isolation by Ownership**:
  - Everything resolves through `$request->user()->worlds()`, then the world's residents, facts and sessions.
  - Relay residents and linked facts must be in the same world.
  - The creator password is the requesting user's; creator mode is a column on a conversation already resolved through the user's assistant.
  - Feature tests cover another user's world, another world's fact, and another session's log. PASS.
- **V. Errors Fail Loudly**:
  - Refused tools return the reason to the model; a failed review rejects the reveal and records the failure as the verdict.
  - Configuration refusals return 422 with the reason; the client shows a toast for every failure. PASS.
- **VI. Feature-Test-First, Factory-Backed**: factories for every new model, with states for relays, known facts and creator-mode conversations. PASS.
- **VII. No Speculative Abstraction**:
  - `ResolveNarratorModel` is extracted because the review is its second real caller.
  - No per-session copy of holders, because nothing moves them in this feature (R1).
  - No output scanner for OOC turns (clarification). PASS.
- **VIII. State Derivation During Render**: the learned-facts toasts and the panel are derived during render from the latest response; fetching uses effect-local closures. PASS.

Post-design re-check: the design above holds; no violations, so Complexity Tracking is not needed.

## Project Structure

### Documentation (this feature)

```text
specs/019-world-facts-reveal/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── api.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Actions/
│   ├── BuildFactsPrompt.php              # new: the facts section per turn mode
│   ├── ReviewReveal.php                  # new: forced-verdict review call
│   ├── ResolveNarratorModel.php          # new: extracted from Narrate
│   ├── LearnFact.php                     # new: record a known fact and its log row
│   ├── CreatorModeTags.php               # new: find and remove activations and commands
│   ├── Narrate.php                       # changed: uses ResolveNarratorModel
│   ├── UseActivity.php                   # changed: linked fact
│   └── GenerateResidentConversationTurn.php  # changed: topics only, sections excluded
├── Enums/RevealSource.php
├── Http/Controllers/Api/
│   ├── FactController.php                # new
│   ├── CreatorPasswordController.php     # new
│   ├── FactPlayController.php            # new: known facts, reveal log
│   ├── ConversationController.php        # changed: turn modes, tools, creator mode
│   ├── ResidentDecisionController.php    # changed: creator sections excluded
│   ├── ItemUseController.php, ItemController.php, ActivityTermsController.php, WorldController.php  # changed
├── Http/Requests/                         # one per new write endpoint
├── Models/Fact.php, KnownFact.php, FactAcknowledgement.php, RevealAttempt.php; Conversation, User, World, Item, ActivityTerms, CreditTransaction, WorldResident (changed)
├── Services/LlmResponseTagParser.php      # changed: OOC detection and stripping
└── Services/AgentLoop/Tools/World/
    ├── RevealTool.php, AcknowledgeTool.php, VerdictTool.php          # new
    ├── SetFactKnownTool.php, GrantTool.php, RemoveTool.php            # new: creator turns
    └── WorldToolbox.php                   # changed: unscoped on creator turns

database/migrations/                       # new tables and columns
database/factories/                        # one per new model

resources/js/
├── components/
│   ├── ResidentFactsEditor.jsx            # new: facts in a resident's section
│   ├── WorldResidentsEditor.jsx           # changed: facts section, warning on removal
│   ├── WorldForm.jsx, ItemsEditor.jsx, ActivityTermsEditor.jsx  # changed: review toggle, fact links
│   ├── world/hud/LearnedFactsPanel.jsx    # new
│   ├── world/hud/ControlsLegend.jsx       # changed: panel key
│   └── RevealLog.jsx                      # new: on the sessions page
├── hooks/useConversationChat.js           # changed: trigger removed, stored content, creator state
├── hooks/useKnownFacts.js                 # new
├── pages/SettingsPage.jsx                 # changed: creator password section
├── pages/WorldPage.jsx, WorldSessionsPage.jsx  # changed
└── components/world/WorldChat.jsx         # changed: learned-fact toasts, creator notice

routes/api.php                             # new routes per contracts/api.md

tests/Feature/Api/FactControllerTest.php, CreatorPasswordControllerTest.php, FactPlayControllerTest.php
tests/Feature/FactWorldToolsTest.php, ReviewRevealTest.php, CreatorModeTest.php
```

**Structure Decision**: The existing single Laravel + React repo layout; new code sits beside the resident, inventory and conversation code it extends.

## Proposed names (awaiting approval)

None of these is used until approved; the plain descriptions stand in for them until then.

| Proposed name | Where | Refers to |
|---|---|---|
| fact, "Facts" | code, tables, section title in a resident's configuration | a secret a resident holds |
| "Topic" | field label, `topic` column | what the fact is about, all the holder sees at first |
| "Secret" | field label, `content` column | the fact's content |
| "When they share it" | field label, `disclosure` column | the disclosure prose |
| "Can be told by the player" | field label, `fact_relays` table | residents who can act on the fact once the player knows it |
| relay resident | code, docs | one of those residents |
| "Review reveals" | world setting toggle, `review_reveals` column | the separate model's check of in-character reveals |
| review, verdict | code, log column | the check and its explanation |
| "Reveals fact" | field label on items and activity terms, `reveals_fact_id` | the fact the player learns from them |
| `reveal`, `acknowledge` | LLM tool names | the holder sharing a fact; a relay resident taking in what the player told them |
| `verdict` | LLM tool name | the review's forced answer |
| `set_fact_known`, `grant`, `remove` | LLM tool names, creator turns | marking a fact known or unknown; creating or destroying items and credits for any holder |
| "Learned" | HUD panel title and key label | the facts the player knows in the session |
| "You learned something" | toast | a fact became known |
| "Reveal log" | sessions page section | the list of reveal attempts |
| "Creator mode", "Creator password" | Settings section and field | the password check for creator mode |
| "Creator mode is on" / "Creator mode didn't activate" / "Set a creator password in Settings first" | chat notices | the outcome of an activation |

## Complexity Tracking

Not needed.
