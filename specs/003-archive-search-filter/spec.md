# Feature Specification: Archive Entry Search & Filter

**Feature Branch**: `003-archive-search-filter`

**Created**: 2026-08-28

**Status**: Draft

**Input**: User description: "A new implementation, in the Archive page, a comprehensive search and filter inputs, we might filter by tags and keywords, and search might filter by title and content of the entries in realtime"

## Clarifications

### Session 2026-08-28

- Q: What's the typical or maximum number of entries you'd expect in a single archive that this search/filter feature needs to handle well? → A: Small — typically under 100 entries per archive; client-side filtering stays sufficient. Large, RAG-backed archives (potentially thousands of entries) are a future consideration, out of scope for this feature.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Real-time text search across entries (Priority: P1)

A user viewing an archive with many entries wants to quickly find a specific entry without scrolling through the whole list. They type into a search field, and the visible entries narrow down to matches as they type — no button to press, no page reload.

**Why this priority**: This is the core capability requested and delivers value on its own: locating a known entry by name or by something it mentions, in a long archive, is the primary pain point being solved.

**Independent Test**: Open an archive containing several entries with distinct titles and content. Type a word that appears in only one entry's title, and confirm only that entry remains visible. Type a word that appears only in another entry's content, and confirm that entry appears instead.

**Acceptance Scenarios**:

1. **Given** an archive with multiple entries, **When** the user types a word that matches part of one entry's title, **Then** only that entry is shown.
2. **Given** an archive with multiple entries, **When** the user types a word that matches part of one entry's content but not its title, **Then** that entry is still shown.
3. **Given** a search term is entered, **When** the user removes characters or clears the field, **Then** the entry list updates immediately to reflect the shorter or empty query, eventually restoring the full list when cleared.
4. **Given** a search term matches no entry's title or content, **When** the results update, **Then** the user sees a clear "no matching entries" message instead of an empty, unexplained list.
5. **Given** the user is typing, **When** each character is entered, **Then** the visible entry list updates without requiring the user to submit the search or reload the page.

---

### User Story 2 - Filter entries by tag (Priority: P2)

A user wants to see only the entries that belong to one or more tags they care about, without needing to remember or type exact wording.

**Why this priority**: Tags are an existing, structured way entries are already organized; filtering by them is high value and builds directly on data that already exists, but is secondary to free-text search for day-to-day lookup.

**Independent Test**: Open an archive whose entries have a mix of tags. Select one tag from the filter control and confirm only entries carrying that tag remain visible. Select a second tag and confirm entries carrying either selected tag are shown.

**Acceptance Scenarios**:

1. **Given** an archive with tagged entries, **When** the user selects a single tag to filter by, **Then** only entries carrying that tag are shown.
2. **Given** a tag filter is active, **When** the user selects an additional tag, **Then** entries carrying either selected tag are shown.
3. **Given** a tag filter is active, **When** the user deselects all tags, **Then** the tag filter no longer restricts the list.
4. **Given** an entry has no tags, **When** any tag filter is active, **Then** that entry is excluded from the filtered results.

---

### User Story 3 - Filter entries by keyword (Priority: P3)

A user wants to narrow the entry list using the free-form keywords already recorded on each entry, independently of tags.

**Why this priority**: Keywords are a secondary, less structured classification than tags and are lower priority, but completing this facet delivers the "comprehensive" filtering the request calls for.

**Independent Test**: Open an archive whose entries have keywords recorded. Select one keyword from the filter control and confirm only entries carrying that keyword remain visible.

**Acceptance Scenarios**:

1. **Given** an archive with entries that have keywords recorded, **When** the user selects a keyword to filter by, **Then** only entries carrying that keyword are shown.
2. **Given** both a tag filter and a keyword filter are active, **When** results are shown, **Then** only entries satisfying both the tag filter and the keyword filter are visible.
3. **Given** a text search, a tag filter, and a keyword filter are all active at once, **When** results are shown, **Then** only entries matching the search text and satisfying both filters are visible.
4. **Given** a keyword filter is active, **When** the user deselects all keywords, **Then** the keyword filter no longer restricts the list.

---

### Edge Cases

- What happens when the search term or selected tag/keyword matches zero entries? The system shows an explicit empty-results message distinct from an archive that has no entries at all.
- What happens when an entry has an empty or very short title but long content that matches the search term? The entry must still surface via content matching.
- What happens when the user searches using a partial word or with different letter casing than the stored text? Matches are found regardless of case, and on partial-word substrings.
- What happens when no tags or keywords exist yet on any entry in the archive? The tag/keyword filter controls show no selectable options, or are hidden, rather than erroring.
- What happens when the user clears the search box and all filters simultaneously? The full, unfiltered entry list is restored.
- What happens when the same word is entered in the search box and also happens to be a tag or keyword? Text search and facet filters operate independently and both apply their own logic to the combined result.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Archive page MUST provide a text search input that filters the currently displayed archive's entries as the user types, with no separate submit action required.
- **FR-002**: The text search MUST match against both an entry's title and its content, case-insensitively, on partial (substring) matches.
- **FR-003**: The Archive page MUST provide a way to filter entries by one or more tags already assigned to entries in that archive.
- **FR-004**: The Archive page MUST provide a way to filter entries by one or more keywords already recorded on entries in that archive.
- **FR-005**: When multiple tags are selected, an entry MUST be shown if it carries at least one of the selected tags (results expand within the tag facet).
- **FR-006**: When multiple keywords are selected, an entry MUST be shown if it carries at least one of the selected keywords (results expand within the keyword facet).
- **FR-007**: When text search, tag filter, and keyword filter are combined, an entry MUST be shown only if it satisfies all three that are currently active (results narrow across facets).
- **FR-008**: The system MUST present a clear indication when no entries match the current search and/or filter combination, distinguishable from an archive containing no entries.
- **FR-009**: The system MUST allow the user to clear the search text and/or any selected tag/keyword filters and return to viewing all of the archive's entries.
- **FR-010**: The list of selectable tags and keywords offered in the filter controls MUST reflect only tags and keywords actually present on entries within the currently viewed archive.
- **FR-011**: Search and filtering MUST operate only on the entries of the archive currently open, not across other archives.

### Key Entities

- **Archive Entry**: A single record within an archive, identified by a title and free-text content; may carry zero or more tags and zero or more keywords. Search and filtering act on these entries.
- **Tag**: A reusable label a user can assign to one or more entries, used as a filter facet.
- **Keyword**: A free-form term recorded directly on an entry, used as a separate filter facet from tags.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can locate a specific known entry within an archive of 50+ entries in under 10 seconds using search and/or filters.
- **SC-002**: The visible entry list reflects the latest search text or filter selection with no perceptible delay, so the interaction feels instantaneous while typing or selecting.
- **SC-003**: Filtering by a given tag or keyword shows every entry carrying it and no entry that doesn't, with zero missed or incorrect matches.
- **SC-004**: Users can combine text search with tag and keyword filters in a single, uninterrupted interaction, without needing to apply or confirm each filter separately.

## Assumptions

- Search and filtering apply within the currently open archive's entries only, matching the existing page structure where one archive's entries are viewed at a time.
- Filtering happens against the entries already loaded for the open archive, consistent with the current architecture where an archive's entries load together rather than in pages.
- Multiple selections within the same filter facet (e.g., two tags) combine as "matches any"; the search text and the two filter facets combine with each other as "matches all" (an entry must satisfy every active criterion).
- This feature targets archives of up to roughly 100 entries, consistent with the current single-request, unpaginated architecture; client-side filtering is sufficient and no new backend search endpoints are required at this scale.
- Large, RAG-backed archives (potentially thousands of entries) are anticipated for a future iteration but are explicitly out of scope here; this feature's client-side approach is not required to scale to that case.
