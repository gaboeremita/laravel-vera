# Contract: Discord video delivery (outbound)

The request `DeliverVideoToDiscord` sends to the Discord API service, and how the app treats each answer.

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
- The app waits up to `DISCORD_API_DELIVERY_TIMEOUT` seconds (default 180) for the answer.

## Answers

| Status | Meaning | App |
|---|---|---|
| 2xx | Posted | Done |
| No answer, connection error, timeout | Service unavailable | Retry |
| 404 | No bot for the assistant | Retry |
| 502 | The service couldn't download `videoUrl` | Retry |
| Any other non-2xx | Unexpected error | Retry |
| 401 | Wrong secret | Log, no retry |
| 422 | Discord refused the post | Log, no retry |
