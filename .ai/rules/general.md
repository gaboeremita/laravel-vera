---
paths:
  - 'app/**'
  - 'resources/js/**'
  - 'routes/**'
  - 'database/**'
  - 'tests/**'
---

# General

## camelCase names
Use camelCase for PHP and JS variables, JS object properties and JSON/API keys. Database columns, Eloquent attributes and arrays that map straight to columns stay snake_case.

## Comments
Write no comments by default. Comment only a non-obvious why (a hidden constraint, workaround or gotcha), on its own line above the code, without narrating what the code does or how it got there.

## Mirror the closest sibling feature
Build a new feature on the structure of its closest existing sibling: contracts, managers, provider classes, tools, jobs, events, table shapes, components, hooks and Tailwind classes. Video generation mirrors image generation.

## Delegate decisions to the LLM
When the model can judge something from context (whether to call a tool, whether a condition holds), leave it to prose and tool descriptions. Code enforces only resources, recorded state and per-session resident sentiments.

## Scope queries to the owning record
Scope every query on user- or assistant-owned data to the actual owning record, never to an account-level default such as the user's first archive.

## Errors fail loudly
Surface every failure through a log entry, a thrown exception or an error response. Empty catch blocks and silent fallbacks are prohibited.

## Data-agnostic code
Code, migrations and seeders work on any install and never reference specific assistants, worlds, regions or ids from a local database.

## No hardcoded content caps
Put no fixed cap on content users create (levels, counts, sizes). When an existing cap gets in the way, remove it.

## They/them for characters
Refer to assistants, residents and NPCs as they/them in prompts, UI text, comments and factories, unless a specific character's gender is stated.
