# Feature Specification: Term Rules Applied From the Assistant's Prompt

**Feature Branch**: `143-prompt-term-rules`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Term rules (a source term that must be rendered as an exact target term) are written as plain text in one of the assistant's existing prompt sections; with no other change the model reads them like any other prompt text. The feature adds optional behaviors, each enabled by its own checkbox on the assistant create/edit form, that read the rules from that prompt section and improve adherence without extra model calls: inline marking of matched terms in the model-facing copy of the user's message, an exact swap for invariant terms, and a highlight of missing terms while the reply streams. Nothing is hardcoded: no languages, inflection logic or glossary content. One model call per turn, negligible added latency, since the main use is real-time translation."

## Clarifications

### Session 2026-09-30

- Q: What does a rule line look like? → A: `source, source variants -> target, target variants`, with optional trailing marks `(invariant)` and `(case)`, for example `hearing, hearings -> audiencia, audiencias`. The first term on each side is the rule's term; the rest are its variant forms.
- Q: How does the code know which prompt section holds the rules? → A: A dropdown next to the checkboxes on the assistant form picks one of the assistant's existing prompt sections.
- Q: How are missing target terms shown? → A: A warning line under the reply lists each missing target term, and in the user's message the source terms whose target is missing are underlined.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Rules in the prompt, matched terms marked in the message (Priority: P1)

The user writes term rules as plain text lines in one of the assistant's prompt sections, one rule per line, for example `hearing, hearings -> audiencia, audiencias`. On the assistant's edit form they pick that section in the dropdown next to the checkboxes and tick the inline marking checkbox. From then on, whenever the user sends a message, every rule term found in it is annotated in place with its required target term in the copy the model receives, so the model sees the requirement right next to the word. The message the user sees in the chat and the stored message stay exactly as typed.

**Why this priority**: The rules alone already work through the prompt; marking the matched terms in the message is the change that raises adherence most for real-time use, where there is no second pass.

**Independent Test**: Give an assistant a prompt section with two rules and tick inline marking. Send a message containing one of the source terms and confirm the model-facing message carries the annotation for that term only, while the chat and the stored message show the text as typed. Untick the checkbox, send the same message, and confirm the model-facing message is unchanged.

**Acceptance Scenarios**:

1. **Given** inline marking is on and the rules include a source term, **When** the user sends a message containing that term, **Then** the model-facing copy annotates that occurrence with the rule's target term.
2. **Given** the same setup, **When** the message is shown in the chat or read back from history, **Then** it appears exactly as the user typed it.
3. **Given** a message containing several rule terms, including a term that appears more than once, **When** it is sent, **Then** every occurrence of every matched term is annotated.
4. **Given** a message containing no rule terms, **When** it is sent, **Then** the model-facing copy is identical to what the user typed.
5. **Given** a rule lists variant forms of its source term, **When** the message contains a variant, **Then** that occurrence is annotated with the rule's target term.
6. **Given** a rule term appears inside a longer word, **When** the message is sent, **Then** that occurrence is left unmarked.
7. **Given** inline marking is off, **When** the user sends any message, **Then** the model-facing copy is identical to what the user typed, and the rules still reach the model through the prompt.

---

### User Story 2 - Exact swap for invariant terms (Priority: P2)

Some rules carry the `(invariant)` mark at the end of their line: the target text is used verbatim and never changes form (names, acronyms, terms kept untranslated). With the exact swap checkbox ticked, each invariant term found in the user's message is replaced by a placeholder in the model-facing copy, and the placeholder is replaced by the exact target text in the reply the user sees, including while the reply streams.

**Why this priority**: It guarantees exactness for the terms where a guarantee is possible, but it covers only invariant terms; marking in User Story 1 covers all of them.

**Independent Test**: Give an assistant a rule marked invariant and tick exact swap. Send a message containing that term and confirm the model-facing copy carries a placeholder, the displayed reply contains the exact target text where the model wrote the placeholder, and no placeholder is ever visible in the chat, even mid-stream.

**Acceptance Scenarios**:

1. **Given** exact swap is on and an invariant rule's term appears in the message, **When** the message is sent, **Then** the model-facing copy contains a placeholder in its place.
2. **Given** the model's reply contains the placeholder, **When** the reply streams to the user, **Then** the user sees the exact target text of the rule and never the placeholder.
3. **Given** a placeholder arrives split across two streamed pieces of the reply, **When** the reply streams, **Then** the user still sees the exact target text once, with no fragment of the placeholder.
4. **Given** the model's reply omits the placeholder, **When** the reply completes, **Then** the reply is shown as written and nothing is inserted.
5. **Given** a rule without the invariant mark, **When** its term appears in the message, **Then** exact swap leaves it alone (inline marking, if on, still applies).
6. **Given** the reply is stored in the conversation history, **When** it is read back, **Then** it contains the exact target text, with no placeholder.

---

### User Story 3 - Highlight of missing target terms (Priority: P3)

With the highlight checkbox ticked, while the reply streams the system checks that, for every rule whose term was found in the user's message, the rule's target term appears in the reply. When the reply finishes without it, a warning line under the reply lists the missing target term, and the source term that required it is underlined in the user's message, so the user (for example, someone interpreting aloud) can correct it on the spot. Nothing is blocked, delayed or retried.

**Why this priority**: It catches the misses the model still makes, but only as a warning; the other two stories are what reduce the misses.

**Independent Test**: Give an assistant a rule and tick the highlight. Send a message containing the rule's term, get a reply that uses a different word, and confirm the chat shows that the rule's target term is missing. Get a reply that uses the target term and confirm no highlight appears.

**Acceptance Scenarios**:

1. **Given** the highlight is on and a rule's term was found in the message, **When** the reply finishes without the rule's target term, **Then** a warning line under the reply lists the target term, and the source term in the user's message is underlined.
2. **Given** the same setup, **When** the reply contains the target term, **Then** the warning line leaves that rule out and its source term stays without underline; when every rule is satisfied, no warning line appears.
3. **Given** a rule lists variant forms of its target term, **When** the reply contains one of them, **Then** the rule counts as satisfied.
4. **Given** the highlight is on, **When** the reply streams, **Then** the reply appears at the same pace as with the highlight off.
5. **Given** the highlight is off, **When** a reply misses a target term, **Then** nothing is shown.

---

### Edge Cases

- A source term of one rule is part of a longer source term of another rule (for example "court" and "supreme court"): the longest match wins, and the overlapping shorter term is left unmarked for that occurrence.
- Two rules share the same source term: the first rule in the section wins.
- Rule lines that don't follow the format are ignored by the code behaviors and still reach the model as prompt text.
- No section is picked in the dropdown, or the picked section is empty or has since been deleted: the checkboxes have no effect, and messages and replies pass through unchanged.
- A checkbox is ticked while no rules exist: the form still saves, and the behavior simply finds nothing to apply.
- Case: matching follows each rule's case setting; the default is case-insensitive.
- Scripts written without spaces between words: word-boundary matching can't separate words there, so rules in those scripts may match inside longer words. This feature accepts that limitation.
- The assistant replies through another channel (Telegram, Discord): inline marking and exact swap apply there too; the highlight appears only in the web chat.
- The user edits the rules mid-conversation: the next message uses the rules as saved.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Term rules MUST be written as plain text lines in the assistant's existing prompt; the feature MUST add no separate storage or editor for the rules.
- **FR-002**: The assistant create and edit forms MUST offer a dropdown, next to the checkboxes, listing the assistant's existing prompt sections; the code behaviors MUST read the rules only from the section picked there.
- **FR-003**: A rule line MUST express a source term, a target term, and optionally variant forms of the source term, variant forms of the target term, an invariant mark and a case-sensitive mark, in the form `source, source variants -> target, target variants`, followed optionally by the marks `(invariant)` and `(case)`. The first term on each side is the rule's term and the rest are its variant forms, all separated by commas. The line MUST stay readable to the model as prompt text.
- **FR-004**: The assistant create and edit forms MUST offer one checkbox for each behavior: inline marking, exact swap for invariant terms, and the missing-term highlight. All three MUST default to off.
- **FR-005**: With every checkbox off, the assistant's behavior MUST be identical to its behavior before this feature.
- **FR-006**: Matching MUST be plain text matching of each rule's source term and its variant forms at word boundaries, case-insensitive unless the rule is marked case-sensitive, preferring the longest match where terms overlap.
- **FR-007**: The system MUST contain no languages, language pairs, inflection logic or glossary content of its own; everything that varies by language MUST come from the rule lines.
- **FR-008**: With inline marking on, the model-facing copy of each user message MUST annotate every matched occurrence in place with the rule's target term.
- **FR-009**: The message shown in the chat and stored in the conversation MUST always be the text the user typed.
- **FR-010**: With exact swap on, every matched occurrence of an invariant rule's term MUST be replaced in the model-facing copy by a placeholder, and every placeholder in the reply MUST be replaced by the rule's exact target text before the user sees it, including across streamed pieces.
- **FR-011**: A stored reply MUST contain the swapped-in target text, with no placeholders.
- **FR-012**: With the highlight on, after each reply the system MUST determine, for every rule matched in the user's message, whether the rule's target term or one of its variant forms appears in the reply, and the web chat MUST show a warning line under the reply listing each missing target term, and underline, in the user's displayed message, the source-term occurrences whose target term is missing. The message text itself MUST stay as typed (FR-009).
- **FR-013**: Every behavior MUST work within the single model call already made per turn; none MAY add a model call, a retry or a wait before the reply starts streaming.
- **FR-014**: Rule lines that don't follow the format MUST be ignored by the behaviors and MUST leave the prompt unchanged.

### Key Entities

- **Term rule**: one line of text in the assistant's prompt, holding a source term, its target term, optional variant forms of each, and optional invariant and case-sensitive marks. It belongs to the assistant whose prompt contains it.
- **Behavior settings**: per assistant, the prompt section picked as the rule source and three on/off values (inline marking, exact swap, missing-term highlight), set on the create/edit form.
- **Model-facing message**: the copy of the user's message the model receives for a turn, possibly carrying annotations and placeholders; it is never shown to the user.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: With inline marking on, on a fixed test set of messages the share of replies that use the required target term for every matched rule is higher than with it off, on the same assistant and model.
- **SC-002**: With exact swap on, 100% of invariant terms whose placeholder the model returned appear in the displayed reply as the exact target text, and no placeholder is ever visible to the user.
- **SC-003**: With all three behaviors on, the time from sending a message to the first visible piece of the reply rises by less than 50 milliseconds, on a prompt holding 500 rules.
- **SC-004**: With the highlight on, 100% of replies that miss a required target term show it as missing, and replies that contain every required target term show nothing.
- **SC-005**: An assistant with all checkboxes off produces model-facing messages and displayed replies byte-for-byte identical to those produced before this feature.

## Assumptions

- The rules share the prompt with the rest of the assistant's instructions, and the model follows them through the prompt alone when every checkbox is off.
- The prompt section holding the rules keeps its place in the prompt from turn to turn, so providers that reuse an unchanged prompt prefix process the rules once.
- Only the user's messages are scanned for rule terms; messages from other participants (residents, other Discord users) are outside this feature.
- The highlight is shown only in the web chat; other channels have no place to show it.
- Rules apply in one direction, source to target, as written; the languages involved are whatever the user writes in the rules and the prompt.
- Temperature and model choice stay in the existing assistant settings; this feature adds none.
