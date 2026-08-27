# Contract: `get_current_datetime` Tool

The single built-in tool for this feature (spec.md Assumptions, User Story 1 Acceptance Scenario 4). Registered directly in code for agent-mode assistants — not MCP-sourced (MCP itself is out of scope for this feature).

## Definition

- **Name**: `get_current_datetime`
- **Description**: "Returns the current date and time."
- **Parameters**: none — deliberately minimal per Constitution Principle VII. No timezone or format parameter; the server's configured timezone (`config('app.timezone')`) is used.

## Output

```json
{
  "datetime": "2026-08-26T14:32:00-05:00",
  "timezone": "America/Chicago"
}
```

ISO 8601 timestamp plus the timezone name it was computed in, so the model can phrase a natural-language answer without needing to parse an offset itself.

## Behavior notes

- Deterministic given a fixed clock — Pest tests freeze time (e.g. `Carbon::setTestNow()`) rather than asserting on a live clock value.
- Cannot fail in any user-observable way (no external call, no input to validate) — the tool-failure edge case (spec.md Edge Cases) is exercised in tests via a forced/simulated failure, not a real failure mode of this specific tool.

## Known limitation, stated plainly

This tool has no parameters, so it cannot be called twice with different inputs to naturally produce a dependent second call. User Story 2 (chained, dependent tool calls) and User Story 3 (step limit reached) are exercised through Pest `Http::fake()`-sequenced responses at the test level (research.md #7), not through a real multi-step interaction with this tool in a live chat session. This was an accepted tradeoff of picking a genuinely useful, parameterless tool over a manufactured chainable fixture.
