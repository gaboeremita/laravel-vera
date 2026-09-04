# Tasks: Discord Voice Messages

**Input**: Design documents from `/specs/011-discord-voice-messages/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: Included — constitution mandates feature-test-first, factory-backed testing (Principle VI).

**Organization**: Tasks grouped by user story for independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup

**Purpose**: Prepare the codebase for voice message handling

- [ ] T001 Fix STT filename to use actual audio content type instead of hardcoded `audio.wav` in `app/Providers/Stt/WhisperSttProvider.php` — add a `$filename` parameter to `transcribe()` and update the `SttProvider` contract in `app/Contracts/SttProvider.php`

**Checkpoint**: STT provider can accept audio in any format Whisper supports (OGG, MP3, WAV, WEBM)

---

## Phase 2: Foundational

**Purpose**: No blocking prerequisites beyond Phase 1. The existing conversation pipeline, TTS, and STT infrastructure are already in place.

**Checkpoint**: Foundation ready — user story implementation can begin

---

## Phase 3: User Story 1 — Send Voice Message to Vera (Priority: P1) MVP

**Goal**: A user sends a voice message on Discord; Vera transcribes the audio, understands it, and replies with a text message.

**Independent Test**: Send a voice message in a Discord channel where Vera is active → Vera replies with text addressing the spoken content.

### Tests for User Story 1

- [ ] T002 [P] [US1] Feature test: discord-messages endpoint accepts `audio` and `audioContentType` fields, transcribes audio, and returns a text response in `tests/Feature/DiscordVoiceMessageTest.php`
- [ ] T003 [P] [US1] Feature test: transcribed text is stored as message content (not raw audio) in `tests/Feature/DiscordVoiceMessageTest.php`
- [ ] T004 [P] [US1] Feature test: message with both text content and audio appends transcription to content in `tests/Feature/DiscordVoiceMessageTest.php`
- [ ] T005 [P] [US1] Feature test: request with `audio` but missing `audioContentType` is rejected with 422 in `tests/Feature/DiscordVoiceMessageTest.php`

### Implementation for User Story 1

- [ ] T006 [US1] Extend `sendDiscordMessage` validation in `app/Http/Controllers/Api/ConversationController.php` to accept `audio` (nullable string) and `audioContentType` (nullable string, required when audio is present)
- [ ] T007 [US1] Add audio transcription logic in `sendDiscordMessage` in `app/Http/Controllers/Api/ConversationController.php` — decode base64 audio, call `SttProvider::transcribe()` with the correct filename derived from `audioContentType`, use transcribed text as (or appended to) message content
- [ ] T008 [US1] Extend node-discord-api message handler in `index.js` to detect audio attachments (check `attachment.contentType` for `audio/` prefix), download as base64, and send in `audio` and `audioContentType` fields instead of `images`

**Checkpoint**: Voice messages sent on Discord are transcribed and Vera replies with text. Full voice-in flow works end-to-end.

---

## Phase 4: User Story 2 — Vera Replies with Voice (Priority: P2)

**Goal**: After transcribing a voice message and generating a text response, Vera synthesizes the response into audio and sends it back as a Discord file attachment.

**Independent Test**: Send a voice message → Vera replies with an audio file attachment containing her spoken response.

### Tests for User Story 2

- [ ] T009 [P] [US2] Feature test: when audio is present in request and voice mode is `both`, response includes `audioBase64` and `audioContentType` in `tests/Feature/DiscordVoiceMessageTest.php`
- [ ] T010 [P] [US2] Feature test: when TTS synthesis fails, response falls back to text-only (no `audioBase64`) in `tests/Feature/DiscordVoiceMessageTest.php`

### Implementation for User Story 2

- [ ] T011 [US2] Add TTS synthesis to `sendDiscordMessage` in `app/Http/Controllers/Api/ConversationController.php` — after generating the LLM response, if the request contained audio: use `TtsManager::forAssistantUser()` to synthesize the response text, base64-encode the audio, and include `audioBase64` and `audioContentType` in the JSON response
- [ ] T012 [US2] Extend node-discord-api response handling in `index.js` — when `audioBase64` is present in the Laravel response: decode it, determine file extension from `audioContentType` (e.g. `audio/mpeg` → `.mp3`, `audio/wav` → `.wav`), create an `AttachmentBuilder`, and send the audio file alongside (or instead of) text

**Checkpoint**: Voice-to-voice loop works end-to-end. Sending a voice message produces both a text reply and an audio attachment.

---

## Phase 5: User Story 3 — Voice Response Toggle (Priority: P3)

**Goal**: A per-assistant setting controls whether Vera replies to voice messages with voice only, text only, or both.

**Independent Test**: Change the setting → send voice messages → verify reply format matches the selected mode.

### Tests for User Story 3

- [ ] T013 [P] [US3] Feature test: when `discordVoiceResponseMode` is `textOnly`, response has no `audioBase64` even though audio was sent in `tests/Feature/DiscordVoiceMessageTest.php`
- [ ] T014 [P] [US3] Feature test: when `discordVoiceResponseMode` is `voiceOnly`, response has `audioBase64` but `content` is null in `tests/Feature/DiscordVoiceMessageTest.php`
- [ ] T015 [P] [US3] Feature test: settings endpoint accepts and persists `discordVoiceResponseMode` in `tests/Feature/DiscordVoiceMessageTest.php`

### Implementation for User Story 3

- [ ] T016 [US3] Add `discordVoiceResponseMode` to settings validation and persistence in `app/Http/Controllers/Api/SettingsController.php` — accept `both`, `voiceOnly`, or `textOnly`; store in `settings.data` JSON
- [ ] T017 [US3] Read `discordVoiceResponseMode` in `sendDiscordMessage` in `app/Http/Controllers/Api/ConversationController.php` — gate TTS synthesis on mode being `both` or `voiceOnly`; gate text content in response on mode being `both` or `textOnly`
- [ ] T018 [US3] Update node-discord-api response handling in `index.js` — when `content` is null (voice-only mode), send only the audio attachment with no text; when `audioBase64` is absent (text-only mode), send only text (existing behavior)
- [ ] T019 [US3] Add voice response mode control to the Discord settings UI in `resources/js/pages/DiscordPage.jsx` or the relevant settings component — a dropdown/radio with three options, wired to the settings endpoint

**Checkpoint**: All three voice response modes work correctly. The setting persists and takes effect immediately.

---

## Phase 6: User Story 4 — /send-voice-message Command (Priority: P4)

**Goal**: A `/send-voice-message` command prefix in a Discord text message forces Vera to reply with a voice message, regardless of the voice response mode setting and whether audio was sent.

**Independent Test**: Type `/send-voice-message hello` in a Discord channel → Vera replies with an audio file attachment.

### Tests for User Story 4

- [ ] T020 [P] [US4] Feature test: `/send-voice-message` prefix is detected and stripped from content, response includes `audioBase64` in `tests/Feature/DiscordVoiceMessageTest.php`
- [ ] T021 [P] [US4] Feature test: `/send-voice-message` overrides `textOnly` voice response mode and still produces audio in `tests/Feature/DiscordVoiceMessageTest.php`
- [ ] T022 [P] [US4] Feature test: `/send-voice-message` with no additional text produces a conversational reply with audio in `tests/Feature/DiscordVoiceMessageTest.php`

### Implementation for User Story 4

- [ ] T023 [US4] Add `/send-voice-message` command detection in `sendDiscordMessage` in `app/Http/Controllers/Api/ConversationController.php` — extract command prefix (following the existing `/create-image` pattern), strip it from content, set a flag to force TTS synthesis on the response
- [ ] T024 [US4] Wire the force-voice flag into the TTS synthesis logic in `sendDiscordMessage` in `app/Http/Controllers/Api/ConversationController.php` — when the flag is set, synthesize audio regardless of `discordVoiceResponseMode`

**Checkpoint**: `/send-voice-message` command works. Users can get voice replies from text input on demand.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T025 [P] Feature test: STT failure (provider unavailable) returns a user-friendly error message in Discord in `tests/Feature/DiscordVoiceMessageTest.php`
- [ ] T026 [P] Feature test: empty transcription result (silent/too-short audio) produces a message indicating Vera couldn't make out what was said in `tests/Feature/DiscordVoiceMessageTest.php`
- [ ] T027 Handle edge cases in `sendDiscordMessage` in `app/Http/Controllers/Api/ConversationController.php` — empty transcription, STT failure error messages
- [ ] T028 Run quickstart.md validation scenarios end-to-end against both services

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1 — nothing to do here
- **Phase 3 (US1)**: Depends on Phase 1 (STT filename fix)
- **Phase 4 (US2)**: Depends on Phase 3 (needs the audio-in flow working to test audio-out)
- **Phase 5 (US3)**: Depends on Phase 4 (needs TTS synthesis in place to gate it)
- **Phase 6 (US4)**: Depends on Phase 4 (needs TTS synthesis in place; independent of US3's mode toggle)
- **Phase 7 (Polish)**: Depends on Phases 3–6

### User Story Dependencies

- **US1 (P1)**: Independent after Phase 1 — voice-in only
- **US2 (P2)**: Depends on US1 — adds voice-out to the voice-in flow
- **US3 (P3)**: Depends on US2 — adds mode toggle to the voice-out behavior
- **US4 (P4)**: Depends on US2 — reuses TTS synthesis, independent of US3

### Within Each User Story

- Tests written first (fail before implementation)
- Laravel changes before node-discord-api changes (API contract drives the bridge)
- Core flow before edge cases

### Parallel Opportunities

- Within US1: T002, T003, T004, T005 (all test tasks) can run in parallel
- Within US2: T009, T010 can run in parallel
- Within US3: T013, T014, T015 can run in parallel
- Within US4: T020, T021, T022 can run in parallel
- US3 and US4 can run in parallel (both depend on US2, independent of each other)
- Within Polish: T025, T026 can run in parallel
- Cross-repo: laravel-vera and node-discord-api tasks within each story are sequential (API contract first)

---

## Parallel Example: User Story 1

```text
# Write all US1 tests in parallel:
T002: Feature test — endpoint accepts audio fields
T003: Feature test — transcribed text stored as message content
T004: Feature test — text + audio appends transcription
T005: Feature test — audio without content type rejected

# Then implement sequentially:
T006: Extend validation → T007: Add transcription logic → T008: node-discord-api audio detection
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Fix STT filename (T001)
2. Complete Phase 3: Voice-in flow (T002–T008)
3. **STOP and VALIDATE**: Send a voice message on Discord → Vera replies with text
4. This is a functional MVP — Vera understands voice messages

### Incremental Delivery

1. T001 (STT fix) → Foundation ready
2. T002–T008 (US1) → Voice-in works → Validate
3. T009–T012 (US2) → Voice-out works → Validate
4. T013–T019 (US3) → Mode toggle works → Validate
5. T020–T024 (US4) → /send-voice-message command works → Validate
6. T025–T028 (Polish) → Edge cases handled → Final validation

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story
- laravel-vera tasks come before node-discord-api tasks within each story (API contract drives the bridge)
- Commit after each task or logical group
- Stop at any checkpoint to validate the story independently
