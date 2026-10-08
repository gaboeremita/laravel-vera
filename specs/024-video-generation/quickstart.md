# Quickstart: Video Generation

## Prerequisites

- `composer run dev` running: queue worker, Reverb and Vite.
- An OpenRouter API key with credit.
- For videos from an image: a tunnel to the local site (e.g. `herd share`), with its HTTPS address in `.env` as `PUBLIC_TUNNEL_URL`. Restart the queue worker after editing `.env`.
- `php artisan migrate`.

## Automated checks

```bash
php artisan test --compact --filter=Video
```

Covers the provider and model settings API and ownership, `/create-video` validation and response, the tool, the job's transitions (`Http::fake` for pending, in progress, completed, failed, expired, rejected submit, failed download and timeout), the broadcasts, and the history line.

## Manual scenarios

1. **Settings (US5)**
   - Open an assistant's menu, then Video Gen. Add provider "OpenRouter" with URL `https://openrouter.ai/api/v1/videos` and your key.
   - Add model "Seedance 2.0" with endpoint `bytedance/seedance-2.0` and config `{ "duration": 5, "aspect_ratio": "16:9", "generate_audio": false }`, then SELECT it.
   - Expect: the page looks and behaves like Image Gen, and the model shows `● ACTIVE`.
2. **Text to video (US1)**
   - In a chat, send `/create-video a cat walking across a piano`.
   - Expect: an in-character reply right away, with a "Generating video…" placeholder under it. Within a few minutes the placeholder turns into a player. Reload: the video is still there.
3. **Settings from the request (FR-005a/b)**
   - Send `/create-video a vertical 40 second clip of rain on a window`.
   - Expect: a 9:16 video at the longest length the model supports.
4. **Image to video (US2)**
   - With the tunnel running, attach an image and send `/create-video the camera slowly pulls back`.
   - Expect: the video starts on the attached image.
   - Stop the tunnel and repeat. Expect: the message ends as failed, with the provider's reason.
5. **Notice (US3)**
   - Start a video, then open another assistant's conversation list.
   - Expect: a toast saying the video is ready, whose OPEN action goes to the conversation.
6. **Assistant decides (US4)**
   - With an agent-mode assistant, say "make me a short clip of us at the beach". Expect: a placeholder, then a video.
   - Chat normally for a few turns. Expect: no video is started.
7. **History (FR-012a)**
   - After a video finishes, ask "what did you think of that clip?" Expect: the reply refers to what the video showed.
