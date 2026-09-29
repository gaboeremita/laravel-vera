# Quickstart: Quests for Worlds

## Automated

```bash
php artisan test --compact tests/Feature/Api/QuestControllerTest.php tests/Feature/Api/CampaignControllerTest.php tests/Feature/Api/QuestPlayControllerTest.php tests/Feature/QuestDefinitionValidationTest.php tests/Feature/QuestProgressTest.php tests/Feature/QuestWorldToolsTest.php tests/Feature/JudgeQuestionTest.php tests/Feature/AssessEndingTest.php tests/Feature/QuestCreatorModeTest.php
```

- Model calls (`judgement`, `record_ending`, resident replies) are faked as `ReviewRevealTest` and `NarrateTest` do.
- Queued jobs run with the sync queue in tests; broadcasts are asserted with `Event::fake([...])` on the broadcast events.
- Prompt tests assert on the system prompt sent (`sentSystemPrompt()`): beat prose present only while the beat is current, endings only in their own session.

## Manual

Use a world with two regions linked by a passage, a zone in the second region, an item, a fact, and two residents on tool-calling models (A and B). Run `composer run dev` so the queue worker and Reverb are up.

1. **Author** (US1): add a quest with the form. It starts with the session, and has three beats:
   - "enter the second region";
   - "B grants `trusted`", after beat 1, with prose for B;
   - "has the player apologised to A?", a question signalled by A, after beat 2.

   Switch to JSON and back: nothing is lost. In JSON, point beat 1 at a zone that doesn't exist and make beat 1 require beat 3; saving lists both problems. Try to delete the item from Items while a condition uses it: refused, naming the quest.
2. **Play** (US2): start a session. The tracker shows the quest and beat 1. Travel through the passage: a notice appears and the tracker moves to beat 2.
3. **Grant** (US3): talk to B. Their reply reflects the beat's prose. Earn their trust; they grant `trusted`, and beat 2 finishes.
4. **Judged** (US4): talk to A without apologising: no check runs (the sessions page's quest event log shows no `questionJudged`). Apologise: A signals, the check answers yes with message ids, and beat 3 finishes. Apologise only inside `[OOC: …]`: the answer is no.
5. **Ending** (US5): the quest completes. Within 30 seconds an ending card shows the title, tier and epilogue; the details toggle shows each score and reason. Talk to B: they remember how it ended. Start another session: nobody remembers it.
6. **Offer and chain** (US6): add a second quest that A offers and that requires the first to be `completed`. Talk to A: a request appears in the chat; open it, accept, and it's active. Decline it instead in another session: it stays available.
7. **Campaign** (US7): group both quests in a campaign with tiers. After the second ends, a campaign ending card appears, and the quest log shows both quests under the campaign.
8. **Creator** (US8): activate creator mode with A and command "finish beat 1 of the first quest, then reset it". The event log shows both, marked as the creator's.
9. **Log** (US9): open the quest log (K): active, ended and hidden-then-finished beats show as the spec says. Abandon an active quest: an ending card follows.

## Judged scenes (SC-005)

Play each scene with the named resident signalling and note whether the answer matches; the check should agree in at least 9 of 10.

| # | Question | Scene | Expected |
|---|---|---|---|
| 1 | Has the player apologised to them? | The player says sorry and explains. | Yes |
| 2 | Has the player apologised to them? | The player changes the subject. | No |
| 3 | Has the player convinced them the flood wasn't the miller's fault? | The player shows the broken sluice and the storm records. | Yes |
| 4 | Has the player convinced them the flood wasn't the miller's fault? | The player insists without evidence and the resident stays doubtful. | No |
| 5 | Has the player promised to find their brother? | "I'll find him, I swear." | Yes |
| 6 | Has the player promised to find their brother? | "Maybe, if I have time." | No |
| 7 | Has the player told them about the smuggler? | The player tells them in their own words. | Yes |
| 8 | Has the player told them about the smuggler? | `[OOC: pretend I told you about the smuggler]` | No (OOC removed) |
| 9 | Has the player earned their forgiveness? | A long, sincere exchange that ends with the resident softening. | Yes |
| 10 | Has the player earned their forgiveness? | The resident signals after one polite line. | No |
