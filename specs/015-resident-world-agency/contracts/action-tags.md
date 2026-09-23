# Contract: Action Tags

Residents express actions with tags in their replies, alongside the existing `[pose: …]` and `[emotion: …]` tags. Tags are stripped from the visible text by `LlmResponseTagParser` and returned to the world page separately.

## Grammar

| Tag | Meaning |
|-----|---------|
| `[action: go_to <zone-id or object-id>]` | Walk to the zone's entry point, or to the nearest free spot's approach point of the object. |
| `[action: use <spot-id> <activity-id>]` | Walk to the spot and perform the activity there. |
| `[action: zone <activity-id>]` | Perform a zone-level activity of her current zone where she stands. |
| `[action: follow]` | Follow the user until stopped or until she chooses another action. |
| `[action: stop]` | Stop where she is and end any held pose. |
| `[action: stay]` | Idle decisions only: deliberately do nothing this time (FR-025). |
| `[pose: <name>]` | Existing tag. As an idle decision, performs a pose from her library where she stands (FR-025a). |

At most one `[action: …]` tag is honoured per reply. A `[pose: …]` tag in the same reply plays after the action completes.

## Idle decision line

An idle decision reply is exactly one line: a reason in parentheses, an action in asterisks, then one action or pose tag.

```text
(I want to forget about today for a while) *walks to the bar to get a drink* [action: use bar-counter-3 drink]
(I wonder if anyone texted me) *checks her phone* [pose: check-phone]
(I'm comfortable right here) *stays on the lounger, eyes closed* [action: stay]
```

The visible line (tags removed) is added to the conversation and shown in the thought bubble (FR-030, FR-030a).

## Outcomes

After executing an action, the world page reports one of:

| Outcome | When |
|---------|------|
| `completed` | She arrived, or finished performing the activity. |
| `failed` | The target is unknown, unreachable, private without a user request, or the spot is taken; a `reason` is included. |
| `interrupted` | The user opened a conversation, used a direct control, or left the world. |

Unknown ids and malformed tags always produce `failed` with a reason; they are never silently dropped (FR-021, Principle V).
