# Contracts: Video Generation

## Video Gen providers and models

Same routes, middleware group, request bodies and responses as the image versions, under new names:

| Route name | Method and path | Mirrors |
|---|---|---|
| `video-gen-providers.index` | `GET /video-gen-providers` | `image-gen-providers.index` (providers with `models`, `has_key`, no `api_key`) |
| `video-gen-providers.store` | `POST /video-gen-providers` | `image-gen-providers.store` |
| `video-gen-providers.update` | `PATCH /video-gen-providers/{id}` | `image-gen-providers.update` |
| `video-gen-providers.destroy` | `DELETE /video-gen-providers/{id}` | `image-gen-providers.destroy` |
| `video-gen-models.store` | `POST /video-gen-providers/{provider}/models` | `image-gen-models.store` |
| `video-gen-models.update` | `PATCH /video-gen-providers/{provider}/models/{model}` | `image-gen-models.update` |
| `video-gen-models.destroy` | `DELETE /video-gen-providers/{provider}/models/{model}` | `image-gen-models.destroy` |
| `settings.selectVideoGenModel` | `PUT /assistants/{assistant}/settings/video-gen-model` with `{ "video_gen_model_id": int\|null }` | `settings.selectImageGenModel` |

`settings.show` also returns `video_gen_model_id`.

## Messages carry their video

`conversations.show` returns a `video` key on each message (null when the message has none):

```json
{
  "id": 42,
  "status": "generating",
  "url": null,
  "prompt": "A slow dolly shot of …",
  "duration": 8,
  "aspect_ratio": "16:9",
  "failure_reason": null
}
```

`url` is set once `status` is `completed`; `failure_reason` once it is `failed`.

## `/create-video`

`POST conversations.sendMessage` with `message.content` starting with `/create-video` (and optionally `message.images[0]`).

Response, `200`:

```json
{
  "conversation_id": 7,
  "content": "in-character reply",
  "thinking": "the improved video description",
  "emotion": "happy",
  "intimate": false,
  "pose": null,
  "tts_instructions": null,
  "video": { "id": 42, "status": "queued", "url": null, "prompt": "…", "duration": 8, "aspect_ratio": "16:9", "failure_reason": null }
}
```

Errors: `422` with `message` for the three validation cases in data-model.md.

## `generate_video` agent tool

Offered to agent-mode assistants when `VideoGenerationService::isAvailableFor()` is true, next to `generate_image`.

```json
{
  "type": "object",
  "properties": {
    "prompt": { "type": "string", "description": "A description of the video to generate." },
    "duration": { "type": "integer", "description": "Length in seconds, when the user asked for one." },
    "aspect_ratio": { "type": "string", "description": "Aspect ratio such as 16:9 or 9:16, when the user asked for one." },
    "generate_audio": { "type": "boolean", "description": "Whether the video has sound, when the user said." }
  },
  "required": ["prompt"]
}
```

Description: "Starts generating a short video from a text description and shows it to the user when it is ready. Use when the user asks for a video, clip or animation. An image the user attached to this message becomes the first frame."

Result: `{ "status": "queued", "video_id": 42, "enhanced_prompt": "…" }`. The chat response's `tool_calls` carry it, and the frontend adds a placeholder message for each `video_id`, as it does for `image_url`.

## Broadcasts

| Event | Channel | Name | Payload |
|---|---|---|---|
| `VideoGenerationStatusUpdated` | `private-conversation.{conversationId}` | `.video-generation.updated` | `{ "messageId": 99, "video": { …same shape as above… } }` |
| `VideoGenerationFinished` | `private-user.{userId}` (new, authorized when `$user->id === $userId`) | `.video-generation.finished` | `{ "conversationId": 7, "assistantId": 3, "assistantName": "…", "status": "completed", "failureReason": null }` |

## Environment

| Key | Default | Purpose |
|---|---|---|
| `PUBLIC_TUNNEL_URL` | empty | Public address that input images are linked through (`ai.video_gen.public_url`) |
| `VIDEO_GEN_URL` | `https://openrouter.ai/api/v1/videos` | Fallback provider URL (`ai.video_gen.url`) |
| `VIDEO_GEN_API_KEY` | `AI_DEFAULT_API_KEY` | Fallback key |
| `VIDEO_GEN_MODEL` | empty | Fallback model; video generation is unavailable without a selected model or this |
| `VIDEO_GEN_TIMEOUT` | `600` | Fallback maximum wait in seconds |
