# Quickstart: World Music Track

Validates the feature end-to-end once implemented.

## Prerequisites

- App running under Herd (`https://laravel-vera.test`), authenticated session.
- At least one `World` you own, created via the existing world creation flow.

## Backend validation

1. Upload a track:
   ```bash
   curl -X POST https://laravel-vera.test/api/worlds/{world_id}/track \
     -H "Accept: application/json" \
     -F "track=@/path/to/song.mp3" \
     --cookie "laravel_session=..."
   ```
   Expect `201` with a `track_url`.

2. Fetch the world and confirm `track_url` / `track_original_name` are present:
   ```bash
   curl https://laravel-vera.test/api/worlds/{world_id} --cookie "..."
   ```

3. Replace the track with a `.wav` file — repeat step 1 with a different file — and confirm the returned `track_url` changed and the old file is gone from storage (`storage/app/public/worlds/{world_id}/track/`).

4. Attempt an unsupported format (e.g. `.flac`) — expect `422`.

5. Attempt a file over 20 MB — expect `422`.

6. As a different user (not the owner), attempt to upload or delete the track — expect `403`.

7. Delete the track:
   ```bash
   curl -X DELETE https://laravel-vera.test/api/worlds/{world_id}/track --cookie "..."
   ```
   Expect `200`, and `track_url` is `null` on subsequent fetch.

## Frontend validation

1. Open a world's edit screen as its owner; upload an audio file; confirm the UI shows the track is set (name/replace/remove controls appear).
2. Enter a session in that world; confirm background music starts playing within ~2 seconds and loops when it ends.
3. Adjust the volume slider; confirm the change applies immediately and persists only for the current user.
4. Press `M`; confirm the music mutes. Press `M` again; confirm it resumes at the prior volume.
5. Enter a session in a world with no track; confirm no background audio plays.

## Automated tests

Run the new feature test:

```bash
php artisan test --compact --filter=WorldTrackControllerTest
```

See `contracts/world-track-api.md` for the exact request/response shapes exercised by these tests, and `data-model.md` for the underlying `World` column changes.
