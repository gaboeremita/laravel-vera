# Contract: `/discord-messages` and the bridge's message handling

## Laravel: `POST /api/assistants/{assistant}/discord-messages`

The request body is unchanged. The `/create-video` behaviour is new.

| Content | Status | Body |
|---|---|---|
| `/create-video <description>`, video started | 200 | `{"content": "<in-character reply>"}` |
| `/create-video` with no description | 422 | `{"message": "Describe what video to generate after /create-video."}` |
| No video model for the assistant | 422 | `{"message": "No video generation model is configured for this assistant."}` |
| Attached image, `PUBLIC_TUNNEL_URL` unset | 422 | `{"message": "Set PUBLIC_TUNNEL_URL to generate a video from an image."}` |
| Description or reply call fails | 502 | `{"message": "<error>"}` |

- When `images[0]` is present, it becomes the video's first frame.
- The response has no `video` field. The video reaches Discord later through [bridge-video-delivery.md](bridge-video-delivery.md).

## Bridge: forwarding a message

1. **Video access:** if the content matches `^/create-video(\s|$)` (case-insensitive) and the author's ID is not in the bot's DM allowlist, reply `You can't request videos.` and don't call Laravel.
2. **Non-2xx from Laravel:** post `message` from the JSON body. If the body has no `message`, or the request throws, post `Connection failed. Try again.` This applies to every message, not just `/create-video`.
