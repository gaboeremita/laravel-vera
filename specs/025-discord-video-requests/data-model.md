# Data Model: Discord Video Requests

No schema changes. The feature reads existing records and sends one transient payload.

## Existing records used

### Conversation

- `discord_channel_id`: set for Discord conversations. Its presence decides whether a finished video is delivered to Discord (FR-006, FR-011).
- `assistantUser()`: gives the `assistant_id` for the bridge route.

### Message

- The **user message** carries `discord_message_id`, the Discord message the video is posted in reply to.
- The **assistant message** carries the `Video` through the `videoable` morph, as in the web flow.
- The reply target for a video is the `discord_message_id` of the latest user message in the same conversation with an ID lower than the assistant message's ID (research R4).

### Video

- `status`: `completed` or `failed` when `VideoGenerationFinished` fires.
- `url` (accessor): the address the bridge downloads the finished file from. It is on the app's own address.
- `failure_reason`: sent to the bridge for failed videos.
- The stored file is never changed by delivery. Shrinking happens on a copy in the bridge (FR-016).

### Image

- An image attached to a Discord `/create-video` message is stored with `Image::storeFromBase64` on the user message, as today. The returned `Image` becomes the video's `first_frame_image_id`.

## Transient: Discord delivery

The body of the request from `DeliverVideoToDiscord` to the bridge. It isn't stored; it exists only while the queued listener runs and retries. Full shape in [contracts/bridge-video-delivery.md](contracts/bridge-video-delivery.md).

| Field | Source | Notes |
|---|---|---|
| `assistantId` (route) | `conversation->assistantUser()->assistant_id` | Picks the bot |
| `channelId` (route) | `conversation->discord_channel_id` | Destination |
| `replyToMessageId` | Reply target (above) | Null when unknown |
| `videoUrl` | `video->url` | Present only when `status` is `completed` |
| `failureReason` | `video->failure_reason` | Present only when `status` is `failed` |

## State

```text
VideoGenerationFinished (completed | failed)
  └─ conversation has discord_channel_id?
       no  → nothing
       yes → deliver
             ├─ 200                   → done
             ├─ unreachable/404/502   → retry after 10s, 30s, 1m, 2m, 5m → log and stop
             └─ 401/422               → log and stop
```
