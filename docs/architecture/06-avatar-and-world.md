# Avatar and World runtime

VERA has two presentation modes. Image portraits use emotion images/videos and `[emotion: name]` tags. `avatar3d` assistants use a VRM file, poses, blendshapes, and optional VRMA/FBX animation files with `[pose: name]` tags. Worlds place only VRM-backed assistants or NPCs as residents.

## Asset and runtime relationships

```mermaid
flowchart TB
    assistant["Assistant - portrait_type and kind"]
    assistant -->|"image"| emotion["Emotions - regular or restricted"]
    emotion --> emotionImage["Polymorphic image"]
    emotion --> emotionVideo["Polymorphic video"]

    assistant -->|"avatar3d"| vrm["Polymorphic VRM file"]
    assistant --> pose["Poses - normalized blendshapes"]
    pose --> animation["Optional VRMA or FBX animation"]
    vrm --> portraitRuntime["VrmAvatar portrait runtime"]
    pose --> portraitRuntime

    assistant --> resident["WorldResident placement"]
    world["World"] --> resident
    world --> glb["Environment GLB"]
    world --> card["Card and portrait images"]
    world --> track["Optional MP3 or WAV track"]
    glb --> worldRuntime["React Three Fiber WorldScene"]
    resident --> worldRuntime
    vrm --> worldRuntime
    pose --> worldRuntime
    track --> music["WorldTrackPlayer"]

    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    classDef store fill:#dcfce7,stroke:#16a34a,color:#052e16
    class assistant,emotion,pose,resident,world,portraitRuntime,worldRuntime,music core
    class emotionImage,emotionVideo,vrm,animation,glb,card,track store
```

## World entry and session lifecycle

```mermaid
stateDiagram-v2
    [*] --> SessionList
    SessionList --> SessionCreated: Create session
    SessionList --> Loading: Select existing session
    SessionCreated --> Loading: Navigate with session ID
    Loading --> Error: World, environment, or session unavailable
    Loading --> Entering: World JSON and optional session loaded
    Entering --> Error: GLB load or collision build fails
    Entering --> Ready: Walkable spawn resolved
    Ready --> Exploring: Pointer lock and movement enabled
    Exploring --> Chatting: Resident within 2.2 units and interact
    Chatting --> Exploring: Close chat
    Exploring --> Saving: Every 10 seconds or exit
    Chatting --> Saving: Component unmount or exit
    Saving --> Exploring: Position PUT succeeds
    Saving --> SessionList: Exit
    Saving --> SessionList: Session no longer exists
    Error --> SessionList: Return
```

```mermaid
sequenceDiagram
    actor User
    participant Page as WorldPage
    participant API as Laravel world APIs
    participant Scene as WorldScene
    participant Env as WorldEnvironment
    participant Collision as WorldCollision
    participant Resident as ResidentController
    participant Chat as WorldChat

    User->>Page: Open world with session query parameter
    Page->>API: GET world
    Page->>API: GET sessions
    API-->>Page: World assets, residents, saved position
    Page->>Scene: Mount with environment URL and initial position
    Scene->>Env: Load GLB
    Env->>Collision: Build octree, bounds, and candidate spawn
    Collision-->>Scene: Collision world and valid spawn
    Scene->>Collision: Restore saved camera position or use spawn
    loop Residents within 30 units
        Scene->>Resident: Lazy-load VRM and update behavior
    end
    Scene-->>Page: Ready
    User->>Scene: WASD and pointer movement
    Scene->>Collision: Stepped collision-aware move
    Scene-->>Page: Changed camera position
    User->>Chat: Interact with nearby resident
    Chat->>API: Find or create conversation scoped to world session
    Chat->>API: Send message with world ID
    API-->>Chat: Reply with optional pose
    Chat->>Resident: Trigger pose animation and blendshapes
    Page->>API: Persist position every 10 seconds and on unmount
```

## Resident behavior and pose state

```mermaid
stateDiagram-v2
    [*] --> Unloaded
    Unloaded --> Deferred: Farther than 30 units
    Unloaded --> LoadingVRM: Valid spawn or saved state, URL present, within 30 units
    LoadingVRM --> Idle: VRM loaded, posture default pose held
    Idle --> Roaming: Behavior is roam, world unpaused, player near
    Roaming --> Idle: Paused or behavior stationary
    Idle --> Routing: go_to, follow, use, wander, plan step
    Routing --> Idle: Arrived, failed after re-plans, or interrupted
    Routing --> OnSpot: Arrived at a spot with a resting posture
    OnSpot --> Routing: Next move stands her up
    Idle --> Swimming: Water deeper than 1.1 m
    Swimming --> AtEdge: Stopped beside the pool wall
    AtEdge --> Swimming: Moves again
    Swimming --> Idle: Water shallower than 0.9 m
    Idle --> PoseActive: Chat, activity or plan step triggers a pose
    OnSpot --> PoseActive: Pose in her posture
    PoseActive --> Idle: Pose finishes and eases back
    Idle --> Disposed: Component unmount
```

Residents move along navigation-grid routes through the same collision world as the player, keep to the middle of doors, take steps straight on, and recover from getting stuck by stepping back, then routing around the failed spot. Each posture has its own default pose; on a spot her hips are fitted to the marked surface. In deep water she floats and swims, and rests at the pool's edge when she stops beside it. Autonomous residents choose their next step themselves while the user is present and active, with a thought bubble showing the reason and action. Each resident's position, spot and posture are saved per session and restored on return; the player's camera position is saved alongside.

## Player interaction

The player is a first-person view with no body. `FirstPersonController` keeps a player state (foot position, movement mode, posture, held spot and activity) that every other part of the world reads, so swimming and postures never skew positions sent to residents or saved to the session.

```mermaid
stateDiagram-v2
    [*] --> Walking
    Walking --> Running: Shift while moving
    Running --> Walking: Shift released
    Walking --> Crouching: Q
    Crouching --> Walking: Q
    Crouching --> Running: Shift
    Walking --> Swimming: Water deeper than 1.1 m
    Swimming --> Walking: Water shallower than 0.9 m
    Walking --> OnSpot: Card activity with a resting posture
    OnSpot --> Walking: WASD, Space or Shift
    Walking --> InActivity: Card activity without a posture
    InActivity --> Walking: Ring fills, or WASD cancels
```

- **Location**: `LocationTracker` resolves the innermost zone and floor every 150 ms with a client port of `ResolveWorldState`, and the page shows a title card on crossings (not when re-entering a zone left under 2 s earlier) and a readout above the minimap.
- **Swimming**: the body keeps walking the pool floor through the collision world, so walls and steps still collide; only the view floats just above the surface. The depths are the residents' own.
- **Discovery**: object markers are points with no mesh, so `SpotBeacons` draws at them: a breathing dot at objects within 6 m, and for the focused object (within 2.5 m, preferring the one looked at) a shader ring per spot and a light column. E opens the object card, G the zone card; arrows and Enter choose an activity.
- **Activities**: resting activities glide the view onto the spot at that posture's eye height with limited look-around; standing and zone activities fill a 3 s ring. The user holds spots in the same `occupiedSpots` map residents use, so neither can take the other's spot.
- **Residents knowing**: chat messages and idle decisions carry `userState`, resolved against the layout into the "the user is" line. Each activity becomes an action line for residents who can see the user (same floor, within 15 m, line of sight, within 4 m or facing within 110°): the resident in the open conversation gets it as the user's message and replies; every other onlooker records a first-person observation of her own through `POST /api/worlds/{world}/sessions/{session}/residents/{resident}/observations`. Idle decisions include her last 6 conversation messages, so observations reach them too.
- **Facing the user**: during a conversation a resident who is standing still or treading water turns toward the user; one holding a seat, bed or lounger keeps its direction.

## Portrait pose lifecycle

```mermaid
flowchart LR
    reply["LLM reply with qualified pose tag"] --> parse["Client or server validates pose name"] --> trigger["Unique trigger ID"]
    trigger --> blendIn["Capture current bones and blend into clip"]
    blendIn --> play["Play VRMA or retargeted FBX once"]
    play --> hold["Hold static final frame when needed"]
    hold --> blendOut["Blend to rest or resume default loop"]
    blendOut --> idle["Idle blink, expressions, and head sway"]
    trigger --> facial["Apply pose blendshapes"] --> decay["Hold with animation or 3.5 second timer"] --> idle

    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    class reply,parse,trigger,blendIn,play,hold,blendOut,idle,facial,decay core
```

## Music lifecycle

An optional world track starts only after the world reaches `ready`. On track end it fades out over 600 ms, waits 3 seconds, restarts, and fades back in. Mute and volume are client-only; volume persists in `localStorage`, not the database.

---

[Previous](05-provider-resolution.md) · [Index](README.md) · [Next: Deployment and operations](07-deployment-and-operations.md)
