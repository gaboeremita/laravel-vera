# Contract: Archive Entry Search Endpoint

## `GET /api/archives/{id}/search`

Route name: `archives.search`. Added alongside the existing archive routes in `routes/api.php` (`ArchiveController::search`).

### Purpose

Runs the debounced, server-side half of hybrid search (FR-003–FR-006): a Postgres full-text keyword query and a native pgvector similarity query against the given archive's entries, merged via Reciprocal Rank Fusion, returning ranked entry IDs only. The instant client-side text-match layer (FR-002) never calls this endpoint — it filters already-loaded entry data locally.

### Auth & Scope

Requires an authenticated user (same middleware as other `/api/archives/*` routes). Must resolve the archive through `$request->user()->archives()->findOrFail($id)`, exactly like `show()`/`save()` — per Constitution Principle IV (Data Isolation by Ownership), never a bare `Archive::find($id)`. A request for an archive the authenticated user doesn't own returns `404`, indistinguishable from a non-existent archive.

### Request

| Param | In | Type | Rules |
|---|---|---|---|
| `id` | path | integer | Must resolve to an archive owned by the authenticated user |
| `q` | query | string | Required, minimum 2 characters (matches the semantic-layer minimum in FR-004 / research.md §6) |

A `q` shorter than 2 characters returns a `422` validation error — the frontend is expected not to call this endpoint below that length in the first place (client-side instant matching still applies).

### Response — 200 OK

```json
{
  "results": [
    { "id": 12, "score": 0.032 },
    { "id": 5, "score": 0.028 },
    { "id": 19, "score": 0.016 }
  ]
}
```

- `results` is already sorted descending by `score` (post-RRF-merge).
- Only entries belonging to the requested archive appear.
- An entry with no `embedding` yet (FR-009) can still appear here via the full-text keyword leg alone — absence from the vector leg does not exclude it from this endpoint's results, only from semantic-only matches.
- Empty `q` matches → `{"results": []}`, not an error. The frontend renders its own "no matching entries" state (FR-007) by combining this with the client-side instant layer's own empty result, not by inspecting this response's emptiness in isolation.
- The response intentionally excludes entry content — the frontend already holds full entry data in state (spec Assumptions), so this keeps the payload minimal.

### Response — 404 Not Found

Archive doesn't exist, or exists but isn't owned by the authenticated user. Same shape as the existing `show()`/`export()` 404 behavior.

### Response — 422 Unprocessable Entity

`q` missing or under 2 characters. Standard Laravel validation error shape.

### Non-goals

This endpoint does not create, update, or delete anything (spec: "Search is read-only"). It does not paginate — full result set (each leg capped at a reasonable internal limit, e.g. 20, before merge) is returned in one response, consistent with the small-archive-scale assumption.
