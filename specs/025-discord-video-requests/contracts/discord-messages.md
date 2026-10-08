# Contract: `POST /api/assistants/{assistant}/discord-messages`

The request body is unchanged. The `/create-video` behaviour is new.

| Content | Status | Body |
|---|---|---|
| `/create-video <description>`, video started | 200 | `{"content": "<in-character reply>"}` |
| `/create-video` with no description | 422 | `{"message": "Describe what video to generate after /create-video."}` |
| No video model for the assistant | 422 | `{"message": "No video generation model is configured for this assistant."}` |
| Attached image, `PUBLIC_TUNNEL_URL` unset | 422 | `{"message": "Set PUBLIC_TUNNEL_URL to generate a video from an image."}` |
| Description or reply call fails | 502 | `{"message": "<error>"}` |

- When `images[0]` is present, it becomes the video's first frame.
- The response has no `video` field. The outcome is sent later through [discord-video-delivery.md](discord-video-delivery.md).
