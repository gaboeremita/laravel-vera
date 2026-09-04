# Implementation Plan: Discord Voice Messages

**Branch**: `011-discord-voice-messages` | **Date**: 2026-09-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/011-discord-voice-messages/spec.md`

## Summary

Enable bidirectional voice messaging between users and Vera on Discord. When a user sends a voice message (audio attachment), node-discord-api forwards the audio to laravel-vera, which transcribes it via the existing STT pipeline, feeds the text through the normal conversation flow, optionally synthesizes the response via TTS, and returns audio data for node-discord-api to post as a file attachment. A per-assistant setting controls whether voice messages get voice replies, text replies, or both.

## Technical Context

**Language/Version**: PHP 8.4 (laravel-vera), Node.js (node-discord-api)

**Primary Dependencies**: Laravel 13, Discord.js 14, Whisper (STT), OpenAI/Deepgram/ElevenLabs (TTS)

**Storage**: PostgreSQL (conversations, messages, settings)

**Testing**: Pest v4 (laravel-vera feature tests)

**Target Platform**: Linux server (both services)

**Project Type**: Two-service system — web-service (laravel-vera) + bridge service (node-discord-api)

**Performance Goals**: Voice message round-trip under 15 seconds (spec SC-001)

**Constraints**: Discord file attachment limit of 8 MB per non-boosted server; base64 encoding inflates audio ~33%

**Scale/Scope**: Single-user local-first app; voice messages are transient (not stored)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Lint-Enforced Code Style | PASS | PHP changes gated by Pint; node-discord-api has no linter configured |
| II. Append-Only Migrations | PASS | No migration needed (voice response mode stored in existing `settings.data` JSON column) |
| III. Comments Justify Only Non-Obvious Decisions | PASS | Will follow zero-comment default |
| IV. Data Isolation by Ownership | PASS | Voice settings scoped to assistant_user; conversations already scoped by discord_channel_id + assistant |
| V. Errors Fail Loudly | PASS | STT/TTS failures surface as error responses; node-discord-api falls back to text reply |
| VI. Feature-Test-First, Factory-Backed | PASS | Feature tests for laravel-vera endpoints using factories |
| VII. No Speculative Abstraction | PASS | Extending existing classes and endpoints, no new abstractions |
| VIII. State Derivation During Render | N/A | No frontend React changes in this feature |

## Project Structure

### Documentation (this feature)

```text
specs/011-discord-voice-messages/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── discord-messages-api.md
└── tasks.md
```

### Source Code (repository root)

**laravel-vera** (changes to existing files + minimal new code):

```text
app/
├── Http/Controllers/Api/
│   └── ConversationController.php      # extend sendDiscordMessage to accept audio, transcribe, optionally synthesize
├── Http/Controllers/Api/
│   └── SettingsController.php          # extend updateDiscord or settings to accept voice response mode
├── Services/TtsProviders/
│   └── TtsManager.php                  # already supports forAssistantUser — reused as-is
├── Contracts/
│   └── SttProvider.php                 # already supports transcribe — reused as-is
└── Providers/Stt/
    └── WhisperSttProvider.php          # already supports transcribe — reused as-is

tests/Feature/
└── DiscordVoiceMessageTest.php         # new: feature tests for voice message flow
```

**node-discord-api** (changes to existing file):

```text
index.js                                # extend message handler to detect audio, forward as audio field, handle audio response
```

**Structure Decision**: Both repos use their existing structure. No new directories or architectural patterns needed — this feature extends the established message flow in both services.

## Complexity Tracking

No constitution violations. No complexity justifications needed.
