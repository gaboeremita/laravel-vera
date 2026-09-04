# Quickstart Validation: Discord Voice Messages

**Date**: 2026-09-03 | **Plan**: [plan.md](plan.md)

## Prerequisites

- laravel-vera running locally (Herd)
- node-discord-api running locally (`node index.js`)
- A Discord server with the bot invited and a channel configured with a trigger mode
- STT configured (`AI_STT_URL`, `AI_STT_MODEL` in laravel-vera `.env`)
- TTS configured (either `ai.tts` config or a voice model selected for the assistant)
- The bot's Discord user ID added to the DM allowlist (for DM testing)

## Validation Scenarios

### V1: Voice Message → Text Reply (P1 — Story 1)

**Setup**: Ensure `discordVoiceResponseMode` is set to `textOnly` for the assistant (or not set, and temporarily modify the code default for this test).

1. Open Discord and navigate to a channel where the bot is active
2. Record and send a voice message saying something distinctive (e.g. "What's the weather like today?")
3. Verify: bot shows typing indicator within 2 seconds
4. Verify: bot replies with a text message that addresses the spoken content
5. Check laravel-vera logs: the transcribed text appears as the message content in the conversation

**Expected**: The bot understands the voice message and replies in text.

### V2: Voice Message → Voice Reply (P2 — Story 2)

**Setup**: Ensure `discordVoiceResponseMode` is `both` (default).

1. Send a voice message to the bot in a Discord channel or DM
2. Verify: bot replies with a text message AND an audio file attachment
3. Play the audio attachment in Discord
4. Verify: the audio is Vera's spoken response, audible and intelligible, in the configured voice

**Expected**: The bot replies with both text and a playable audio file.

### V3: Voice-Only Mode (P3 — Story 3)

**Setup**: Set `discordVoiceResponseMode` to `voiceOnly`.

1. Send a voice message to the bot
2. Verify: bot replies with ONLY an audio file attachment (no text message)
3. Play the audio
4. Verify: the audio contains Vera's response

**Expected**: No text message, only audio.

### V4: Text-Only Mode (P3 — Story 3)

**Setup**: Set `discordVoiceResponseMode` to `textOnly`.

1. Send a voice message to the bot
2. Verify: bot replies with ONLY a text message (no audio attachment)
3. Verify: the text response addresses the spoken content (audio was still transcribed)

**Expected**: Audio is transcribed and understood, but reply is text-only.

### V5: TTS Failure Fallback

**Setup**: Temporarily misconfigure TTS (invalid URL or model) with `discordVoiceResponseMode` set to `both`.

1. Send a voice message
2. Verify: bot replies with a text message (graceful degradation)
3. Verify: no crash or silent failure in either service's logs

**Expected**: TTS failure produces a text-only reply, not a crash.

### V6: Context Continuity

1. Send a voice message: "My name is [name]"
2. Wait for reply
3. Send a text message: "What did I just tell you?"
4. Verify: bot references the name from the voice message

**Expected**: Voice and text messages share the same conversation context.

### V7: Regular Audio File

1. Upload a regular audio file (an MP3 of music or a recording) to the channel
2. Verify: bot attempts transcription (may produce garbled text, but processes it)
3. Verify: no crash

**Expected**: Audio files that aren't voice messages are still processed.

### V8: /send-voice-message Command

1. Type `/send-voice-message how are you doing today?` in a channel where the bot is active
2. Verify: bot replies with an audio file attachment containing her spoken response
3. Verify: text content is also present (mode is `both` by default)
4. Set voice response mode to `textOnly`
5. Type `/send-voice-message tell me a joke`
6. Verify: bot still replies with an audio file attachment (command overrides the setting)

**Expected**: The command forces a voice reply even from text input, overriding the mode setting.

## Automated Tests (Pest)

Run the feature test suite for Discord voice messages:

```bash
php artisan test --compact --filter=DiscordVoiceMessage
```

Tests should cover:
- Audio field accepted and transcribed in discord-messages endpoint
- Transcribed text stored as message content
- TTS synthesis triggered when voice response mode is `both` or `voiceOnly`
- TTS skipped when mode is `textOnly`
- Fallback to text when TTS fails
- Validation rejects audio without audioContentType
- Voice response mode setting persists and reads correctly
