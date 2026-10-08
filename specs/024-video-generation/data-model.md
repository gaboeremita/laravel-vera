# Data Model: Video Generation

Three new tables. `video_gen_providers` and `video_gen_models` copy `image_gen_providers` and `image_gen_models` column for column. The model selected for an assistant lives in the existing `settings.data` JSON, next to `image_gen_model_id`.

## video_gen_providers

Copies `image_gen_providers` (migrations `2026_08_18_234520`, `2026_08_19_001614`).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK users, cascade on delete | Owner (Principle IV) |
| name | string | Unique per user (`unique(user_id, name)`) |
| url | string | Base video endpoint, e.g. `https://openrouter.ai/api/v1/videos` |
| api_key | text, nullable | Cast `encrypted`, in `$hidden`; `has_key` appended (FR-025) |
| prompt | json, nullable | Extra instructions for the description writer, provider-wide |
| config_schema | json, nullable | Field definitions that drive the model `config` form (`SchemaForm`) and `ValidModelConfig` |
| format | enum `VideoGenProviderFormat`, default `openrouter` | Only `openrouter` today |
| timestamps | | |

**Model** `App\Models\VideoGenProvider`: `#[Fillable]` like `ImageGenProvider`, with `user()` BelongsTo and `models()` HasMany (`provider_id`). `User::videoGenProviders()` HasMany.

## video_gen_models

Copies `image_gen_models`.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| provider_id | FK video_gen_providers, cascade on delete | |
| name | string | Display name, e.g. "Seedance 2.0" |
| endpoint | string | Provider model id, e.g. `bytedance/seedance-2.0` |
| prompt | json, nullable | Model-specific instructions for the description writer |
| config | json, nullable | Defaults read by the code: `duration` (int s), `resolution` (string), `aspect_ratio` (string), `generate_audio` (bool), `timeout` (int s, maximum wait, default 600) |
| additional_config | json, nullable | Passed through into the submit payload, as with images |
| timestamps | | |

**Model** `App\Models\VideoGenModel`: casts `config`, `additional_config` and `prompt` to `array`, with `provider()` BelongsTo.

The supported durations and aspect ratios are not stored. They are read from the provider and cached (research R5).

## videos

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| message_id | FK messages, cascade on delete | The assistant message that shows the video |
| video_gen_model_id | FK video_gen_models, nullable, null on delete | The model it is generated with; null when the configured fallback (`ai.video_gen`) is used or the model was deleted |
| status | string, cast `VideoStatus` | `queued`, `generating`, `completed`, `failed` |
| job_id | string, nullable | Provider job id, set on submission |
| prompt | text | The improved description sent to the provider |
| duration | unsigned smallint, nullable | Seconds requested, after the closest-value adjustment |
| aspect_ratio | string, nullable | As requested, after adjustment |
| generate_audio | boolean, nullable | As requested |
| first_frame_image_id | FK images, nullable, null on delete | The attached image used as the first frame |
| failure_reason | text, nullable | Set when `failed` |
| path | string, nullable | Stored file, set when `completed` |
| disk | string, default `public` | |
| mime_type | string, nullable | |
| size | unsigned bigint, nullable | Bytes |
| timestamps | | `created_at` starts the maximum wait |

**Model** `App\Models\Video`:
- `message()` BelongsTo, `model()` BelongsTo `VideoGenModel`, `firstFrame()` BelongsTo `Image`.
- `url` accessor (`Storage::disk($this->disk)->url($this->path)`, null until completed), like `Image::getUrlAttribute`.
- `historyNote()`: the one line the assistant sees in later turns (research R7).
- `storeDownloaded(string $tempPath, string $storagePath)`: moves the file onto the disk and fills `path`, `mime_type` and `size`, like `Image::storeFromBase64`.

`Message::video()` HasOne.

## Enums

- `App\Enums\VideoGenProviderFormat` (`OpenRouter = 'openrouter'`) with `providerClass()`, like `ImageGenProviderFormat`.
- `App\Enums\VideoStatus`: `Queued`, `Generating`, `Completed`, `Failed`. `isFinished()` is true for `Completed` and `Failed`.

## State transitions

```text
queued ──(submitted, provider pending)──────────▶ queued
queued ──(provider in_progress)─────────────────▶ generating
queued|generating ──(completed + downloaded)────▶ completed
queued|generating ──(failed|cancelled|expired)──▶ failed  (provider reason)
queued|generating ──(submit rejected)───────────▶ failed  (provider reason)
queued|generating ──(download failed)───────────▶ failed  (download error)
queued|generating ──(maximum wait passed)───────▶ failed  ("timed out after N seconds")
```

`completed` and `failed` are final. Every transition saves the video and broadcasts `VideoGenerationStatusUpdated`. Reaching a final state also broadcasts `VideoGenerationFinished`.

## Validation rules

- **Provider store and update:** same rules as `ImageGenProviderController`, with `format` validated as `Enum(VideoGenProviderFormat::class)`.
- **Model store and update:** same rules as `ImageGenModelController`, including `ValidModelConfig($provider->config_schema)`.
- **`settings.selectVideoGenModel`:** `video_gen_model_id` is nullable, an integer, and must exist in `video_gen_models` under one of the user's providers, as with `selectImageGenModel`.
- **`/create-video`:**
  - Empty description: 422 "Describe what video to generate after /create-video."
  - No model available: 422 "No video generation model is configured for this assistant."
  - Attached image without `ai.video_gen.public_url`: 422 "Set PUBLIC_TUNNEL_URL to generate a video from an image."
