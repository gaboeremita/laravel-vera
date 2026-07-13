# VERA

A multi-assistant AI platform with a dynamic visual expression system, built on Laravel, React, and a pluggable LLM backend.

## Overview

VERA is a local-first multi-AI platform. Each assistant has its own personality, expression set, and prompt — all configured in the database. LLM providers and models are managed through the UI with no config file changes required. The interface supports multiple themes, selectable per-user and persisted in the database.

## Tech Stack

- **Backend:** Laravel 13 (PHP 8.4)
- **Frontend:** React 19 (via Vite, React Router)
- **LLM:** Any OpenAI-compatible API or Anthropic — configured via the Providers UI
- **Database:** PostgreSQL
- **Styling:** Tailwind CSS v4
- **Auth:** Laravel Sanctum (SPA mode)

## Prerequisites

- PHP 8.4+
- Composer
- Node.js & npm
- PostgreSQL
- An LLM API endpoint (OpenRouter, Anthropic, a local Ollama-compatible server, etc.)

## Installation

```bash
# Clone the repo
git clone <repo-url>
cd laravel-vera

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed emotions
php artisan emotions:sync

# Create your user
php artisan tinker --execute 'User::create(["name" => "YourName", "email" => "you@email.com", "password" => bcrypt("yourpassword")]);'

# Start development
npm run dev
```

If using Laravel Herd, add the site through Herd's UI. Otherwise run `php artisan serve`.

## Configuration

### Environment Variables

```env
# Application
APP_URL=https://laravel-vera.test

# Database
DB_CONNECTION=pgsql
DB_DATABASE=vera

# Sanctum
SANCTUM_STATEFUL_DOMAINS=laravel-vera.test

# Default LLM provider (fallback if no model is selected in the UI)
AI_DEFAULT_URL=https://openrouter.ai/api/v1/chat/completions
AI_DEFAULT_API_KEY=
AI_DEFAULT_MODEL=google/gemma-3-27b-it
AI_DEFAULT_FORMAT=generic        # generic (OpenAI-compatible) | anthropic
AI_DEFAULT_THINKING=false
AI_DEFAULT_MAX_TOKENS=4096
AI_DEFAULT_TIMEOUT=600
AI_STREAM=false

# Telegram (optional)
TELEGRAM_URL=https://api.telegram.org
TELEGRAM_BOT_TOKEN=
TELEGRAM_USER_ID=
TELEGRAM_CHAT_ID=
TELEGRAM_ASSISTANT_ID=
```

### LLM Providers

Providers and models are managed through the **Providers** page in the UI (`/providers`). Each provider has:

- A base URL (any OpenAI-compatible endpoint, or Anthropic)
- An API key (encrypted at rest)
- A format (`generic` for OpenAI-compatible APIs, `anthropic` for the Anthropic API)

Each model has:
- An endpoint/model identifier (e.g. `google/gemma-4-26b-a4b-it`)
- Optional thinking toggle and thinking budget
- Per-model config (JSON) and prompt override

The active model is selected per-user via the **SELECT** button in the Providers UI. If no model is selected, the fallback config from `.env` is used.

### Theming

The app supports multiple themes, selectable per-user via the Settings page (`/settings`) and persisted in the database.

Available themes are defined in the `Theme` enum (`app/Enums/Theme.php`):

| Value | Description |
|---|---|
| `default` | Clean, minimal, light/dark |
| `terminal` | Classic green-on-black CRT terminal |
| `slate` | Cool blue-grey dark theme |
| `grimoire` | Dark, arcane, warm-toned |

Each theme is a CSS file under `resources/css/themes/` that declares a set of semantic CSS custom properties scoped to `[data-theme="<value>"]`. The active theme is applied by `ThemeContext` as a `data-theme` attribute on `<html>`.

**To add a new theme:**
1. Create `resources/css/themes/<name>.css` defining all required CSS tokens under `[data-theme="<name>"]` (use an existing theme file as reference)
2. Import it in `resources/css/styles.css`
3. Add a new case to `app/Enums/Theme.php`

The `SettingsController@show` endpoint returns `available_themes` by reading `Theme::cases()`, so the new option will appear in the UI automatically.

### Prompt Configuration

Each assistant's prompt is stored as a JSON object in the `prompt` column of the `Assistant` model (database-driven). At request time, `PromptDirector` receives this JSON, filters sections as needed, and assembles it into the system prompt via `PromptBuilder`. Available emotions are injected automatically from the assistant's emotion set.

The structure of the prompt JSON is flexible — any key becomes a section in the assembled system prompt. The `opening_message` field on the `Assistant` model is used as the first message when a new conversation is created.

## Project Structure

```
laravel-vera/
├── app/
│   ├── Builders/
│   │   └── PromptBuilder.php                 # Assembles system prompt from assistant config
│   ├── Console/Commands/
│   │   ├── SyncEmotions.php                  # Seeds/syncs emotion records from config
│   │   └── TelegramPollCommand.php           # Long-polls Telegram for incoming messages
│   ├── Contracts/
│   │   └── LlmProvider.php                   # LLM interface (chat method)
│   ├── Directors/
│   │   └── PromptDirector.php                # Reads assistant prompt config, builds system prompt
│   ├── DTOs/
│   │   └── LlmResponse.php                   # Unified response: content + thinking
│   ├── Enums/
│   │   └── AiProviderFormat.php              # generic | anthropic
│   ├── Http/Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php            # Login/logout
│   │   └── Api/
│   │       ├── AiProviderController.php      # CRUD for AI providers
│   │       ├── AiModelController.php         # CRUD for AI models
│   │       ├── AssistantPromptController.php # Prompt CRUD (show/store/update/destroy)
│   │       ├── ConversationController.php    # CRUD + message sending
│   │       ├── EmotionController.php         # Serve emotions with image/video URLs
│   │       ├── LorebookController.php        # Lorebook read/save
│   │       ├── SettingsController.php        # Theme + active model selection
│   │       └── VoiceController.php           # Stub
│   ├── Models/
│   │   ├── User.php
│   │   ├── Assistant.php                     # Assistant config (prompt, opening_message, emotions)
│   │   ├── AssistantUser.php                 # Pivot: user ↔ assistant
│   │   ├── Settings.php                      # Per-user, per-assistant settings (theme, model)
│   │   ├── AiProvider.php                    # DB-managed LLM provider
│   │   ├── AiModel.php                       # DB-managed LLM model
│   │   ├── Conversation.php
│   │   ├── Message.php
│   │   ├── Emotion.php                       # Expression name + restricted flag
│   │   ├── Lorebook.php
│   │   ├── LoreEntry.php
│   │   ├── Tag.php
│   │   ├── Image.php                         # Polymorphic, stored on disk
│   │   └── Video.php                         # Polymorphic, stored on disk
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   └── Services/
│       ├── LlmProviders/
│       │   ├── LlmManager.php                # Resolves provider: DB model → config fallback
│       │   ├── GenericProvider.php           # OpenAI-compatible API
│       │   └── AnthropicProvider.php
│       └── TelegramService.php               # Telegram API wrapper
├── config/
│   └── ai.php                                # Default LLM config + telegram config
├── database/migrations/
│   ├── create_conversations_table.php
│   ├── create_messages_table.php
│   ├── create_images_table.php
│   ├── create_emotions_table.php
│   ├── create_videos_table.php
│   ├── create_ai_providers_table.php
│   ├── create_ai_models_table.php
│   └── create_settings_table.php
├── resources/js/
│   ├── app.jsx                               # React entry + React Router routes
│   ├── contexts/
│   │   └── ThemeContext.jsx                  # Global theme state
│   ├── layouts/
│   │   └── AuthenticatedLayout.jsx           # Shared layout for protected routes
│   ├── pages/
│   │   ├── LoginPage.jsx
│   │   ├── ConversationsPage.jsx             # Conversation list
│   │   ├── ChatPage.jsx                      # Main chat interface
│   │   ├── LorebookPage.jsx                  # Lorebook editor
│   │   ├── PromptPage.jsx                    # Visual prompt editor
│   │   ├── SettingsPage.jsx                  # Theme selection
│   │   └── ProvidersPage.jsx                 # AI provider/model management
│   ├── components/
│   │   ├── common/
│   │   │   ├── Accordion.jsx                 # Reusable collapsible accordion
│   │   │   └── ConfirmationModal.jsx         # Confirmation modal
│   │   ├── ModelAccordion.jsx                # Model config + select/deselect
│   │   ├── ProviderAccordion.jsx             # Provider config + nested models
│   │   ├── EntryAccordion.jsx                # Lorebook entry accordion
│   │   ├── Portrait.jsx                      # Expression display
│   │   ├── ChatMessage.jsx                   # Message rendering
│   │   ├── ThinkingBlock.jsx                 # Collapsible LLM reasoning
│   │   ├── BootSequence.jsx                  # Boot animation
│   │   ├── ConversationList.jsx              # Sidebar conversation list
│   │   ├── ToastContainer.jsx                # Toast notification display
│   │   └── Scanlines.jsx                     # CRT scanline overlay
│   ├── hooks/
│   │   ├── useConversations.js               # Conversation CRUD state
│   │   ├── useEmotions.js                    # Emotion set fetching
│   │   ├── usePrompt.js                      # Prompt tree CRUD + save/destroy
│   │   ├── useProviders.js                   # Provider/model CRUD + active model state
│   │   └── useToast.js                       # Toast notification state
│   └── utils/
│       ├── api.js                            # API wrapper (fetch with auth)
│       ├── formatMessage.jsx                 # Text formatting (actions, thoughts, OOC)
│       └── parsers.js                        # Response parsing (emotion tags)
└── storage/app/public/                       # Expression images and user-uploaded images
```

## Features

### Implemented

- **Multi-assistant architecture** — each assistant has its own prompt, expression set, and opening message, all stored in the DB
- **Multi-theme support** — theme selection via Settings page, stored per-user in the DB
- **Dynamic expression system** — emotion images and videos served from the database, per assistant
- **Restricted emotion set** — alternate expressions unlocked based on context
- **Authentication** — Sanctum SPA auth with login flow
- **Image sending** — attach and send images for the assistant to analyze (stored on disk)
- **Thinking display** — collapsible view of the LLM's reasoning process
- **Text formatting** — actions in italics, inner thoughts in purple, OOC in bold cyan
- **Boot sequence** — animated startup with the assistant's opening message
- **Structured prompt system** — JSON-based assistant configuration, assembled on the backend
- **Visual prompt editor** — add, edit, rename, and delete prompt sections at any depth via the UI (`/prompt`)
- **DB-driven LLM provider management** — add/edit/delete providers and models via the UI; active model selected per-user
- **Multi-format LLM support** — OpenAI-compatible (`generic`) and Anthropic formats
- **Config fallback** — if no model is selected in the UI, the `.env` default is used
- **Conversation persistence** — messages stored in PostgreSQL
- **Conversation management UI** — list, create, delete, and rename conversations
- **Lorebook with RAG** — editable knowledge base with semantic retrieval injected into the system prompt
- **Toast notifications** — non-intrusive feedback for UI actions
- **Telegram integration** — long-poll bot for interacting with any configured assistant via Telegram

### Planned / Nice-to-Have

- Voice output (TTS integration)
- Voice input (Web Speech API)
- Local image generation (ComfyUI/Stable Diffusion)
- Full multi-assistant UI (assistant switcher, creation flow)

## Expression System

Emotions are stored in the database as `Emotion` records with associated `Image` and `Video` files on disk, scoped per assistant. Two sets exist:

- **Standard set** (`restricted = false`) — default expressions
- **Restricted set** (`restricted = true`) — alternate expressions, unlocked via the `unlocked` query param on `GET /api/emotions`

The LLM prefixes each response with an emotion tag (e.g. `[annoyed]`) which is parsed by the frontend and used to look up the matching expression asset.

Run `php artisan emotions:sync` to seed/update emotion records from config.

## License

Private. Not for distribution.
