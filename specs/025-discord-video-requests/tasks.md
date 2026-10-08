---

description: "Task list for Discord video requests"
---

# Tasks: Discord Video Requests

**Input**: Design documents from `specs/025-discord-video-requests/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/bridge-video-delivery.md, contracts/discord-messages.md, quickstart.md

**Tests**: Included for Laravel. CLAUDE.md requires every change to be tested, and the constitution (Principle VI) requires factory-backed Pest feature tests. The bridge has no test runner; it is validated with the manual scenarios in quickstart.md (research R10). Per CLAUDE.md, the test suite, Pint and ESLint run once, when the owner says it is time to push (T027).

**Organization**: Tasks are grouped by user story. Story phases run US1 → US2 → US4 → US3: failure delivery (US4) comes before shrinking (US3) because the bridge's "too large" notice reuses the notice posting from US4.

**Two repositories**: paths starting with `node-discord-api/` are in `/Users/gaboontiveros/repos/node-discord-api`. Every other path is in this repository. Bridge changes are committed in the bridge's own repository.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1–US4)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Configuration for the delivery request

- [ ] T001 [P] Add `'delivery_timeout' => (int) env('DISCORD_API_DELIVERY_TIMEOUT', 180),` to the `discord` block in `config/ai.php`, after `timeout`. Add `DISCORD_API_DELIVERY_TIMEOUT=180` to `.env.example` next to the other `DISCORD_API_*` keys.
- [ ] T002 [P] In `node-discord-api/index.js`, add `app.use(express.json());` before the route declarations, so the new POST route can read JSON bodies.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The reply-target lookup that every delivery uses

- [ ] T003 Add `public int $videoId;` to `app/Events/VideoGenerationFinished.php`, set from `$video->id` in the constructor, next to the existing `conversationId`. The event only carries IDs, and the listener needs the video. The extra field also goes out in the broadcast payload, which is harmless.
- [ ] T004 Create `app/Listeners/DeliverVideoToDiscord.php` (auto-discovered like `app/Listeners/UnlockQuests.php`; no registration). Make it implement `ShouldQueue` and use `InteractsWithQueue`. Give it:
  - `public int $tries = 6;` and `public int $timeout = 200;`.
  - `backoff(): array` returning `[10, 30, 60, 120, 300]`.
  - `shouldQueue(VideoGenerationFinished $event): bool`, true only when `Conversation::whereKey($event->conversationId)->whereNotNull('discord_channel_id')->exists()` (FR-011).
  - A private `replyTarget(Video $video): ?string` returning the `discord_message_id` of the latest `role = 'user'` message in the same conversation with an `id` lower than `$video->videoable_id` (research R4). Add one comment above it saying the earlier user message is reliable because the bridge sends one request at a time.
  - A `handle(VideoGenerationFinished $event): void` that loads `Video::find($event->videoId)` and `Conversation::find($event->conversationId)` and returns when either is null (the conversation was deleted after the event). US1 adds the delivery.

**Checkpoint**: Foundation ready. User story work can begin.

---

## Phase 3: User Story 1 - Ask for a video in Discord and get it as a reply (Priority: P1) 🎯 MVP

**Goal**: `/create-video` from Discord starts a video and replies in character, and the finished video is posted as a reply to the request.

**Independent Test**: quickstart.md manual scenarios 1–6 and 14, plus the US1 tests below.

### Tests for User Story 1

- [ ] T005 [P] [US1] Create `tests/Feature/DiscordCreateVideoTest.php`. Use `RefreshDatabase`, `Queue::fake()`, `setUpAgentAssistant('assistant')` and `configureVideoGenModel()` from `tests/Pest.php`, and post to `/api/assistants/{id}/discord-messages` with `actingAs($user)` as `tests/Feature/DiscordVoiceMessageTest.php` does. Fake the LLM with `Http::fake` the way `tests/Feature/CreateVideoCommandTest.php` does for the description and the reply. Cover:
  - `/create-video a cat` with `channel_id` and `message_id` returns 200 with `content` only (no `video` key), creates an assistant message with a `queued` `Video`, and pushes `PollVideoGeneration`.
  - The user message stores the sent `message_id` as `discord_message_id`.
  - `/create-video` with no description returns 422 with `Describe what video to generate after /create-video.` and pushes nothing.
  - No video model returns 422 with `No video generation model is configured for this assistant.`
  - The in-character reply request to the LLM leaves out the emotion and pose tag sections: give the assistant a pose with `Pose::factory()` and `portrait_type` `avatar3d`, then assert `promptOfRequest()` does not contain `# POSE TAGS` or `# EMOTION TAGS`, as `tests/Feature/Api/ConversationPosePromptTest.php` asserts sections.
- [ ] T006 [P] [US1] Create `tests/Feature/DeliverVideoToDiscordTest.php`. Use `RefreshDatabase` and `Http::fake()`, and set `config(['ai.discord.api_url' => 'http://discord-api.test', 'ai.discord.api_secret' => 'secret'])`. Build the conversation with `discord_channel_id`, a user message with `discord_message_id`, and an assistant message carrying `Video::factory()->completed()`. Call `app(DeliverVideoToDiscord::class)->handle(new VideoGenerationFinished($video))` directly. Cover:
  - It POSTs to `http://discord-api.test/assistants/{assistantId}/channels/{channelId}/videos` with the `X-Internal-Secret: secret` header and body `{replyToMessageId, videoUrl}`, where `videoUrl` equals `$video->url`.
  - `replyToMessageId` is the `discord_message_id` of the user message just before the video's message, even when an older user message in the same conversation has a different one.
  - `replyToMessageId` is null when that user message has none.
  - `shouldQueue()` is false for a conversation without `discord_channel_id`.
  - `handle()` sends nothing when the conversation was deleted after the event was created.
  - `backoff()` returns `[10, 30, 60, 120, 300]`, and `$tries` is 6.

### Implementation for User Story 1

- [ ] T007 [US1] In `app/Http/Controllers/Api/ConversationController.php`, `sendDiscordMessage()`:
  - Keep the `Image` returned by `Image::storeFromBase64($validated['images'][0], ...)` in `$attachedImage` (null when there is no image).
  - After the `/create-image` branch, add a `/create-video` branch mirroring the one in `chat()`: `extractVideoGenPrompt()`, the empty-description 422, `isAvailableFor()` and its 422, the `hasPublicUrl()` check for `$attachedImage` and its 422, then `startVideoMessage($request, $assistantUser, $conversation, $videoGenerationService, $videoGenPrompt, $attachedImage)` inside a `try`/`catch (\RuntimeException $e)` that returns 502 with the message.
  - Update the conversation title as the `/create-image` branch does, and return `response()->json(['content' => $started['content']])`.
- [ ] T008 [US1] In `reactToStartedVideo()` in the same controller, add the `if ($conversation->discord_channel_id)` block from `reactToGeneratedImage()` that appends `'emotion tags'` and `'pose tags'` to `$excludedSections`, before the `PromptDirector` is built. Keep its existing comment wording from `reactToGeneratedImage()`.
- [ ] T009 [US1] Fill in `handle()` in `app/Listeners/DeliverVideoToDiscord.php` for completed videos:
  - Read `config('ai.discord')`. Post with `Http::timeout($apiConfig['delivery_timeout'])->withHeaders(['X-Internal-Secret' => $apiConfig['api_secret']])` to `{api_url}/assistants/{assistantId}/channels/{discord_channel_id}/videos`, where `assistantId` is `$conversation->assistantUser()->assistant_id`.
  - Send `['replyToMessageId' => $this->replyTarget($video), 'videoUrl' => $video->url]`.
  - Return on success.
  - On any connection exception, 404 or 502, throw a `RuntimeException` with the status and body, so the queue retries.
  - Leave the other statuses to US4.
- [ ] T010 [US1] In `node-discord-api/index.js`, add `async function deliverVideo(req, res)` and register `app.post('/assistants/:assistantId/channels/:channelId/videos', deliverVideo);`. It must:
  - Check `x-internal-secret` exactly as `discovery()` does (401).
  - Find the bot in `botsByAssistantId` (404 with `No bot configured for assistant {id}`).
  - Fetch the channel with `bot.client.channels.fetch(channelId)`. On a Discord error, `logError` and answer 422 with the error message.
  - When `videoUrl` is present, download it with `downloadAttachmentAsBuffer`. On failure, `logError` and answer 502.
  - Post with `new AttachmentBuilder(buffer, { name: 'video.mp4' })` through a helper `postReply(channel, replyToMessageId, payload)`, which sends with `reply: { messageReference: replyToMessageId, failIfNotExists: false }` when `replyToMessageId` is set and plain otherwise (FR-010). On a Discord error, `logError` and answer 422.
  - Answer `200 { posted: 'video' }`.
- [ ] T011 [US1] In `node-discord-api/index.js`, in the `MessageCreate` handler, before `enqueue`: when `message.content` matches `/^\/create-video(?:\s|$)/i` and `dmAllowlist` does not have `message.author.id`, `await message.channel.send("You can't request videos.")` and return (FR-001a).
- [ ] T012 [US1] In `node-discord-api/index.js`, in the `!res.ok` branch after `fetch(.../discord-messages)`: parse the body as JSON when possible. Post its `message` when it is a non-empty string, otherwise post `Connection failed. Try again.` Keep the existing `logError` call (FR-005, FR-005a).

**Checkpoint**: Discord requests produce a reply now and a posted video later.

---

## Phase 4: User Story 2 - Start a Discord video from an attached image (Priority: P1)

**Goal**: An image attached to `/create-video` in Discord becomes the first frame.

**Independent Test**: quickstart.md manual scenarios 7 and 8, plus the US2 tests below.

### Tests for User Story 2

- [ ] T013 [US2] Add to `tests/Feature/DiscordCreateVideoTest.php`:
  - With `config(['ai.video_gen.public_url' => 'https://tunnel.test'])` and `Storage::fake('public')`, `/create-video` with `images: [<base64 png>]` creates the `Video` with `first_frame_image_id` set to the `Image` stored on the user message.
  - With `public_url` null, the same request returns 422 with `Set PUBLIC_TUNNEL_URL to generate a video from an image.` and pushes nothing.

### Implementation for User Story 2

- [ ] T014 [US2] Confirm T007 passes `$attachedImage` to `startVideoMessage()` and runs the `hasPublicUrl()` check before it. Fix `sendDiscordMessage()` in `app/Http/Controllers/Api/ConversationController.php` if the US2 tests fail.

**Checkpoint**: First-frame videos work from Discord.

---

## Phase 5: User Story 4 - Failures reach the user in Discord (Priority: P2)

**Goal**: Failed videos post a plain notice. Delivery retries on an unreachable bridge and stops on a refused post.

**Independent Test**: quickstart.md manual scenarios 10–13, plus the US4 tests below.

### Tests for User Story 4

- [ ] T015 [US4] Add to `tests/Feature/DeliverVideoToDiscordTest.php`:
  - A `Video::factory()->failed('Provider error')` posts `{replyToMessageId, failureReason: 'Provider error'}` with no `videoUrl`.
  - A connection exception (`Http::fake(fn () => throw new ConnectionException('down'))`), a 404 and a 502 each make `handle()` throw.
  - A 422 and a 401 do not throw. They call `fail()` on the job and log an error: bind a mocked `InteractsWithQueue` job with `$listener->setJob($job)` and expect `fail` once, and use `Log::spy()`.
  - `failed($event, $exception)` logs an error with `video_id` and `conversation_id`.

### Implementation for User Story 4

- [ ] T016 [US4] In `app/Listeners/DeliverVideoToDiscord.php`:
  - Send `failureReason` instead of `videoUrl` when `$video->status === VideoStatus::Failed`.
  - On 401 or 422, `Log::error('Discord video delivery refused', [...])` with `video_id`, `conversation_id`, status and body, then `$this->fail(...)` and return.
  - Add `failed(VideoGenerationFinished $event, Throwable $exception): void` that logs `Discord video delivery failed` with `video_id`, `conversation_id` and the exception message (FR-018).
- [ ] T017 [US4] In `node-discord-api/index.js`, `deliverVideo()`: when `failureReason` is present, call `postReply(channel, replyToMessageId, { content: \`Video failed: ${failureReason}\` })` and answer `200 { posted: 'notice' }` (FR-009).

**Checkpoint**: Every Discord video ends with a post or a logged delivery failure.

---

## Phase 6: User Story 3 - Videos too large for Discord are shrunk to fit (Priority: P2)

**Goal**: Videos over the destination's upload limit are re-encoded to fit, or replaced by a notice.

**Independent Test**: quickstart.md manual scenario 9. No Laravel changes; the original file is never modified.

### Implementation for User Story 3

- [ ] T018 [US3] In `node-discord-api/index.js`, add:
  - A constant `UPLOAD_LIMIT_BYTES_BY_TIER = { 0: 10 * 1024 * 1024, 1: 10 * 1024 * 1024, 2: 50 * 1024 * 1024, 3: 100 * 1024 * 1024 }`.
  - `function uploadLimitFor(channel)`, returning the tier-0 limit for DMs (`channel.type === ChannelType.DM`) and `UPLOAD_LIMIT_BYTES_BY_TIER[channel.guild.premiumTier]` otherwise (research R7).
- [ ] T019 [US3] In `node-discord-api/index.js`, add `async function shrinkToFit(buffer, limitBytes)`, using `child_process.execFile` (promisified with `util.promisify`), `os.tmpdir()` and `fs.promises`:
  - Write the buffer to a temp `.mp4`.
  - Read the length in seconds with `ffprobe -v error -show_entries format=duration -of csv=p=0`.
  - Encode with `ffmpeg -y -i in -c:v libx264 -b:v {v}k -maxrate {v}k -bufsize {2v}k -c:a aac -b:a 128k -movflags +faststart out`, where `v = floor(limitBytes * 8 * 0.92 / seconds / 1000) - 128`. Put one comment above the formula saying the 8% margin covers container overhead.
  - If the output is over `limitBytes`, encode again with `-vf scale=-2:480`.
  - Return the first output buffer under the limit, or `null`.
  - Delete every temp file in a `finally`.
  - On an ffmpeg or ffprobe error, `logError('shrink-video', ...)` and return `null`.
- [ ] T020 [US3] In `deliverVideo()` in `node-discord-api/index.js`: after downloading, when `buffer.length > uploadLimitFor(channel)`, replace the buffer with `await shrinkToFit(buffer, limit)`. When that returns `null`, call `postReply(channel, replyToMessageId, { content: 'Video too large for Discord — watch it in the web app' })` and answer `200 { posted: 'notice' }` (FR-015).

**Checkpoint**: Every video posted in Discord fits the destination's limit.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T021 [P] Update `README.md`: in the Discord section, document `/create-video` from Discord, the allowlist rule, the shrink behaviour, `DISCORD_API_DELIVERY_TIMEOUT`, and that the bridge needs `ffmpeg`/`ffprobe`. Add `DeliverVideoToDiscord.php` to the Project Structure tree under a `Listeners/` entry.
- [ ] T022 [P] Update `ARCHITECTURE.md`: add the delivery flow (`VideoGenerationFinished` → `DeliverVideoToDiscord` → bridge route) and the outbound bridge call next to the discovery call.
- [ ] T023 [P] Update `node-discord-api/README.md`: document the new route, its body and responses (from contracts/bridge-video-delivery.md), the "You can't request videos." rule, rejection messages, and the `ffmpeg`/`ffprobe` requirement.
- [ ] T024 Run `php -l app/Listeners/DeliverVideoToDiscord.php` and `php -l app/Http/Controllers/Api/ConversationController.php`, and `node --check node-discord-api/index.js` (parse checks only, per CLAUDE.md).
- [ ] T025 Walk through quickstart.md manual scenarios 1–14 with the owner, who runs them against the live bots.
- [ ] T026 Commit the bridge changes in `node-discord-api` as `Gabriel <gabrieleremita@gmail.com>` with the `Co-Authored-By` trailer.
- [ ] T027 When the owner says it is time to push: run `vendor/bin/pint --dirty --format agent`, `npm run lint` and `php artisan test --compact` once, and fix everything they surface.

---

## Dependencies & Execution Order

- **Setup (T001–T002)**: no dependencies.
- **Foundational (T003–T004)**: after Setup. Blocks US1 and US4.
- **US1 (T005–T012)**: after T004. T007 → T008 (same file). T009 needs T004. T010 needs T002. T011 and T012 touch `index.js`, so they run one after another after T010.
- **US2 (T013–T014)**: after T007.
- **US4 (T015–T017)**: after T009 and T010.
- **US3 (T018–T020)**: after T017 (reuses `postReply` and the notice path).
- **Polish (T021–T027)**: after the stories it documents. T027 runs last, only on the owner's instruction.

### Parallel opportunities

- T001 and T002 (different repos).
- T005 and T006 (different test files).
- Laravel tasks (T007–T009) and bridge tasks (T010–T012) run side by side.
- T021, T022 and T023 (different files).

## Implementation Strategy

1. **MVP**: Setup, Foundational and US1. A Discord `/create-video` posts a reply now and the video later. Validate with quickstart scenarios 1–6.
2. Add US2 (first frame), then US4 (failure notices and retries), then US3 (shrinking).
3. Documentation, parse checks, the owner's manual run-through, then the gates once at push time.
