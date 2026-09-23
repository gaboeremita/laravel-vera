# Contract: World Tools

Residents in a marked world get these tools through the existing agent loop, in every world conversation and idle decision. Tool arguments that name places, things, spots or activities are enums of the world's real ids, taken from its layout.

## Query tools

Answered on the server from the layout, within her turn.

| Tool | Arguments | Returns |
|------|-----------|---------|
| `where_can_i` | `activity`: words describing what she wants to do | Every place offering a matching activity (by activity name or id): the place name and id, the thing and spot where it happens, and the activity id. An empty list when nothing matches. |
| `what_is_in` | `place`: a zone id | The zone's name, description and floor, its own activities, and its things with their spots and activities. |
| `describe` | `id`: a zone or thing id | The zone or thing's name, description and, for a thing, the place it is in. |

## Action tools

Each records her choice for the world page to carry out, and returns `{ "status": "started", "note": "…" }`. Only one action tool per turn; a second one in the same turn is rejected with the action already chosen.

| Tool | Arguments | World page action |
|------|-----------|-------------------|
| `go_to` | `target`: a zone or thing id | `{ verb: "go_to", target }` |
| `follow` | none | `{ verb: "follow" }` |
| `stop` | none | `{ verb: "stop" }` |
| `use` (US5) | `spot`, `activity` ids | `{ verb: "use", target: spot, activity }` |
| `zone` (US5) | `activity` id of her current zone | `{ verb: "zone", activity }` |

The chosen action is returned in the message response as `action`, and the world page reports its outcome as in [world-agency-api.md](world-agency-api.md).

## Errors

A call with an unknown id or a missing argument throws inside the tool, and the agent loop returns the error to her in the same turn, so she can call again with a valid argument. Assistants whose model lacks tool calling get a `422` for world conversations. NPCs use the application's default model and always get the tools.
