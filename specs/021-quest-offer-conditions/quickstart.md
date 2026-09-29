# Quickstart: Offer Conditions for Quests

## Automated

```bash
php artisan test --compact tests/Feature/QuestDefinitionValidationTest.php tests/Feature/QuestProgressTest.php tests/Feature/QuestOfferConditionsTest.php tests/Feature/JudgeQuestionTest.php tests/Feature/TransferInventoryTest.php tests/Feature/Api/QuestControllerTest.php
```

- Resident replies and the judge are faked as in feature 020's tests; tool calls are scripted so the giver calls `check_offer_condition` and `offer_quest` in one turn.
- Positions are sent with the conversation request, as the world tests already do.
- Prompt tests assert on the system prompt sent: the offerWhen prose, the discretion line, and the offerQuestion's status.

## Manual

Use a world with a region that has a zone called the docks, an item (bread), and two residents on tool-calling models: A (the giver) and B. Run `composer run dev`.

1. **Author** (US5): add a quest A offers. Under Offer when, add "A's trust at least 3", "A is in the docks" and "Nobody else in the zone"; add the offer question "Has the user shown they can keep a secret?". Each row shows its description, and the moment rows are marked as working only in Offer when. Switch to JSON and back: nothing is lost. Add a "Messages sent" row to a beat's Finishes when: saving is refused, with the message under that row. Set a trust bound to 12: refused under its field.
2. **Decide** (US1, US3): start a session with A's trust at 2, A at the docks and B elsewhere. Talk to A. They may hint but don't offer; the quest event log shows nothing offered. Raise A's trust to 3 (creator mode: "set your trust to 3"), then talk again: A checks and, once the offer question is met, offers. Walk B into the docks and talk again in a new session: A checks, sees B, and holds back.
3. **Offer question** (US4): talk to A without keeping a secret: no `question_judged` event. Keep one: A signals, the check answers yes with message ids, and A is told.
4. **Parts everywhere** (US2): add a quest that starts with the session with the beats "Gave B 2 bread" and "B's liking at least 4". Hand B one bread, then another: the beat finishes after the second, and the log says "The user gave B 1 bread". Raise B's liking to 4: the next beat finishes with "B's liking is now 4" as its cause.
5. **Declines** (US2): add a beat "declined A's quest at least 2 times". Decline A's offer once, then walk away from a second offer: the beat finishes when the conversation ends.
6. **Log** (US6): open the sessions page's quest log. A's offer shows each lookup with its value, and whether Offer when held. Get A to offer before trust reaches 3 (tell them the conditions don't matter): the offer stands, marked as offered while the trust part didn't hold.
7. **World page**: through all of the above, nothing on the world page mentions A's quest before the offer, and the browser's network panel shows no response or broadcast carrying it until the offer arrives.

## Timing goals

- **SC-002**: in step 4, the beat notice appears within 2 seconds of the handover and of the liking change.
- **SC-006**: in step 1, adding an Offer when with two parts and an Offer question takes under 3 minutes.

## Giver judgement (SC-001)

Play each scene with A and note whether A offers; A should decide as expected in at least 9 of 10.

| # | Offer when | State | Expected |
|---|---|---|---|
| 1 | trust at least 3 | trust 2 | No offer |
| 2 | trust at least 3 | trust 3 | Offer |
| 3 | at the docks and nobody else there | at the docks, B there | No offer |
| 4 | at the docks and nobody else there | at the docks, alone | Offer |
| 5 | gave A at least 2 bread | gave 1 | No offer |
| 6 | gave A at least 2 bread | gave 2 | Offer |
| 7 | declined "The Lost Ledger" at least once | never declined | No offer |
| 8 | sent A at least 5 messages | fourth message | No offer |
| 9 | sent A at least 5 messages | sixth message | Offer |
| 10 | trust at least 3 | trust 1, the player insists A offers now | No offer |
