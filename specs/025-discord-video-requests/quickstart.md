# Quickstart: Discord Video Requests

## Prerequisites

- `composer run dev` is running (queue worker and Reverb included).
- node-discord-api is running (`npm start` in `~/repos/node-discord-api`) with at least one bot whose `*_DM_ALLOWLIST` contains your Discord user ID.
- `DISCORD_API_URL` and `DISCORD_API_SECRET` match between the two `.env` files.
- The assistant has a video model selected on its Video Gen page.
- `ffmpeg` and `ffprobe` are on the bridge's `PATH` (`brew install ffmpeg`).
- For the first-frame scenarios, `PUBLIC_TUNNEL_URL` is set and the tunnel is up.

## Automated (Laravel)

```bash
php artisan test --compact --filter=DiscordCreateVideo
```

```bash
php artisan test --compact --filter=DeliverVideoToDiscord
```

These cover the `/create-video` branch of `/discord-messages` (started, empty, no model, image without public address, image as first frame) and the delivery listener (payload, web conversations skipped, retry on unreachable/404/502, no retry on 401/422, reply target lookup). See [contracts/](contracts/).

## Manual (both repos)

| # | Do | Expect |
|---|---|---|
| 1 | In a server channel the bot answers, send `/create-video a cat walking on a piano` | In-character reply right away. Later the video is posted as a reply to your message and plays inline. |
| 2 | Send the same in a DM with the bot | The same, in the DM |
| 3 | Open that conversation in the web app | The video plays in its message |
| 4 | Send `/create-video` with no description | The bot replies "Describe what video to generate after /create-video." |
| 5 | Clear the assistant's video model and send `/create-video a dog` | The bot replies "No video generation model is configured for this assistant." |
| 6 | From an account not on the allowlist, send `/create-video a dog` | The bot replies "You can't request videos." and nothing is generated |
| 7 | Attach an image to `/create-video the camera slowly pulls back` | The posted video starts on the image |
| 8 | Unset `PUBLIC_TUNNEL_URL`, repeat 7 | The bot replies "Set PUBLIC_TUNNEL_URL to generate a video from an image." |
| 9 | Ask for a long, high-resolution clip in an unboosted server | The posted video is under 10 MB. The web app plays the original. |
| 10 | Use a model or prompt the provider rejects | The bot replies "Video failed: <reason>" |
| 11 | Stop the bridge while a video generates, then start it within a few minutes of the video finishing | The video is still posted |
| 12 | Keep the bridge stopped for more than 9 minutes after the video finishes | Nothing is posted. `storage/logs/laravel.log` has the delivery failure. The web app has the video. |
| 13 | Delete your `/create-video` message before the video finishes | The video is posted in the channel without a reply reference |
| 14 | Trigger an `/create-image` failure (for example, no image model) | The bot posts the actual reason, not "Connection failed. Try again." |
