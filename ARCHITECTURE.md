# VERA — Architecture Analysis

> Volatile Emotional Response Architecture
> A local-first AI companion with a cyberpunk CRT terminal aesthetic.

---

## Overview

VERA is a full-stack web application that wraps a local or remote LLM in a highly stylized, character-driven interface. The user talks to VERA — a fictional AI persona living in a cyberpunk city called The Bridge — and she responds with dynamically changing expressions, styled text, and personality-driven replies. The LLM backend is pluggable: Ollama (local), OpenRouter, and Anthropic are all supported.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 (PHP 8.4) |
| Frontend | React 19 via Vite |
| Styling | Tailwind CSS v4 |
| LLM Runtime | Ollama / OpenRouter / Anthropic |
| Database | MySQL |
| Auth | Laravel Sanctum (SPA / cookie-based) |
| Dev Environment | Laravel Herd (macOS) |

---

## High-Level Architecture

```
Browser (React SPA)
    |
    |-- GET  /sanctum/csrf-cookie         (Sanctum handshake)
    |-- POST /login                       (AuthController)
    |-- GET  /api/user                    (auth check on load)
    |-- GET  /api/emotions                (EmotionController)
    |-- GET  /api/conversations           (ConversationController@index)
    |-- POST /api/conversations           (ConversationController@store)
    |-- GET  /api/conversations/{id}/messages   (ConversationController@show)
    |-- POST /api/conversations/{id}/messages   (ConversationController@sendMessage)
    |-- DELETE /api/conversations/{id}    (ConversationController@destroy)
    |
Laravel Backend (API)
    |
    |-- Sanctum session auth (cookie + CSRF)
    |-- ConversationController proxies to LLM via LlmProvider contract
    |-- PromptDirector assembles system prompt from vera_prompt.json
    |-- Persists conversations, messages, images to MySQL / disk
    |
LLM (resolved by LlmManager)
    |
    |-- OllamaProvider   → localhost:11434
    |-- OpenRouterProvider → openrouter.ai API
    |-- AnthropicProvider  → anthropic API
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
All routes behind `auth:sanctum` middleware:
- `GET /api/user` — returns authenticated user
- `GET /api/emotions` — `EmotionController@index` (supports `?unlocked=true`)
- `GET /api/conversations` — `ConversationController@index`
- `POST /api/conversations` — `ConversationController@store`
- `DELETE /api/conversations/{id}` — `ConversationController@destroy`
- `GET /api/conversations/{id}/messages` — `ConversationController@show`
- `POST /api/conversations/{id}/messages` — `ConversationController@sendMessage`

### Controllers

**`AuthController`** (`app/Http/Controllers/Auth/`)
- Standard Sanctum SPA login: validates credentials, calls `Auth::attempt()`, regenerates session
- Logout invalidates session and regenerates CSRF token
- Returns JSON responses only (no redirects)

**`ConversationController`** (`app/Http/Controllers/Api/`)
The core backend controller. Replaces the old `ChatController`. Handles full conversation lifecycle:

- `index` — returns all conversations for the authenticated user (id, title, updated_at)
- `store` — creates a new empty conversation
- `destroy` — deletes a conversation (and cascades to messages)
- `show` — returns all messages for a conversation, with image URLs resolved from storage
- `sendMessage` — the main chat endpoint:
  1. Validates `messages[]` array (role/content/images)
  2. Finds the conversation by `{id}` (scoped to user)
  3. Saves the last user message; stores any attached image via `Image::storeFromBase64()`
  4. Builds the system prompt via `(new PromptDirector())->build()`
  5. Resolves the LLM provider via the `LlmProvider` binding and calls `chat()`
  6. Saves the assistant reply (content + thinking) to the messages table
  7. Returns `conversation_id`, `content`, `thinking`

**`EmotionController`** (`app/Http/Controllers/Api/`)
- `index` — returns the emotion set filtered by `restricted` flag
  - `?unlocked=false` (default) → standard expressions
  - `?unlocked=true` → alternate/restricted expressions
  - Each emotion includes `name`, `image_url`, `video_url` resolved from storage

**`VoiceController`** (`app/Http/Controllers/Api/`)
- Stub. Not yet implemented.

### LLM Provider System

The LLM layer uses a contract + manager pattern:

**`App\Contracts\LlmProvider`**
```php
interface LlmProvider {
    public function chat(array $messages): LlmResponse;
}
```

**`App\DTOs\LlmResponse`**
Unified return type: `content` (string) + `thinking` (nullable string).

**`App\Services\LlmProviders\LlmManager`**
Reads `config('ai.default')` and resolves the correct provider instance. Supported providers:
- `ollama` → `OllamaProvider` (local HTTP, think: true supported)
- `openrouter` → `OpenRouterProvider` (remote, reasoning tokens)
- `anthropic` → `AnthropicProvider` (remote, extended thinking)

Bound in `AppServiceProvider`:
```php
$this->app->bind(LlmProvider::class, fn() => (new LlmManager())->resolve());
```

**`config/ai.php`**
Defines `default`, `defaults` (timeout, stream), and per-provider `providers` config blocks read from `.env`.

### Prompt System (Backend)

The system prompt is now assembled entirely on the backend — the frontend never sees or sends it.

**`App\Builders\PromptBuilder`**
Renders `vera_prompt.json` sections recursively into a natural language string. Strings are used as-is, sequential arrays become comma-separated lists, associative arrays become labeled sub-sections.

**`App\Directors\PromptDirector`**
Reads `vera_prompt.json`, supports `only([...])` and `except([...])` to filter sections, then delegates to `PromptBuilder`. Called on every `sendMessage` request.

### Models & Database

**`User`** — standard Laravel user, has many `Conversation`s

**`Conversation`**
- `user_id`, `title`
- Has many `Message`s

**`Message`**
- `conversation_id`, `role` (`user` | `assistant`), `content`, `thinking`, `emotion`
- Has one `Image` (polymorphic morph)
- The `thinking` column stores the LLM's internal reasoning chain
- `emotion` is defined in the schema but not yet written by the controller

**`Image`**
- Polymorphic (`imageable_type`, `imageable_id`) — attaches to `Message` or `Emotion`
- `path`, `disk`, `mime_type`, `size`
- `url` accessor resolves the storage URL via `Storage::disk($this->disk)->url($this->path)`
- `Image::storeFromBase64()` static helper: decodes base64, detects MIME type by magic bytes, writes to `storage/public`, creates the record

**`Video`**
- Polymorphic (`videoable_type`, `videoable_id`) — attaches to `Emotion`
- `path`, `disk`, `mime_type`, `size`
- `url` accessor same as Image

**`Emotion`**
- `name`, `restricted` (boolean)
- `restricted = false` → standard expression set shown by default
- `restricted = true` → alternate expression set shown when `unlocked`
- Has one `Image`, has one `Video` (polymorphic)

### Artisan Commands

**`SyncEmotions`** (`php artisan emotions:sync`)
Seeds or updates `Emotion` records from config. Run after initial setup or when adding expressions.

**`TelegramPollCommand`** (`php artisan telegram:poll`)
Long-polls the Telegram Bot API for incoming messages, passes them through the LLM pipeline, and replies via `TelegramService`.

### Telegram Integration

**`App\Services\TelegramService`**
Wrapper around the Telegram Bot HTTP API:
- `getUpdates(offset, timeout)` — long-poll for new messages (timeout + 5s HTTP timeout to avoid premature disconnect)
- `sendMessage(chatId, text)` — send a reply

---

## Frontend

### Entry Point

**`resources/js/app.jsx`** — bootstraps React, mounts `<Vera />` to the DOM
**`resources/js/app.js`** — Vite/CSS entry (imports `app.css`)

### State Machine in `Vera.jsx`

The main component owns the entire application state across three sequential phases:

```
Phase 1: Unauthenticated
  - Shows ASCII art VERA logo + login terminal UI
  - Portrait renders as a pixelated, darkened lockscreen image
  - loginStep state machine: 'email' → 'password' → 'authenticating' → null
  - Calls getCsrfCookie() then POST /login

Phase 2: Authenticated, not yet booted
  - <BootSequence /> plays a terminal startup animation
  - On complete: bootComplete() fires, sets booted=true, injects opening scene message

Phase 3: Booted — active chat
  - Full chat interface: conversation sidebar, message list, input bar, image attachment
  - Each message sent goes to POST /api/conversations/{id}/messages
  - Response parsed for emotion tag → Portrait swaps expression
  - Conversation list managed via useConversations hook
```

**Key state:**
- `isAuthenticated` — checked on mount via `GET /api/user`
- `booted` — controls BootSequence / chat visibility
- `messages[]` — local message history (role, content, image, thinking, loading)
- `conversationId` — tracks current backend conversation
- `currentEmotion` — drives Portrait expression
- `pendingImage` — base64 image staged before send

### Components

**`Portrait.jsx`**
Three rendering modes:
1. **Unauthenticated**: renders the neutral image onto a `<canvas>` at 16x24 pixels with `brightness(0.15)` — deliberately degraded pixelated lockscreen
2. **Video intro**: on first `neutral` emotion after auth, plays a short video before switching to static image
3. **Authenticated**: renders the emotion-mapped image from the emotion set fetched via `useEmotions`, with a scanline overlay and `mood: {emotion}` label

**`ConversationList.jsx`**
Sidebar component. Lists all conversations with timestamps. Supports selecting (loads history), creating, and deleting conversations. Wired to `useConversations`.

**`ChatMessage.jsx`**
Renders a single message. Differentiates VERA (red label) from USER (grey label). Renders thinking blocks via `ThinkingBlock`, image attachments inline via URL, and passes content through `formatMessage()`.

**`ThinkingBlock.jsx`**
Collapsible component displaying the LLM's `thinking` chain-of-thought. Hidden by default.

**`BootSequence.jsx`**
Animated terminal boot sequence. Fires `onComplete` callback when done.

**`TerminalModal.jsx`**
Reusable modal component styled in the terminal aesthetic.

**`ToastContainer.jsx`**
Renders active toast notifications. Wired to `useToast`.

**`Scanlines.jsx`**
Full-viewport CRT scanline overlay. Purely CSS, pointer-events disabled.

### Hooks

**`useConversations.js`**
Manages conversation list state: fetches from `GET /api/conversations`, exposes create/delete actions, tracks the active conversation ID, and loads message history via `GET /api/conversations/{id}/messages`.

**`useEmotions.js`**
Fetches the emotion set from `GET /api/emotions` (supports `?unlocked=true`). Returns a map of emotion name → `{ image_url, video_url }`.

**`useToast.js`**
Toast notification state: add/remove toasts with auto-dismiss.

### Utilities

**`api.js`**
Thin fetch wrapper. Sets `credentials: 'include'` and `Accept: application/json`. Handles CSRF cookie fetch for Sanctum separately.

**`parsers.js`**
`parseEmotionFromResponse(text)` — strips the leading `[emotion]` tag, validates against the known emotion names, returns `{ emotion, text }`. Falls back to `neutral`.

**`formatMessage.jsx`**
Parses message text into React elements:
- `*text*` → italic, muted grey (action/emote)
- `(text)` → italic, purple (inner thought)
- `[text]` → bold cyan (bracketed annotation)
- Everything else → plain span

---

## Data Flow: A Single Chat Turn

```
1. User types message, hits Enter
2. Vera.jsx: append user message to local state, show loading cursor
3. Vera.jsx: POST /api/conversations/{id}/messages
   - body: { messages: [...history, user_msg] }  (NO system prompt — backend adds it)
4. ConversationController: validate → find Conversation → save user Message + Image
5. PromptDirector: reads vera_prompt.json → builds system prompt string
6. LlmManager: resolves configured provider
7. LlmProvider.chat([system_prompt, ...messages]) → LlmResponse
8. ConversationController: save assistant Message → return JSON to frontend
9. Vera.jsx: parseEmotionFromResponse(content) → extract [tag] + clean text
10. Vera.jsx: setCurrentEmotion(emotion) → Portrait swaps expression
11. Vera.jsx: render ChatMessage with formatted text + thinking block
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

The login flow is rendered entirely within the chat terminal — the user types email and password into the same input field used for chatting.

---

## Current Limitations & Planned Work

### Known Gaps
- **Emotion not persisted** — the `emotion` column exists on `messages` but `ConversationController` never writes to it. Emotion is frontend-only.
- **VoiceController is a stub** — voice input/output not yet implemented.
- **No metrics system** — the `metrics` section in `vera_prompt.json` exists but affection/trust/patience values are not tracked or used for expression gating yet.

### Planned Features
- Affection/trust/comfort/patience metrics system
- Expression gating based on relationship metrics
- Voice input/output (Web Speech API + TTS)
- Local image generation (ComfyUI/Stable Diffusion)
- Multiple character support
- Alternate outfit system (unlockable at relationship thresholds)
- NPC interaction system for The Bridge

---

## File Reference

```
laravel-vera/
├── app/
│   ├── Builders/
│   │   └── PromptBuilder.php               renders vera_prompt.json to text
│   ├── Console/Commands/
│   │   ├── SyncEmotions.php                seeds emotion records
│   │   └── TelegramPollCommand.php         Telegram bot long-poll loop
│   ├── Contracts/
│   │   └── LlmProvider.php                 interface: chat(messages): LlmResponse
│   ├── Directors/
│   │   └── PromptDirector.php              reads JSON, filters sections, builds prompt
│   ├── DTOs/
│   │   └── LlmResponse.php                 content + thinking
│   ├── Http/Controllers/
│   │   ├── Auth/AuthController.php         login/logout
│   │   └── Api/
│   │       ├── ConversationController.php  CRUD + sendMessage
│   │       ├── EmotionController.php       serve emotions (locked/unlocked)
│   │       └── VoiceController.php         stub
│   ├── Models/
│   │   ├── User.php
│   │   ├── Conversation.php                belongs to User, has many Messages
│   │   ├── Message.php                     role/content/thinking/emotion, morphOne Image
│   │   ├── Emotion.php                     name/restricted, morphOne Image, morphOne Video
│   │   ├── Image.php                       polymorphic, disk-stored, url accessor
│   │   └── Video.php                       polymorphic, disk-stored, url accessor
│   ├── Providers/
│   │   └── AppServiceProvider.php          binds LlmProvider via LlmManager
│   └── Services/
│       ├── LlmProviders/
│       │   ├── LlmManager.php              resolves provider from config
│       │   ├── OllamaProvider.php
│       │   ├── OpenRouterProvider.php
│       │   └── AnthropicProvider.php
│       └── TelegramService.php             getUpdates + sendMessage
├── config/
│   └── ai.php                              default provider + per-provider config
├── database/
│   ├── migrations/                         users, conversations, messages, images, emotions, videos
│   └── factories/                          User, Conversation, Message, Emotion factories
├── routes/
│   ├── web.php                             SPA entry + auth routes
│   └── api.php                             conversations, emotions, user (sanctum protected)
├── resources/js/
│   ├── app.jsx                             React mount
│   ├── Vera.jsx                            root component + all state
│   ├── components/
│   │   ├── Portrait.jsx                    expression display (3 render modes)
│   │   ├── ChatMessage.jsx                 message rendering
│   │   ├── ThinkingBlock.jsx               collapsible LLM reasoning
│   │   ├── BootSequence.jsx                terminal boot animation
│   │   ├── ConversationList.jsx            sidebar: list/create/delete conversations
│   │   ├── TerminalModal.jsx               reusable terminal-style modal
│   │   ├── ToastContainer.jsx              toast notification display
│   │   └── Scanlines.jsx                   CRT overlay
│   ├── hooks/
│   │   ├── useConversations.js             conversation list + history loading
│   │   ├── useEmotions.js                  emotion set fetching (locked/unlocked)
│   │   └── useToast.js                     toast state
│   └── utils/
│       ├── api.js                          fetch wrapper (Sanctum-aware)
│       ├── parsers.js                      emotion tag extraction
│       └── formatMessage.jsx               text → styled React elements
├── storage/app/public/                     emotion images/videos + user-uploaded images
└── vera_prompt.json                        VERA's entire personality + world config
```
