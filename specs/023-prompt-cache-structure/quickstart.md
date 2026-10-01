# Quickstart: Cache-Friendly Prompt Structure

## Automated checks

```bash
php artisan test --compact --filter='PromptLayout|PromptDirector|ComposeChatRequest|ConversationHistory|ProviderCacheMarks|SummarizeConversation|AiModelCachingSettings|PromptCachingTurns'
```

Expected:
- Two consecutive turns produce identical text up to the end of the earlier turn's history (SC-002).
- The two shared rules each appear once (SC-003).
- Turn sections render under the approved headings, in order, and only when they have content.
- The history start follows the 100/50 and 60/30 limits (SC-006).
- New summaries are appended at the end.
- Provider requests carry `cache_control` only when `cache_marks` is on, and the identifier field only when `conversation_key_field` is set.

## Manual check against a real provider

Run this once per provider kind you use.

1. In the AI model settings, set **conversation key field** and **cache marks** for the model under test:

   | Model | conversation key field | cache marks |
   |---|---|---|
   | Anthropic model through OpenRouter | `session_id` | on |
   | OpenAI model through OpenRouter | `session_id` | off |
   | Native Anthropic API | empty | on |
   | Other OpenAI-compatible endpoints | the field that endpoint documents, or empty | on only if the endpoint documents `cache_control` |

2. Open a conversation with the assistant and send three messages in a row without changing region, voice mode, or creator mode.
3. Expand the usage details under each reply:
   - Generic endpoints report `prompt_tokens_details.cached_tokens`. Anthropic reports `cache_read_input_tokens`.
   - From the second reply on, the cached count covers about the unchanging part, roughly 14k tokens or more for the current assistant (SC-001).
   - From the third reply on, it also covers the earlier messages.
4. Expand **Prompt sent** on two consecutive replies. Every top-level section has a `# ` heading, and the per-turn sections appear last, under `# CURRENT STATE`, `# RECENT ACTIVITY`, `# RETRIEVED KNOWLEDGE`, and `# RELATIONSHIP STATE`.
5. Trigger a summary (the memory panel's summarize action), then send one more message. The cached count still includes the memory as it was before the summary (FR-018).

If step 3 shows `0` on a provider that should cache, first check that its minimum cacheable size is below the unchanging part, and that the field name and cache-marks setting match what that provider documents.
