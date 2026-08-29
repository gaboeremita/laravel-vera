# Quickstart: Validating 3D Avatar Scene Backgrounds

## Prerequisites

- An `Assistant` with `portrait_type = Avatar3D` and a VRM file attached (see existing VRM avatar setup — out of scope for this feature).
- An image-generation provider configured for that assistant (existing `ImageGenProvider`/`ImageGenModel` setup, same requirement `ImageGenerationTool` already has via `ImageGenerationService::isAvailableFor()`).
- A queue worker running (`php artisan queue:work`, or `composer run dev` which already starts one — check `composer.json`'s `dev` script).

## Scenario 1 — Automatic background at conversation start (User Story 2, P2)

1. Ensure the assistant has an `opening_message` describing or implying a setting (e.g. "Welcome to the rooftop bar...").
2. Start a new conversation with that assistant (`POST /assistants/{assistant}/conversations`, or via the UI's "New conversation").
3. Poll `GET /assistants/{assistant}/conversations/{id}/avatar-background` (or watch the UI) until `background` is non-null.
4. **Expected**: within ~60s, the avatar is shown standing on a floor with a curved backdrop matching the opening message's setting, cross-fading in rather than popping in abruptly.

## Scenario 2 — Manual command (User Story 1, P1)

1. In an existing conversation, send `/change-background a futuristic park` as a chat message.
2. Poll the status endpoint.
3. **Expected**: the previous background cross-fades out, the new futuristic-park scene cross-fades in within ~60s. The chat transcript is unaffected (no new visible message from the command itself, beyond whatever the assistant naturally says next).

## Scenario 3 — Agent-mode natural-language request (User Story 1, P1)

1. Using an assistant in `mode = Agent`, send a normal chat message asking to change the background (e.g. "can you change the background to a futuristic park?").
2. **Expected**: the tool-calling model invokes `change_avatar_background` (visible in the response's `tool_calls`), and the background updates the same way as Scenario 2.

## Scenario 4 — Archive-grounded accuracy (User Story 1, acceptance scenario 3)

1. Add an `ArchiveEntry` to the assistant's linked archive describing a specific location in detail (e.g. "The Neon Bar: red neon signage, rain-slicked street outside, jazz playing").
2. Request that location by name via either trigger surface above.
3. **Expected**: the generated scene visibly reflects details from the archive entry, not just a generic bar — confirmed by human review, per SC-003.

## Scenario 5 — Cache-miss auto-regeneration (FR-012a)

1. Generate a background for a conversation (any scenario above).
2. Manually expire the cache entry (e.g. `Cache::forget("avatar-background:{$conversationId}")` in Tinker, or wait out the TTL).
3. Reopen the conversation.
4. **Expected**: no error, no blank scene — a new background is generated automatically from the conversation's current context, without any manual request.

## Scenario 6 — Non-blocking generation (FR-017)

1. Trigger a background change (Scenario 2 or 3).
2. Immediately send another chat message, without waiting for the background to finish.
3. **Expected**: the message send/reply completes normally and promptly — it is not delayed by the in-progress background generation.

## Scenario 7 — Generation failure fallback

1. Simulate a provider failure (e.g. temporarily misconfigure the image-gen provider, or use `Http::fake()` to return an error in a test).
2. Trigger a background change.
3. **Expected**: the previously displayed background (or default, if none yet) remains visible — no error state, no blank scene.

## Running the automated tests

```bash
php artisan test --compact --filter=AvatarBackground
```

Covers the scenarios above at the feature-test level, following the existing `ImageGenerationTool*Test.php` conventions (`Http::fake()` for LLM/image-gen calls, `Queue::fake()` for dispatch assertions, direct job execution for cache/file side effects).
