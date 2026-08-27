# Contract: `basic_calculator` Tool

The second built-in tool for this feature (spec.md Assumptions, User Story 1 Acceptance Scenario 5, User Story 2 Acceptance Scenario 1). Registered directly in code for agent-mode assistants — not MCP-sourced.

## Definition

- **Name**: `basic_calculator`
- **Description**: "Evaluates a basic arithmetic expression (addition, subtraction, multiplication, division, parentheses) and returns the result."
- **Parameters**: `{expression: string}` — e.g. `"78 * 3"`, `"(4 + 2) / 3"`.

## Supported grammar

Numbers (integers and decimals), `+`, `-`, `*`, `/`, and parentheses only, with standard operator precedence (`* /` before `+ -`) and left-to-right associativity within the same precedence level. No functions, no variables, no percentages, no exponents/roots — those belong to a separate, later "scientific calculator" (spec.md Assumptions, research.md #9).

## Output

```json
{ "result": 234 }
```

## Implementation constraint

MUST NOT use `eval()` or any other dynamic code execution to compute the result (research.md #9) — a hand-rolled tokenizer and recursive-descent evaluator that only recognizes the grammar above, with no fallback path to executing arbitrary code.

## Error handling

- Division by zero and malformed expressions (unbalanced parentheses, invalid tokens) MUST return a tool-result error the model can react to (Constitution Principle V — errors fail loudly), not a crash and not a silently wrong number.

## Behavior notes

- Fully deterministic — no external call, no clock dependency. Pest tests can assert exact expected results directly.
