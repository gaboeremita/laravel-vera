# API Contract: Discord Messages Endpoint

**Date**: 2026-09-03 | **Plan**: [plan.md](plan.md)

## POST `/api/assistants/{assistant}/discord-messages`

The existing endpoint that node-discord-api calls when a Discord message arrives. Extended with audio fields.

### Request (node-discord-api → laravel-vera)

```json
{
  "channel_id": "string (required)",
  "message_id": "string|null",
  "content": "string|null",
  "images": ["string (base64)"],
  "audio": "string|null (base64-encoded audio bytes)",
  "audioContentType": "string|null (MIME type, e.g. audio/ogg, audio/mpeg)",
  "dm_username": "string|null"
}
```

**New fields**:
- `audio` — base64-encoded raw audio from the Discord voice message attachment. Mutually exclusive with `images` in practice (a single Discord message has one attachment type), but validation allows both.
- `audioContentType` — the MIME type of the audio, used to determine the file extension for STT. Required when `audio` is present.

**Behavior when `audio` is present**:
1. Decode base64 audio
2. Transcribe via STT provider
3. Use transcribed text as (or appended to) `content` for the conversation pipeline
4. After generating the LLM response, check the assistant's `discordVoiceResponseMode` setting
5. If mode is `both` or `voiceOnly`: synthesize the response text via TTS and include audio in the response
6. If mode is `textOnly` or TTS fails: return text-only response

### Response (laravel-vera → node-discord-api)

```json
{
  "content": "string|null",
  "image_url": "string|null",
  "audioBase64": "string|null (base64-encoded synthesized audio)",
  "audioContentType": "string|null (MIME type of the synthesized audio, e.g. audio/mpeg)"
}
```

**New fields**:
- `audioBase64` — base64-encoded audio of Vera's spoken response. Present when voice response mode is `both` or `voiceOnly` and TTS succeeds. Absent otherwise.
- `audioContentType` — MIME type of the audio (matches the TTS provider's `contentType()`: `audio/mpeg` for Deepgram/ElevenLabs, `audio/wav` for OpenAI). Required when `audioBase64` is present.

**node-discord-api behavior on response**:
- If `audioBase64` is present: decode it and send as a file attachment (using `AttachmentBuilder`) with the appropriate file extension derived from `audioContentType`.
- If `content` is present and mode is not `voiceOnly`: send as a text message (chunked if over 1900 chars, per existing behavior).
- If both `content` and `audioBase64` are present: send the text message with the audio file attached.
- If `image_url` is present: existing image attachment behavior, unchanged.

## PUT `/api/assistants/{assistant}/settings`

### Request (extended)

The existing settings update endpoint. The `discordVoiceResponseMode` is stored in the same `data` JSON blob as other settings.

```json
{
  "discordVoiceResponseMode": "both|voiceOnly|textOnly"
}
```

**Validation**: optional string, must be one of the three values. When absent, the existing value is preserved. Default behavior when no value has ever been set: `both`.
