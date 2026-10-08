---

description: "Task list for video generation"
---

# Tasks: Video Generation

**Input**: Design documents from `specs/024-video-generation/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Included. CLAUDE.md requires every change to be tested, and the constitution (Principle VI) requires factory-backed Pest feature tests. Per CLAUDE.md, the test suite, Pint and ESLint run once, when the owner says it is time to push (T046).

**Organization**: Tasks are grouped by user story so each story can be implemented and tested on its own. Story phases run US5 → US1 → US2 → US3 → US4: the settings story (US5) comes first because every other story needs a configured model.

**Copy the image-generation code**: every task that names an `ImageGen*` or image file as its model copies that file's structure, naming, validation, docblocks and Tailwind classes, and changes only what video needs. Read the named file before writing the new one.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1–US5)

## Path Conventions

Laravel + React in one repository: `app/`, `config/`, `database/`, `routes/`, `resources/js/`, `tests/Feature/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Tables, enums, models, factories and config

- [X] T001 Create the migrations with `php artisan make:migration --no-interaction`, in this order:
  - `create_video_gen_providers_table`: copies `database/migrations/2026_08_18_234520_create_image_gen_providers_table.php`, with `prompt` as `json` from the start and `format` as an enum over `VideoGenProviderFormat` values, default `openrouter`.
  - `create_video_gen_models_table`: copies `2026_08_18_234521_create_image_gen_models_table.php`, with `prompt` as `json` and `provider_id` constrained to `video_gen_providers`.
  - `add_generation_columns_to_videos_table`: makes `path` nullable on the existing `videos` table and adds the nullable columns in data-model.md.
- [X] T002 [P] Create `app/Enums/VideoGenProviderFormat.php`, which copies `app/Enums/ImageGenProviderFormat.php`: one case `OpenRouter = 'openrouter'`, and `providerClass()` returning `OpenRouterVideoGenProvider::class`.
- [X] T003 [P] Create `app/Enums/VideoStatus.php`, a string-backed enum with `Queued = 'queued'`, `Generating = 'generating'`, `Completed = 'completed'` and `Failed = 'failed'`, plus `isFinished(): bool` (true for `Completed` and `Failed`).
- [X] T004 [P] Create `app/Models/VideoGenProvider.php` and `app/Models/VideoGenModel.php`, copying `app/Models/ImageGenProvider.php` and `app/Models/ImageGenModel.php`: `#[Fillable]`, `$hidden = ['api_key']`, the `has_key` append, casts, and relations. Add `HasFactory`.
- [X] T005 [P] Extend the existing `app/Models/Video.php`, keeping `videoable()` and its emotion fields:
  - `#[Fillable]` with every data-model.md column except `id` and timestamps.
  - Casts: `status` to `VideoStatus`, `generate_audio` to boolean.
  - Relations: `videoable()` stays; add `model()` (BelongsTo `VideoGenModel`, `video_gen_model_id`) and `firstFrame()` (BelongsTo `Image`, `first_frame_image_id`).
  - A `url` accessor that returns null without `path`, otherwise copies `Image::getUrlAttribute`.
  - `toChatPayload(): array` returning `id`, `status`, `url`, `prompt`, `duration`, `aspect_ratio`, `failure_reason`, in the shape in contracts/api.md.
  - `historyNote(): string` returning `[Video: "<prompt>" — generating]`, `— ready]` or `— failed: <failure_reason>]`. `queued` counts as generating. The assistant sees "ready" for the `Completed` status, matching FR-012a's wording.
  - `storeDownloaded(string $tempPath, string $storagePath): void`: puts the file on the `public` disk as `{storagePath}/{uuid}.mp4` and fills `path`, `disk`, `mime_type` (`video/mp4`) and `size`.

  Add `HasFactory`.
- [X] T006 [P] Add `video(): MorphOne` (`videoable`) to `app/Models/Message.php`, next to `image()`. Add `videoGenProviders(): HasMany` to `app/Models/User.php`, next to `imageGenProviders()`.
- [X] T007 [P] Create the factories:
  - `database/factories/VideoGenProviderFactory.php`: belongs to a `User`, with `url` `https://fake-video.test/api/v1/videos`, `api_key` `test-key`, `format` `openrouter`.
  - `database/factories/VideoGenModelFactory.php`: belongs to a provider, with `endpoint` `test/video-model` and `config` `['duration' => 5, 'aspect_ratio' => '16:9', 'generate_audio' => false, 'timeout' => 600]`.
  - `database/factories/VideoFactory.php`: `videoable` is an assistant `Message`, and it belongs to a `VideoGenModel`, with `status` `queued` and a `prompt`, plus the states `generating()`, `completed()` (sets `path`, `mime_type`, `size`) and `failed(string $reason = 'Provider error')`.
- [X] T008 [P] Add a `video_gen` block to `config/ai.php` after `image_gen`, with the keys in contracts/api.md Environment:
  - `url`, defaulting to `https://openrouter.ai/api/v1/videos`
  - `key`, defaulting to `VIDEO_GEN_API_KEY` and then `AI_DEFAULT_API_KEY`
  - `model`, defaulting to `VIDEO_GEN_MODEL`, empty
  - `format`, defaulting to `openrouter`
  - `timeout`, defaulting to `(int) VIDEO_GEN_TIMEOUT`, 600
  - `public_url`, defaulting to `PUBLIC_TUNNEL_URL`

  Add the same keys to `.env.example`, after the image generation block.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The provider contract, the OpenRouter client, the manager and the broadcasts. Every story needs these.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

### Tests for the foundation

- [X] T009 [P] Write `tests/Feature/OpenRouterVideoGenProviderTest.php` with `Http::fake`. Cover:
  - `submit()` posts `model`, `prompt`, `duration`, `aspect_ratio`, `generate_audio`, the model's `additional_config`, and `frame_images` with `frame_type: first_frame` when a first-frame URL is given. It sends the Bearer header and returns the job id.
  - `submit()` throws a `RuntimeException` carrying the response body on a non-2xx response.
  - `status()` maps `pending` to `Queued`, `in_progress` to `Generating`, `completed` to `Completed` with `contentUrl` from `unsigned_urls.0`, and `failed`, `cancelled` and `expired` to `Failed` with the provider's `error` (or the status word when `error` is missing).
  - `download()` sends the Bearer header and writes the body to the given path. A non-2xx response throws.
  - `supportedSettings()` reads `supported_durations` and `supported_aspect_ratios` for the model's endpoint from `GET {url}/models`, and caches them.

### Implementation for the foundation

- [X] T010 [P] Create `app/DTOs/VideoGenJobStatus.php` (readonly: `VideoStatus $status`, `?string $contentUrl`, `?string $error`) and `app/DTOs/VideoGenSupportedSettings.php` (readonly: `list<int> $durations`, `list<string> $aspectRatios`), styled like `app/DTOs/ImageGenResult.php`.
- [X] T011 [P] Create `app/Contracts/VideoGenProvider.php`, styled like `app/Contracts/ImageGenProvider.php`:
  - `submit(string $prompt, array $options = [], ?string $firstFrameUrl = null): string`
  - `status(string $jobId): VideoGenJobStatus`
  - `download(string $contentUrl, string $targetPath): void`
  - `supportedSettings(): ?VideoGenSupportedSettings`
  - `static fromModel(VideoGenModel $videoGenModel): static`
- [X] T012 Create `app/Services/VideoGenProviders/OpenRouterVideoGenProvider.php`, which copies `OpenRouterImageGenProvider`: constructor promotion, `fromModel()`, `headers()`, `payload()`, and `ConnectionException` handling. Endpoints come from research R1. `supportedSettings()` caches the listing for a day under `video-gen-supported:{md5(url)}:{endpoint}`, and returns null (logging a warning) when the listing can't be read or doesn't list the model. Make T009 pass.
- [X] T013 Create `app/Services/VideoGenProviders/VideoGenManager.php`, which copies `ImageGenManager`:
  - `forAssistantUser()`, `fromModel()`, `fromConfig()`
  - `resolveVideoGenModel()`, reading `settings.data.video_gen_model_id`, scoped to the user's providers
  - `fromConfig()` throws `InvalidArgumentException` when `ai.video_gen.model` is empty.
- [X] T014 [P] Create `app/Events/VideoGenerationStatusUpdated.php`, which copies `AvatarBackgroundStatusUpdated`: `ShouldBroadcastNow` on `PrivateChannel("conversation.{id}")`, named `video-generation.updated`, with payload `['messageId' => …, 'video' => $video->toChatPayload()]`. It is built from a `Video`.
- [X] T015 [P] Create `app/Events/VideoGenerationFinished.php`: `ShouldBroadcastNow` on `PrivateChannel("user.{userId}")`, named `video-generation.finished`, with payload `conversationId`, `assistantId`, `assistantName`, `status` and `failureReason`. It is built from a `Video` by loading `message.conversation`, the conversation's owning user and its assistant.
- [X] T016 [P] Add `Broadcast::channel('user.{userId}', fn (User $user, int $userId) => $user->id === $userId);` to `routes/channels.php`.

**Checkpoint**: The provider client and broadcasts exist. User story work can begin.

---

## Phase 3: User Story 5 - Configure video providers and models per assistant (Priority: P1)

**Goal**: A Video Gen page per assistant, identical in layout and behaviour to Image Gen

**Independent Test**: Add a provider and a model on the Video Gen page, select the model, reload, and see it still marked active.

### Tests for User Story 5

- [X] T017 [P] [US5] Write `tests/Feature/VideoGenProviderControllerTest.php`. Cover:
  - CRUD for providers and models through the named routes in contracts/api.md.
  - `api_key` is never returned, and `has_key` reflects it.
  - `config` is validated against `config_schema`.
  - Another user's provider or model gets a 404.
  - `settings.selectVideoGenModel` stores the id, rejects another user's model, and accepts null.
  - `settings.show` returns `video_gen_model_id`.
  - Deleting the selected model makes `VideoGenManager::resolveVideoGenModel()` return null.

### Implementation for User Story 5

- [X] T018 [P] [US5] Create `app/Http/Controllers/Api/VideoGenProviderController.php` and `app/Http/Controllers/Api/VideoGenModelController.php`, copying the two `ImageGen*` controllers, with `videoGenProviders()` and `VideoGenProviderFormat`.
- [X] T019 [US5] Add `selectVideoGenModel()` to `app/Http/Controllers/Api/SettingsController.php`, copying `selectImageGenModel()`, and return `video_gen_model_id` from `show()`. Register the routes from contracts/api.md in `routes/api.php`, next to the image-gen ones. Make T017 pass.
- [X] T020 [P] [US5] Create `resources/js/hooks/useVideoGenProviders.js`, copying `useImageGenProviders.js`, with the `video-gen-*` routes, `settings.selectVideoGenModel`, `video_gen_model_id`, and the toast texts with "video". A new provider's form prefills `url` with `https://openrouter.ai/api/v1/videos`, and prefills `config_schema` with these fields in `SchemaEditor`'s format:
  - `duration` (number)
  - `resolution` (string)
  - `aspect_ratio` (string)
  - `generate_audio` (boolean)
  - `timeout` (number)
- [X] T021 [P] [US5] Create `resources/js/components/VideoGenProviderAccordion.jsx` and `resources/js/components/VideoGenModelAccordion.jsx`, copying the image accordions, with the placeholders `e.g. Seedance 2.0` and `e.g. bytedance/seedance-2.0`.
- [X] T022 [US5] Create `resources/js/pages/VideoGenProvidersPage.jsx`, copying `ImageGenProvidersPage.jsx`, with the header text "Video Gen Providers" and the empty state "No video generation providers configured." Add the route `video-gen-providers` in `resources/js/app.jsx`, next to `image-gen-providers`. Add `{ label: 'Video Gen', to: …/video-gen-providers, icon: Video }`, with `Video` imported from lucide-react, after Image Gen in `resources/js/utils/assistantMenu.jsx`.

**Checkpoint**: Providers and models can be configured and selected.

---

## Phase 4: User Story 1 - Ask for a video and watch it arrive in the chat (Priority: P1) 🎯 MVP

**Goal**: `/create-video <description>` replies in character, shows a placeholder, and turns into a playable video that survives reloads

**Independent Test**: quickstart.md scenario 2

### Tests for User Story 1

- [X] T023 [P] [US1] Write `tests/Feature/CreateVideoCommandTest.php`. Set up the assistant with the existing helpers in `tests/Pest.php` (as the image command tests do), fake the LLM with `Http::fake`, and use `Bus::fake`. Cover:
  - An empty description gets a 422 with the data-model.md message.
  - No model available gets a 422.
  - A valid command returns the contracts/api.md response with `video.status` `queued`, creates an assistant message with the reply text and a `Video` holding the improved description and the model's defaults, and dispatches `PollVideoGeneration`.
  - Length, aspect ratio and sound from the improver's JSON override the defaults, adjusted to the closest supported value (see T026).
  - A non-JSON improver reply falls back to the whole reply and the defaults.
  - A failing improver or reply LLM call returns a 502 with its message and creates no video.
  - The same command in a world conversation (`worldStateScenario()`) creates the video the same way.
  - `conversations.show` returns `video` on that message.
- [X] T024 [P] [US1] Write `tests/Feature/PollVideoGenerationTest.php` with `Http::fake` sequences, `Event::fake([VideoGenerationStatusUpdated::class, VideoGenerationFinished::class])` and `Storage::fake('public')`. Cover:
  - The first run submits, stores `job_id`, and releases.
  - `in_progress` sets `generating` and broadcasts.
  - `completed` downloads, stores the file, sets `completed`, and broadcasts both events.
  - `failed`, `expired` and `cancelled` set `failed` with the reason and broadcast both events.
  - A rejected submit sets `failed` with the response body.
  - A failed download sets `failed`.
  - `failed()` after the deadline sets `failed` with `timed out after N seconds`.
  - A deleted conversation makes the job end without error and without broadcasts.
  - Two videos in the same conversation each get their own `job_id`, status changes and broadcasts.

### Implementation for User Story 1

- [X] T025 [P] [US1] Create `app/Services/VideoGenProviders/VideoGenPromptEnhancer.php`, copying `ImageGenPromptEnhancer`: same director setup, retrieval, memory, `additionalPrompts()` and `recentHistory()`, but under the section name `video generation instructions`. Its task instruction asks for one JSON object `{"description": string, "duration": int|null, "aspect_ratio": string|null, "generate_audio": bool|null}`, describing motion and camera as well as subject, setting, lighting and style, with `null` for anything the request doesn't specify. Follow the no-negatives rule for LLM prompts. `enhance()` returns `array{description: string, duration: ?int, aspectRatio: ?string, generateAudio: ?bool}`. If the reply doesn't decode, it uses the trimmed reply (or the raw prompt) as the description with nulls, and logs a warning.
- [X] T026 [US1] Create `app/Services/VideoGenProviders/VideoGenerationService.php`, copying `ImageGenerationService`'s constructor and `isAvailableFor()`/`resolveTimeoutFor()` shape. Add `start(AssistantUser $assistantUser, Conversation $conversation, Message $carrierMessage, string $description, array $requested, ?Image $firstFrame): Video`. It:
  - merges `$requested` over the model's `config` defaults;
  - replaces duration and aspect ratio with the closest values from `supportedSettings()` (research R5), skipping this when that returns null;
  - creates the `Video` (`queued`, `video_gen_model_id`, the prompt, settings, `first_frame_image_id`);
  - dispatches `PollVideoGeneration`;
  - returns the video.

  Add `improveDescription()`, which wraps the enhancer. Add `firstFrameUrl(Image $image): string`, which builds the link from research R4 and throws `InvalidArgumentException` when `ai.video_gen.public_url` is empty.
- [X] T027 [US1] Create `app/Jobs/PollVideoGeneration.php`, styled like `GenerateAvatarBackground`. It has:
  - `ShouldQueue`, `Queueable`, `public bool $deleteWhenMissingModels = true`, `POLL_SECONDS = 30`
  - a constructor taking `public Video $video`
  - `retryUntil()` returning `created_at` plus the resolved timeout

  `handle(VideoGenManager $manager)` resolves the provider from `$video->model` (or `fromConfig()` when it is null), then:
  1. Without `job_id`, it submits with the first-frame URL when there is a first frame.
  2. Otherwise it reads the status.
  3. On `Completed`, it downloads to a temp file, calls `storeDownloaded()` under `messages/{userId}/{conversationId}`, and marks the video completed.
  4. On `Failed`, it marks the video failed with the error.
  5. While still running, it updates the status when it changed and calls `release(self::POLL_SECONDS)`.
  6. A `RuntimeException` from the provider marks the video failed with its message.

  Every status change saves and broadcasts `VideoGenerationStatusUpdated`. Final states also broadcast `VideoGenerationFinished` and log failures. `failed(Throwable)` marks the video failed with `timed out after N seconds` when the deadline passed, otherwise with the exception message. Comment why the job re-queues itself (plan Constitution Check III). Make T024 pass.
- [X] T028 [US1] In `app/Http/Controllers/Api/ConversationController.php`:
  - Add `/create-video` to `COMMANDS`, and `extractVideoGenPrompt()` next to `extractImageGenPrompt()`.
  - In `sendMessage`, right after the `/create-image` block, handle `/create-video` with the 422s from data-model.md, then call a new private `startVideoMessage()`, styled like `generateImageMessage()`. It improves the description and gets the in-character reply from a new `reactToStartedVideo()`, a copy of `reactToGeneratedImage()` whose current-state line reads `[You just started making a video. What it will show: "<description>"]`. It creates the assistant message with the parsed reply, and calls `VideoGenerationService::start()`.
  - Wrap the improver, reply and `start()` calls the way `/create-image` wraps `generateImageMessage()`: a `RuntimeException` returns a 502 with its message, and nothing is created.
  - Return the contracts/api.md payload.
  - In `show()`, eager-load `video` and add `$message->video = $message->video?->toChatPayload()` next to `image_url`.

  Make T023 pass.
- [X] T029 [P] [US1] Create `resources/js/components/MessageVideo.jsx`:
  - **Queued or generating:** the same thinking-avatar and `thinking-label` markup `ChatMessage` uses for `msg.loading`, with the text `Generating video…` or `Queued…`.
  - **Completed:** `<video src={video.url} controls playsInline preload="metadata" className="mt-1 mb-2 max-h-48 rounded border border-line-1" />`, the image block's classes.
  - **Failed:** `<p className="text-danger text-[0.7rem] mt-1">Video failed: {reason}</p>`, styled like `missingTerms`.
- [X] T030 [US1] In `resources/js/components/ChatMessage.jsx`, render `{msg.video && <MessageVideo video={msg.video} />}` right after the `msg.image` block, and label the `ThinkingBlock` "Video Prompt" when `msg.video` is set. In `resources/js/hooks/useConversationChat.js`, map `video: msg.video ?? null` in all three message mappers. Make the `/create-video` response (`data.video`) produce a reply message with `video`, the same way `data.image_url` produces one with `image`.
- [X] T031 [US1] Create `resources/js/hooks/useConversationVideos.js`, copying `useAvatarBackground.js`'s effect structure. It subscribes to `echo.private('conversation.{id}')` and listens for `.video-generation.updated`, calling `onVideoUpdated(data)`. Cleanup calls only `stopListening('.video-generation.updated')`, with a comment on why it doesn't call `echo.leave` (research R8). Use it in `useConversationChat.js` to replace the `video` of the message whose `video.id` matches.

**Checkpoint**: The MVP. Text-to-video works end to end and survives reloads.

---

## Phase 5: User Story 2 - Start a video from an attached image (Priority: P1)

**Goal**: An image attached to the `/create-video` message becomes the first frame

**Independent Test**: quickstart.md scenario 4

### Tests for User Story 2

- [X] T032 [P] [US2] Extend `tests/Feature/CreateVideoCommandTest.php`:
  - With `images[0]` and `ai.video_gen.public_url` set, the video's `first_frame_image_id` is the user message's image.
  - Without `public_url`, the request gets the 422 and creates no video.
- [X] T033 [P] [US2] Extend `tests/Feature/PollVideoGenerationTest.php`: the submit payload's `frame_images[0].image_url.url` is `{public_url}/storage/{path}` with `frame_type` `first_frame`, and a provider rejection of the image ends as `failed` with the body.

### Implementation for User Story 2

- [X] T034 [US2] In `ConversationController::sendMessage`'s `/create-video` branch, read the user message's stored `image`. Reject it with the 422 when `ai.video_gen.public_url` is empty, before the improver runs, and otherwise pass it to `start()` as `$firstFrame`. In `PollVideoGeneration`, pass `VideoGenerationService::firstFrameUrl($video->firstFrame)` to `submit()` when there is a first frame. Make T032 and T033 pass.

**Checkpoint**: Image-to-video works through the tunnel.

---

## Phase 6: User Story 3 - Be told when a video is ready anywhere in the app (Priority: P2)

**Goal**: A toast with an OPEN action on any page when a video finishes or fails

**Independent Test**: quickstart.md scenario 5

### Tests for User Story 3

- [X] T035 [P] [US3] Write `tests/Feature/VideoGenerationFinishedBroadcastTest.php`:
  - `VideoGenerationFinished` broadcasts on `private-user.{ownerId}` as `video-generation.finished`, with the contracts/api.md payload.
  - The `user.{id}` channel authorizes its owner and refuses another user (`POST /api/broadcasting/auth`, as the existing channel tests do, or `Broadcast::channel` resolution).

### Implementation for User Story 3

- [X] T036 [P] [US3] Extend `resources/js/hooks/useToast.js` so `addToast(message, type, extra)` keeps `extra.action` (`{ label, onClick }`). In `resources/js/components/ToastContainer.jsx`, render it before the ✕ as a button styled like the ✕ button (`text-accent/50 hover:text-accent`, `tracking-[0.15em] uppercase text-[0.6rem] font-bold`). It calls `onClick` and then dismisses the toast.
- [X] T037 [US3] Create `resources/js/hooks/useVideoGenerationNotices.js(userId, addToast, navigate)`. It subscribes to `echo.private('user.{userId}')` and listens for `.video-generation.finished`. On `completed` it shows a `success` toast "`{assistantName}`'s video is ready". On `failed` it shows an `error` toast "`{assistantName}`'s video failed: `{failureReason}`". Both carry the action `{ label: 'OPEN', onClick: () => navigate('/assistants/{assistantId}/conversations/{conversationId}') }`. Match the chat route already used by the conversation list. In `resources/js/layouts/AuthenticatedLayout.jsx`, keep the user id from the existing `user.show` response in state and call the hook once it is known.

**Checkpoint**: Notices reach the user anywhere in the app.

---

## Phase 7: User Story 4 - Assistants make videos on their own (Priority: P2)

**Goal**: Agent-mode assistants start a video when the user asks for one in plain words

**Independent Test**: quickstart.md scenario 6

### Tests for User Story 4

- [X] T038 [P] [US4] Write `tests/Feature/VideoGenerationToolTest.php`, modelled on `tests/Feature/ImageGenerationToolAvailabilityTest.php` and `ImageGenerationToolSingleCallTest.php`. Cover:
  - The tool is offered to agent-mode assistants only when a video model is available.
  - A `generate_video` call creates a carrier message with a `queued` video, dispatches the job, and returns `{status, video_id, enhanced_prompt}`.
  - `duration`, `aspect_ratio` and `generate_audio` arguments override the defaults.
  - An image attached to the triggering user message becomes the first frame.
  - With an attached image and no `ai.video_gen.public_url`, the tool call fails with the 422 wording, and no message or video is created.
  - Tool arguments win over the improver's values, which win over the model defaults.
  - The chat reply returns without waiting for the job (`Bus::fake`).

### Implementation for User Story 4

- [X] T039 [US4] Create `app/Services/AgentLoop/Tools/VideoGenerationTool.php`, copying `ImageGenerationTool`. It has the name `generate_video`, and the description and parameters from contracts/api.md. `handle()`:
  - validates `prompt`;
  - reads the conversation's latest user message image. With an image and an empty `ai.video_gen.public_url`, it throws a `RuntimeException` with the 422 wording from data-model.md before creating anything, so the agent loop reports it to the model;
  - improves the description;
  - creates the empty carrier message, as the image tool does;
  - calls `start()` with `$requested` built from the improver's values, overridden by any `duration`, `aspect_ratio` or `generate_audio` tool argument;
  - returns the contracts/api.md result.

  `timeoutSeconds()` is `config('ai.default.config.timeout') + 30`, covering the improver's LLM call. Register it in `ConversationController::sendMessage` next to `ImageGenerationTool`, when `VideoGenerationService::isAvailableFor()` is true. Make T038 pass.
- [X] T040 [US4] In `resources/js/hooks/useConversationChat.js`, next to the `call.result?.image_url` mapping, add a placeholder message `{ role: 'assistant', content: '', video: { id: call.result.video_id, status: 'queued', url: null, … } }` for each tool call whose `result.video_id` is set.

**Checkpoint**: All five stories work.

---

## Phase 8: Later turns know about videos (FR-012a)

**Purpose**: The assistant can talk about earlier videos. This spans US1 and US4.

- [X] T041 [P] Write `tests/Feature/VideoHistoryTest.php`:
  - `BuildConversationHistory::handle()` includes an assistant message with empty text and a video.
  - Its content ends with `Video::historyNote()` for each of the three states.
  - Messages without a video are unchanged.
- [X] T042 In `app/Actions/BuildConversationHistory.php`, change `stored()` to keep messages with non-empty content OR a video, eager-loading `video`. In `forChat()`, append `"\n".$message->video->historyNote()` (or use the note alone when the text is empty) when the message has a video. Make T041 pass.

---

## Phase 9: Polish & Cross-Cutting Concerns

- [X] T043 [P] Add the video generation feature and the `PUBLIC_TUNNEL_URL` / `VIDEO_GEN_*` keys to `README.md`, in the same place and style as image generation.
- [X] T044 [P] Check `ARCHITECTURE.md`. If it describes image generation, add the matching video generation paragraph: the job, broadcasts, and the tunnel address.
- [ ] T045 Walk through quickstart.md scenarios 1–7 by hand with the owner. The owner runs them; no browser automation. The second half of scenario 6 is the check for US4 scenario 4, which depends on the model's judgment and has no automated test.
- [ ] T046 When the owner says it is time to push: run `vendor/bin/pint --format agent`, `npm run lint` and `php artisan test --compact` once, and fix everything they report.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies.
- **Foundational (Phase 2)**: depends on Phase 1. It blocks every story.
- **US5 (Phase 3)**: depends on Phase 2.
- **US1 (Phase 4)**: depends on Phase 2. Its tests use factories, so it doesn't need US5's UI, but manual testing does.
- **US2 (Phase 5)**: depends on US1 (T026–T028).
- **US3 (Phase 6)**: depends on US1's job (T027) for real events. T035 and T036 can start right after Phase 2.
- **US4 (Phase 7)**: depends on US1's service (T026) and job (T027).
- **History (Phase 8)**: depends on T005. It can run any time after Phase 1.
- **Polish (Phase 9)**: after everything else.

### Within Each Story

Tests first, then backend, then frontend. A model comes before its service, its service before the controller, and the controller before the UI.

### Parallel Opportunities

- **Phase 1:** T002–T008 in parallel after T001.
- **Phase 2:** T009, T010, T011, T014, T015 and T016 in parallel. T012 and T013 run after T010 and T011.
- **US5:** T017, T018, T020 and T021 in parallel. T019 and T022 run after them.
- **US1:** T023, T024, T025 and T029 in parallel. Then T026 → T027 → T028, then T030 and T031.
- **US3:** T035 and T036 in parallel, then T037.
- **Phase 8:** T041 and T042 alongside any story phase.

## Parallel Example: User Story 1

```text
Together: T023 CreateVideoCommandTest, T024 PollVideoGenerationTest, T025 VideoGenPromptEnhancer, T029 MessageVideo.jsx
Then:     T026 VideoGenerationService → T027 PollVideoGeneration → T028 ConversationController
Then:     T030 ChatMessage + useConversationChat, T031 useConversationVideos
```

## Implementation Strategy

### MVP First

1. Phases 1 and 2.
2. Phase 3 (US5), so a model can be configured.
3. Phase 4 (US1): text-to-video in the chat. Stop and check quickstart scenarios 1–2.

### Incremental Delivery

1. Add US2 (image to video), and check scenario 4.
2. Add US3 (notices), and check scenario 5.
3. Add US4 (the assistant decides), and check scenario 6.
4. Add Phase 8 (history), and check scenario 7.
5. Polish, then gates once at push time (T046).
