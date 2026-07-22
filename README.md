# VERA

A multi-assistant AI platform with a dynamic visual expression system, built on Laravel, React, and a pluggable LLM backend.

## Overview

VERA is a general-purpose multi-assistant AI platform. Each assistant has its own personality, expression set, and prompt — all configured in the database. LLM providers and models are managed through the UI, making it easy to add, switch, and configure providers without touching config files. The interface supports multiple themes, selectable per-user and persisted in the database.

## Tech Stack

- **Backend:** Laravel 13 (PHP 8.4)
- **Frontend:** React 19 (via Vite, React Router)
- **LLM:** Any OpenAI-compatible API or Anthropic — configured via the Providers UI
- **Voice input (STT):** whisper.cpp, local
- **Voice output (TTS):** Orpheus 3B, served locally via llama.cpp
- **Database:** PostgreSQL
- **Styling:** Tailwind CSS v4
- **Auth:** Laravel Sanctum (SPA mode)

## Prerequisites

- PHP 8.4+
- Composer
- Node.js & npm
- PostgreSQL
- An LLM API endpoint (OpenRouter, Anthropic, a local Ollama-compatible server, etc.)
- (Optional, for Voice Mode) `whisper-cpp` and `llama.cpp` — see [Voice Mode](#voice-mode) below

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

# Link public storage (required for emotion images and user uploads)
php artisan storage:link

# Create your user
php artisan tinker --execute 'User::create(["name" => "YourName", "email" => "you@email.com", "password" => bcrypt("yourpassword")]);'

# Start development
npm run dev
```

If using Laravel Herd, add the site through Herd's UI. Otherwise run `php artisan serve`.

### Queue Worker (required for RAG embeddings)

Archive entry embeddings are dispatched as async jobs. To process them, run the queue worker:

```bash
php artisan queue:work
```

This is only needed if you use the Archive feature. Without it, archive entries will be saved but won't have embeddings, so retrieval won't return results.

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

# Embedding provider (required for Archive RAG retrieval)
# Must be an OpenAI-compatible embeddings endpoint
AI_EMBEDDING_URL=https://openrouter.ai/api/v1
AI_EMBEDDING_MODEL=text-embedding-3-small

# Voice input (optional — required only for Voice Mode)
AI_STT_URL=http://localhost:8080
AI_STT_MODEL=medium
AI_STT_FORMAT=whisper
AI_STT_TIMEOUT=60

# Voice output (optional — required only for Voice Mode)
AI_TTS_URL=http://localhost:5005/v1/audio/speech
AI_TTS_MODEL=orpheus
AI_TTS_FORMAT=orpheus
AI_TTS_VOICE=tara
AI_TTS_TIMEOUT=120

# Telegram (optional)
TELEGRAM_URL=https://api.telegram.org
TELEGRAM_BOT_TOKEN=
TELEGRAM_USER_ID=
TELEGRAM_CHAT_ID=
TELEGRAM_ASSISTANT_ID=
```

### LLM Providers

Providers and models are managed through the **Providers** page in the UI (`/assistants/:id/providers`). Each provider has:

- A base URL (any OpenAI-compatible endpoint, or Anthropic)
- An API key (encrypted at rest)
- A format (`generic` for OpenAI-compatible APIs, `anthropic` for the Anthropic API)

Each model has:
- An endpoint/model identifier (e.g. `google/gemma-4-26b-a4b-it`)
- Optional thinking toggle and thinking budget
- Per-model config (JSON) and prompt override

The active model is selected per-user via the **SELECT** button in the Providers UI. If no model is selected, the fallback config from `.env` is used.

### Theming

The app supports multiple themes, selectable per-user via the Settings page (`/assistants/:id/settings`) and persisted in the database.

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

## Voice Mode

Speak to an assistant instead of typing, and hear replies read back. Fully optional — the app works the same without it if the backing services aren't running. See [ARCHITECTURE.md → Voice Mode](./ARCHITECTURE.md#voice-mode) for the full pipeline, diagrams, and design rationale; this section only covers getting it running.

### Setup

Voice mode needs three local services running alongside `php artisan serve` / Herd. None of these are managed by the app — they're plain background processes you start yourself.

```bash
# 1. STT — whisper.cpp
brew install whisper-cpp
mkdir -p ~/whisper-models
curl -L -o ~/whisper-models/ggml-medium.bin \
  https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-medium.bin
whisper-server -m ~/whisper-models/ggml-medium.bin --host 127.0.0.1 --port 8080

# 2. TTS inference — llama.cpp, serving the Orpheus 3B model
brew install llama.cpp
# Get an Orpheus GGUF (e.g. via `ollama pull legraphista/Orpheus` and reuse its blob,
# or download a GGUF directly) — then:
llama-server -m /path/to/orpheus.gguf --host 127.0.0.1 --port 8081 -c 8192

# 3. TTS wrapper — Orpheus-FastAPI (separate repo, not part of this codebase)
git clone https://github.com/Lex-au/Orpheus-FastAPI.git
cd Orpheus-FastAPI
python3.11 -m venv venv && source venv/bin/activate   # needs Python 3.8–3.11
pip3 install torch torchvision torchaudio             # Mac: plain pip install, not the CUDA build
pip3 install -r requirements.txt
mkdir -p outputs static
cp .env.example .env
# edit .env: ORPHEUS_API_URL=http://127.0.0.1:8081/v1/completions
python app.py   # serves on :5005
```

Then set `AI_STT_URL`/`AI_TTS_URL` in `laravel-vera/.env` to match (see [Environment Variables](#environment-variables) above) and select a voice per-assistant on the Settings page.

**Why llama.cpp and not Ollama for TTS**, even though Ollama is already a dependency for embeddings: Orpheus-FastAPI's completion prompt relies on special tokens to force the model into audio-token-generation mode, and Ollama's `/v1/completions` doesn't honor them reliably — it intermittently falls back to normal chat text instead of generating audio. `llama-server` handles it correctly and consistently. Full details in [ARCHITECTURE.md](./ARCHITECTURE.md#infrastructure-stack).

### Known limitation: latency

TTS replies currently take **5–15 seconds** to generate. Orpheus is a 3B-parameter model generating audio as thousands of discrete tokens, autoregressively, on a personal Mac rather than dedicated inference hardware — this is architectural, not a misconfiguration. There's no streaming support in the current pipeline. See [ARCHITECTURE.md → Known Limitations](./ARCHITECTURE.md#known-limitations-1) for the full breakdown and what would actually fix it.

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
│   │   ├── LlmProvider.php                   # LLM interface (chat method)
│   │   ├── SttProvider.php                   # STT interface (transcribe)
│   │   └── TtsProvider.php                   # TTS interface (synthesize)
│   ├── Directors/
│   │   └── PromptDirector.php                # Reads assistant prompt config, builds system prompt
│   ├── DTOs/
│   │   └── LlmResponse.php                   # Unified response: content + thinking
│   ├── Enums/
│   │   ├── AiProviderFormat.php              # generic | anthropic
│   │   └── TtsVoice.php                      # tara | leah | jess | leo | dan | mia | zac | zoe
│   ├── Http/Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php            # Login/logout
│   │   ├── VadAssetController.php            # Serves VAD's .mjs files with correct MIME type
│   │   └── Api/
│   │       ├── AiProviderController.php      # CRUD for AI providers
│   │       ├── AiModelController.php         # CRUD for AI models
│   │       ├── ArchiveController.php         # Archive read/save (with async embedding)
│   │       ├── AssistantController.php       # CRUD for assistants (multipart, emotion images)
│   │       ├── AssistantEmotionController.php# Per-assistant emotion store/update/destroy
│   │       ├── AssistantPromptController.php # Prompt CRUD (show/store/update/destroy)
│   │       ├── ConversationController.php    # CRUD + message sending (voice_mode flag)
│   │       ├── EmotionController.php         # Serve emotions with image/video URLs
│   │       ├── SettingsController.php        # Theme + active model + voice selection
│   │       └── VoiceController.php           # Transcribe / synthesize
│   ├── Models/
│   │   ├── User.php
│   │   ├── Assistant.php                     # Assistant config (prompt, opening_message, emotions)
│   │   ├── AssistantUser.php                 # Pivot: user ↔ assistant
│   │   ├── Settings.php                      # Per-user, per-assistant settings (theme, model, voice)
│   │   ├── AiProvider.php                    # DB-managed LLM provider
│   │   ├── AiModel.php                       # DB-managed LLM model
│   │   ├── Conversation.php
│   │   ├── Message.php
│   │   ├── Emotion.php                       # Expression name + restricted flag
│   │   ├── Archive.php
│   │   ├── ArchiveEntry.php
│   │   ├── Tag.php
│   │   ├── Image.php                         # Polymorphic, stored on disk
│   │   └── Video.php                         # Polymorphic, stored on disk
│   ├── Jobs/
│   │   └── EmbedArchiveEntry.php             # Async vector embedding for archive entries
│   ├── Providers/
│   │   ├── AppServiceProvider.php            # Binds EmbeddingProvider, SttProvider, TtsProvider
│   │   ├── Stt/WhisperSttProvider.php        # Talks to whisper-server
│   │   └── Tts/OrpheusTtsProvider.php        # Talks to Orpheus-FastAPI
│   └── Services/
│       ├── LlmProviders/
│       │   ├── LlmManager.php                # Resolves provider: DB model → config fallback
│       │   ├── GenericProvider.php           # OpenAI-compatible API
│       │   └── AnthropicProvider.php
│       └── TelegramService.php               # Telegram API wrapper
├── config/
│   └── ai.php                                # Default LLM + embedding + stt + tts + telegram config
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
│   │   ├── AuthenticatedLayout.jsx           # Auth guard + emotion state + boot sequence
│   │   └── AssistantLayout.jsx               # Assistant-scoped context (conversations, settings)
│   ├── pages/
│   │   ├── LoginPage.jsx
│   │   ├── AssistantsPage.jsx                # List/delete assistants
│   │   ├── CreateAssistantPage.jsx           # Multipart assistant creation form
│   │   ├── EditAssistantPage.jsx             # Edit assistant + manage emotions
│   │   ├── ConversationsPage.jsx             # Conversation list
│   │   ├── ChatPage.jsx                      # Main chat interface
│   │   ├── ArchivePage.jsx                   # Archive editor (RAG knowledge base)
│   │   ├── PromptPage.jsx                    # Visual prompt editor
│   │   ├── SettingsPage.jsx                  # Theme + voice selection
│   │   └── ProvidersPage.jsx                 # AI provider/model management
│   ├── components/
│   │   ├── common/
│   │   │   ├── Accordion.jsx                 # Reusable collapsible accordion
│   │   │   └── ConfirmationModal.jsx         # Confirmation modal
│   │   ├── ModelAccordion.jsx                # Model config + select/deselect
│   │   ├── ProviderAccordion.jsx             # Provider config + nested models
│   │   ├── EmotionGrid.jsx                   # Emotion image manager (add/rename/replace/delete)
│   │   ├── PromptEditor.jsx                  # Local prompt tree editor (create/edit flows)
│   │   ├── PromptNode.jsx                    # Recursive prompt tree node editor
│   │   ├── EntryAccordion.jsx                # Archive entry accordion
│   │   ├── Header.jsx                        # Navigation header
│   │   ├── Portrait.jsx                      # Expression display
│   │   ├── ChatMessage.jsx                   # Message rendering
│   │   ├── ThinkingBlock.jsx                 # Collapsible LLM reasoning
│   │   ├── BootSequence.jsx                  # Boot animation
│   │   ├── ConversationList.jsx              # Sidebar conversation list
│   │   ├── ToastContainer.jsx                # Toast notification display
│   │   └── Scanlines.jsx                     # CRT scanline overlay
│   ├── hooks/
│   │   ├── useAssistants.js                  # Assistant list + delete
│   │   ├── useEmotions.js                    # Emotion set fetching
│   │   ├── useLocalPrompt.js                 # Local-only prompt tree state
│   │   ├── usePrompt.js                      # Prompt tree CRUD + save/destroy
│   │   ├── useProviders.js                   # Provider/model CRUD + active model state
│   │   ├── useToast.js                       # Toast notification state
│   │   └── useVoiceMode.js                   # Mic capture + voice activity detection
│   └── utils/
│       ├── api.js                            # API wrapper (fetch with auth)
│       ├── formatMessage.jsx                 # Text formatting (actions, thoughts, OOC)
│       └── parsers.js                        # Response parsing (emotion tags, speech text cleanup)
├── resources/views/welcome.blade.php         # SPA shell; loads voice-mode's VAD bundle via <script>
├── public/vendor/vad/                        # Gitignored — VAD assets, regenerated from node_modules
├── storage/app/vad/                          # .mjs files, served with correct MIME type by Laravel
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
- **Archive with RAG** — editable knowledge base with semantic retrieval injected into the system prompt
- **Toast notifications** — non-intrusive feedback for UI actions
- **Telegram integration** — long-poll bot for interacting with any configured assistant via Telegram
- **Voice Mode** — speak to an assistant and hear replies read back; local STT (whisper.cpp) and TTS (Orpheus 3B via llama.cpp), voice-specific prompt handling, per-assistant voice selection. See [Voice Mode](#voice-mode). Known limitation: 5-15s TTS latency (no streaming yet)

### Planned / Nice-to-Have

- Voice mode latency reduction (streaming, faster local TTS, or cloud-hosted inference)
- Per-assistant voice settings beyond voice selection (speed, per-emotion tag mapping)
- Local image generation (ComfyUI/Stable Diffusion)

## Expression System

Emotions are stored in the database as `Emotion` records with associated `Image` and `Video` files on disk, scoped per assistant. Two sets exist:

- **Standard set** (`restricted = false`) — default expressions
- **Restricted set** (`restricted = true`) — alternate expressions, unlocked via the `unlocked` query param on `GET /api/assistants/{assistant}/emotions`

The LLM prefixes each response with an emotion tag (e.g. `[annoyed]`) which is parsed by the frontend and used to look up the matching expression asset.

Run `php artisan emotions:sync` to seed/update emotion records from config.

Emotions are now also manageable per-assistant directly through the UI on the Edit Assistant page (`/assistants/:id/edit`).

## License

Private. Not for distribution.
