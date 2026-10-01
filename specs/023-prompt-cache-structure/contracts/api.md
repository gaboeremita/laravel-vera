# Contracts: Cache-Friendly Prompt Structure

## Web chat: send message

`POST conversations.sendMessage` (`/conversations/{id}/messages` inside the existing assistant route group)

The request body replaces `messages` (the full list of loaded messages) with the new message only:

```json
{
  "message": { "content": "string|null", "images": ["base64", "..."] },
  "voice_mode": true
}
```

- `message.content`: nullable string.
- `message.images`: optional array.
- All other existing fields (`voice_mode`, `worldId`, `regionId`, `worldSessionId`, `positions`, `userState`, `residentState`, `residentPosture`, `occupiedSpots`, `stackedSpots`, …) are unchanged.
- The server reads the previous messages from storage (see the history limit in data-model.md).

The response is unchanged, except that `system_prompt` now holds the full prompt sent: system parts plus per-turn parts. `usage` is now also returned for Anthropic-format models.

## AI model settings

`POST ai-models.store` and `PATCH ai-models.update` (`/ai-providers/{provider}/models[/{model}]`) accept two new fields, and the model payloads returned to the settings page include them:

```json
{ "cache_marks": false, "conversation_key_field": "session_id" }
```

`conversation_key_field` must be `null` or match `^[A-Za-z_][A-Za-z0-9_]*$`, with a maximum of 64 characters. A non-matching value is answered with a 422 on that field.

## Provider wire format

Example: the model has `cache_marks` on and `conversation_key_field = "session_id"`.

**Generic (OpenAI-compatible)**

```json
{
  "model": "…",
  "session_id": "<64 hex>",
  "messages": [
    { "role": "system", "content": [
      { "type": "text", "text": "<unchanging>", "cache_control": { "type": "ephemeral" } },
      { "type": "text", "text": "# LONG-TERM MEMORY\n<summary 1>" },
      { "type": "text", "text": "<summary n>", "cache_control": { "type": "ephemeral" } }
    ]},
    { "role": "user", "content": "…" },
    { "role": "assistant", "content": [ { "type": "text", "text": "<last history message>", "cache_control": { "type": "ephemeral" } } ] },
    { "role": "user", "content": [
      { "type": "text", "text": "# CURRENT STATE\n…" },
      { "type": "text", "text": "<user message>" }
    ]}
  ]
}
```

With `cache_marks` off, each content array is sent as one string: the parts' text joined with `\n\n`. With `conversation_key_field` empty, no identifier field is sent.

**Anthropic**: the same parts, sent as `system` text blocks and as message content blocks, with `cache_control` on the marked blocks. The identifier field is added only if configured.
