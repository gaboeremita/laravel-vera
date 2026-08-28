# Feature Specification: Archive Entry Hybrid Search

**Feature Branch**: `003-archive-search-filter`

**Created**: 2026-08-28

**Status**: Draft

**Input**: User description: "A new implementation, in the Archive page, a comprehensive search and filter inputs, we might filter by tags and keywords, and search might filter by title and content of the entries in realtime"

A follow-up technical brief refined this into a hybrid search approach (instant text matching plus semantic matching, merged) and dropped the standalone tag/keyword filter controls in favor of a single search input. That brief is preserved in full at [technical-brief.md](technical-brief.md) for reference during planning.

## Clarifications

### Session 2026-08-28

- Q: What's the typical or maximum number of entries you'd expect in a single archive that this search/filter feature needs to handle well? → A: Small — typically under 100 entries per archive; client-side filtering stays sufficient. Large, RAG-backed archives (potentially thousands of entries) are a future consideration, out of scope for this feature.
- Q: Should this feature's search match literal text (case-insensitive substring on title/content) or use semantic/embedding-based matching against the archive entry's existing `embedding` column? → A: Both — hybrid search. Instant client-side matching (title, content, tags, keywords) runs on every keystroke; a debounced server-side semantic match, using the existing embedding infrastructure, adds conceptually related entries a short pause after typing stops. Results are merged and deduplicated, with entries found only via semantic match visually indicated.
- Q: Should the dedicated tag/keyword filter controls (select a tag or keyword from a list to narrow results) still ship alongside this hybrid search box, or does the hybrid search box replace them entirely? → A: Replace. Dedicated tag/keyword selection controls are dropped; tags and keywords are searchable text matched by the search box, not a separate browsable filter.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Instant search across entries (Priority: P1)

A user viewing an archive with many entries wants to quickly find a specific entry without scrolling through the whole list. They type into a search field, and matching entries appear immediately as they type — no button to press, no page reload, no waiting.

**Why this priority**: This is the core capability requested and delivers value entirely on its own: locating a known entry by something in its title, content, tags, or keywords, in a long archive, is the primary pain point being solved, and it must feel instantaneous.

**Independent Test**: Open an archive containing several entries with distinct titles, content, tags, and keywords. Type a word that appears in only one entry's title, and confirm only that entry remains visible immediately. Type a word that appears only in another entry's content, tags, or keywords, and confirm that entry appears instead, still immediately.

**Acceptance Scenarios**:

1. **Given** an archive with multiple entries, **When** the user types a word that matches part of one entry's title, **Then** only that entry is shown, with no perceptible delay.
2. **Given** an archive with multiple entries, **When** the user types a word that matches an entry's content, tags, or keywords but not its title, **Then** that entry is still shown immediately.
3. **Given** a search term is entered, **When** the user removes characters or clears the field, **Then** the entry list updates immediately to reflect the shorter or empty query, restoring the full list once cleared.
4. **Given** a search term matches no entry, **When** results are shown, **Then** the user sees a clear "no matching entries" message instead of an empty, unexplained list.
5. **Given** the user is typing, **When** each character is entered, **Then** the visible entry list updates without requiring the user to submit the search or reload the page.

---

### User Story 2 - Conceptually related results via semantic search (Priority: P2)

A user searches using a word or phrase that describes what an entry is *about*, even if that entry's title, content, tags, and keywords don't literally contain those words. After a brief pause in typing, entries that are conceptually related also appear, blended in with the instant text matches.

**Why this priority**: This extends recall beyond exact wording, which matters for a personal archive where the user may not remember the exact phrasing they used when writing an entry. It builds on User Story 1 and is not usable without it, so it is secondary.

**Independent Test**: Open an archive with entries that have had time to generate their semantic representation. Search using a term that is thematically related to one entry but does not appear literally in that entry's title, content, tags, or keywords. Confirm that entry appears in the results a short pause after typing stops, and confirm it's visually distinguishable from entries matched by literal text.

**Acceptance Scenarios**:

1. **Given** an entry whose title, content, tags, and keywords do not contain the search term but are conceptually related to it, **When** the user pauses after typing, **Then** that entry appears in the results, marked as a semantic match.
2. **Given** an entry that matches both by literal text and by semantic relevance, **When** results are shown, **Then** the entry appears once, not duplicated, and ranks at or near the top.
3. **Given** the user is actively typing, **When** each character is entered, **Then** semantic matching does not fire on every keystroke — only after the user pauses.
4. **Given** a semantic match arrives after the instant text matches were already shown, **When** it is added, **Then** the previously visible instant matches remain visible and are not replaced or hidden.

---

### Edge Cases

- What happens when the search term matches no entry, by text or by meaning? The system shows an explicit empty-results message distinct from an archive that has no entries at all.
- What happens when the query is very short (e.g. a single character)? Instant text matching still applies; semantic matching does not fire until the query reaches a minimum meaningful length.
- What happens when an entry was just created or edited and hasn't yet had its semantic representation generated? It remains findable via instant text matching, and simply doesn't yet appear via semantic matching until that representation is ready.
- What happens when the user searches using different letter casing than the stored text? Instant text matching is case-insensitive.
- What happens when the user clears the search box? The full, unfiltered entry list is restored and any in-progress or prior semantic results are discarded.
- What happens if the semantic-matching capability is slow or temporarily unavailable? Instant text-match results still appear without delay; the user is not blocked or shown an error for the instant part of the experience.
- What happens when the same entry is matched by both instant text matching and semantic matching? It is shown once, deduplicated, ranked at or above single-criterion matches.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Archive page MUST provide a single search input that filters the currently displayed archive's entries, with no separate submit action required.
- **FR-002**: As the user types, the system MUST instantly show entries whose title, content, tags, or keywords contain the typed text, case-insensitively, with no network round-trip required for this instant layer.
- **FR-003**: After a brief pause in typing, the system MUST additionally surface entries that are conceptually related to the search text, even when they share no literal matching words.
- **FR-004**: Semantic matching MUST NOT fire on every keystroke; it MUST wait for the query to reach a minimum meaningful length and for typing to pause briefly.
- **FR-005**: Instant text matches and semantic matches MUST be merged into a single result list, with each matching entry shown only once, ranked so entries matching by both criteria surface above entries matching by only one.
- **FR-006**: Entries found only via semantic matching (not via literal text) MUST be visually distinguishable from entries found via literal text, so the user can tell why an entry appeared.
- **FR-007**: The system MUST present a clear indication when no entries match the current search, distinguishable from an archive containing no entries.
- **FR-008**: The system MUST allow the user to clear the search and return to viewing all of the archive's entries, discarding any in-flight or previously merged semantic results.
- **FR-009**: An entry that does not yet have a semantic representation (e.g., newly created or recently edited) MUST remain findable via instant text matching, even though it is not yet eligible to be found via semantic matching.
- **FR-010**: Search MUST operate only on the entries of the archive currently open, not across other archives.
- **FR-011**: If semantic matching is slow or temporarily unavailable, instant text-match results MUST still display without delay or failure.

### Key Entities

- **Archive Entry**: A single record within an archive, identified by a title and free-text content; may carry zero or more tags and zero or more keywords. All of title, content, tags, and keywords are searchable text for both the instant and semantic matching layers. Tags and keywords are no longer exposed as standalone, selectable filter controls in this feature.
- **Tag**: A reusable label a user can assign to one or more entries; contributes to search matching only.
- **Keyword**: A free-form term recorded directly on an entry; contributes to search matching only.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can locate a specific known entry within an archive of 50+ entries in under 10 seconds using search.
- **SC-002**: Instant text-match results appear with no perceptible delay as the user types; semantically related results are added within roughly half a second of the user pausing.
- **SC-003**: A search using words that describe an entry's topic but don't literally appear in it still surfaces that entry via semantic matching, at a success rate comparable to literal-text searches for entries with matching wording.
- **SC-004**: Users see a single, deduplicated, ranked result list regardless of whether an entry matched by text, by meaning, or by both, with zero visible duplicate entries.

## Assumptions

- Archives are assumed to be small-to-medium scale (up to roughly 100 entries) for this iteration; the hybrid approach is designed to perform well at this scale.
- This feature requires a new backend search capability that returns ranked, deduplicated entry matches; semantic matching cannot run purely client-side the way the instant text layer does.
- This feature depends on the archive entry's existing semantic representation (embedding) and a working similarity-search capability at the data layer; that capability is referenced by existing chat-retrieval code but is not currently installed/functional, so enabling it here also unblocks that existing feature.
- Entries without a populated semantic representation remain findable through instant text matching only, until that representation is generated asynchronously in the background.
- Dedicated tag/keyword selection controls are out of scope for this feature; tags and keywords contribute to search matching but are not separately browsable or filterable via their own UI control.
