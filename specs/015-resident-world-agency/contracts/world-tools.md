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
| `go_to` | `target`: a zone or thing id, or `user` | `{ verb: "go_to", target }`; for `user` she walks to within about a metre of the user, or, while swimming, swims to the side of the pool nearest them and rests at the edge |
| `follow` | none | `{ verb: "follow" }` |
| `stop` | none | `{ verb: "stop" }` |
| `wander` | optional `place`: a zone id | `{ verb: "wander", target: place }`; for 30–60 s she moves between random reachable spots inside the place, or within about 7 m of where she is, pausing 2–5 s at each; in the water, without a place, she swims between spots in deep water |
| `swim_to_edge` | none | `{ verb: "swim_to_edge" }`; while in the water she swims to the nearest side of the pool and rests there with her Swim To Edge pose; out of the water it fails with "not in the water" |
| `use` (US5) | `spot`, `activity` ids, optional `pose` (one of hers) | `{ verb: "use", target: spot, activity }` |
| `zone` (US5) | `activity` id of her current zone, optional `pose` (one of hers) | `{ verb: "zone", activity }` |
| `plan` | `goal`, and `steps`: 1–5 steps in order, each `go_to` (`target`), `use` (`target` spot, `activity`), `zone` (`activity`), `pose` (`pose`), `do` (`description`, optional `pose`) or `stay` | `{ verb: "plan", target: goal, steps: [{ verb, target, activity, pose, description }] }` |

A plan is checked step by step before it starts: ids must exist, a `use` spot must offer the activity and be free, a `zone` activity must belong to the place she will be in by that step, and a `pose` must be one of hers. A failing step is returned to her as `Step N: …`. The world page runs the steps in order and records each as its own activity; the first step that does not complete stops the plan, and she decides again right away. A `do` step is anything she describes with no marked spot or pose behind it; she holds where she is for a few seconds, with the step's pose if one is given, while her narration carries it.

The chosen action is returned in the message response as `action`, and the world page reports its outcome as in [world-agency-api.md](world-agency-api.md).

## Errors

A call with an unknown id or a missing argument throws inside the tool, and the agent loop returns the error to her in the same turn, so she can call again with a valid argument. Assistants whose model lacks tool calling get a `422` for world conversations. NPCs use the application's default model and always get the tools.

## Poses for activities

An activity may name the pose it plays. `use`, `zone` and plan steps take an optional `pose` from her own library, so she can match an activity to her pose of another name ("prepare_drink" for "Mix a drink"). The pose must have a version for the posture the activity puts her in; one without is returned to her as an error listing her poses in that posture, since an activity's pose never stands her up. Without one, the activity's own pose plays when she has a pose of that name, compared case-insensitively with `_`, `-` and spaces alike; otherwise she holds her default stance. The chosen pose is returned in the action as `pose`.
