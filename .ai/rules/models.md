---
paths:
  - 'app/Models/**'
  - 'database/migrations/**'
---

# Models

## Model and table names
Name a model and its table after the thing itself (`Sentiment`, `sentiments`), even when it belongs to a parent. A `ParentChild` name is only for a pivot table.

## Check the name is free
Before creating a model, table or class, search app/Models, database/migrations and app/ for the name. If a sibling already owns it, extend that sibling the way the codebase already does, such as a shared polymorphic table.
