---
paths:
  - 'resources/js/**'
---

# Frontend

## Derive state during render
Compute state that resets or derives from a prop during render, by comparing against a stored previous value and calling the setter in the render body, never in `useEffect`. Async work inside an effect lives in a closure local to that effect.
