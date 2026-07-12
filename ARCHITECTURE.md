# VERA — Architecture Analysis

> Volatile Emotional Response Architecture
> A local-first AI companion with a cyberpunk CRT terminal aesthetic.

---

## Overview

VERA is a full-stack web application that wraps a local or remote LLM in a highly stylized, character-driven interface. The user talks to VERA — a fictional AI persona living in a cyberpunk city called The Bridge — and she responds with dynamically changing expressions, styled text, and personality-driven replies. LLM providers and models are managed entirely through the UI and stored in the database. A config-based fallback is used when no model is selected.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 (PHP 8.4) |
| Frontend | React 19 via Vite + React Router |
| Styling | Tailwind CSS v4 |
| LLM Runtime | Any OpenAI-compatible API or Anthropic (DB-managed) |
| Database | PostgreSQL |
| Auth | Laravel Sanctum (SPA / cookie-based) |
| Dev Environment | Laravel Herd (macOS) |

---

## High-Level Architecture

```
Browser (React SPA — React Router)
    |
    |-- GET  /sanctum/csrf-cookie                     (Sanctum handshake)
    |-- POST /login                                   (AuthController)
    |-- GET  /api/user                                (auth check on load)
    |-- GET  /api/settings                            (SettingsController@show)
    |-- PUT  /api/settings                            (SettingsController@update — theme)
    |-- PUT  /api/settings/model                      (SettingsController@selectModel)
    |-- GET  /api/emotions                            (EmotionController)
    |-- GET  /api/assistants/{assistant}/conversations
    |-- POST /api/assistants/{assistant}/conversations
    |-- GET  /api/assistants/{assistant}/conversations/{id}/messages
    |-- POST /api/assistants/{assistant}/conversations/{id}/messages
    |-- DELETE|PATCH /api/assistants/{assistant}/conversations/{id}
    |-- GET|POST|PATCH|DELETE /api/ai-providers
    |-- POST|PATCH|DELETE /api/ai-providers/{provider}/models
    |-- GET|POST /api/lorebook
    |
Laravel Backend (API)
    |
    |-- Sanctum session auth (cookie + CSRF)
    |-- ConversationController proxies to LLM via LlmProvider contract
    |-- PromptDirector assembles system prompt from vera_prompt.json
    |-- LlmManager resolves provider: user Settings → AiModel → AiProvider → GenericProvider or AnthropicProvider
    |-- Falls back to config/ai.php if no model selected
    |-- Persists conversations, messages, images to PostgreSQL / disk
    |
LLM (resolved by LlmManager)
    |
    |-- GenericProvider   → any OpenAI-compatible endpoint
    |-- AnthropicProvider → Anthropic API
```

The SPA pattern means Laravel serves a single Blade view which loads the React bundle. All subsequent interaction is via JSON API calls.

---

## Backend

### Routing

**`routes/web.php`**
- `GET /` — serves the Blade entry point (React SPA shell)
- `POST /login` — `AuthController@login`
- `POST /logout` — `AuthController@logout`

**`routes/api.php`**
All routes behind `auth:sanctum` middleware:

| Method | Route | Handler |
|---|---|---|
| GET | `/api/user` | returns authenticated user |
| GET | `/api/settings` | `SettingsController@show` |
| PUT | `/api/settings` | `SettingsController@update` (theme) |
| PUT | `/api/settings/model` | `SettingsController@selectModel` |
| GET | `/api/emotions` | `EmotionController@index` |
| GET | `/api/lorebook` | `LorebookController@show` |
| POST | `/api/lorebook` | `LorebookController@save` |
| GET | `/api/assistants/{assistant}/conversations` | `ConversationController@index` |
| POST | `/api/assistants/{assistant}/conversations` | `ConversationController@store` |
| GET | `/api/assistants/{assistant}/conversations/{id}/messages` | `ConversationController@show` |
| POST | `/api/assistants/{assistant}/conversations/{id}/messages` | `ConversationController@sendMessage` |
| DELETE | `/api/assistants/{assistant}/conversations/{id}` | `ConversationController@destroy` |
| PATCH | `/api/assistants/{assistant}/conversations/{id}` | `ConversationController@update` |
| GET | `/api/ai-providers` | `AiProviderController@index` |
| POST | `/api/ai-providers` | `AiProviderController@store` |
| PATCH | `/api/ai-providers/{id}` | `AiProviderController@update` |
| DELETE | `/api/ai-providers/{id}` | `AiProviderController@destroy` |
| POST | `/api/ai-providers/{provider}/models` | `AiModelController@store` |
| PATCH | `/api/ai-providers/{provider}/models/{model}` | `AiModelController@update` |
| DELETE | `/api/ai-providers/{provider}/models/{model}` | `AiModelController@destroy` |

### Controllers

**`AuthController`**
Standard Sanctum SPA login: validates credentials, calls `Auth::attempt()`, regenerates session. Logout invalidates session and regenerates CSRF token.

**`ConversationController`**
Full conversation lifecycle, scoped to `assistants/{assistant}`:

- `index` — conversations for the authenticated user under the given assistant
- `store` — creates a new empty conversation
- `destroy` — deletes a conversation (cascades to messages)
- `update` — renames a conversation
- `show` — returns all messages with image URLs resolved from storage
- `sendMessage`:
  1. Validates `messages[]` array (role/content/images)
  2. Saves the last user message; stores any attached image via `Image::storeFromBase64()`
  3. Builds system prompt via `PromptDirector`
  4. Resolves the LLM provider via `LlmManager::forAssistantUser()`
  5. Calls `chat()`, saves the assistant reply (content + thinking)
  6. Returns `conversation_id`, `content`, `thinking`

**`AiProviderController`**
CRUD for `AiProvider` records. API key is encrypted at rest and never returned in responses (`has_key` boolean appended instead). Validates `format` against the `AiProviderFormat` enum.

**`AiModelController`**
CRUD for `AiModel` records nested under a provider. Manages `name`, `endpoint`, `thinking`, `prompt`, `config`.

**`SettingsController`**
- `show` — returns `selected_theme`, `available_themes`, `ai_model_id` (scoped to `assistant_id = 1`)
- `update` — saves theme, merging into existing settings data
- `selectModel` — saves `ai_model_id` into settings data, or clears it (nullable)

**`EmotionController`**
Returns the emotion set filtered by `restricted` flag. `?unlocked=true` returns alternate expressions.

**`LorebookController`**
Reads and saves the user's lorebook (entries with tags). Injected into the system prompt at request time.

### LLM Provider System

**`App\Contracts\LlmProvider`**
```php
interface LlmProvider {
    public function chat(array $messages): LlmResponse;
    public static function fromModel(AiModel $aiModel): static;
}
```

**`App\DTOs\LlmResponse`**
Unified return type: `content` (string) + `thinking` (nullable string).

**`App\Enums\AiProviderFormat`**
```php
enum AiProviderFormat: string {
    case Generic   = 'generic';    // any OpenAI-compatible API
    case Anthropic = 'anthropic';  // Anthropic API
}
```
Each case maps to a provider class via `providerClass()`.

**`App\Services\LlmProviders\LlmManager`**
Resolution order:
1. `forAssistantUser(AssistantUser $assistantUser)` — looks up `Settings` for the user+assistant pair
2. If `ai_model_id` is set → loads `AiModel` with its `AiProvider` → calls `fromModel()`
3. If no model selected → `fromConfig()` reads `config/ai.php` and constructs synthetic model/provider objects

**`GenericProvider`**
Handles any OpenAI-compatible chat completions API. Supports:
- Bearer token auth (optional)
- Multipart image messages (`image_url` content type)
- Configurable thinking/reasoning budget via provider `config_schema.thinking_key`
- Per-model `max_tokens` and `timeout` from config

**`AnthropicProvider`**
Handles the Anthropic Messages API with extended thinking support.

**`config/ai.php`**
Defines the config-based fallback (`default`) and auxiliary config blocks (`embedding`, `telegram`). The `default` block mirrors the shape of an `AiModel`+`AiProvider` so `LlmManager::fromConfig()` can construct provider instances from it.

### Prompt System

The system prompt is assembled entirely on the backend.

**`App\Builders\PromptBuilder`**
Renders `vera_prompt.json` sections recursively into natural language. Strings pass through as-is, sequential arrays become comma-separated lists, associative arrays become labeled sub-sections.

**`App\Directors\PromptDirector`**
Reads `vera_prompt.json`, supports `only([...])` and `except([...])` filtering, delegates to `PromptBuilder`. Called on every `sendMessage` request.

### Models & Database

**`User`** — standard Laravel user; has many `Conversation`s, belongs to many `Assistant`s via `AssistantUser`

**`Assistant`**
- `name`, `slug`, `description`, `prompt` (JSON), `opening_message`
- Belongs to many `User`s via `AssistantUser`; has many `Emotion`s

**`AssistantUser`** (pivot)
- Links `User` ↔ `Assistant`; has many `Conversation`s scoped to this pairing

**`Settings`**
- `user_id`, `assistant_id`, `data` (JSON)
- Stores: `theme`, `ai_model_id`
- Scoped per user+assistant pair

**`AiProvider`**
- `name`, `url`, `api_key` (encrypted), `format` (`AiProviderFormat` enum), `prompt`, `config_schema` (JSON)
- `api_key` is hidden; `has_key` boolean is appended
- Has many `AiModel`s

**`AiModel`**
- `provider_id`, `name`, `endpoint`, `thinking` (boolean), `prompt`, `config` (JSON)
- `endpoint` is the model identifier sent to the API (e.g. `google/gemma-4-26b-a4b-it`)
- Belongs to `AiProvider`

**`Conversation`**
- `assistant_user_id`, `title`
- Has many `Message`s

**`Message`**
- `conversation_id`, `role`, `content`, `thinking`, `emotion`
- `thinking` stores the LLM's internal reasoning chain
- `emotion` is defined but not yet written by the controller (frontend-only state)

**`Image`** — polymorphic (`imageable_type/id`), disk-stored, `url` accessor
**`Video`** — polymorphic (`videoable_type/id`), disk-stored, `url` accessor
**`Emotion`** — `name`, `restricted`; has one `Image`, has one `Video`
**`Lorebook`** — belongs to `User`; has many `LoreEntry`s
**`LoreEntry`** — content, belongs to many `Tag`s

### Artisan Commands

**`php artisan emotions:sync`** — seeds/updates `Emotion` records
**`php artisan telegram:poll`** — long-polls Telegram Bot API, routes messages through the LLM pipeline

---

## Frontend

### Routing

The app uses React Router. `app.jsx` defines all routes:

```
/login                    → LoginPage
/conversations            → ConversationsPage     (authenticated)
/conversations/:id        → ChatPage              (authenticated)
/lorebook                 → LorebookPage          (authenticated)
/settings                 → SettingsPage          (authenticated)
/providers                → ProvidersPage         (authenticated)
*                         → redirect to /conversations
```

`AuthenticatedLayout` wraps all protected routes — handles auth check on mount and provides the toast context via `useOutletContext`.

### Theme System

`ThemeContext` holds the active theme string. It fetches the current theme from `GET /api/settings` on mount and applies it as a `data-theme` attribute on `<html>`. Theme changes call `PUT /api/settings`.

### Pages

**`LoginPage`**
Terminal-style login. Email → password → authenticate. Calls `getCsrfCookie()` then `POST /login`.

**`ConversationsPage`**
Lists conversations for the active assistant. Create, select (navigate to `/conversations/:id`), delete, rename.

**`ChatPage`**
Main chat interface:
- Message list with `ChatMessage` components
- Input bar with image attachment
- Emotion tag parsed from each response → `Portrait` expression swap
- `BootSequence` plays on first load for a new conversation

**`LorebookPage`**
Lorebook editor. Displays `EntryAccordion` components for each lore entry.

**`SettingsPage`**
Theme selector. Fetches available themes from `GET /api/settings`, applies selection via `PUT /api/settings`.

**`ProvidersPage`**
AI provider and model management:
- Lists providers via `useProviders` hook
- `ProviderAccordion` for each provider (collapsible config form)
- `ModelAccordion` nested per model — shows SELECT button in header; clicking `● ACTIVE` deselects
- Active model loaded from `GET /api/settings` on mount; selection saved to `PUT /api/settings/model`

### Key Components

**`Accordion`** (`components/common/`)
Reusable collapsible panel. Props: `label`, `title`, `collapsed`, `onToggle`, `onDelete`, `badge` (rendered in header), `actions` (rendered in header right side, stopPropagation handled).

**`ProviderAccordion`**
Provider config form (name, URL, API key, format, prompt, config schema) inside an `Accordion`. Embeds `ModelAccordion` for each model. Passes `activeModelId` and `onSelectModel`/`onDeselect` down.

**`ModelAccordion`**
Model config form (name, endpoint, thinking, prompt, config) inside an `Accordion`. Header shows `● ACTIVE` badge (clickable to deselect) and `SELECT` button when applicable.

**`Portrait`**
Three rendering modes:
1. **Unauthenticated** — pixelated, dark canvas lockscreen
2. **Video intro** — plays short video on first `neutral` emotion after auth
3. **Authenticated** — emotion-mapped image from `useEmotions` with scanline overlay and mood label

**`ChatMessage`** — renders a single message; differentiates VERA / USER, handles thinking blocks and images
**`ThinkingBlock`** — collapsible chain-of-thought display
**`BootSequence`** — animated terminal boot, fires `onComplete` callback
**`ConfirmationModal`** — terminal-style modal with configurable options
**`ConversationList`** — sidebar list with create/delete/rename
**`ToastContainer`** / **`Scanlines`** — toast display and CRT overlay

### Hooks

**`useProviders(addToast)`**
- Loads providers from `GET /api/ai-providers` and active model from `GET /api/settings` in parallel
- Full CRUD: `addProvider`, `saveProvider`, `deleteProvider`, `addModel`, `saveModel`, `deleteModel`
- `activeModelId` state + `selectModel(modelId)` — calls `PUT /api/settings/model`; `null` deselects

**`useConversations`** — list + history loading for the active assistant
**`useEmotions`** — fetches emotion name → `{ image_url, video_url }` map
**`useToast`** — add/remove toasts with auto-dismiss

### Utilities

**`api.js`** — fetch wrapper; `credentials: 'include'`, `Accept: application/json`, Sanctum CSRF helper
**`parsers.js`** — strips `[emotion]` tag from response, validates against known names, falls back to `neutral`
**`formatMessage.jsx`** — `*text*` → italic, `(text)` → purple italic, `[text]` → bold cyan

---

## Data Flow: A Single Chat Turn

```
1. User types message, hits Enter
2. ChatPage: append user message to local state, show loading cursor
3. ChatPage: POST /api/assistants/{assistant}/conversations/{id}/messages
   body: { messages: [...history, user_msg] }  — no system prompt, backend adds it
4. ConversationController: validate → find Conversation → save user Message + Image
5. PromptDirector: reads vera_prompt.json → builds system prompt string
6. LlmManager::forAssistantUser(): checks Settings for ai_model_id
   → if set: load AiModel + AiProvider → instantiate GenericProvider or AnthropicProvider
   → if not set: fromConfig() builds provider from config/ai.php
7. LlmProvider.chat([system_prompt, ...messages]) → LlmResponse
8. ConversationController: save assistant Message → return JSON
9. ChatPage: parseEmotionFromResponse(content) → extract [tag] + clean text
10. ChatPage: setCurrentEmotion(emotion) → Portrait swaps expression
11. ChatPage: render ChatMessage with formatted text + thinking block
```

---

## Authentication Flow

VERA uses Sanctum's SPA cookie authentication — no tokens, no localStorage:

```
1. Page load → AuthenticatedLayout checks GET /api/user
   - 200: session active, render layout
   - 401: redirect to /login
2. Login: GET /sanctum/csrf-cookie → sets XSRF-TOKEN cookie
3. POST /login with credentials → Laravel sets session cookie
4. All subsequent API requests send both cookies automatically
5. Logout: POST /logout → session invalidated server-side → redirect to /login
```

---

## Current Limitations & Planned Work

### Known Gaps

- **Emotion not persisted** — the `emotion` column exists on `messages` but is never written. Emotion state is frontend-only.
- **VoiceController is a stub** — voice input/output not implemented.
- **No metrics system** — `vera_prompt.json` has a `metrics` section but affection/trust/patience values are not tracked or used for expression gating.
- **assistant_id hardcoded to 1** — Settings queries are currently scoped to `assistant_id = 1` pending proper multi-assistant UI.

### Planned Features

- Affection/trust/comfort/patience metrics system
- Expression gating based on relationship metrics
- Voice input/output (Web Speech API + TTS)
- Local image generation (ComfyUI/Stable Diffusion)
- Multiple character support with full multi-assistant UI
- Alternate outfit system
- NPC interaction system for The Bridge

---

## File Reference

```
laravel-vera/
├── app/
│   ├── Builders/PromptBuilder.php              renders vera_prompt.json to text
│   ├── Console/Commands/
│   │   ├── SyncEmotions.php                    seeds emotion records
│   │   └── TelegramPollCommand.php             Telegram bot long-poll loop
│   ├── Contracts/LlmProvider.php               interface: chat() + fromModel()
│   ├── Directors/PromptDirector.php            reads JSON, filters, builds prompt
│   ├── DTOs/LlmResponse.php                    content + thinking
│   ├── Enums/AiProviderFormat.php              generic | anthropic → provider class
│   ├── Http/Controllers/
│   │   ├── Auth/AuthController.php             login/logout
│   │   └── Api/
│   │       ├── AiProviderController.php        provider CRUD
│   │       ├── AiModelController.php           model CRUD
│   │       ├── ConversationController.php      CRUD + sendMessage
│   │       ├── EmotionController.php           serve emotions (locked/unlocked)
│   │       ├── LorebookController.php          lorebook read/save
│   │       ├── SettingsController.php          theme + model selection
│   │       └── VoiceController.php             stub
│   ├── Models/
│   │   ├── User.php
│   │   ├── Assistant.php                       name/slug/prompt/opening_message
│   │   ├── AssistantUser.php                   pivot; has many Conversations
│   │   ├── Settings.php                        data JSON (theme, ai_model_id)
│   │   ├── AiProvider.php                      url/api_key(encrypted)/format/config_schema
│   │   ├── AiModel.php                         name/endpoint/thinking/prompt/config
│   │   ├── Conversation.php                    assistant_user_id/title
│   │   ├── Message.php                         role/content/thinking/emotion
│   │   ├── Emotion.php                         name/restricted, morphOne Image/Video
│   │   ├── Lorebook.php                        belongs to User
│   │   ├── LoreEntry.php                       content, many-to-many Tags
│   │   ├── Tag.php
│   │   ├── Image.php                           polymorphic, disk-stored, url accessor
│   │   └── Video.php                           polymorphic, disk-stored, url accessor
│   ├── Providers/AppServiceProvider.php
│   └── Services/
│       ├── LlmProviders/
│       │   ├── LlmManager.php                  forAssistantUser() / fromConfig()
│       │   ├── GenericProvider.php             OpenAI-compatible, fromModel()
│       │   └── AnthropicProvider.php           Anthropic API, fromModel()
│       └── TelegramService.php                 getUpdates + sendMessage
├── config/ai.php                               default provider + embedding + telegram
├── database/migrations/                        all tables
├── routes/
│   ├── web.php                                 SPA entry + auth routes
│   └── api.php                                 all API routes (sanctum protected)
├── resources/js/
│   ├── app.jsx                                 React mount + router
│   ├── contexts/ThemeContext.jsx               global theme state
│   ├── layouts/AuthenticatedLayout.jsx         auth guard + toast context
│   ├── pages/
│   │   ├── LoginPage.jsx
│   │   ├── ConversationsPage.jsx
│   │   ├── ChatPage.jsx
│   │   ├── LorebookPage.jsx
│   │   ├── SettingsPage.jsx
│   │   └── ProvidersPage.jsx
│   ├── components/
│   │   ├── common/
│   │   │   ├── Accordion.jsx                   label/title/badge/actions/collapsed
│   │   │   └── ConfirmationModal.jsx           terminal-style modal
│   │   ├── ModelAccordion.jsx                  model form + select/deselect in header
│   │   ├── ProviderAccordion.jsx               provider form + nested models
│   │   ├── EntryAccordion.jsx                  lorebook entry form
│   │   ├── Portrait.jsx                        expression display (3 render modes)
│   │   ├── ChatMessage.jsx                     message rendering
│   │   ├── ThinkingBlock.jsx                   collapsible LLM reasoning
│   │   ├── BootSequence.jsx                    terminal boot animation
│   │   ├── ConversationList.jsx                sidebar list
│   │   ├── ToastContainer.jsx                  toast display
│   │   └── Scanlines.jsx                       CRT overlay
│   ├── hooks/
│   │   ├── useConversations.js                 list + history loading
│   │   ├── useEmotions.js                      emotion map (locked/unlocked)
│   │   ├── useProviders.js                     provider/model CRUD + activeModelId
│   │   └── useToast.js                         toast state
│   └── utils/
│       ├── api.js                              fetch wrapper (Sanctum-aware)
│       ├── parsers.js                          emotion tag extraction
│       └── formatMessage.jsx                   text → styled React elements
├── storage/app/public/                         emotion images/videos + user uploads
└── vera_prompt.json                            VERA's entire personality + world config
```
