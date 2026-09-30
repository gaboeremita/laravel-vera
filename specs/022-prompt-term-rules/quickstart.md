# Quickstart: Term Rules Applied From the Assistant's Prompt

## Prerequisites

- App running under Herd with `npm run dev`.
- An assistant you own, with a working model.

## Setup

1. Open the assistant's edit page and add a top-level string prompt section holding rule lines, for example:

   ```
   Translate each message. Terms written `source -> target` must use the target exactly.
   Copy every ⟦n⟧ marker into the reply exactly as written.
   hearing, hearings -> audiencia, audiencias
   ACME -> ACME (invariant)
   ```

2. Pick that section in the dropdown next to the checkboxes. Tick the checkboxes for the scenario you are checking. Save.

## Scenarios

| # | Action | Expected |
|---|---|---|
| 1 | All checkboxes off, send "The hearing is at ACME" | Model receives the message unchanged; "Prompt sent" shows the rule lines in the system prompt (FR-005) |
| 2 | Inline marking on, same message | Model-facing message reads `The [hearing -> audiencia] is at [ACME -> ACME]`; the chat bubble shows the text as typed (FR-008, FR-009) |
| 3 | Exact swap on, same message | Model-facing message carries `⟦1⟧` for ACME; the reply shows `ACME`, never `⟦1⟧`; reloading the conversation shows `ACME` (FR-010, FR-011) |
| 4 | Highlight on, get a reply without "audiencia" | Warning line under the reply lists `audiencia`; "hearing" is underlined in your message; after reload both are gone (FR-012) |
| 5 | Send a second message | Only the new message is marked; the first reaches the model as typed (FR-008a) |
| 6 | Add the line `hearing ->` to the picked section and save | Save refused with an error naming that line (FR-014) |
| 7 | Send a message containing "rehearing" | "hearing" inside it is left unmarked |
| 8 | Edit the rule section, then send another message | The new message uses the edited rules |

## Automated checks

```bash
php artisan test --compact --filter=TermRules
```
