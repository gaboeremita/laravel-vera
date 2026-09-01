# Research: World Music Track

## Storage shape: polymorphic `Track` model vs. columns on `worlds`

**Decision**: Store the track as a row in a new polymorphic `tracks` table (`Track` model, `trackable` morph), structured identically to the existing `images` table/`Image` model — not as columns on `worlds`.

**Rationale**: `worlds` stays a stable, unmodified table. The polymorphic shape already proven for `Image` (`imageable`) is the established pattern in this codebase for "a world owns a media asset" — reusing it for audio via `trackable` keeps the two media concepts consistent and leaves room for other models to attach a track later without touching `worlds` again.

**Alternatives considered**: Columns directly on `worlds` (`track_disk`/`track_path`/...), matching the `.glb` environment file's storage — rejected per explicit product direction: tracks should follow the `Image` polymorphic pattern, not extend the `worlds` table.

## File validation and storage mechanics

**Decision**: Validate uploads inline in the controller with `['track' => ['required', 'file', 'mimes:mp3,wav', 'max:20480']]` (20 MB), store via `$file->store("worlds/{$world->id}/track", 'public')`, then `$world->track()->updateOrCreate([], [...])` to create-or-replace the `Track` row, deleting the previous file only after the new one is successfully persisted — mirroring `WorldImageController::store()` exactly (same `updateOrCreate`-on-a-morph-relation shape).

**Rationale**: Directly reuses a pattern already proven in this codebase (`WorldImageController`), including its failure handling (delete newly-stored file and rethrow if the DB write fails) — satisfies Constitution Principle V (Errors Fail Loudly) without inventing new mechanics.

**Alternatives considered**: A `StoreWorldTrackRequest` FormRequest (as used for the `.glb` environment upload) — either is acceptable; inline validation was chosen for consistency with the other single-file-per-world upload (`WorldImageController`), which is the closer precedent (small owner-only auxiliary asset, not part of core world creation).

## Playback: fade-out, paused restart, fade-in, per-user volume, and mute

**Decision**: Render a single `<audio>` element (or Web Audio node) scoped to the active world session, sourced from the world's track URL when present, with native looping disabled. Shortly before the track ends (or on the element's `timeupdate` nearing its end / `ended` event), ramp its volume down to 0 over a short interval, then wait a short delay (a few seconds) via `setTimeout`, then restart playback from the beginning and ramp volume back up to the user's chosen level over a short interval — rather than relying on `loop`, which both restarts with no gap and cuts/resumes at full volume abruptly. The fade targets the user's current volume setting, so a muted or lowered track fades within that ceiling rather than always ramping to 100%. Volume/mute state itself lives in component/local state (or `localStorage` for persistence across sessions), never synced from a prop via an effect — toggling mute in a keydown handler and volume via a slider's `onChange`, both applied directly to the audio element (subject to the in-progress fade's ramp, not overriding it instantaneously).

**Rationale**: Matches Constitution Principle VIII: state derivation happens in the event handler that causes it (keypress, slider input), not in a `useEffect` reacting to a change. This also naturally keeps the setting local per browser tab/user, since nothing is written to shared/session state.

**Alternatives considered**: Storing volume/mute in the per-user `WorldUser` or `WorldSession` record — rejected; the spec explicitly requires this to be local-only and not affect other users, and persisting it server-side would add unnecessary write traffic for a UI preference.

## Format validation

**Decision**: Accept only `mp3` and `wav` extensions server-side (Laravel's `mimes:mp3,wav` rule), matching the spec's explicit format decision.

**Rationale**: Directly specified by the user during specification; no further research needed.
