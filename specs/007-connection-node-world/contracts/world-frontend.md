# Connection Node Frontend Contract

`/worlds/connection-node` transitions to a loader until the environment and residents are ready. The runtime moves through `Loading`, `Exploring`, `Chatting`, and `Error` states.

`ConnectionNodeScene` receives the world and resident collection. `WorldCharacter` owns local visibility-aware animation state and reports proximity changes. `WorldChatPanel` pauses movement while using the selected resident's existing conversation.

`EditWorldPage` uses the current header and accordion styling. Its sections are Residents, NPCs, Environment, and Experience. NPC editing composes existing VRM and pose controls with name, one prompt, archive, and placement fields.

When the route unmounts, it disposes world-only resources. Residents outside visible and interactive space suspend nonessential behavior and restore it before interaction or visibility.
