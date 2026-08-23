<!--
Sync Impact Report
Version change: 1.0.0 → 1.1.0 (new principle added)
Modified principles: n/a
Added sections:
  - Core Principle VIII (State Derivation Happens During Render, Not in
    Effects)
Removed sections: n/a
Deferred TODOs: none

Prior report (1.0.0, initial ratification):
Added sections:
  - Core Principles I–VII (Lint-Enforced Code Style; Append-Only Migrations;
    Comments Justify Only Non-Obvious Decisions; Data Isolation by Ownership;
    Errors Fail Loudly; Feature-Test-First, Factory-Backed; No Speculative
    Abstraction)
  - Quality Gates
  - Relationship to CLAUDE.md
  - Governance
-->

# VERA Constitution

## Core Principles

### I. Lint-Enforced Code Style
PHP code MUST pass `vendor/bin/pint --test` with zero errors. Frontend
JavaScript/JSX code MUST pass `npm run lint` (ESLint) with zero errors. A
change is not complete until both hold, regardless of whether the change
itself touched styling.

**Rationale**: consistent, mechanically-enforced style removes a class of
review friction and drift entirely; leaving it to convention rather than a
gate lets it decay silently.

### II. Append-Only Migrations
Existing migration files MUST NOT be edited once they exist. Every schema
change is a new migration file.

**Rationale**: a migration is a historical record of what actually ran
against real databases. Editing one after the fact makes that record false
for anyone who already ran it, silently diverging their schema from the
codebase's.

### III. Comments Justify Only Non-Obvious Decisions
Code MUST default to zero comments. A comment MAY be added only to explain a
hidden constraint, a workaround, or a gotcha — never to restate what the
code does. If removing a comment would not confuse a future reader, it MUST
NOT be written.

**Rationale**: well-named identifiers already say what code does; comments
that repeat that add noise without adding information, and rot as the code
changes around them. The only comments worth the maintenance cost are the
ones carrying information the code itself cannot express — the why.

### IV. Data Isolation by Ownership
Every query that touches user- or assistant-owned data MUST be scoped to
the actual owning record, not inferred from account-level defaults (e.g.
"the account's first archive"). Data belonging to one user or one assistant
MUST NOT be reachable by another without an explicit, deliberate scoping
decision.

**Rationale**: this codifies a real production bug (issue #44): retrieval
context was pulled from `$user->archives()->first()` instead of the
requesting assistant's own linked archive, so every assistant on an account
silently shared the same archive's content regardless of what was actually
assigned to it. Convenience shortcuts on ownership-scoped queries are how
this class of bug happens.

### V. Errors Fail Loudly
Exceptions MUST NOT be silently swallowed. Empty catch blocks are
prohibited. A failure MUST surface — via a log entry, a thrown exception, or
an error response — never disappear silently.

**Rationale**: a silently swallowed error trades a loud, findable bug for a
quiet, undiagnosable one. Failing loudly costs nothing when things work and
saves hours when they don't.

### VI. Feature-Test-First, Factory-Backed
Pest feature tests are the default test type; unit tests are the exception,
used only when a feature test cannot exercise the behavior in question.
Test data MUST come from model factories, not manually constructed models,
unless a factory genuinely cannot express the required state.

**Rationale**: feature tests verify behavior the way the application is
actually used, which catches integration mistakes unit tests structurally
cannot see. Factories keep test setup consistent and cheap to maintain as
models evolve.

### VII. No Speculative Abstraction
Code MUST solve the problem actually stated, not a generalized version of
it. Shared behavior MAY be extracted into an abstraction once a second real
caller needs it — not in anticipation of one that does not yet exist.

**Rationale**: an abstraction built for a hypothetical future caller is a
guess about requirements that don't exist yet, paid for immediately in
complexity. Three similar lines of code are cheaper to carry than a wrong
abstraction.

### VIII. State Derivation Happens During Render, Not in Effects
Setting state synchronously to reset or derive it in response to a
prop/value change MUST be computed during render — by comparing the current
value against a stored previous value and calling the setter directly in
the render body when it differs — not inside `useEffect`. An effect that
performs async work (e.g. data fetching) and sets state as a result MUST
define that async logic as a closure local to the effect itself, not call
out to a function defined in the outer component scope, even when wrapped
in `void` — a function that escapes the effect (e.g. because it is also
exposed via context or props) gets flagged even when invoked correctly.

**Rationale**: `eslint-plugin-react-hooks` v7's compiler-derived rules
(`set-state-in-effect`, `purity`) reject this codebase's previous idioms for
both cases, and neither failure mode is obvious from the error message
alone — both took real debugging effort to work out (see
`ConversationList.jsx`, `Portrait.jsx`, `VoiceModelAccordion.jsx`, and
`ChatPage.jsx` for the render-time-derivation pattern; `AssistantLayout.jsx`
for the effect-local-closure requirement). Writing it down here means it
gets applied going forward instead of re-discovered by trial and error.

## Quality Gates

A change is not mergeable until: it passes Pint and ESLint with zero errors
(Principle I), and any Pest-covered behavior it touches has passing feature
tests backed by factories (Principle VI). These gates apply uniformly —
there is no informal or one-off exception for "just this once."

## Relationship to CLAUDE.md

This constitution governs product, architecture, and quality principles —
what the software itself must satisfy, independent of who or what is
writing it. Agent-operational mechanics — how Claude Code should behave in
this repository (workflow, tool usage, confirmation requirements,
communication style, Laravel Boost conventions) — live in CLAUDE.md and are
out of scope here. CLAUDE.md references this constitution rather than
duplicating its content, so the two do not drift out of sync.

## Governance

This constitution supersedes ad-hoc convention wherever the two conflict.
Amendments are made by editing `.specify/memory/constitution.md` (via
`/speckit-constitution` or directly) and MUST update the version per
semantic versioning: MAJOR for backward-incompatible principle removals or
redefinitions, MINOR for a new principle or materially expanded guidance,
PATCH for wording or clarification only. `/speckit-plan` checks proposed
technical plans against this constitution before implementation begins;
deviation from a principle MUST be justified explicitly in that plan or the
plan MUST be revised to comply.

**Version**: 1.1.0 | **Ratified**: 2026-08-23 | **Last Amended**: 2026-08-23
