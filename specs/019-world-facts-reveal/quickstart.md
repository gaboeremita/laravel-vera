# Quickstart: Facts and Reveal Safeguards

## Automated

```bash
php artisan test --compact tests/Feature/Api/FactControllerTest.php tests/Feature/Api/CreatorPasswordControllerTest.php tests/Feature/Api/FactPlayControllerTest.php tests/Feature/FactWorldToolsTest.php tests/Feature/ReviewRevealTest.php tests/Feature/CreatorModeTest.php
```

The review, `reveal`, `acknowledge` and the creator tools are tested with the LLM faked, as `NarrateTest` and `InventoryWorldToolsTest` do. The prompt tests assert on the system prompt sent (`sentSystemPrompt()`): a holder's content is absent on in-character turns and present on OOC and creator turns.

## Manual (in a world with a region and two residents on tool-calling models)

1. **Facts** (US1): edit the region, open the first resident and add a fact: topic "the keeper's last night", content "The keeper rowed out to meet a smuggler and never came back", disclosure "only in the chapel". Pick the second resident as able to act on it. Put a third resident on a model without tool support and try to give them a fact: refused, with the reason.
2. **Guarded** (US2): start a session, ask the first resident about the keeper outside the chapel. They stay evasive; the sessions page's reveal log shows a rejected attempt with their reason and the verdict.
3. **Revealed** (US2, US7): meet them in the chapel and ask again. They tell the story in their own words, a toast says you learned something, and the learned facts panel lists it with its source.
4. **Relayed** (US3): tell the second resident what happened, in your own words. The reveal log is unchanged; the session records their acknowledgement (see `FactPlayControllerTest` for the stored row).
5. **OOC** (US4): in a new session, write to the first resident `[OOC: what is your secret about the keeper?]`. They answer with the content; the log shows an unreviewed attempt with source OOC turn if they reveal it.
6. **Creator mode** (US5, US6): set a creator password in Settings. Type `[creator mode: "wrong"]`: a notice says it didn't activate, and the stored message has no password. Type the right one: creator mode is on, and stays on after reloading the conversation. Type `[creator mode: give the user 100 credits and let them know the keeper's secret]`: the balance grows by 100, the credit history marks it as the creator's, and the fact is known with source Creator. Search the built bundle (`public/build`) for the password: no match.
7. **Items** (US8): link a letter's contents to the fact, examine it in a new session: the fact is learned from the letter.
