# Research: Term Rules Applied From the Assistant's Prompt

## R1. Where the behavior settings live

- **Decision**: Store the picked section and the three on/off values inside the existing `agent_config` JSON column on `assistants`, under a `termRules` key: `{ "section": string|null, "markTerms": bool, "swapInvariant": bool, "highlightMissing": bool }`.
- **Rationale**: `agent_config` is already a per-assistant JSON settings column (it holds `step_limit`), cast to an array on the model and fillable. Using it needs no migration and keeps the change minimal.
- **Alternatives considered**: Four new columns on `assistants` (one migration, typed columns, but a schema change for settings that are read together as one unit).

## R2. Which prompt sections can hold rules

- **Decision**: The dropdown lists the assistant's top-level prompt sections whose value is a string. Rules are read one per line from that string.
- **Rationale**: `PromptBuilder` renders a top-level string as-is, so each rule line reaches the model on its own line. A list section is rendered comma-joined, which would merge rule lines and collide with the comma that separates variants; an object section is rendered as `Label: value` pairs.
- **Alternatives considered**: Accepting list sections with one rule per item (the rendered prompt would read `a -> b, c -> d`, which the model can't split reliably).

## R3. Rule line grammar

- **Decision**: `source[, variant…] -> target[, variant…] [(invariant)] [(case)]`. Split once on `->`; trim; split each side on `,`; trim each term; the marks are recognised only at the end of the line, in any order. A line with `->` parses when both sides have at least one non-empty term. Lines without `->` are ordinary text.
- **Rationale**: Matches the clarified format and stays readable to the model as written.
- **Alternatives considered**: None beyond the formats offered during clarification.

## R4. Matching

- **Decision**: Build one regular expression per assistant turn from all rule terms (source term plus source variants), sorted longest first, each escaped with `preg_quote`, joined with `|`, wrapped in Unicode-aware boundaries `(?<![\p{L}\p{N}])(…)(?![\p{L}\p{N}])` with the `u` flag. Case-insensitive rules go in an `i`-flagged pattern and case-sensitive rules in a separate pattern; matches from both are merged, keeping the longest at any overlapping position. When two rules share a source term, the first line wins.
- **Rationale**: A single alternation pass over one message is sub-millisecond even with 500 rules (SC-003). `\b` is ASCII-oriented and fails next to accented letters; the lookarounds treat any letter or digit in any script as a word character.
- **Alternatives considered**: Looping over rules with one search per rule (O(rules) passes); tokenising the message (language-specific).

## R5. Inline marking format

- **Decision**: Each matched occurrence becomes `[occurrence -> target]` in the model-facing copy, reusing the rule line's own arrow syntax.
- **Rationale**: The user already explains the arrow syntax to the model in their prompt, so the annotation needs no extra instruction and adds no language-specific text.
- **Alternatives considered**: XML-style tags (`<term target="…">`), which would need their own explanation.

## R6. Placeholders for invariant rules

- **Decision**: Each matched occurrence of an invariant rule becomes `⟦n⟧` (n counting from 1 per message). After the model call, every `⟦n⟧` in the reply is replaced with that rule's exact target text. The code adds no model-facing text of its own; the user's prompt explains that `⟦n⟧` markers are copied into the reply unchanged (quickstart shows an example line).
- **Rationale**: FR-007 keeps every piece of model-facing wording in the user's configuration. The mathematical brackets rarely appear in ordinary text and survive translation as symbols.
- **Alternatives considered**: A fixed instruction section appended by code on turns with placeholders (hardcoded wording in one language, which FR-007 rules out).

## R7. Replies are returned whole

- **Finding**: The web chat receives the reply in one JSON response from `ConversationController::sendMessage`; provider streaming (`config('ai.stream')`) is collected server-side before the response is built. Discord and Telegram also receive whole replies.
- **Decision**: Placeholder restoration and the missing-term check run once on the complete reply text, server-side, before it is stored and returned. The spec was corrected to drop the streamed-piece scenarios.

## R8. Where the behaviors hook in

- **Decision**:
  - Web chat: `ConversationController::sendMessage`, after the user message is stored and creator-mode tags are stripped, transform only `$validated['messages'][$lastUserIndex]['content']` (FR-008a). Retrieval keeps using the unmarked text. After the model call (both the agent-loop and plain `chat` branches), restore placeholders in `$content` before TTS parsing and storage. When `highlightMissing` is on, add `missingTerms` to the JSON response.
  - Discord: `ConversationController::sendDiscordMessage`, marking and swap on the incoming content, whichever Discord user wrote it.
  - Telegram: `TelegramPollCommand`, marking and swap on the incoming text.
- **Rationale**: These are the three places a user's own message reaches the assistant's model. Resident-to-resident turns and image/scene prompts carry no user message and stay untouched.

## R9. Validation on save

- **Decision**: A validation rule checks the picked section for lines with `->` that fail to parse and fails with one message per failing line. It runs in `AssistantPromptController@update` (prompt saves, manual or JSON) and in `AssistantController@store/update` when `agent_config.termRules` or `prompt` is present, always against the prompt and section that will be saved together.
- **Rationale**: FR-014 requires refusing either save while the picked section holds a broken rule line.

## R10. Showing missing terms

- **Decision**: The response carries `missingTerms: [{ target, ranges: [[start, length], …] }]`, where the ranges point into the user's message as typed (UTF-16 offsets, so they index JavaScript strings directly). The chat keeps it on the in-memory message objects only; `ChatMessage` underlines those ranges in the user message and renders a warning line under the reply. Nothing is stored (clarification: live only).
- **Rationale**: Computing ranges server-side keeps one matcher; UTF-16 offsets avoid re-matching in the browser.
