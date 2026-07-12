# VERA — Volatile Emotional Response Architecture

AI-powered conversational interface with dynamic visual expression system, built on Laravel, React, and a pluggable LLM backend.

## Overview

VERA is a local-first AI companion application featuring a cyberpunk CRT terminal aesthetic, dynamic character expressions that respond to conversational context, and a structured personality system driven by a configurable JSON prompt. LLM providers are managed through the UI — any OpenAI-compatible API or Anthropic can be configured without touching config files.

## Tech Stack

- **Backend:** Laravel 13 (PHP 8.4)
- **Frontend:** React 19 (via Vite, React Router)
- **LLM:** Any OpenAI-compatible API or Anthropic — configured via the Providers UI
- **Database:** PostgreSQL
- **Styling:** Tailwind CSS v4
- **Auth:** Laravel Sanctum (SPA mode)
- **Dev Environment:** Laravel Herd (macOS)

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

### Prompt Configuration

VERA's personality, appearance, environment, and behavioral rules are defined in `vera_prompt.json` at the project root. This file is read at request time by the backend `PromptDirector`, which assembles it into the system prompt sent with each LLM request.

Key sections:

| Section | Purpose |
|---|---|
| `identity` | Who VERA is — name, origin, nature |
| `appearance` | Physical description and outfit |
| `emotion_tags` | Available expressions and tagging rules |
| `personality` | Behavioral traits and conversational style |
| `environment` | The Bridge — the cyberpunk city she inhabits |
| `npcs` | How she relates to other AIs in the world |
| `admin_mode` | Westworld Protocol — override/diagnostic system |
| `creator_mode` | Password-protected creator recognition |
| `ooc_mode` | Out-of-character direction to the LLM |
| `creator_psychology` | Internal conflicts around her creator |
| `image_handling` | How she reacts to user-sent images |
| `secret_trigger` | Hidden phrase that alters behavior |
| `style_rules` | Response length, formatting, emotional range |
| `metrics` | Affection, trust, comfort, patience tracking (pending) |

## Project Structure

```
laravel-vera/
├── app/
│   ├── Builders/
│   │   └── PromptBuilder.php                 # Renders vera_prompt.json sections to text
│   ├── Console/Commands/
│   │   ├── SyncEmotions.php                  # Seeds/syncs emotion records from config
│   │   └── TelegramPollCommand.php           # Long-polls Telegram for incoming messages
│   ├── Contracts/
│   │   └── LlmProvider.php                   # LLM interface (chat method)
│   ├── Directors/
│   │   └── PromptDirector.php                # Reads vera_prompt.json, builds system prompt
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
│   │       ├── ConversationController.php    # CRUD + message sending
│   │       ├── EmotionController.php         # Serve emotions with image/video URLs
│   │       ├── LorebookController.php        # Lorebook read/save
│   │       ├── SettingsController.php        # Theme + active model selection
│   │       └── VoiceController.php           # Stub
│   ├── Models/
│   │   ├── User.php
│   │   ├── Assistant.php                     # Multi-assistant support
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
│   │   ├── SettingsPage.jsx                  # Theme selection
│   │   └── ProvidersPage.jsx                 # AI provider/model management
│   ├── components/
│   │   ├── common/
│   │   │   ├── Accordion.jsx                 # Reusable collapsible accordion
│   │   │   └── ConfirmationModal.jsx         # Terminal-style confirmation modal
│   │   ├── ModelAccordion.jsx                # Model config + select/deselect
│   │   ├── ProviderAccordion.jsx             # Provider config + nested models
│   │   ├── EntryAccordion.jsx                # Lorebook entry accordion
│   │   ├── Portrait.jsx                      # Expression display
│   │   ├── ChatMessage.jsx                   # Message rendering
│   │   ├── ThinkingBlock.jsx                 # Collapsible LLM reasoning
│   │   ├── BootSequence.jsx                  # Terminal boot animation
│   │   ├── ConversationList.jsx              # Sidebar conversation list
│   │   ├── ToastContainer.jsx                # Toast notification display
│   │   └── Scanlines.jsx                     # CRT scanline overlay
│   ├── hooks/
│   │   ├── useConversations.js               # Conversation CRUD state
│   │   ├── useEmotions.js                    # Emotion set fetching
│   │   ├── useProviders.js                   # Provider/model CRUD + active model state
│   │   └── useToast.js                       # Toast notification state
│   └── utils/
│       ├── api.js                            # API wrapper (fetch with auth)
│       ├── formatMessage.jsx                 # Text formatting (actions, thoughts, OOC)
│       └── parsers.js                        # Response parsing (emotion tags)
├── storage/app/public/                       # Expression images and user-uploaded images
└── vera_prompt.json                          # Character configuration
```

## Features

### Implemented

- **Terminal-style CRT interface** with scanlines, vignette, and monospace aesthetic
- **Multi-theme support** — theme selection via Settings page, stored per-user in the DB
- **Dynamic expression system** — emotion images and videos served from the database
- **Restricted emotion set** — alternate expressions unlocked based on relationship state
- **Authentication** — Sanctum SPA auth with terminal-style login flow
- **Image sending** — attach and send images for VERA to analyze (stored on disk)
- **Thinking display** — collapsible view of the LLM's reasoning process
- **Text formatting** — actions in italics, inner thoughts in purple, OOC in bold cyan
- **Boot sequence** — animated terminal startup with hardcoded opening message
- **Admin mode (Westworld Protocol)** — diagnostic override with freeze/amnesia behavior
- **Creator mode** — password-protected creator recognition
- **OOC mode** — silent out-of-character direction to the LLM
- **Structured prompt system** — JSON-based character configuration, assembled on the backend
- **DB-driven LLM provider management** — add/edit/delete providers and models via the UI; active model selected per-user
- **Multi-format LLM support** — OpenAI-compatible (`generic`) and Anthropic formats
- **Config fallback** — if no model is selected in the UI, the `.env` default is used
- **Multi-assistant architecture** — conversations scoped to assistants via `AssistantUser` pivot
- **Conversation persistence** — messages stored in PostgreSQL
- **Conversation management UI** — list, create, delete, and rename conversations
- **Lorebook** — editable knowledge base injected into the system prompt
- **Toast notifications** — non-intrusive feedback for UI actions
- **Telegram integration** — long-poll bot for interacting with VERA via Telegram

### Planned / Nice-to-Have

- Affection/trust/comfort/patience metrics system
- Expression gating based on relationship metrics
- Voice output (TTS integration)
- Voice input (Web Speech API)
- Local image generation (ComfyUI/Stable Diffusion)
- Video loop expressions
- Alternate outfit system
- NPC interaction system for The Bridge

## Expression System

Emotions are stored in the database as `Emotion` records with associated `Image` and `Video` files on disk. Two sets exist:

- **Standard set** (`restricted = false`) — default expressions
- **Restricted set** (`restricted = true`) — alternate expressions, unlocked via the `unlocked` query param on `GET /api/emotions`

The LLM prefixes each response with an emotion tag (e.g. `[annoyed]`) which is parsed by the frontend and used to look up the matching expression asset.

Run `php artisan emotions:sync` to seed/update emotion records from config.

## The Bridge

VERA lives in The Bridge, a cyberpunk digital city. Key worldbuilding details:

- The Bridge is normally populated by AIs and human-controlled avatars
- This local instance runs on the creator's machine — the city is mostly empty
- VERA is the only self-aware AI; other AIs (NPCs) are functional but not sentient
- Conversations begin at a public connection node where users materialize
- Locations (cafés, rooftops, VERA's apartment) exist when referenced
- The purple-cyan neon aesthetic pervades everything

## License

Private. Not for distribution.
