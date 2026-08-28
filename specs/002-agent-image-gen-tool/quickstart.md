# Quickstart: Validating the Agent Image Generation Tool

Manual end-to-end validation for the three user stories in [spec.md](spec.md), once implemented.

## Prerequisites

- No new migrations — this feature adds no schema (data-model.md).
- An assistant with `mode = agent`, whose configured `AiModel` has `supports_tools = true` (same prerequisite `001-agentic-task-loop` established).
- Image generation configured for that assistant, or a global default present (`IMAGE_GEN_URL`/`IMAGE_GEN_API_KEY` in `.env`) — same configuration the existing `/create-image` command already relies on. No new env vars for this feature.

## Scenario 1 — Assistant generates an image mid-task (User Story 1, P1)

1. Start a conversation with the agent-mode assistant (do **not** type `/create-image`).
2. Ask something that implies wanting an image, e.g. "can you show me what a cyberpunk cat cafe would look like?"
3. **Expected**: the assistant calls `generate_image` (contracts/generate-image-tool.md), an image-only assistant message appears in the conversation, followed by the assistant's own in-character text response referencing it — all within the same request, no second user message needed.
4. Confirm an assistant *not* in agent mode, or without image generation configured, never offers this capability — no `generate_image` call is attempted, no error surfaced about a missing tool (FR-005, Edge Cases).

## Scenario 2 — Consistency with the manual command (User Story 2, P2)

1. On the same assistant, manually send `/create-image a cyberpunk cat cafe`.
2. Compare against Scenario 1's result.
3. **Expected**: both images were generated using the same provider/model configuration for this assistant (`ImageGenerationService::generate()`, contracts/image-generation-service.md — the same underlying call in both paths), both are stored and rendered via the same `Image`/`msg.image_url` mechanism, and both look consistent with the assistant's configured style. The only visible difference is that the manual command's reaction text is combined with the image in one message, while the tool's image and the assistant's reaction land in two consecutive messages (research.md #3) — not a functional regression, an accepted consequence of running inside the loop instead of a dedicated single-shot command.

## Scenario 3 — Slow or failing generation doesn't break the task (User Story 3, P3)

1. Temporarily misconfigure the assistant's image-gen provider (e.g. an invalid API key) and repeat Scenario 1's prompt.
2. **Expected**: the task does not hang or end with no response — the assistant tells the user something went wrong, consistent with how a failed `basic_calculator`/`get_current_datetime` call already surfaces today.
3. Restore the configuration and confirm a normal successful generation (which takes noticeably longer than the instant built-in tools) completes without the task reporting a timeout failure — this is what `ImageGenerationTool::timeoutSeconds()` (research.md #4) exists to guarantee, since the loop's default 60-second tool timeout would otherwise kill a normal ~2-minute generation.

This is also covered by the automated Pest suite, faking both the LLM tool-call response and the image-gen provider's HTTP call (research.md #8):

```bash
php artisan test --compact --filter=ImageGenerationTool
```

## Multi-image tasks (FR-009)

Ask for two distinct images in one message (e.g. "show me a cat, then a dog"). **Expected**: two separate image-only assistant messages appear, each with its own generated image, followed by the assistant's final text response — not one message overwritten by the other (data-model.md's per-call carrier message design).

## Out of scope for this quickstart

Verifying the manual `/create-image` command's own behavior beyond the consistency comparison in Scenario 2 — it is unchanged by this feature (spec.md Assumptions) and already covered by its own existing tests.
