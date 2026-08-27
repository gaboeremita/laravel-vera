# Contract: `LlmProvider` (extended)

Internal PHP interface (`App\Contracts\LlmProvider`), implemented by `AnthropicProvider` and `GenericProvider`. This is the seam the agent loop is built on top of.

## `chat()` — extended signature

```
chat(array $messages, array $options = [], array $tools = []): LlmResponse
```

- `$tools`: provider-agnostic tool definitions — `array{name: string, description: string, parameters: array<string, mixed>}[]`. Empty array (default) preserves today's exact behavior for every non-agent-mode call — no wire-format change when no tools are passed.
- Each provider translates `$tools` into its own wire shape internally (research.md #1, #2) — no shared translator.

## `LlmResponse` — extended shape

```
final class LlmResponse
{
    public readonly string $content;
    public readonly ?string $thinking;
    /** @var ToolCallRequest[] */
    public readonly array $toolCalls;   // empty when the model returned a final answer
    public readonly bool $isFinal;      // true when there are no further tool calls to make
}
```

- `ToolCallRequest`: `{id: string, name: string, arguments: array<string, mixed>}` — normalized regardless of provider (research.md #3).
- `isFinal` replaces relying on provider-specific `stop_reason`/`finish_reason` strings outside the provider classes — the loop only ever checks this one boolean.

## Loop contract (consumer side)

The orchestrating loop (new `App\Services\AgentLoop\AgentLoopRunner`) is the only caller that ever passes a non-empty `$tools` array or reads `$response->toolCalls`. `ConversationController` for non-agent-mode assistants continues calling `chat()` exactly as it does today, with no `$tools` argument.

## Backward compatibility

Existing call sites in `ConversationController` (voice-mode calls, title generation, etc.) are unaffected — the new parameter is optional and defaults to no tools, and `LlmResponse`'s existing `content`/`thinking` fields are unchanged.
