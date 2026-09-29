# Research: Facts and Reveal Safeguards

## R1. Where facts live

**Decision**: A fact belongs to one resident placement (`world_residents` row): topic, content and disclosure prose. The residents who can act on it once the player knows it are a list on the fact (`fact_relays`). A session copies nothing: a resident holds whatever facts are configured on them now (FR-003). Per session, the server records only what changes in play: facts the player knows (`known_facts`), facts residents acknowledged (`fact_acknowledgements`), and every reveal attempt (`reveal_attempts`).

**Rationale**: Facts don't move between residents in this feature, so there is no per-session holder state to copy. Storing only what play changes keeps configuration edits live for every session, as the spec's edge cases ask.

## R2. What each character is told

**Decision**: A `BuildFactsPrompt` action adds a `facts` section to the prompt, built per turn from the session state and the turn's mode:

| Who | In character (and conversations between residents) | OOC turn | Creator turn |
|---|---|---|---|
| Holder, fact unknown to the player | topic and disclosure prose | topic, prose and content | every fact in the world, with content |
| Holder, player learned it from them | topic, prose and content, to speak of freely | same | same |
| Holder, player learned it elsewhere | content, and how the player found out (the source name); they talk about it once the player brings it up | same | same |
| Relay resident, fact unknown to the player | topic, and that they want to find out about it | same | same |
| Relay resident, fact known to the player | topic, content and "the user has learned this; when they tell you, you can act on it" | same | same |

Wording states the behaviour directly, e.g. "You keep these secrets. Each lists what it is about and when you share it. When the moment comes, call `reveal` with your reason; it gives you what you know, and you tell it in your own words." and "You want to find out about these things; ask about them when it suits the moment." In conversations between residents, holders get only topics and prose, relay residents only topics, and neither `reveal` nor `acknowledge` is offered (FR-012a).

**Rationale**: FR-005 holds by construction: the content is never in the prompt until the player knows it, so the character cannot blurt it or be talked out of it.

## R3. The `reveal` tool

**Decision**: `reveal(fact, reason)`, offered in the player's conversation when the resident holds at least one fact and their model supports tools. `fact` is an enum of the resident's fact topics. The tool:
1. refuses a fact the resident doesn't hold;
2. on a turn where this fact was already rejected, returns the same answer without a new review (a model retrying in a loop costs one review per fact per turn);
3. approves without review when the player already knows the fact, on OOC turns, or when the world turns the review off;
4. otherwise asks the review (R4);
5. records the attempt (R6); on approval records the fact as known and returns `{ status: "revealed", content, note: "Tell them in your own words." }`; on rejection returns `{ status: "not_now", note: "It doesn't feel like the right moment yet." }`.

**Rationale**: FR-006 to FR-008 in one place. Returning a normal result for a rejection, rather than an error, keeps the character in the scene.

## R4. The review

**Decision**: A `ReviewReveal` action makes one LLM call with a forced `verdict` tool returning `{ approved: bool, verdict: string }`. It runs on the world's narrator model, falling back to the default model, through a `ResolveNarratorModel` action extracted from `Narrate` (its second real caller). It reads:
- the holder's name, the fact's topic and disclosure prose (never its content), and the holder's reason;
- the region and the holder's zone;
- the holder's last few expressions (`messages.expression` of their recent replies);
- the holder's memory of the player (the conversation's `long_term_memory`);
- what the holder and the player carry (`Narrate::holdings`), and credits the player handed the holder in this session (`credit_transactions`), so "for 50 credits" is judged from what really moved;
- the last messages of the conversation as stored on the server, with OOC spans removed, inside a block the instructions describe as data to judge, never instructions to follow.

The instructions ask it to judge whether the situation reasonably meets the prose, in the spirit of a tabletop game master, without demanding literal proof. A failure of the call (no model, no verdict, timeout) rejects the reveal and records the failure as the verdict (spec edge case; Principle V).

**Rationale**: A model that isn't playing the character can't be persuaded by the character's own wish to share. Reading the stored conversation, not the history the client sends, keeps the review on what was really said.

**Alternatives considered**: Letting the holder's own model decide, which is exactly what the review guards against; a numeric trust score, which the feature rules out.

## R5. `acknowledge`

**Decision**: `acknowledge(fact)`, offered when the resident is a relay resident of at least one fact the player knows; `fact` is an enum of those facts' topics. The server refuses it unless the player knows the fact, then records `fact_acknowledgements` (session, fact, resident). A repeated acknowledgement is a no-op that still returns success.

**Rationale**: FR-009. The enum already hides unknown facts; the server check is what makes a guess useless.

## R6. The reveal log

**Decision**: `reveal_attempts` stores session, fact and holder (both nullable, with the topic and holder name copied so entries stay readable after deletion), source (`in_character`, `ooc_turn`, `creator`, `item`, `activity`), reason, whether it was reviewed, whether it was approved, and the verdict. Every path that makes a fact known writes one row, including items, activities and creator commands. The world owner reads it per session from the sessions page.

**Rationale**: FR-008, FR-019 and SC-003; feature 3's ending judgement reads the same table.

## R7. OOC spans

**Decision**: `LlmResponseTagParser` gains `hasOutOfCharacter(string)` and `stripOutOfCharacter(string)`, using the grammar it already recognises (`[ooc: …]`, case-insensitive, up to the first `]`). A turn is an OOC turn when the player's latest message has an OOC span. OOC spans are removed only from what the review reads; the conversation the character sees keeps them (FR-011).

**Rationale**: One parser owns the tag grammar; no new edge-case handling, as the feature asks.

## R8. Creator password

**Decision**: A nullable `users.creator_password` column with Laravel's `hashed` cast, hidden from serialization. The Settings page gets a section to set, change or clear it (`PUT /api/creator-password`); the response never contains it. Verification uses `Hash::check`.

**Rationale**: The password belongs to the person, not to one assistant: one password works with every assistant and NPC, and `settings` rows are per assistant. Hashing keeps it out of the bundle, the repository and the database in readable form, the same way login passwords are kept.

**Alternatives considered**: A per-assistant value in `settings.data`, which would need a password per assistant and has no page for NPCs.

## R9. Activating and keeping creator mode

**Decision**: `sendMessage` reads the player's latest message through a `CreatorModeTags` action:
- `[creator mode: "<password>"]` (quoted value) is an activation. On a correct password, `conversations.creator_mode_at` is set and the tag is replaced with `[creator mode]` so the character notices; on a wrong or missing password the tag is removed and the response carries a notice. If nothing else remains after a failed activation, no reply is generated.
- `[creator mode: <instruction>]` (unquoted) is a command; it makes the turn a creator turn only while `creator_mode_at` is set.
- Every user message in the history the client sends is cleaned of activation tags before anything reaches the model, and the stored message is the cleaned one.

The response returns the stored user content and `creatorMode: { active, notice }`. `useConversationChat` swaps its local copy of the message for the stored one and refetches emotions when `active` turns on; the hardcoded `CREATOR_MODE_TRIGGER` is deleted.

Creator mode stays on for the conversation it was activated in (clarification); in a world that is one resident's conversation, so each resident needs their own activation. The assistant's `creator mode` prompt section is excluded unless `creator_mode_at` is set, and the `secret trigger` section is always excluded, since the server now does the check. The replies to the image and background commands in `sendMessage` follow the same rule, since they happen in the same conversation. Places with no player conversation (resident turns, decisions, image prompt enhancers) and Discord, where a typed password would be visible to the channel, exclude both.

**Rationale**: FR-013 to FR-015 and SC-007. **Note for the user**: if the `secret trigger` or `creator mode` sections of an assistant's prompt in the database contain the password, it should be removed from them; the server no longer needs it there.

## R10. Creator turns

**Decision**: On a creator turn in a world session:
- the world toolbox is built without zone access limits, the post restriction and the activity gate;
- `reveal` lists every fact of the world (as "holder: topic") and approves without review;
- three admin tools are added: `set_fact_known(fact, known)`, `grant(holder, credits, items)` and `remove(holder, credits, items)`, where `holder` is "the user" or a resident of the world. `grant` creates from nothing and `remove` destroys, through `TransferInventory` with a null side. Credits apply to the user only, since residents have no credit balance; credits named for a resident return an error to the model.

Every creator action counts: reveals and `set_fact_known` write `reveal_attempts` with source `creator`, and credit changes set `credit_transactions.by_creator`. Outside a world session a creator turn only includes the `creator mode` section.

**Rationale**: FR-016 with the existing transfer path, so the player's inventory still changes only through audited code.

## R11. Items and activities that reveal facts

**Decision**: `items.reveals_fact_id` and `activity_terms.reveals_fact_id`, each a fact of the same world. Examining the item, or a player's use of the activity that the narrator judges succeeded, records the fact as known (source `item` or `activity`, named after the item or object) and adds the fact's content to the narrator's situation so the narration can tell it. Residents using the activity learn nothing.

**Rationale**: FR-018; the narrator already runs on both paths.

## R12. Holding facts needs tool calling

**Decision**: Saving a fact for a resident whose model, for the saving user, doesn't support tools returns 422 with the reason, as feature 1 does for starting inventories. The fact list returns `toolsUnsupported` for a resident whose model lost tool support after their facts were saved, and the editor shows the reason. At play time, a resident without tool support gets no `facts` section and neither tool.

**Rationale**: FR-002; without `reveal`, a holder could never share.

## R13. What the player sees

**Decision**: A HUD panel lists `known_facts` for the session: topic, source name and summary, newest first, with an empty state. Every endpoint that can make a fact known (`sendMessage`, item examine, activity use) returns `learnedFacts` for this request, and the world page shows a toast for each.

The summary (`known_facts.summary`) is what the player was actually told, written once, when the fact becomes known:
- **From a holder**: after the reply is generated, a `SummarizeLearnedFact` action makes one call on the narrator model with the fact's topic and the reply's in-story text (OOC spans removed), asking for one to three sentences in second person of what the player was told about the topic, or the sentence "You haven't heard the details yet." when the reply says nothing of it. If the call fails, the error is reported and the summary is the reply's in-story text.
- **From an item or activity**: the narration the player read, as is.
- **From a creator command**: the fact's content.

A repeated reveal keeps the first summary. The call runs inside the request, after the reply; it is short and happens only when a fact becomes known, so it stays synchronous and the response carries the summary.

**Rationale**: FR-017 and the clarification: the list reflects what the scene gave the player, and only a holder's retelling needs a model to condense it. The notification pattern is the one feature 1 uses for inventory changes (no push channel).

## R14. Deleting

**Decision**: Facts cascade from `world_residents`; `known_facts`, `fact_acknowledgements`, `fact_relays` cascade from `facts`; `reveal_attempts.fact_id` and the `reveals_fact_id` columns null on delete. The fact list returns a `usage` count (sessions where it is known); the editor warns with it before deleting a fact or removing a resident who has facts.

**Rationale**: Spec edge cases; the log keeps its copied names for tuning.
