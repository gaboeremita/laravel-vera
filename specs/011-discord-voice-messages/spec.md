# Feature Specification: Discord Voice Messages

**Feature Branch**: `011-discord-voice-messages`

**Created**: 2026-09-03

**Status**: Draft

**Input**: User description: "I want to be able to send voice messages to Vera that she can listen to, and I want her to be able to send me voice messages too, maybe if I send her a voice message, she responds with a voice message herself ON DISCORD"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Send Voice Message to Vera on Discord (Priority: P1)

A user records and sends a voice message to Vera in a Discord channel or DM. Vera transcribes the audio, understands what the user said, and responds to the content of the voice message as part of the ongoing conversation.

**Why this priority**: This is the foundational capability — without receiving and understanding voice messages, the rest of the feature has no trigger.

**Independent Test**: Can be fully tested by sending a voice message attachment in a Discord channel where Vera is active and verifying that Vera responds with a text reply that addresses the spoken content.

**Acceptance Scenarios**:

1. **Given** a Discord channel where Vera is active, **When** the user sends a voice message attachment, **Then** Vera transcribes the audio and replies with a contextually relevant response.
2. **Given** a Discord DM with Vera, **When** the user sends a voice message, **Then** Vera transcribes the audio and replies as part of the existing DM conversation thread.
3. **Given** a voice message in a language Vera's STT provider supports, **When** the message is received, **Then** the transcription accurately captures the spoken content.

---

### User Story 2 - Vera Replies with a Voice Message (Priority: P2)

When a user sends a voice message to Vera on Discord, Vera replies with her own voice message — synthesized audio sent as a Discord voice message attachment — in addition to or instead of a text reply.

**Why this priority**: This completes the voice-to-voice loop and is the core of the user's request, but it depends on Story 1's transcription pipeline being in place first.

**Independent Test**: Can be fully tested by sending a voice message to Vera and verifying that she sends back an audio file attachment in Discord that contains spoken audio of her response.

**Acceptance Scenarios**:

1. **Given** a voice message sent to Vera in a Discord channel, **When** Vera generates her text response, **Then** she synthesizes that response into audio using her configured TTS provider and sends it as a voice message attachment in Discord.
2. **Given** a voice message sent to Vera in a DM, **When** Vera generates her response, **Then** she replies with a voice message attachment in the same DM.
3. **Given** the assistant has a specific TTS voice model configured, **When** Vera synthesizes her reply, **Then** the audio uses that configured voice and model.

---

### User Story 3 - Voice Response Toggle (Priority: P3)

The user (or assistant administrator) can configure whether Vera replies to voice messages with voice, with text, or with both. This setting is per-assistant and managed through the existing assistant settings interface.

**Why this priority**: Adds user control over the behavior, but the feature is functional without it (defaulting to voice replies for voice messages).

**Independent Test**: Can be fully tested by changing the voice response mode setting and sending voice messages, verifying the reply format matches the selected mode.

**Acceptance Scenarios**:

1. **Given** the voice response mode is set to "voice only", **When** a user sends a voice message, **Then** Vera replies with only a voice message attachment.
2. **Given** the voice response mode is set to "text only", **When** a user sends a voice message, **Then** Vera replies with only a text message (the audio is still transcribed and understood).
3. **Given** the voice response mode is set to "both", **When** a user sends a voice message, **Then** Vera replies with both a text message and a voice message attachment.

---

### Edge Cases

- What happens when the voice message audio is too short (under 1 second) or silent? Vera should still attempt transcription; if the result is empty, she responds with a message indicating she couldn't make out what was said.
- What happens when the voice message exceeds the maximum size the STT provider can handle? Vera should reply with an error message explaining the voice message was too long to process.
- What happens when the TTS provider is unavailable or fails? Vera should fall back to a text-only reply and include a note that voice synthesis is temporarily unavailable.
- What happens when the user sends a regular audio file (music, a recording) rather than a voice message? The system should attempt transcription regardless — Discord voice messages are just audio attachments with a specific flag, but the processing pipeline treats all audio attachments in voice-enabled channels the same way.
- What happens when the Discord bot lacks permission to attach files in a channel? Vera should fall back to a text-only reply.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST detect and download audio attachments (voice messages) from incoming Discord messages in channels and DMs where Vera is active.
- **FR-002**: System MUST transcribe received voice message audio into text using the configured STT provider before passing it to the conversation pipeline.
- **FR-003**: System MUST include the transcribed text as the user's message content in the conversation, preserving conversation history and context.
- **FR-004**: System MUST synthesize Vera's text response into audio using the assistant's configured TTS provider and voice model.
- **FR-005**: System MUST send the synthesized audio as a file attachment in the Discord reply message.
- **FR-006**: System MUST support a per-assistant voice response mode setting with three options: voice only, text only, or both.
- **FR-007**: System MUST default the voice response mode to "both" (text and voice) when no explicit setting exists.
- **FR-008**: System MUST fall back to text-only replies when TTS synthesis fails or the bot lacks file-attachment permissions in the channel.
- **FR-009**: System MUST respect the existing per-channel and per-server trigger modes — voice message processing only occurs in channels where Vera is already configured to respond.
- **FR-010**: System MUST store the transcribed text (not the raw audio) as the message content in the conversation record, consistent with how text messages are stored today.

### Key Entities

- **Voice Message**: An audio attachment on an incoming Discord message. Key attributes: source audio URL, file size, duration, originating channel/DM, associated conversation.
- **Voice Response Mode**: A per-assistant configuration value controlling how Vera replies to voice messages. One of: voice_only, text_only, both.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can send a voice message to Vera on Discord and receive a contextually relevant reply within 15 seconds of the message being sent.
- **SC-002**: Vera's voice replies are audible, intelligible, and use the assistant's configured voice.
- **SC-003**: Voice message conversations maintain full context continuity — a voice message followed by a text message (or vice versa) references the same conversation thread.
- **SC-004**: The voice response mode setting successfully controls reply format with 100% consistency across all three modes.
- **SC-005**: When TTS synthesis fails, Vera still delivers a text reply — no voice-message interaction results in zero response.

## Assumptions

- The existing Discord bot (discord-api service) is capable of receiving message attachments and can be extended to send file attachments in replies.
- The existing STT provider (Whisper) can handle the audio formats Discord uses for voice messages (OGG/Opus).
- The existing TTS providers can produce audio in a format Discord can play inline (MP3 or OGG).
- Voice message processing uses the same conversation pipeline as text messages — transcribed text is fed into the same LLM interaction flow.
- Audio files are processed transiently (downloaded, transcribed, then discarded) — raw audio is not permanently stored on the VERA server.
- The Discord bot's rate limits and file size limits (8 MB for non-boosted servers) are sufficient for typical voice reply lengths.
