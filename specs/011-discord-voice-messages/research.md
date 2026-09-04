# Research: Discord Voice Messages

**Date**: 2026-09-03 | **Plan**: [plan.md](plan.md)

## R1: Discord Voice Message Format

**Decision**: Detect audio attachments by MIME type prefix (`audio/`) on the attachment's `contentType` property. Discord voice messages are OGG/Opus files; regular audio uploads may be MP3, WAV, or other formats. All are treated the same way (transcription attempted on any audio attachment).

**Rationale**: Discord.js exposes `attachment.contentType` (e.g. `audio/ogg`, `audio/mpeg`). Checking for the `audio/` prefix is simpler and more robust than checking for Discord's `flags` field on the message (the voice message flag `IS_VOICE_MESSAGE` requires the `MessageFlags` intent and is less reliable for bot accounts). The STT provider (Whisper) handles OGG, MP3, WAV, and most common audio formats.

**Alternatives considered**: Checking `message.flags.has(MessageFlags.IsVoiceMessage)` — more precise but requires additional intent permissions and doesn't cover regular audio file uploads that the user also wants transcribed.

## R2: Audio Data Transfer Between Services

**Decision**: node-discord-api downloads the audio attachment and sends it as a base64-encoded string in an `audio` field alongside the existing `content` and `images` fields in the POST to laravel-vera's discord-messages endpoint. laravel-vera returns synthesized audio as a base64-encoded string in an `audioBase64` field alongside the existing `content` field.

**Rationale**: This mirrors the existing pattern for image attachments (`images[]` sent as base64). Base64 keeps the JSON API contract simple — no multipart form handling needed on either side. The 33% overhead of base64 is acceptable given typical voice message sizes (a 60-second OGG voice message is ~100-200 KB; base64 makes it ~130-270 KB, well within reasonable payload sizes).

**Alternatives considered**: Multipart form upload from node-discord-api to laravel-vera — more efficient for large files but breaks the existing JSON-only API pattern and adds complexity to both sides. URL-based (laravel-vera downloads from Discord CDN directly) — adds a coupling between laravel-vera and Discord that the bridge architecture is designed to avoid.

## R3: TTS Output Format for Discord

**Decision**: Use the TTS provider's native output format (MP3 for Deepgram/ElevenLabs, WAV for OpenAI/OpenAI-compatible). The file extension sent to Discord is derived from the `contentType()` return value. Discord can play MP3 and WAV inline.

**Rationale**: No format conversion is needed. All four TTS providers produce formats Discord supports natively. The `contentType()` method already exists on every TTS provider and returns the correct MIME type.

**Alternatives considered**: Converting all output to OGG/Opus to match Discord's native voice message format — adds an ffmpeg dependency for no user-facing benefit, since Discord plays MP3/WAV fine.

## R4: Voice Response Mode Storage

**Decision**: Store the voice response mode in the existing `settings.data` JSON column as `discordVoiceResponseMode`, alongside the existing `tts_model_id` and `tts_voice` keys. Valid values: `both`, `voiceOnly`, `textOnly`. Default when absent: `both`.

**Rationale**: The `settings` table already stores per-assistant, per-user preferences as a JSON blob. Adding a key to that blob requires no migration and follows the established pattern. The setting is read during Discord message processing to decide whether to synthesize audio.

**Alternatives considered**: A new database column on `assistant_discord_channels` — too granular (the spec defines this as per-assistant, not per-channel) and requires a migration.

## R5: Whisper Compatibility with OGG/Opus

**Decision**: The existing `WhisperSttProvider` sends audio bytes with a hardcoded filename `audio.wav`. This needs to change to pass the correct file extension based on the actual audio format so Whisper can decode it properly. The MIME type from the Discord attachment determines the extension.

**Rationale**: whisper.cpp and OpenAI's Whisper API both support OGG, MP3, WAV, WEBM, and other formats, but they rely on the file extension or content type to select the decoder. Sending an OGG file named `audio.wav` may cause decoding failures.

**Alternatives considered**: Always converting audio to WAV before transcription — adds an ffmpeg dependency and processing time for a problem solvable by using the correct filename.
