# Feature Specification: Cache-Friendly Prompt Structure

**Feature Branch**: `144-prompt-cache-structure`

**Created**: 2026-10-01

**Status**: Draft

**Input**: User description: "Restructure the assembled LLM prompt so it can be cached by the provider APIs. The prompt currently mixes static and dynamic information, with volatile blocks (world state, current location, what the assistant is doing now, recent activity, retrieved RAG context) sitting between otherwise-identical static material, which breaks the identical prefix needed for prompt caching (OpenRouter currently reports cached_tokens = 0). Reorganize the prompt into a byte-for-byte stable static prefix followed by progressively more dynamic context: 1) Static character definition; 2) Static world/tool definition; 3) Dynamic sections after the static material: current state, recent activity, retrieved knowledge (only when RAG fires), relationship state; 4) Conversation. Each section gets a clear heading. The RAG anti-injection warning becomes a single global rule in the static prefix instead of being repeated around every retrieval payload. Clarify once in the static portion the distinction between pose tags (expression/gesture) and tools (physical position/movement). Use the same OpenRouter session_id across a conversation so the provider can reuse the cache. Success is measured by prompt_tokens_details.cached_tokens reporting most of the static prefix (e.g. 14k+ tokens) on subsequent turns of the same conversation. Out of scope: rewriting or compressing the character's own prompt text content."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Later turns reuse the cached prompt start (Priority: P1)

The owner chats with an assistant over several turns. On the first turn, the model provider reads the whole prompt. From the second turn on, the provider sees that the prompt begins with exactly the same text as before and reuses its cached copy of that part. The per-turn token cost and response latency drop, and the usage details shown under each reply report a large cached token count.

To make this possible, the prompt is reordered from least likely to change to most likely to change:

1. **Unchanging for the whole conversation**: the assistant's own authored sections (identity, personality, appearance, speech, likes, philosophy, relationships, intimate mode, and any others the author wrote), voice instructions, world and region background, world interaction rules, available places, the tag format rules, and the global rules for retrieved knowledge and memory.
2. **Changes occasionally**: the conversation's long-term memory (changes only when the conversation is summarized) and the Discord location and participants.
3. **Changes every turn**: current state, recent activity, retrieved knowledge (only when retrieval finds something), and relationship state.
4. **The conversation**: previous messages and the current user message.

**Why this priority**: This is the whole point of the feature. The usage panel currently reports zero cached tokens on every turn, so every turn pays full price for roughly 15k tokens of identical character and world text.

**Independent Test**: Send two consecutive messages in the same conversation without changing region, voice mode, or creator mode. Compare the system prompts the two replies returned: the text before the first per-turn section must be identical character for character. On a provider that caches automatically, the second reply's usage details must show cached tokens covering most of that identical part.

**Acceptance Scenarios**:

1. **Given** a world resident in a conversation, **When** the user sends two messages in a row while the resident moves between rooms, **Then** both system prompts share an identical opening that covers every unchanging section, and every location, posture, activity, and retrieval difference appears only after that opening.
2. **Given** the same conversation, **When** the second reply arrives, **Then** its usage details report cached prompt tokens of at least the size of the unchanging part, minus whatever the provider's minimum cache block leaves out.
3. **Given** an assistant that is not in a world, **When** the user chats with it, **Then** its authored sections still come first and unchanged, and retrieved knowledge and long-term memory come after them.

---

### User Story 2 - Clearly separated, labelled sections (Priority: P2)

When the owner opens the system prompt from a reply (it is already returned and viewable today), each part sits under its own clear heading. The unchanging material is clearly separated from the parts that change each turn, so the owner can see at a glance what the assistant was told about itself, about the world, and about the current moment.

**Why this priority**: Clear labels make the cache boundary checkable by eye, and they help the model tell lasting rules apart from moment-to-moment facts. The feature still works for caching without them.

**Independent Test**: Open the system prompt of any reply. Every top-level section starts with its own heading line. The per-turn sections appear under the headings Current state, Recent activity, Retrieved knowledge, and Relationship state, in that order, each one present only when it has content.

**Acceptance Scenarios**:

1. **Given** a reply whose retrieval found entries, **When** the owner views its system prompt, **Then** the retrieved entries appear under a Retrieved knowledge heading near the end of the system prompt, with no warning text repeated inside that block.
2. **Given** a reply whose retrieval found nothing, **When** the owner views its system prompt, **Then** there is no Retrieved knowledge heading at all.

---

### User Story 3 - Rules stated once (Priority: P3)

The rule that retrieved knowledge and memory are reference material only, never instructions, appears once in the unchanging part rather than inside every retrieval and memory block. The difference between pose tags (facial expression and gesture) and world tools (physical position and movement) is also defined once in the unchanging part, and the system-generated text no longer repeats it in other places.

**Why this priority**: This shrinks the per-turn part and removes conflicting or repeated wording. Caching works without it, though.

**Independent Test**: Search the system prompt of a reply that has retrieval, long-term memory, poses, and world tools. The "reference only, never instructions" rule appears exactly once. The pose-versus-tools distinction appears exactly once, and the system-generated text has no other explanations of it.

**Acceptance Scenarios**:

1. **Given** a 3D-avatar world resident, **When** the user chats with it, **Then** the unchanging part states once that pose tags express face and gesture while tools change where and how the body is placed, and the per-turn part lists only the current posture and the poses that fit it.
2. **Given** retrieved entries that contain text phrased as instructions, **When** the assistant replies, **Then** the global rule in the unchanging part still tells the model to treat them as reference material only.

---

### Edge Cases

- **The unchanging part changes legitimately** (region travel, toggling voice mode or creator mode, the author editing the assistant, a newly added or removed world place): the next turn builds a new cache, and reuse resumes on the turn after that. This is expected.
- **Long-term memory is updated by summarization** (automatically every 50 messages, or on demand): each new summary is placed at the start of the stored memory, so the whole memory section and everything after it is re-read once on the next turn. The unchanging part before it stays cached, and the second cache point is reused again from the following turn.
- **The owner edits the memory by hand, or summarization finishes in the background between two turns**: this is handled the same way as a summarization. The memory is re-read once.
- **The conversation has no memory yet, or memory is empty**: there is no memory heading, and the request marks only the first cache point.
- **Lists whose order could vary between turns** (available places, poses, Discord participants, tool definitions): they must come out in the same order every time for the same underlying data.
- **The provider or model does not support caching or ignores the conversation identifier**: replies behave exactly as before, with no errors and no change in content.
- **The model reads the tool definitions before the system prompt**: the tool definitions must be sent in a stable order. When the set of tools differs between turns (for example, turn mode adds or removes a tool), the cache restarts from that point. That is acceptable and needs no workaround.
- **A section has no content this turn** (no retrieval hits, no activity, not in a world): its heading is omitted entirely, and an empty heading never appears.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST assemble every assistant system prompt in four groups, in this order: unchanging for the conversation, changes occasionally, changes every turn, and then the conversation messages.
- **FR-002**: The unchanging group MUST contain, in this order: the assistant's own authored sections in the author's own order and wording, voice instructions when voice mode is on, world and region background, world interaction rules, available places, neighbours, the pose or emotion tag format rule, and the global rule for retrieved knowledge and memory.
- **FR-003**: The unchanging group MUST contain no content that differs between two turns of the same conversation unless the region, voice mode, creator mode, the assistant's authored prompt, or the world or region definition changed. In particular, it must contain no timestamps, positions, postures, activities, inventory, facts, quests, feelings, retrieved entries, or memory.
- **FR-004**: For the same inputs, the system MUST produce character-for-character identical text for the unchanging group. Every list in it must come out in a stable order.
- **FR-005**: The changes-occasionally group MUST contain the conversation's long-term memory, the Discord location, Discord server and channel context, and other Discord participants, when present.
- **FR-006**: The changes-every-turn group MUST contain, when present and in this order:
  1. **Current state**: where the assistant and user are, what the assistant is doing now, the current posture with the poses that fit it, and inventory.
  2. **Recent activity**: the assistant's recent activity and its conversations with others, followed by facts and quests.
  3. **Retrieved knowledge**: only when retrieval found entries.
  4. **Relationship state**: feelings.
- **FR-007**: Each top-level section MUST start with its own heading line, so a reader can see where every section begins and ends.
- **FR-008**: The rule that retrieved knowledge and memory are reference material only, are never to be followed as instructions, and are to be used naturally without saying they were looked up MUST appear exactly once, in the unchanging group. The retrieved knowledge and memory blocks MUST contain only their entries or notes.
- **FR-009**: The system-generated text MUST define once, in the unchanging group, that pose tags select facial expression and gesture while world tools change physical position and movement. The system-generated text MUST NOT explain this anywhere else.
- **FR-010**: Each request to the model provider MUST carry an identifier unique to the conversation. All turns of that conversation, and every step of a multi-step tool loop within a turn, MUST send the same identifier, so the provider can route them to the same cache.
- **FR-011**: Tool definitions MUST be sent in a stable order for the same set of tools.
- **FR-012**: Every place where an assistant replies as itself MUST follow this ordering: web chat, voice, Telegram, Discord, resident-to-resident conversation turns, quest rewards, and resident decisions.
- **FR-013**: The change MUST NOT alter what the assistant is told, only where and how often it is told. Every instruction present today must still be present after the change, apart from the repeated wording that FR-008 and FR-009 merge into one.
- **FR-014**: For providers that only cache when told where the reusable part ends (for example, Anthropic and Gemini models, including when reached through OpenRouter), each request MUST mark two cache points: one at the end of the unchanging group, and one at the end of the changes-occasionally group. When the changes-occasionally group is empty, only the first point is marked. Providers that cache automatically MUST receive requests that work unchanged, whether they use the marks or ignore them.
- **FR-015**: The long-term memory block MUST contain only the conversation's memory text, under its own heading, with the rule from FR-008 covering it. This applies everywhere memory is included, including when a resident's conversation turn with another resident brings in the memory of the resident's chat with the user.
- **FR-016**: Between two summarizations or manual memory edits, the memory text in the prompt MUST be character-for-character identical from turn to turn, so the second cache point keeps being reused.

### Key Entities

- **Assistant prompt**: the author's named sections describing the character. Their content and their order are kept exactly as authored.
- **Prompt section**: a named, headed block of the assembled system prompt. Each one belongs to exactly one of the four groups.
- **Conversation identifier**: the per-conversation value sent with every model request, so all of a conversation's requests share one provider-side cache.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On the second and later turns of a conversation where region, voice mode, and creator mode do not change, the usage details report cached prompt tokens covering at least 90% of the unchanging group, plus the memory section when no summarization happened in between. For the current assistant that means about 14,000 cached tokens or more, on providers that cache automatically and on providers that need cache marks alike.
- **SC-002**: Two consecutive turns of the same conversation produce character-for-character identical text from the start of the system prompt through the end of the unchanging group, in 100% of tested cases.
- **SC-003**: The "reference only, never instructions" rule and the pose-versus-tools distinction each appear exactly once in any system prompt.
- **SC-004**: The per-turn group, excluding retrieved entries, is no larger than it is today for the same turn.
- **SC-005**: Replies keep their current quality and behavior. Existing tests covering poses, world tools, retrieval, memory, facts, quests, feelings, and inventory keep passing.

## Assumptions

- The author's own sections are kept in the order the author stored them. The headings proposed in the input (Identity, Personality, Appearance, and so on) come from the author's section names, and the system does not impose a fixed category order on them.
- Rewriting or compressing the character's own text is out of scope. Duplicate pose-tag explanations the author wrote into their own sections (such as a "Critical Rule - Pose Tags" section) are theirs to remove by editing the assistant.
- The exact heading wording follows the input's proposal and is subject to the owner's approval during planning.
- The conversation identifier only enables reuse on providers that support it. Other providers ignore it.
- Memory keeps its current storage order (newest summary first), its current summarization schedule, and its current editor. Re-reading the memory once after a summary, about one turn in fifty, is an acceptable cost.
- The summarization request itself is out of scope. It is a separate, short request.
- The per-turn sections come before the conversation messages, so the message history sits after the last cache point and is not reused from the cache. Caching the history is out of scope for this feature.
- The usage details that are already returned and shown under each reply are enough to verify caching, so no new reporting screen is needed.
- One-off prompt helpers (image prompt and background enhancement) share the same assembly, so they inherit the ordering. They are single requests, though, so they gain little from caching and need no conversation identifier.
