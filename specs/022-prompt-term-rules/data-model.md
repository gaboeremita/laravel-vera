# Data Model: Term Rules Applied From the Assistant's Prompt

No migration. All stored data lives in existing columns.

## Term rule settings (stored)

Location: `assistants.agent_config` (JSON, cast to array), key `termRules`.

| Field | Type | Default | Rules |
|---|---|---|---|
| `section` | string or null | null | Must name a top-level key of `assistants.prompt` whose value is a string, or be null |
| `markTerms` | bool | false | Inline marking on/off |
| `swapInvariant` | bool | false | Exact swap for invariant rules on/off |
| `highlightMissing` | bool | false | Missing-term highlight on/off |

A missing `termRules` key behaves as all defaults (FR-005). Other keys in `agent_config` (such as `step_limit`) are preserved on save.

## Term rule (derived, never stored)

Parsed on each turn from the string value of `prompt[section]`, one per line containing `->`.

| Field | Type | Source in the line |
|---|---|---|
| `source` | string | first term left of `->` |
| `sourceVariants` | string[] | remaining terms left of `->` |
| `target` | string | first term right of `->` |
| `targetVariants` | string[] | remaining terms right of `->` |
| `invariant` | bool | trailing `(invariant)` mark |
| `caseSensitive` | bool | trailing `(case)` mark |
| `line` | int | 1-based line number, used in validation errors |

Validation: a line containing `->` is valid when both sides have at least one non-empty term after trimming. When two rules share a source term (or variant), the earlier line wins.

## Marked message (per turn, never stored)

| Field | Type | Meaning |
|---|---|---|
| `text` | string | model-facing copy of the newest user message |
| `placeholders` | map `⟦n⟧` → target | placeholders inserted for invariant rules |
| `matches` | list of `{ rule, start, length }` | every matched occurrence in the text as typed |

## Missing-term result (per reply, returned, never stored)

`missingTerms`: list of `{ target: string, ranges: [[start, length], …] }`. `ranges` are UTF-16 offsets into the user's message as typed, one per occurrence of the rule's source term or variants. The list is empty when every matched rule's target (or a target variant) appears in the reply.
