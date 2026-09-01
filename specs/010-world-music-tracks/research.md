# Research: World Music Track

## Storage shape: columns on `worlds` vs. polymorphic `Image`-style table

**Decision**: Store the track as a set of nullable columns directly on the `worlds` table (`track_disk`, `track_path`, `track_original_name`, `track_mime_type`, `track_size`), not as a row in the polymorphic `images` table.

**Rationale**: The spec scopes this to exactly one track per world, replaced wholesale on upload — the same cardinality as the existing `.glb` environment file, which already lives as `environment_disk`/`environment_path`/`environment_original_name` columns on `worlds`. The polymorphic `Image` model exists specifically to support multiple *roles* of image per world (`card`, `portrait`); a single always-one-or-none track doesn't need that generality.

**Alternatives considered**: A polymorphic `WorldTrack` row (or reusing `images` with a `track` role) — rejected because it introduces a relation and join for something that is always 0-or-1 and never queried by role alongside images; a direct column set matches the environment-file precedent already in this codebase and needs no new model.

## File validation and storage mechanics

**Decision**: Validate uploads inline in the controller with `['track' => ['required', 'file', 'mimes:mp3,wav', 'max:20480']]` (20 MB), store via `$file->store("worlds/{$world->id}/track", 'public')`, delete the previous file only after the new one is successfully persisted — mirroring `WorldImageController::store()`.

**Rationale**: Directly reuses a pattern already proven in this codebase (`WorldImageController`), including its failure handling (delete newly-stored file and rethrow if the DB write fails) — satisfies Constitution Principle V (Errors Fail Loudly) without inventing new mechanics.

**Alternatives considered**: A `StoreWorldTrackRequest` FormRequest (as used for the `.glb` environment upload) — either is acceptable; inline validation was chosen for consistency with the other single-file-per-world upload (`WorldImageController`), which is the closer precedent (small owner-only auxiliary asset, not part of core world creation).

## Playback: paused restart, per-user volume, and mute

**Decision**: Render a single `<audio>` element (or Web Audio node) scoped to the active world session, sourced from the world's track URL when present, with native looping disabled. On the element's `ended` event, wait a short delay (a few seconds) via `setTimeout`, then restart playback from the beginning — rather than relying on `loop`, which restarts with no gap. Volume/mute state lives in component/local state (or `localStorage` for persistence across sessions), never synced from a prop via an effect — toggling mute in a keydown handler and volume via a slider's `onChange`, both applied directly to the audio element.

**Rationale**: Matches Constitution Principle VIII: state derivation happens in the event handler that causes it (keypress, slider input), not in a `useEffect` reacting to a change. This also naturally keeps the setting local per browser tab/user, since nothing is written to shared/session state.

**Alternatives considered**: Storing volume/mute in the per-user `WorldUser` or `WorldSession` record — rejected; the spec explicitly requires this to be local-only and not affect other users, and persisting it server-side would add unnecessary write traffic for a UI preference.

## Format validation

**Decision**: Accept only `mp3` and `wav` extensions server-side (Laravel's `mimes:mp3,wav` rule), matching the spec's explicit format decision.

**Rationale**: Directly specified by the user during specification; no further research needed.
