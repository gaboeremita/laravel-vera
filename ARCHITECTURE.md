# VERA — Architecture Analysis

> Volatile Emotional Response Architecture
> A local-first multi-AI platform with a dynamic expression system.

---

## Overview

VERA is a full-stack web application that connects users to AI assistants through a stylized, character-driven interface. Each assistant is fully configured in the database — its personality, prompt, expression set, and opening message are all data-driven with no hardcoded content. LLM providers and models are managed through the UI. A config-based fallback is used when no model is selected.

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
    |-- GET  /sanctum/csrf-cookie                               (Sanctum handshake)
    |-- POST /login                                             (AuthController)
    |-- GET  /api/user                                          (auth check on load)
    |-- GET|POST|PATCH|DELETE /api/assistants                   (AssistantController)
    |-- GET  /api/assistants/{assistant}/settings               (SettingsController@show)
    |-- PUT  /api/assistants/{assistant}/settings               (SettingsController@update — theme)
    |-- PUT  /api/assistants/{assistant}/settings/model         (SettingsController@selectModel)
    |-- GET  /api/assistants/{assistant}/emotions               (EmotionController)
    |-- POST|POST|DELETE /api/assistants/{assistant}/emotions   (AssistantEmotionController)
    |-- GET|POST /api/assistants/{assistant}/conversations
    |-- GET|POST /api/assistants/{assistant}/conversations/{id}/messages
    |-- DELETE|PATCH /api/assistants/{assistant}/conversations/{id}
    |-- GET|POST|PUT|DELETE /api/assistants/{assistant}/prompt
    |-- GET|POST|PATCH|DELETE /api/ai-providers
    |-- POST|PATCH|DELETE /api/ai-providers/{provider}/models
    |-- GET|POST /api/archives
    |-- GET|POST /api/archives/{id}
    |
Laravel Backend (API)
    |
    |-- Sanctum session auth (cookie + CSRF)
    |-- ConversationController proxies to LLM via LlmProvider contract
    |-- PromptDirector assembles system prompt from Assistant->prompt (DB)
    |-- LlmManager resolves provider: user Settings → AiModel → AiProvider → GenericProvider or AnthropicProvider
    |-- Falls back to config/ai.php if no model selected
    |-- Persists conversations, messages, images to PostgreSQL / disk
    |-- EmbedArchiveEntry job dispatches async embeddings on archive entry create/update
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
| GET | `/api/assistants` | `AssistantController@index` |
| POST | `/api/assistants` | `AssistantController@store` |
| GET | `/api/assistants/{id}` | `AssistantController@show` |
| PATCH | `/api/assistants/{id}` | `AssistantController@update` |
| DELETE | `/api/assistants/{id}` | `AssistantController@destroy` |
| GET | `/api/assistants/{assistant}/settings` | `SettingsController@show` |
| PUT | `/api/assistants/{assistant}/settings` | `SettingsController@update` (theme) |
| PUT | `/api/assistants/{assistant}/settings/model` | `SettingsController@selectModel` |
| GET | `/api/assistants/{assistant}/emotions` | `EmotionController@index` |
| POST | `/api/assistants/{assistant}/emotions` | `AssistantEmotionController@store` |
| POST | `/api/assistants/{assistant}/emotions/{emotion}` | `AssistantEmotionController@update` |
| DELETE | `/api/assistants/{assistant}/emotions/{emotion}` | `AssistantEmotionController@destroy` |
| GET | `/api/assistants/{assistant}/conversations` | `ConversationController@index` |
| POST | `/api/assistants/{assistant}/conversations` | `ConversationController@store` |
| GET | `/api/assistants/{assistant}/conversations/{id}/messages` | `ConversationController@show` |
| POST | `/api/assistants/{assistant}/conversations/{id}/messages` | `ConversationController@sendMessage` |
| DELETE | `/api/assistants/{assistant}/conversations/{id}` | `ConversationController@destroy` |
| PATCH | `/api/assistants/{assistant}/conversations/{id}` | `ConversationController@update` |
| GET | `/api/assistants/{assistant}/prompt` | `AssistantPromptController@show` |
| POST | `/api/assistants/{assistant}/prompt` | `AssistantPromptController@store` |
| PUT | `/api/assistants/{assistant}/prompt` | `AssistantPromptController@update` |
| DELETE | `/api/assistants/{assistant}/prompt` | `AssistantPromptController@destroy` |
| GET | `/api/ai-providers` | `AiProviderController@index` |
| POST | `/api/ai-providers` | `AiProviderController@store` |
| PATCH | `/api/ai-providers/{id}` | `AiProviderController@update` |
| DELETE | `/api/ai-providers/{id}` | `AiProviderController@destroy` |
| POST | `/api/ai-providers/{provider}/models` | `AiModelController@store` |
| PATCH | `/api/ai-providers/{provider}/models/{model}` | `AiModelController@update` |
| DELETE | `/api/ai-providers/{provider}/models/{model}` | `AiModelController@destroy` |
| GET | `/api/archives` | `ArchiveController@index` |
| GET | `/api/archives/{id}` | `ArchiveController@show` |
| POST | `/api/archives` | `ArchiveController@save` (create) |
| POST | `/api/archives/{id}` | `ArchiveController@save` (update) |

### Controllers

**`AuthController`**
Standard Sanctum SPA login: validates credentials, calls `Auth::attempt()`, regenerates session. Logout invalidates session and regenerates CSRF token.

**`AssistantController`**
Full CRUD for `Assistant` records, scoped to the authenticated user:

- `index` — returns all user's assistants with conversation stats and default emotion image
- `show` — returns full assistant detail including emotions, restricted_emotions, and archive_id
- `store` — creates assistant via multipart form; requires at least one `default` emotion with image upload; wraps in DB transaction; attaches user via pivot
- `update` — patches scalar fields (name, slug, description, opening_message, prompt, archive_id)
- `destroy` — deletes the assistant

**`AssistantEmotionController`**
Manages emotions for a specific assistant:

- `store` — creates a new emotion with image upload; rejects duplicate names
- `update` — renames and/or replaces the image for an existing emotion; deletes old image from disk
- `destroy` — deletes emotion and its image; rejects deletion of the `default` emotion

**`ConversationController`**
Full conversation lifecycle, scoped to `assistants/{assistant}`:

- `index` — conversations for the authenticated user under the given assistant
- `store` — creates a new conversation; seeds the first message from `Assistant->opening_message`
- `destroy` — deletes a conversation (cascades to messages)
- `update` — renames a conversation
- `show` — returns paginated messages with image URLs resolved from storage
- `sendMessage`:
  1. Validates `messages[]` array (role/content/images)
  2. Saves the last user message; stores any attached image via `Image::storeFromBase64()`
  3. Loads the `Assistant` model and its emotion set
  4. Builds system prompt via `PromptDirector($assistant->prompt)` — prompt comes from the DB
  5. Injects available emotions and runs RAG retrieval against the linked archive if available
  6. Resolves the LLM provider via `LlmManager::forAssistantUser()`
  7. Calls `chat()`, saves the assistant reply (content + thinking)
  8. Returns `conversation_id`, `content`, `thinking`

**`AiProviderController`**
CRUD for `AiProvider` records. API key is encrypted at rest and never returned in responses (`has_key` boolean appended instead). Validates `format` against the `AiProviderFormat` enum.

**`AiModelController`**
CRUD for `AiModel` records nested under a provider. Manages `name`, `endpoint`, `thinking`, `prompt`, `config`.

**`SettingsController`**
- `show` — returns `selected_theme`, `available_themes`, `ai_model_id` (scoped to the given assistant)
- `update` — saves theme, merging into existing settings data
- `selectModel` — saves `ai_model_id` into settings data, or clears it (nullable)

**`EmotionController`**
Returns the emotion set for the active assistant filtered by `restricted` flag. `?unlocked=true` returns alternate expressions.

**`AssistantPromptController`**
Manages the `prompt` JSON on an `Assistant` record:
- `show` — returns the current prompt JSON
- `store` — creates the prompt (409 if one already exists); validated by `ValidPromptStructure`
- `update` — replaces the prompt; validated by `ValidPromptStructure`
- `destroy` — clears the prompt (sets to `[]`)

`ValidPromptStructure` is a custom validation rule that enforces the prompt tree structure: top-level must be an associative array; each value must be a string, a sequential array of strings, or a nested associative array (recursive).

**`ArchiveController`**
Reads and saves the user's archives (RAG knowledge base). Each archive has a name, description, and a set of entries with title, content, keywords, and tags. The `save` action (POST) handles both create and update in a single endpoint — if `{id}` is provided, it updates; otherwise creates. Entries not present in the payload are deleted. On entry create or content change, `EmbedArchiveEntry` is dispatched for async embedding.

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

The system prompt is assembled entirely on the backend from data stored in the database.

**`App\Builders\PromptBuilder`**
Renders a prompt config array recursively into natural language. Strings pass through as-is, sequential arrays become comma-separated lists, associative arrays become labeled sub-sections.

**`App\Directors\PromptDirector`**
Accepts the `Assistant->prompt` JSON array (from DB) as its config. Supports `only([...])`, `except([...])`, and `append(key, value)` for injecting dynamic data (e.g. emotion tags, retrieved lore). Called on every `sendMessage` request. Also supports `withRetrieval()` for RAG — embedding the user's message and retrieving semantically similar archive entries.

### Models & Database

**`User`** — standard Laravel user; belongs to many `Assistant`s via `AssistantUser`

**`Assistant`**
- `name`, `slug`, `description`, `prompt` (JSON), `opening_message`, `archive_id` (nullable FK)
- Belongs to many `User`s via `AssistantUser`; has many `Emotion`s
- `archive_id` links the assistant to a specific `Archive` for RAG injection

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

**`Emotion`** — `name`, `restricted`, `assistant_id`; morphOne `Image`, morphOne `Video`

**`Archive`** — `name`, `description`, `user_id`; has many `ArchiveEntry`s; belongs to `User`

**`ArchiveEntry`** — `archive_id`, `title`, `content`, `keywords` (array); many-to-many `Tag`s; embedding dispatched on create/content change

**`Tag`** — `name`, `user_id`

**`Image`** — polymorphic (`imageable_type/id`), disk-stored, `url` accessor
**`Video`** — polymorphic (`videoable_type/id`), disk-stored, `url` accessor

### Jobs

**`EmbedArchiveEntry`** — async job dispatched by `ArchiveController` when an entry is created or its content changes; handles vector embedding for RAG retrieval.

### Artisan Commands

**`php artisan emotions:sync`** — seeds/updates `Emotion` records
**`php artisan telegram:poll`** — long-polls Telegram Bot API, routes messages through the LLM pipeline

---

## Frontend

### Routing

The app uses React Router. `app.jsx` defines all routes:

```
/login                               → LoginPage
/assistants                          → AssistantsPage        (authenticated)
/assistants/create                   → CreateAssistantPage   (authenticated)
/assistants/:assistantId/edit        → EditAssistantPage     (authenticated)
/assistants/:assistantId/            → AssistantLayout       (authenticated)
  conversations                      → ConversationsPage
  conversations/:id                  → ChatPage
  prompt                             → PromptPage
  archive                            → ArchivePage
  settings                           → SettingsPage
  providers                          → ProvidersPage
*                                    → redirect to /assistants
```

`AuthenticatedLayout` wraps all protected routes — handles auth check on mount and provides emotion state, boot sequence, and toast context via `useOutletContext`.

`AssistantLayout` wraps all assistant-scoped routes — fetches conversations, assistant info, and settings on `assistantId` change; passes `assistantId`, `assistantName`, `archiveId`, `conversations`, `setConversations`, and `fetchConversations` down via outlet context.

### Theme System

Themes are defined by the `Theme` enum (`app/Enums/Theme.php`): `default`, `terminal`, `slate`, `grimoire`. Each maps to a CSS file under `resources/css/themes/` that declares semantic CSS custom properties (colors, fonts, radii, shadows) scoped to `[data-theme="<value>"]`. Layout and spacing tokens defined in `base.css` are theme-independent so switching themes never causes a reflow — only a re-skin.

`ThemeContext` (React) holds the active theme string. On mount it fetches `GET /api/assistants/{assistant}/settings`, reads `selected_theme`, and sets `document.documentElement.setAttribute('data-theme', theme)`. Theme changes call `PUT /api/assistants/{assistant}/settings` with the new value and update the attribute immediately.

`SettingsController@show` returns `available_themes` by calling `array_column(Theme::cases(), 'value')`, so any new case added to the enum automatically appears as an option in the UI without further changes.

The selected theme is stored in the `data` JSON column of the `Settings` model, scoped to the user + assistant pair. The `update` method merges the theme key rather than overwriting the entire data object, preserving other settings (e.g. `ai_model_id`).

### Pages

**`LoginPage`**
Email → password → authenticate. Calls `getCsrfCookie()` then `POST /login`.

**`AssistantsPage`**
Lists all assistants belonging to the authenticated user. Shows conversation count, last activity, and default emotion avatar. Supports delete with confirmation. Links to create and edit pages.

**`CreateAssistantPage`**
Multipart form to create a new assistant: name, slug, description, opening message, prompt JSON, and required emotion images (at least one named `default`). Also accepts restricted emotions.

**`EditAssistantPage`**
Edit assistant fields (name, slug, description, opening message) and manage emotions via `AssistantEmotionController`. Uses `EmotionGrid` for add/rename/replace/delete emotion interactions.

**`ConversationsPage`**
Lists conversations for the active assistant. Create, select (navigate to `conversations/:id`), delete, rename.

**`ChatPage`**
Main chat interface:
- Message list with `ChatMessage` components
- Input bar with image attachment
- Emotion tag parsed from each response → `Portrait` expression swap
- `BootSequence` plays on first load for a new conversation

**`ArchivePage`**
Archive editor. Displays entries with title, content, keywords, and tags. Saves via `POST /api/archives` or `POST /api/archives/{id}`.

**`PromptPage`**
Visual prompt editor for the active assistant. Renders the prompt JSON as an interactive tree of `PromptNode` components. Supports adding, renaming, and deleting sections at any depth. Each node can be a string, list of strings, or nested object. Changes are saved via `PUT /api/assistants/{assistant}/prompt` (or `POST` if no prompt exists yet). The entire prompt can also be deleted from this page. Raw JSON toggle available.

**`SettingsPage`**
Theme selector. Fetches available themes from `GET /api/assistants/{assistant}/settings`, applies selection via `PUT /api/assistants/{assistant}/settings`.

**`ProvidersPage`**
AI provider and model management:
- Lists providers via `useProviders` hook
- `ProviderAccordion` for each provider (collapsible config form)
- `ModelAccordion` nested per model — shows SELECT button in header; clicking `● ACTIVE` deselects
- Active model loaded from `GET /api/assistants/{assistant}/settings` on mount; selection saved to `PUT /api/assistants/{assistant}/settings/model`

### Key Components

**`Accordion`** (`components/common/`)
Reusable collapsible panel. Props: `label`, `title`, `collapsed`, `onToggle`, `onDelete`, `badge` (rendered in header), `actions` (rendered in header right side, stopPropagation handled).

**`ProviderAccordion`**
Provider config form (name, URL, API key, format, prompt, config schema) inside an `Accordion`. Embeds `ModelAccordion` for each model. Passes `activeModelId` and `onSelectModel`/`onDeselect` down.

**`ModelAccordion`**
Model config form (name, endpoint, thinking, prompt, config) inside an `Accordion`. Header shows `● ACTIVE` badge (clickable to deselect) and `SELECT` button when applicable.

**`EmotionGrid`**
Displays the current emotion set for an assistant. Supports adding new emotions (name + image upload), renaming, replacing images, and deleting. Used in `EditAssistantPage`.

**`PromptEditor`**
Standalone prompt tree editor used in create/edit flows (before a prompt is saved to the DB). Mirrors `PromptNode` but operates on local state.

**`Portrait`**
Three rendering modes:
1. **Unauthenticated** — pixelated, dark canvas lock screen
2. **Video intro** — plays a short video on the first `neutral` emotion after auth
3. **Authenticated** — emotion-mapped image from `useEmotions` with scanline overlay and mood label

When no assistant is active, renders a neutral waiting state.

**`ChatMessage`** — renders a single message; differentiates assistant / user labels, handles thinking blocks and images
**`ThinkingBlock`** — collapsible chain-of-thought display
**`BootSequence`** — animated startup sequence, fires `onComplete` callback
**`Header`** — navigation header rendered within authenticated views
**`ConfirmationModal`** — modal with configurable options
**`ConversationList`** — sidebar list with create/delete/rename
**`ToastContainer`** / **`Scanlines`** — toast display and CRT overlay

### Hooks

**`useAssistants(addToast)`**
- Loads assistants from `GET /api/assistants`
- `deleteAssistant(id)` — calls DELETE and removes from local state

**`useProviders(addToast)`**
- Loads providers from `GET /api/ai-providers` and active model from `GET /api/assistants/{assistant}/settings` in parallel
- Full CRUD: `addProvider`, `saveProvider`, `deleteProvider`, `addModel`, `saveModel`, `deleteModel`
- `activeModelId` state + `selectModel(modelId)` — calls `PUT /api/assistants/{assistant}/settings/model`; `null` deselects

**`usePrompt(assistantId, addToast)`**
- Loads the assistant's prompt JSON from `GET /api/assistants/{assistant}/prompt`
- Manages the prompt tree in local state via `structuredClone` for immutable updates
- `setValueAtPath(path, value)` — update any leaf node by path array
- `addKey(parentPath, key, type)` — add a string, list, or object node
- `removeKey(path)` / `renameKey(path, newKey)` — structural edits (rename preserves key order)
- `addListItem(path)` / `removeListItem(path, index)` / `updateListItem(path, index, value)` — list management
- `save()` — POST (create) or PUT (update) depending on whether a prompt exists
- `destroy()` — DELETE and reset local state to null

**`useLocalPrompt`**
- Local-only prompt state manager used in create/edit assistant flows before the prompt is persisted to the DB
- Same tree manipulation API as `usePrompt` but without API calls

**`useEmotions`** — fetches emotion name → `{ image_url, video_url }` map; `fetchEmotions(assistantId)` to reload for a specific assistant
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
5. Load Assistant model → fetch its emotion set from DB
6. PromptDirector($assistant->prompt): prompt JSON comes from DB
   → inject emotion tags via append()
   → run RAG via withRetrieval() against linked Archive if assistant has archive_id
   → build system prompt string
7. LlmManager::forAssistantUser(): checks Settings for ai_model_id
   → if set: load AiModel + AiProvider → instantiate GenericProvider or AnthropicProvider
   → if not set: fromConfig() builds provider from config/ai.php
8. LlmProvider.chat([system_prompt, ...messages]) → LlmResponse
9. ConversationController: save assistant Message → return JSON
10. ChatPage: parseEmotionFromResponse(content) → extract [tag] + clean text
11. ChatPage: setCurrentEmotion(emotion) → Portrait swaps expression
12. ChatPage: render ChatMessage with formatted text + thinking block
```

---

## Authentication Flow

The app uses Sanctum's SPA cookie authentication — no tokens, no localStorage:

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
- **Voice not implemented** — voice input/output has been removed entirely.
- **Metrics not implemented** — affection/trust/patience system planned but not built.

### Planned Features

- Voice input/output (Web Speech API + TTS)
- Local image generation (ComfyUI/Stable Diffusion)

---

## File Reference

```
laravel-vera/
├── app/
│   ├── Builders/PromptBuilder.php              assembles system prompt from assistant config
│   ├── Console/Commands/
│   │   ├── SyncEmotions.php                    seeds emotion records
│   │   └── TelegramPollCommand.php             Telegram bot long-poll loop
│   ├── Contracts/LlmProvider.php               interface: chat() + fromModel()
│   ├── Directors/PromptDirector.php            reads assistant prompt config, filters, builds
│   ├── DTOs/LlmResponse.php                    content + thinking
│   ├── Enums/AiProviderFormat.php              generic | anthropic → provider class
│   ├── Http/Controllers/
│   │   ├── Auth/AuthController.php             login/logout
│   │   └── Api/
│   │       ├── AiProviderController.php        provider CRUD
│   │       ├── AiModelController.php           model CRUD
│   │       ├── ArchiveController.php           archive read/save (with async embedding)
│   │       ├── AssistantController.php         assistant CRUD (multipart, emotion images)
│   │       ├── AssistantEmotionController.php  per-assistant emotion store/update/destroy
│   │       ├── AssistantPromptController.php   prompt CRUD (show/store/update/destroy)
│   │       ├── ConversationController.php      CRUD + sendMessage
│   │       ├── EmotionController.php           serve emotions (locked/unlocked)
│   │       └── SettingsController.php          theme + model selection
│   ├── Jobs/EmbedArchiveEntry.php              async vector embedding for archive entries
│   ├── Models/
│   │   ├── User.php
│   │   ├── Assistant.php                       name/slug/prompt/opening_message/archive_id
│   │   ├── AssistantUser.php                   pivot; has many Conversations
│   │   ├── Settings.php                        data JSON (theme, ai_model_id)
│   │   ├── AiProvider.php                      url/api_key(encrypted)/format/config_schema
│   │   ├── AiModel.php                         name/endpoint/thinking/prompt/config
│   │   ├── Conversation.php                    assistant_user_id/title
│   │   ├── Message.php                         role/content/thinking/emotion
│   │   ├── Emotion.php                         name/restricted, morphOne Image/Video
│   │   ├── Archive.php                         name/description, belongs to User
│   │   ├── ArchiveEntry.php                    title/content/keywords, many-to-many Tags
│   │   ├── Tag.php
│   │   ├── Image.php                           polymorphic, disk-stored, url accessor
│   │   └── Video.php                           polymorphic, disk-stored, url accessor
│   ├── Providers/AppServiceProvider.php
│   ├── Rules/ValidPromptStructure.php          validates prompt tree (string/list/nested object)
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
│   ├── layouts/
│   │   ├── AuthenticatedLayout.jsx             auth guard + emotion state + boot sequence
│   │   └── AssistantLayout.jsx                 assistant-scoped context (conversations, settings)
│   ├── pages/
│   │   ├── LoginPage.jsx
│   │   ├── AssistantsPage.jsx                  list/delete assistants
│   │   ├── CreateAssistantPage.jsx             multipart assistant creation form
│   │   ├── EditAssistantPage.jsx               edit assistant + manage emotions
│   │   ├── ConversationsPage.jsx
│   │   ├── ChatPage.jsx
│   │   ├── ArchivePage.jsx                     archive editor (RAG knowledge base)
│   │   ├── PromptPage.jsx
│   │   ├── SettingsPage.jsx
│   │   └── ProvidersPage.jsx
│   ├── components/
│   │   ├── common/
│   │   │   ├── Accordion.jsx                   label/title/badge/actions/collapsed
│   │   │   └── ConfirmationModal.jsx           modal with configurable options
│   │   ├── ModelAccordion.jsx                  model form + select/deselect in header
│   │   ├── ProviderAccordion.jsx               provider form + nested models
│   │   ├── EmotionGrid.jsx                     emotion image manager (add/rename/replace/delete)
│   │   ├── PromptEditor.jsx                    local prompt tree editor (create/edit flows)
│   │   ├── PromptNode.jsx                      recursive prompt tree node editor
│   │   ├── EntryAccordion.jsx                  archive entry form
│   │   ├── Header.jsx                          navigation header
│   │   ├── Portrait.jsx                        expression display (3 render modes)
│   │   ├── ChatMessage.jsx                     message rendering
│   │   ├── ThinkingBlock.jsx                   collapsible LLM reasoning
│   │   ├── BootSequence.jsx                    startup animation
│   │   ├── ConversationList.jsx                sidebar list
│   │   ├── ToastContainer.jsx                  toast display
│   │   └── Scanlines.jsx                       CRT overlay
│   ├── hooks/
│   │   ├── useAssistants.js                    assistant list + delete
│   │   ├── useEmotions.js                      emotion map (locked/unlocked)
│   │   ├── useLocalPrompt.js                   local-only prompt tree state
│   │   ├── usePrompt.js                        prompt tree CRUD + save/destroy
│   │   ├── useProviders.js                     provider/model CRUD + activeModelId
│   │   └── useToast.js                         toast state
│   └── utils/
│       ├── api.js                              fetch wrapper (Sanctum-aware)
│       ├── parsers.js                          emotion tag extraction
│       └── formatMessage.jsx                   text → styled React elements
└── storage/app/public/                         emotion images/videos + user uploads
```
