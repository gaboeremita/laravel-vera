# Contract: Bridge video delivery

New route in node-discord-api, called by Laravel's `DeliverVideoToDiscord` listener.

## Request

```text
POST {DISCORD_API_URL}/assistants/{assistantId}/channels/{channelId}/videos
X-Internal-Secret: {DISCORD_API_SECRET}
Content-Type: application/json
```

Completed video:

```json
{
  "replyToMessageId": "1290000000000000000",
  "videoUrl": "https://laravel-vera.test/storage/messages/1/42/video.mp4"
}
```

Failed video:

```json
{
  "replyToMessageId": "1290000000000000000",
  "failureReason": "The provider rejected the request."
}
```

- `replyToMessageId` may be `null`.
- Exactly one of `videoUrl` and `failureReason` is present.

## Bridge behaviour

1. Check `X-Internal-Secret` the same way as discovery.
2. Find the bot for `assistantId` and fetch `channelId` with that bot's client.
3. If `failureReason` is present, post `Video failed: {failureReason}`.
4. Otherwise:
   1. Download `videoUrl`.
   2. Work out the destination's upload limit (research R7).
   3. Shrink the video if it's over the limit (research R8).
   4. Post the file as `video.mp4`, or, when no copy fits, post `Video too large for Discord — watch it in the web app`.
5. Post as a reply to `replyToMessageId`. When it's null or the message no longer exists, post in the channel without a reply reference.

## Responses

| Status | Body | Meaning | Laravel |
|---|---|---|---|
| 200 | `{"posted": "video"}`, `{"posted": "notice"}` | Posted | Done |
| 401 | `{"message": "Unauthorized"}` | Wrong secret | Log, no retry |
| 404 | `{"message": "No bot configured for assistant {id}"}` | Bot not running | Retry |
| 422 | `{"message": "<Discord error>"}` | Discord refused the channel fetch or the post | Log, no retry |
| 502 | `{"message": "<download error>"}` | Couldn't download `videoUrl` | Retry |

Laravel waits up to `DISCORD_API_DELIVERY_TIMEOUT` seconds (default 180) for the answer.
