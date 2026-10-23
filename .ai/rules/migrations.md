---
paths:
  - 'database/migrations/**'
---

# Migrations

## Append-only migrations
Never edit an existing migration. Every schema change gets a new migration.

## Schema only
Migrations change schema only. Moving or backfilling existing data is a one-off script kept outside the repo.
