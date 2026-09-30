# API Contract Changes

## Assistant create and update

`POST /api/assistants` (`assistants.store`) and `PATCH /api/assistants/{id}` (`assistants.update`) accept an optional `agent_config.termRules` object:

```json
{
  "agent_config": {
    "termRules": {
      "section": "glossary",
      "markTerms": true,
      "swapInvariant": false,
      "highlightMissing": true
    }
  }
}
```

- `section`: nullable string; must be a top-level key of the prompt being saved (or the stored prompt when `prompt` is absent) whose value is a string.
- The three flags are booleans.
- Only `termRules` is written; other `agent_config` keys are kept.
- 422 when `section` names a missing or non-string section, or when any line containing `->` in that section fails to parse. Errors are keyed `agent_config.termRules.section`, one message per failing line: `Line 4 is not a valid rule: "hearing ->"`.

`GET /api/assistants/{id}` returns `agent_config.termRules` (or the defaults when absent).

## Prompt update

`PUT /api/assistants/{assistant}/prompt` (`prompt.update`): when the assistant's `termRules.section` is set, the new prompt is checked the same way. 422 with errors keyed `prompt`, one message per failing line. Removing or renaming the picked section is allowed; the behaviors then find no rules (spec edge case).

## Send message (web chat)

`POST /api/assistants/{assistant}/conversations/{id}/messages`: request unchanged. Response additions:

```json
{
  "content": "…reply with placeholders already restored…",
  "missingTerms": [
    { "target": "audiencia", "ranges": [[4, 7]] }
  ]
}
```

- `missingTerms` is present only when `highlightMissing` is on and at least one rule matched the newest user message; it may be an empty list.
- `content` never contains a `⟦n⟧` placeholder from this turn.
- `userContent` stays the message as typed.

## Discord and Telegram

No contract change. Replies arrive with placeholders restored; no missing-term data is sent.
