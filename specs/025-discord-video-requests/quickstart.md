# Quickstart: Discord Video Requests

## Prerequisites

- `composer run dev` is running (queue worker included).
- The assistant has a video model selected on its Video Gen page.
- `DISCORD_API_URL` and `DISCORD_API_SECRET` are set, and the Discord API service is running for the end-to-end scenarios.
- For the first-frame scenarios, `PUBLIC_TUNNEL_URL` is set and the tunnel is up.

## Automated

```bash
php artisan test --compact --filter=DiscordCreateVideo
```

```bash
php artisan test --compact --filter=DeliverVideoToDiscord
```

These cover the `/create-video` branch of the Discord messages endpoint (started, empty, no model, image without public address, image as first frame, no expression tags in the reply) and the delivery listener (payload, reply target, web conversations skipped, deleted conversations, retry on unreachable/404/502, no retry on 401/422, logging). See [contracts/](contracts/).

## End to end

| # | Do | Expect in the app |
|---|---|---|
| 1 | Send `/create-video a cat walking on a piano` from Discord | An in-character reply comes back right away. The conversation shows a queued video on the assistant's message. |
| 2 | Wait for it to finish | `storage/logs/laravel.log` has no delivery error, and the video is posted in Discord |
| 3 | Open that conversation in the web app | The video plays in its message |
| 4 | Send `/create-video` with no description | Reply: "Describe what video to generate after /create-video." |
| 5 | Clear the assistant's video model, send `/create-video a dog` | Reply: "No video generation model is configured for this assistant." |
| 6 | Attach an image to `/create-video the camera slowly pulls back` | The video starts on the image |
| 7 | Unset `PUBLIC_TUNNEL_URL`, repeat 6 | Reply: "Set PUBLIC_TUNNEL_URL to generate a video from an image." |
| 8 | Stop the Discord API service while a video generates, start it within a few minutes of the video finishing | The video is still delivered |
| 9 | Keep the Discord API service stopped for more than 9 minutes after the video finishes | `storage/logs/laravel.log` has "Discord video delivery failed" with the video and conversation IDs. The web app has the video. |
