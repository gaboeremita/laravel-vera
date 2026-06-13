# VERA — Architecture Analysis

> Volatile Emotional Response Architecture
> A local-first AI companion with a cyberpunk CRT terminal aesthetic.

---

## Overview

VERA is a full-stack web application that wraps a local LLM (via Ollama) in a highly stylized, character-driven interface. The user talks to VERA — a fictional AI persona living in a cyberpunk city called The Bridge — and she responds with dynamically changing expressions, styled text, and personality-driven replies. The entire inference pipeline runs locally on the machine; no cloud AI calls are made.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 (PHP 8.4) |
| Frontend | React 19 via Vite |
| Styling | Tailwind CSS v4 |
| LLM Runtime | Ollama (local, HTTP API) |
| Database | MySQL |
| Auth | Laravel Sanctum (SPA / cookie-based) |
| Dev Environment | Laravel Herd (macOS) |

---

## High-Level Architecture

```
Browser (React SPA)
    |
    |-- GET  /sanctum/csrf-cookie   (Sanctum handshake)
    |-- POST /login                 (AuthController)
    |-- GET  /api/user              (auth check on load)
    |-- POST /api/chat              (ChatController → Ollama)
    |
Laravel Backend (API)
    |
    |-- Sanctum session auth (cookie + CSRF)
    |-- ChatController proxies to Ollama HTTP API
    |-- Persists conversations + messages to MySQL
    |
Ollama (localhost:11434)
    |
    |-- /api/chat  (model: gemma4 or configured model)
    |-- stream: false, think: true
```

The SPA pattern means Laravel serves a single Blade view (`welcome`) which loads the React bundle. All subsequent interaction is via JSON API calls. Laravel never renders HTML for the chat itself.

---

## Backend

### Routing (`routes/`)

**`routes/web.php`**
- `GET /` — serves the Blade entry point (React SPA shell)
- `POST /login` — `AuthController@login`
- `POST /logout` — `AuthController@logout`

**`routes/api.php`**
- All routes behind `auth:sanctum` middleware
- `GET /api/user` — returns authenticated user (used for session check on page load)
- `POST /api/chat` — `ChatController@send`

### Controllers

**`AuthController`** (`app/Http/Controllers/Auth/`)
- Standard Sanctum SPA login: validates credentials, calls `Auth::attempt()`, regenerates session
- Logout invalidates session and regenerates CSRF token
- Returns JSON responses only (no redirects)

**`ChatController`** (`app/Http/Controllers/Api/`)
The core backend controller. On each `POST /api/chat`:

1. Validates incoming payload: `conversation_id` (nullable), `messages[]` array with role/content/images
2. Finds or creates a `Conversation` record for the authenticated user
3. Saves the latest user message (with optional base64 image) to the `messages` table
4. Proxies the full messages array to Ollama's `/api/chat` endpoint with `think: true` enabled
5. Extracts `content` and `thinking` from Ollama's response
6. Saves the assistant reply (with thinking block) to the `messages` table
7. Returns `conversation_id`, `content`, and `thinking` to the frontend

Note: the system prompt is assembled and injected by the frontend (via `promptBuilder.js`) and passed as the first message in the array. The backend treats it as just another message.

### Models & Database

**`User`** — standard Laravel user, has many `Conversation`s

**`Conversation`**
- `user_id`, `title` (auto-generated from first message, truncated to 50 chars)
- Has many `Message`s

**`Message`**
- `conversation_id`, `role` (`user` | `assistant`), `content`, `thinking`, `image`, `emotion`
- The `thinking` column stores the LLM's internal reasoning (when supported by the model)
- The `image` column stores a single base64 image string per message
- `emotion` is defined in the schema but not yet populated by the controller

### AI Configuration (`config/ai.php`)
Reads `OLLAMA_URL` and `OLLAMA_MODEL` from `.env`. The controller calls `config('ai.ollama.url')` and `config('ai.ollama.model')` — clean separation from hardcoded values.

---

## Frontend

### Entry Point

**`resources/js/app.jsx`** — bootstraps React, mounts `<Vera />` to the DOM
**`resources/js/app.js`** — Vite/CSS entry (imports `app.css`)

### State Machine in `Vera.jsx`

The main component owns the entire application state. It operates across three sequential phases:

```
Phase 1: Unauthenticated
  - Shows ASCII art VERA logo + login terminal UI
  - Portrait renders as a pixelated, darkened lockscreen image
  - loginStep state machine: 'email' → 'password' → 'authenticating' → null
  - Calls getCsrfCookie() then POST /login

Phase 2: Authenticated, not yet booted
  - <BootSequence /> plays a terminal startup animation
  - On complete: bootComplete() fires, sets booted=true, injects the hardcoded opening scene message

Phase 3: Booted — active chat
  - Full chat interface: message list, input bar, image attachment
  - Each message sent goes to POST /api/chat
  - Response parsed for emotion tag → Portrait swaps image
  - conversation_id persisted in state for continued conversations
```

**Key state:**
- `isAuthenticated` — checked on mount via `GET /api/user`
- `booted` — controls BootSequence / chat visibility
- `messages[]` — local message history (role, content, image, thinking, loading)
- `conversationId` — tracks current backend conversation for persistence
- `currentEmotion` — drives Portrait expression
- `pendingImage` — base64 image staged before send

### Components

**`Portrait.jsx`**
Three rendering modes:
1. **Unauthenticated**: renders the neutral image onto a `<canvas>` at 16x24 pixels with `brightness(0.15)` — a deliberately degraded, pixelated lockscreen effect
2. **Video intro**: on first `neutral` emotion after auth, plays `neutral_intro.mp4` before switching to static image
3. **Authenticated**: renders the emotion-mapped static image with a scanline overlay and a `mood: {emotion}` label

Emotion-to-image map is hardcoded in the component as `EXPRESSION_IMAGES` — 12 expressions total.

**`ChatMessage.jsx`**
Renders a single message. Differentiates VERA (red label, light text) from USER (grey label, muted text). Renders thinking blocks via `ThinkingBlock`, image attachments inline, and passes content through `formatMessage()`.

**`ThinkingBlock.jsx`**
Collapsible component that displays the LLM's `thinking` content (the model's internal chain-of-thought). Hidden by default, expandable by click.

**`BootSequence.jsx`**
An animated terminal boot sequence. Purely presentational — fires `onComplete` callback when animation finishes.

**`Scanlines.jsx`**
A full-viewport overlay component that renders the CRT scanline effect. Purely CSS-driven, pointer-events disabled.

### Utilities

**`api.js`**
Thin fetch wrapper. Sets `credentials: 'include'` and `Accept: application/json` on all requests. Handles CSRF cookie fetch for Sanctum separately. No error handling at this layer — callers handle failures.

**`promptBuilder.js`**
Reads `vera_prompt.json` at build time (imported as a JS module) and assembles a single system prompt string. Each section of the JSON maps to a block of natural language instructions. This is what gets injected as the first message in every chat request.

Exports three functions:
- `buildSystemPrompt()` — full assembled system prompt
- `getAvailableEmotions()` — array of valid emotion tag strings
- `getSecretTrigger()` — the hidden trigger phrase from config

**`parsers.js`**
Single function: `parseEmotionFromResponse(text)`. Strips the leading `[emotion]` tag from VERA's response using a regex, validates it against the emotion list from config, and returns `{ emotion, text }`. Falls back to `neutral` if no valid tag found.

**`formatMessage.jsx`**
Parses message text into React elements with visual formatting:
- `*text*` → italic, muted grey (action/emote)
- `(text)` → italic, purple (inner thought)
- `[text]` → bold cyan (bracketed annotation)
- Everything else → plain span

---

## The Prompt System

VERA's entire personality, rules, and world are defined in `vera_prompt.json` at the project root. This is the central configuration file for the character.

```
vera_prompt.json
  ├── identity          — who VERA is
  ├── appearance        — physical description
  ├── emotion_tags      — available expressions + tagging rules
  ├── personality       — behavioral traits list
  ├── style_rules       — response formatting rules
  ├── admin_mode        — Westworld Protocol override behavior
  ├── creator_mode      — password-protected creator recognition
  ├── ooc_mode          — out-of-character silent direction
  ├── creator_psychology — internal conflicts about the creator
  ├── environment       — The Bridge worldbuilding
  ├── npcs              — other AIs, loneliness context
  ├── image_handling    — how VERA reacts to images
  ├── secret_trigger    — hidden phrase that changes behavior
  └── metrics           — affection/trust/patience (pending)
```

`promptBuilder.js` imports this file directly and compiles it into a multi-section natural language string at bundle time. The result is a static constant (`SYSTEM_PROMPT`) set once when the React app loads.

**Implication**: changing VERA's personality requires editing `vera_prompt.json` and rebuilding the frontend bundle. The backend never reads this file.

---

## Data Flow: A Single Chat Turn

```
1. User types message, hits Enter
2. Vera.jsx: append user message to local state, show loading cursor
3. Vera.jsx: POST /api/chat
   - body: { conversation_id, messages: [system_prompt, ...history, user_msg] }
4. ChatController: validate → find/create Conversation → save user Message
5. ChatController: HTTP POST to Ollama /api/chat (120s timeout)
6. Ollama: runs inference, returns { message: { content, thinking } }
7. ChatController: save assistant Message to DB → return JSON to frontend
8. Vera.jsx: parseEmotionFromResponse(content) → extract [tag] + clean text
9. Vera.jsx: setCurrentEmotion(emotion) → Portrait swaps image
10. Vera.jsx: render ChatMessage with formatted text + thinking block
```

---

## Authentication Flow

VERA uses Sanctum's SPA cookie authentication — no tokens, no localStorage:

```
1. Page load: GET /api/user — if 200, setIsAuthenticated(true) (resume session)
2. Login: GET /sanctum/csrf-cookie → sets XSRF-TOKEN cookie
3. POST /login with credentials → Laravel sets session cookie
4. All subsequent API requests send both cookies automatically (credentials: 'include')
5. Logout: POST /logout → session invalidated server-side
```

The login flow is rendered entirely within the chat terminal — the user types email and password into the same input field used for chatting, with the terminal displaying `> Enter email:` / `> Enter password:` prompts. The portrait shows a pixelated lockscreen during this state.

---

## Current Limitations & Planned Work

### Known Gaps
- **System prompt lives on the frontend** — the full character prompt is assembled in JS and sent with every request. A malicious client could omit it entirely or send a different one. Moving prompt assembly to the backend is listed as planned work.
- **No conversation history loading** — on page reload, `messages[]` state is lost. The DB stores everything, but there's no endpoint to fetch prior messages. The `conversationId` resets to `null` on reload, so the next message starts a new conversation.
- **Emotion not persisted** — the `emotion` column exists on `messages` but `ChatController` never writes to it. Emotion state is frontend-only.
- **Image storage** — images are stored as raw base64 strings in the `image` column, which will bloat the database quickly at scale.

### Planned Features (from README)
- Conversation management UI (list, switch, delete)
- Load conversation history from DB on session resume
- Affection/trust/comfort/patience metrics system
- Expression gating based on relationship metrics
- Voice input/output (Web Speech API + TTS)
- Local image generation (ComfyUI/Stable Diffusion)
- Multiple character support
- Video loop expressions
- Alternate outfit system
- NPC interaction system

---

## File Reference

```
laravel-vera/
├── app/
│   ├── Http/Controllers/
│   │   ├── Auth/AuthController.php         login/logout
│   │   └── Api/ChatController.php          Ollama proxy + DB persistence
│   └── Models/
│       ├── User.php
│       ├── Conversation.php                belongs to User, has many Messages
│       └── Message.php                     role/content/thinking/image/emotion
├── config/
│   └── ai.php                              OLLAMA_URL + OLLAMA_MODEL
├── database/
│   ├── migrations/                         users, conversations, messages tables
│   └── factories/                          User, Conversation, Message factories
├── routes/
│   ├── web.php                             SPA entry + auth routes
│   └── api.php                             /user + /chat (sanctum protected)
├── resources/js/
│   ├── app.jsx                             React mount
│   ├── Vera.jsx                            Root component + all state
│   ├── components/
│   │   ├── Portrait.jsx                    Expression display (3 render modes)
│   │   ├── ChatMessage.jsx                 Message rendering
│   │   ├── ThinkingBlock.jsx               Collapsible LLM reasoning
│   │   ├── BootSequence.jsx                Terminal boot animation
│   │   └── Scanlines.jsx                   CRT overlay
│   └── utils/
│       ├── api.js                          Fetch wrapper (Sanctum-aware)
│       ├── promptBuilder.js                JSON → system prompt string
│       ├── parsers.js                      Emotion tag extraction
│       └── formatMessage.jsx               Text → styled React elements
└── vera_prompt.json                        VERA's entire personality + world config
```
