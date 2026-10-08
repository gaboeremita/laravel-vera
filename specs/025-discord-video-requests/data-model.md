# Data Model: Discord Video Requests

No schema changes. The feature reads existing records and sends one transient payload.

## Existing records used

### Conversation

- `discord_channel_id`: set for Discord conversations. Its presence decides whether a video's outcome is delivered (FR-007, FR-010).
- `assistantUser()`: gives the `assistant_id` for the delivery route.

### Message

- The **user message** carries `discord_message_id`, the reply target.
- The **assistant message** carries the `Video` through the `videoable` morph, as in the web flow.
- The reply target for a video is the `discord_message_id` of the latest user message in the same conversation with an ID lower than the assistant message's ID (research R4).

### Video

- `status`: `completed` or `failed` when `VideoGenerationFinished` fires.
- `url` (accessor): the address the Discord API service downloads the finished file from, on the app's own address.
- `failure_reason`: sent for failed videos.

### Image

- An image attached to a Discord `/create-video` message is stored with `Image::storeFromBase64` on the user message, as today. The returned `Image` becomes the video's `first_frame_image_id`.

## Event change

`VideoGenerationFinished` gains `public int $videoId`, set from the video in its constructor. The listener loads the video by it.

## Transient: Discord delivery

The body sent by `DeliverVideoToDiscord`. It isn't stored; it exists only while the queued listener runs and retries. Full shape in [contracts/discord-video-delivery.md](contracts/discord-video-delivery.md).

| Field | Source | Notes |
|---|---|---|
| `assistantId` (route) | `conversation->assistantUser()->assistant_id` | |
| `channelId` (route) | `conversation->discord_channel_id` | |
| `replyToMessageId` | Reply target (above) | Null when unknown |
| `videoUrl` | `video->url` | Present only when `status` is `completed` |
| `failureReason` | `video->failure_reason` | Present only when `status` is `failed` |

## State

```text
VideoGenerationFinished (completed | failed)
  └─ conversation has discord_channel_id?
       no  → nothing
       yes → deliver
             ├─ 2xx                    → done
             ├─ unreachable/other      → retry after 10s, 30s, 1m, 2m, 5m → log and stop
             └─ 401/422                → log and stop
```
