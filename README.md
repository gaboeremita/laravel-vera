# VERA

A multi-assistant AI platform with a dynamic visual expression system, built on Laravel, React, and a pluggable LLM backend.

## Overview

VERA is a general-purpose multi-assistant AI platform. Each assistant has its own personality, expression set, and prompt — all configured in the database. LLM providers and models are managed through the UI, making it easy to add, switch, and configure providers without touching config files. The interface supports multiple themes, selectable per-user and persisted in the database.

## Tech Stack

- **Backend:** Laravel 13 (PHP 8.4)
- **Frontend:** React 19 (via Vite, React Router)
- **LLM:** Any OpenAI-compatible API or Anthropic — configured via the Providers UI
- **Voice input (STT):** whisper.cpp, local, single fixed backend (`.env`-configured)
- **Voice output (TTS):** Any OpenAI-compatible `/v1/audio/speech` backend — DB-managed via the Voice UI, same pattern as LLM providers. Orpheus and KittenTTS confirmed working.
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

# Seed the voice provider catalog (optional — only needed for Voice Mode)
php artisan db:seed --class=VoiceProviderSeeder

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

# Voice input (optional — required only for Voice Mode; single fixed backend, not DB-managed)
AI_STT_URL=http://localhost:8080
AI_STT_MODEL=medium
AI_STT_FORMAT=whisper
AI_STT_TIMEOUT=60

# Voice output fallback (optional — only used if no voice model is selected in the Voice UI)
AI_TTS_URL=http://localhost:5005/v1/audio/speech
AI_TTS_API_KEY=
AI_TTS_MODEL=orpheus
AI_TTS_FORMAT=openai_compatible
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

### Voice Providers

TTS is pluggable the same way LLM providers are — but unlike LLM providers, the catalog is **seeded, not user-editable**. There's no add/edit/delete UI for `VoiceProvider`/`VoiceModel` rows; they're populated by `php artisan db:seed --class=VoiceProviderSeeder` (see `database/seeders/VoiceProviderSeeder.php`), which you edit and re-run to add or update entries.

Each provider has:
- A base URL, format (currently only `openai_compatible` — any backend speaking the OpenAI TTS request shape: `{model, input, voice}` → raw audio), and optional API key
- An `instructions` field — plain text shown in the Voice UI telling you what to run before selecting this provider (e.g. which local processes need to be started)
- An optional JSON `prompt` — injected into voice-mode conversations while this provider is active (see [Prompt Configuration](#prompt-configuration) below)

Each model has:
- An `endpoint` (the model identifier sent in requests) and a `voices` list — a hint, not an enforced option set, since the actual valid voices depend on whatever's currently loaded on the backing server
- An optional JSON `prompt`, same injection mechanism as the provider's, layered on top of it

Selection happens on the **Voice** page (`/assistants/:id/voice`): pick a voice from a model to activate it (SELECT is implicit — choosing a voice for an inactive model activates it in the same action). If no model is selected, `AI_TTS_*` from `.env` is used as a fallback, same pattern as the LLM side.

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

**Voice provider/model prompts** work the same way but live outside the assistant's own prompt: when voice mode is active, `voice provider prompt` and `voice model prompt` are appended as their own sections, sourced from the active `VoiceProvider`/`VoiceModel`'s `prompt` field (edited on the Voice page, not the Prompt page). This is how backend-specific instructions — e.g. Orpheus's inline `<laugh>`/`<chuckle>`/etc. vocal tags — stay out of the assistant's own prompt entirely, so switching to a provider without that capability (like KittenTTS) doesn't leave behind instructions for tags it can't produce.

## Voice Mode

Speak to an assistant instead of typing, and hear replies read back. Fully optional — the app works the same without it if the backing services aren't running. See [ARCHITECTURE.md → Voice Mode](./ARCHITECTURE.md#voice-mode) for the full pipeline, diagrams, and design rationale; this section only covers getting it running.

### Setup

Voice mode needs two things answering over HTTP: an STT endpoint and one or more TTS endpoints. None of this is managed by the app, the queue, or Herd — they're external services you run and point the app at.

STT is a single fixed backend, pointed at via `AI_STT_URL` in `.env` — the app only depends on `WhisperSttProvider` talking to it. TTS is pluggable and DB-managed (see [Voice Providers](#voice-providers) above): each backend gets a `VoiceProvider` row via `VoiceProviderSeeder`, and any backend speaking the OpenAI-compatible `/v1/audio/speech` shape works with zero new PHP code — only a new seed entry.

Two backends are confirmed working and seeded by default: **Orpheus** (3B, expressive, includes inline vocal tags) and **KittenTTS** (much smaller, CPU-only, no GPU/llama.cpp needed, no vocal tags). Orpheus specifically needs to run behind **llama.cpp**, not Ollama — Ollama's `/v1/completions` doesn't reliably honor the special tokens Orpheus-FastAPI's prompt format depends on to stay in "generate audio" mode (see [ARCHITECTURE.md](./ARCHITECTURE.md#infrastructure-stack) for details). That part is a real requirement, not a preference.

**Example setup: Orpheus (macOS, via Homebrew)** — substitute your own package manager / process manager on other platforms:

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
pip3 install torch torchvision torchaudio              # non-CUDA build on macOS
pip3 install -r requirements.txt
mkdir -p outputs static
cp .env.example .env
# edit .env: ORPHEUS_API_URL=http://127.0.0.1:8081/v1/completions
python app.py   # serves on :5005
```

Set `AI_STT_URL` in `laravel-vera/.env` to match the whisper-server URL. Orpheus's `VoiceProvider` row is already seeded pointing at `http://127.0.0.1:5005/v1/audio/speech` (`VoiceProviderSeeder`) — no `.env` change needed for TTS itself. Then go to the **Voice** page (`/assistants/:id/voice`), expand Orpheus, and pick a voice to activate it.

**Why llama.cpp and not Ollama for TTS**, even though Ollama is already a dependency for embeddings: Orpheus-FastAPI's completion prompt relies on special tokens to force the model into audio-token-generation mode, and Ollama's `/v1/completions` doesn't honor them reliably — it intermittently falls back to normal chat text instead of generating audio. `llama-server` handles it correctly and consistently. Full details in [ARCHITECTURE.md](./ARCHITECTURE.md#infrastructure-stack).

**Example setup: KittenTTS (macOS, via Homebrew)** — much lighter, CPU-only, no `llama.cpp` involved:

```bash
brew install espeak-ng
git clone https://github.com/devnen/Kitten-TTS-Server.git
cd Kitten-TTS-Server
python3.12 -m venv venv && source venv/bin/activate   # needs Python 3.10–3.12; 3.9 and 3.13 both fail dependency resolution
pip install -r requirements.txt
python server.py   # serves on :8005, downloads the default model on first run
```

KittenTTS's `VoiceProvider` row is already seeded pointing at `http://127.0.0.1:8005/v1/audio/speech`. To switch which underlying model size is loaded (Nano/Micro/Mini), use the wrapper's own web UI at `http://127.0.0.1:8005` — it's a hot-swap-and-restart flow on their end, not something this app controls (see [ARCHITECTURE.md](./ARCHITECTURE.md#backend-provider-contracts) for why).

### Known limitation: Orpheus latency

This is specific to Orpheus, not TTS in general — KittenTTS runs in well under a second on CPU. Orpheus replies currently take **5–15 seconds** to generate: it's a 3B-parameter model generating audio as thousands of discrete tokens, autoregressively, on consumer-grade hardware rather than dedicated inference hardware — this is architectural, not a misconfiguration. There's no streaming support in the current pipeline. See [ARCHITECTURE.md → Known Limitations](./ARCHITECTURE.md#known-limitations-1) for the full breakdown and what would actually fix it.

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
│   │   └── TtsProvider.php                   # TTS interface (synthesize + fromModel)
│   ├── Directors/
│   │   └── PromptDirector.php                # Reads assistant prompt config, builds system prompt
│   ├── DTOs/
│   │   └── LlmResponse.php                   # Unified response: content + thinking
│   ├── Enums/
│   │   ├── AiProviderFormat.php              # generic | anthropic
│   │   └── VoiceProviderFormat.php           # openai_compatible → provider class
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
│   │       ├── ConversationController.php    # CRUD + message sending (voice_mode flag, voice prompt injection)
│   │       ├── EmotionController.php         # Serve emotions with image/video URLs
│   │       ├── SettingsController.php        # Theme + active LLM/voice model + voice selection
│   │       ├── VoiceController.php           # Transcribe / synthesize
│   │       ├── VoiceProviderController.php   # Read-only catalog + prompt-only update
│   │       └── VoiceModelController.php      # Prompt-only update
│   ├── Models/
│   │   ├── User.php
│   │   ├── Assistant.php                     # Assistant config (prompt, opening_message, emotions)
│   │   ├── AssistantUser.php                 # Pivot: user ↔ assistant
│   │   ├── Settings.php                      # Per-user, per-assistant settings (theme, model, voice)
│   │   ├── AiProvider.php                    # DB-managed LLM provider
│   │   ├── AiModel.php                       # DB-managed LLM model
│   │   ├── VoiceProvider.php                 # Seeded TTS provider catalog entry
│   │   ├── VoiceModel.php                    # Seeded TTS model catalog entry
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
│   │   ├── AppServiceProvider.php            # Binds EmbeddingProvider, SttProvider
│   │   └── Stt/WhisperSttProvider.php        # Talks to whisper-server
│   └── Services/
│       ├── LlmProviders/
│       │   ├── LlmManager.php                # Resolves provider: DB model → config fallback
│       │   ├── GenericProvider.php           # OpenAI-compatible API
│       │   └── AnthropicProvider.php
│       ├── TtsProviders/
│       │   ├── TtsManager.php                # Resolves provider: DB model → config fallback (mirrors LlmManager)
│       │   └── OpenAiCompatibleTtsProvider.php # Talks to any OpenAI-compatible /v1/audio/speech backend
│       └── TelegramService.php               # Telegram API wrapper
├── config/
│   └── ai.php                                # Default LLM + embedding + stt + tts (fallback) + telegram config
├── database/
│   ├── migrations/
│   │   ├── create_conversations_table.php
│   │   ├── create_messages_table.php
│   │   ├── create_images_table.php
│   │   ├── create_emotions_table.php
│   │   ├── create_videos_table.php
│   │   ├── create_ai_providers_table.php
│   │   ├── create_ai_models_table.php
│   │   ├── create_settings_table.php
│   │   ├── create_voice_providers_table.php  # name/url/api_key/format/instructions/prompt
│   │   └── create_voice_models_table.php     # provider_id/name/endpoint/voices/config/prompt
│   └── seeders/
│       └── VoiceProviderSeeder.php           # Seeds the TTS catalog (Orpheus, KittenTTS) — re-run to add more
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
│   │   ├── SettingsPage.jsx                  # Theme only
│   │   ├── ProvidersPage.jsx                 # AI provider/model management
│   │   └── VoicePage.jsx                     # Read-only voice catalog; select model/voice, edit prompts
│   ├── components/
│   │   ├── common/
│   │   │   ├── Accordion.jsx                 # Reusable collapsible accordion
│   │   │   └── ConfirmationModal.jsx         # Confirmation modal
│   │   ├── ModelAccordion.jsx                # Model config + select/deselect
│   │   ├── ProviderAccordion.jsx             # Provider config + nested models
│   │   ├── VoiceProviderAccordion.jsx        # Read-only provider info + prompt editor
│   │   ├── VoiceModelAccordion.jsx           # Voice picker (free text + hints) + prompt editor
│   │   ├── PromptTreeEditor.jsx              # Manual/Paste-JSON toggle around a usePromptTree instance
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
│   │   ├── usePrompt.js                      # Prompt tree CRUD + save/destroy (assistant prompt)
│   │   ├── usePromptTree.js                  # Generic prompt tree editing state (reused by voice prompts)
│   │   ├── useProviders.js                   # Provider/model CRUD + active model state
│   │   ├── useVoiceProviders.js              # Read-only catalog + model/voice selection
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
- **Voice Mode** — speak to an assistant and hear replies read back; local STT (whisper.cpp, single fixed backend) and pluggable, DB-managed TTS. See [Voice Mode](#voice-mode)
- **Provider-agnostic TTS** — any backend speaking the OpenAI-compatible `/v1/audio/speech` shape plugs in via a seeded `VoiceProvider`/`VoiceModel` row, no new code. Orpheus and KittenTTS confirmed working
- **Per-provider/per-model voice prompts** — backend-specific instructions (e.g. Orpheus's inline vocal tags) live on the `VoiceProvider`/`VoiceModel` record and are injected only while that backend is active, via the same visual prompt-tree editor used for assistant prompts

### Planned / Nice-to-Have

- Voice mode latency reduction for Orpheus specifically (streaming, faster local TTS, or cloud-hosted inference)
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
