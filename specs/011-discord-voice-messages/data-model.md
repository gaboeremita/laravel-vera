# Data Model: Discord Voice Messages

**Date**: 2026-09-03 | **Plan**: [plan.md](plan.md)

## Existing Entities (no schema changes)

### Settings

The `settings` table already stores per-user, per-assistant preferences in a JSON `data` column. Voice response mode is added as a new key in this JSON blob.

| Field | Type | Description |
|-------|------|-------------|
| `data.discordVoiceResponseMode` | string (new key) | One of: `both`, `voiceOnly`, `textOnly`. Controls whether Vera replies to Discord voice messages with voice, text, or both. Absent = `both`. |

### Message

The `messages` table stores conversation messages. Voice messages are stored as transcribed text — no schema change needed. The `content` column holds the transcription result, identical to how text messages are stored.

### Conversation

No changes. Discord conversations are already identified by `discord_channel_id`.

### AssistantUser

No changes. Used to resolve the assistant's TTS/STT configuration.

## No New Entities

This feature requires no new database tables or migrations. All data fits into existing structures:

- **Incoming audio**: transient — downloaded, transcribed, discarded. Never persisted.
- **Outgoing audio**: transient — synthesized, sent to node-discord-api, discarded. Never persisted.
- **Voice response mode**: stored as a JSON key in the existing `settings.data` column.
- **Transcribed text**: stored as `message.content` (existing column).

## Validation Rules

| Field | Rules |
|-------|-------|
| `discordVoiceResponseMode` | Optional string, must be one of: `both`, `voiceOnly`, `textOnly` |
| Incoming audio (base64) | Required when present; max size governed by Discord's attachment limit (~8 MB raw, ~10.7 MB base64) |
