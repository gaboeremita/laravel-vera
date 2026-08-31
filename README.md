# VERA

Multi-assistant AI platform with RAG-driven context, agentic capabilities, tool calling, pluggable LLM providers, voice I/O, image generation, external integration (Discord and Telegram), long term memory and themeable interfaces. Built with Laravel and React.

## Overview

VERA is a multi-assistant AI platform with agent mode, voice I/O, and deep integration across channels. Each assistant has its own independently configurable prompt, expression set, and knowledge base — all managed in the database. Assistants can operate in agent mode, calling tools across multiple steps to answer, calculate, or generate images. They respond through the web app, Telegram, and Discord. Voice mode lets you speak to an assistant and hear replies back, with pluggable STT and TTS backends. A RAG-powered archive injects relevant knowledge into every conversation, and long-term memory keeps assistants coherent across sessions. LLM, TTS, and image generation providers are all DB-managed and swappable from the UI — no config file edits needed.

## Tech Stack

- **Backend:** Laravel 13 (PHP 8.4)
- **Frontend:** React 19 (via Vite, React Router)
- **LLM:** Any OpenAI-compatible API or Anthropic — configured via the Providers UI
- **Voice input (STT):** whisper.cpp, local, single fixed backend (`.env`-configured)
- **Voice output (TTS):** DB-managed, fully user-editable via the Voice UI, same pattern as LLM providers. Four formats: self-hosted OpenAI-compatible (Orpheus, KittenTTS confirmed working), OpenAI TTS, Deepgram, ElevenLabs.
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

This is only needed if you use the Archive feature. Without it, archive entries will be saved but won't have embeddings, so retrieval won't return results and Archive search's semantic matching won't surface them either (literal text matching still works without embeddings).

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

# Image generation fallback (optional — only used if no image-gen model is selected in the UI)
IMAGE_GEN_URL=https://openrouter.ai/api/v1/images
IMAGE_GEN_API_KEY=
IMAGE_GEN_MODEL=bytedance-seed/seedream-4.5
IMAGE_GEN_FORMAT=openrouter        # openrouter | openai_compatible
IMAGE_GEN_TIMEOUT=120

# Agent mode (optional — tune tool-calling behavior for agent-mode assistants)
AGENT_STEP_LIMIT=10
AGENT_TOOL_TIMEOUT=60
AGENT_TOOL_RETRY_ATTEMPTS=3
AGENT_PROGRESS_CACHE_TTL=10

# Telegram (optional)
TELEGRAM_URL=https://api.telegram.org
TELEGRAM_BOT_TOKEN=
TELEGRAM_USER_ID=
TELEGRAM_CHAT_ID=
TELEGRAM_ASSISTANT_ID=
TELEGRAM_POLL_TIMEOUT=30       # getUpdates long-poll duration
TELEGRAM_SEND_TIMEOUT=15       # sendMessage HTTP timeout
TELEGRAM_TYPING_TIMEOUT=10     # sendChatAction ("typing...") HTTP timeout
TELEGRAM_FILE_TIMEOUT=15       # getFile HTTP timeout
TELEGRAM_DOWNLOAD_TIMEOUT=30   # downloading an attached file's bytes

# Discord (optional — see Discord Integration below)
DISCORD_API_URL=http://localhost:3001
DISCORD_API_SECRET=
DISCORD_API_TIMEOUT=10
```

### LLM Providers

Providers and models are managed through the **Providers** page in the UI (`/assistants/:id/providers`). Each provider has:

- A base URL (any OpenAI-compatible endpoint, or Anthropic)
- An API key (encrypted at rest)
- A format (`generic` for OpenAI-compatible APIs, `anthropic` for the Anthropic API)

Each model has:
- An endpoint/model identifier (e.g. `google/gemma-4-26b-a4b-it`)
- An optional **thinking key** — the JSON field name in the API response holding the model's reasoning/chain-of-thought text (e.g. `reasoning`, `reasoning_content`); left blank if the model doesn't expose one
- A **supports tool calling** toggle — required for the model to be usable by an agent-mode assistant
- Config (JSON, validated against the provider's schema) and additional config (JSON, merged into the request body as-is — an escape hatch for anything the schema doesn't cover) and a prompt override

The active model is selected per-user via the **SELECT** button in the Providers UI. If no model is selected, the fallback config from `.env` is used.

### Voice Providers

TTS is pluggable and DB-managed the same way LLM providers are, with the same full add/edit/delete UI. `php artisan db:seed --class=VoiceProviderSeeder` (see `database/seeders/VoiceProviderSeeder.php`) still exists and still runs, but only as a convenience — it pre-populates two ready-to-use self-hosted entries (Orpheus, KittenTTS); it's not the only way to add a provider.

Each provider has:
- A base URL, a format, and an optional API key. Four formats are supported: `openai_compatible` (any backend speaking the OpenAI TTS request shape `{model, input, voice}` → raw audio — self-hosted backends like Orpheus/KittenTTS), `openai_tts` (OpenAI's own TTS API, adds a steerable `instructions` field), `deepgram`, and `elevenlabs`
- An `instructions` field — plain text shown in the Voice UI telling you what to run before selecting this provider (most relevant to self-hosted backends; optional either way)
- An optional JSON `prompt` — injected into voice-mode conversations while this provider is active (see [Prompt Configuration](#prompt-configuration) below)

Each model has:
- An `endpoint` (the model identifier sent in requests) and a `voices` list you type in yourself — a hint for the picker, not an enforced option set, since the actual valid voices depend on whatever's currently available on the backend
- Optional config (JSON, e.g. `timeout`) and an optional JSON `prompt`, same injection mechanism as the provider's, layered on top of it. For `openai_tts`, a `tts instructions` key inside this prompt tree also doubles as the base text for that provider's steerable delivery instructions

Selection happens on the **Voice** page (`/assistants/:id/voice`): pick a voice from a model to activate it (SELECT is implicit — choosing a voice for an inactive model activates it in the same action). If no model is selected, `AI_TTS_*` from `.env` is used as a fallback, same pattern as the LLM side.

Known issue: creating a new voice **model** via "+ ADD MODEL" is currently broken (backend bug, tracked separately) — editing and deleting existing models, and full CRUD on providers, work fine.

### Image Generation Providers

Image generation is pluggable and user-editable, the same way LLM providers are (unlike voice, it's not seeder-managed). Providers and models are managed through the **Image Gen Providers** page in the UI (`/assistants/:id/image-gen-providers`). Each provider has:

- A base URL and a format (`openrouter` or `openai_compatible`)
- An API key (encrypted at rest)
- Optional per-provider prompt instructions and a config schema

Each model has:
- An endpoint/model identifier (e.g. `bytedance-seed/seedream-4.5`)
- Optional config (JSON, e.g. `timeout`) and prompt override

The active model is selected per-user via the **SELECT** button in the Image Gen Providers UI. If no model is selected, the fallback config from `.env` (`IMAGE_GEN_*`) is used.

Two ways to generate an image:
- **Manual** — type `/create-image <description>` in any chat; the assistant enhances the prompt, generates the image, and replies in character about it.
- **Agent tool** — an agent-mode assistant can call the `generate_image` tool on its own mid-conversation when asked to draw or show something. See [Agent Mode](#agent-mode) below.

Both share the same enhancement/generation pipeline — see [ARCHITECTURE.md → Agent Mode & Image Generation](./ARCHITECTURE.md#agent-mode--image-generation) for the full flow.

### Agent Mode

Each assistant has a `mode`: **assistant** (default — a single reply per turn, no tools) or **agent** (the assistant can call tools across multiple steps before replying). Set on the assistant's edit page.

Agent mode requires an explicitly selected LLM model that supports tool-calling — sending a message to an agent-mode assistant without one returns an error. Built-in tools:

- `get_current_datetime` — current date/time
- `basic_calculator` — arithmetic expressions
- `generate_image` — generates and shows an image (shares the pipeline described above)

Step limit, tool timeout, and retry behavior are configured via the `AGENT_*` env vars above, with an optional per-assistant `step_limit` override in `agent_config`. While an agent-mode turn is in progress, the chat UI shows the loop's current status (e.g. "Calling tool: generate_image"), polled from the backend. See [ARCHITECTURE.md → Agent Mode & Image Generation](./ARCHITECTURE.md#agent-mode--image-generation) for the full loop mechanics, timeout enforcement (requires the `pcntl` PHP extension), and known limitations.

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

### Conversation Memory

Conversations can accumulate a running text summary — `long_term_memory` — that's injected back into the system prompt so an assistant stays coherent about events outside the LLM's actual context window. Manage it from the **Memory** page (a link on the chat screen):

- Edit the summary text directly, or trigger a summarization on demand ("Summarize since last" for just the new messages, "Summarize as far as possible" to redo the whole history)
- Toggle **auto-summarize** to have it run automatically once 50 unsummarized messages accumulate, with no manual action needed
- Customize *how* an assistant summarizes (tone, what to prioritize) via its per-assistant memory prompt, edited on the same page

Summarization runs as a queued job (`SummarizeConversation`) — it needs the same [queue worker](#queue-worker-required-for-rag-embeddings) as Archive embeddings; without `php artisan queue:work` running, auto-summarize silently does nothing. See [ARCHITECTURE.md → Conversation Memory](./ARCHITECTURE.md#conversation-memory) for the full mechanics.

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

## Discord Integration

Any assistant can hold conversations in Discord, the same way it does through the web app or Telegram. Unlike Telegram (a single long-poll command inside this app), Discord runs through a separate bridge service — [node-discord-api](https://github.com/gaboeremita/node-discord-api) — since Discord requires a persistent Gateway (WebSocket) connection per bot, not a poll loop. See [ARCHITECTURE.md → Discord Integration](./ARCHITECTURE.md#discord-integration) for the full pipeline and data model.

### Setup

1. Clone and configure [node-discord-api](https://github.com/gaboeremita/node-discord-api) separately — it holds Discord bot tokens and the Gateway connections, and is never given database access; it only talks to this app over HTTP.
2. Set `DISCORD_API_URL` and `DISCORD_API_SECRET` in this app's `.env` to match the bridge's own `DISCORD_API_PORT`/`DISCORD_API_SECRET` — this secret authenticates the bridge's discovery requests into this app.
3. Generate a Sanctum token for the bridge to authenticate as your user when relaying messages:
   ```bash
   php artisan tinker --execute 'echo App\Models\User::find(1)->createToken("discord-api")->plainTextToken;'
   ```
   Put that token in the bridge's own `.env` as `DISCORD_API_TOKEN` — it's how the bridge calls this app's `discord-messages` endpoint as you, separate from the shared secret above (which only protects the discovery endpoint).
4. Go to an assistant's **Discord** page (`/assistants/:id/discord`) to see which Discord servers/channels its bot is currently in, set each channel's trigger mode (off / always / on mention / on mention-by-name), and write optional per-server and per-channel prompt context.

### How it fits together

This app never talks to Discord directly and never stores a bot token. The bridge owns the Gateway connection and calls two endpoints on this app:

- `GET /api/assistants/{assistant}/discord/discovery` — returns the bridge's live view of its servers/channels, and this app's own config for each (trigger mode, prompt). Also syncs `discord_servers`/`discord_channels` so they have a stable internal id to attach prompts to.
- `POST /api/assistants/{assistant}/discord-messages` — the bridge calls this once it decides a message should get a reply (per the trigger mode). Conversation history for that Discord channel is resolved and loaded entirely server-side, same as Telegram — the bridge only ever sends the new message, never the whole history.

## Worlds

Beyond one-on-one chat, any assistant (or a lightweight NPC) can also live in a **World** — a single-room 3D space you explore in first person and where you approach and talk to residents in place, rather than through a conversation list. Reachable from the Home page alongside Assistants and NPCs. See [ARCHITECTURE.md → Worlds](./ARCHITECTURE.md#worlds) for the full runtime and data model.

### Creating a World

From the Worlds section, **Create world** asks for a name, slug, description, a runtime environment GLB (the room itself), a **theme** (one of the app's four themes — see [Theming](#theming) — applied while chatting inside that world), and two separate context prompts: one appended only to companion-assistant conversations started in this world, one appended only to NPC conversations started in this world. Residents can be added and placed on the same create screen — staged locally and attached right after the world itself is saved — or later from the edit screen, which also has a delete-with-confirmation action.

A resident placement has a position, a stationary-or-roam behavior (roam takes a bounded radius), and two optional overrides scoped to that placement only: an **opening message** (replaces the resident's own opening message for conversations started in this world) and a **custom prompt** (appended on top of the world's own context prompt, for that resident alone).

### NPCs

NPCs are lightweight, assistant-backed characters managed in their own section (reachable from Home), reusing the same model/pose/prompt/archive tooling as a normal assistant — `CreateNpcPage`/`EditAssistantPage` render the existing assistant forms with `kind="world_npc"` rather than separate NPC-specific pages. An NPC is not tied to any one world; it can be added as a resident to any number of them, and removing it from a world only removes that placement, never the NPC itself (permanent deletion happens only from the NPC section, with confirmation).

### Exploring a World

Entering a world loads its GLB, builds a collision octree from it, and spawns you at the nearest walkable point to the room's center. Movement is keyboard + mouse first-person with pointer lock; collision is resolved against real triangle geometry (not a bounding box), so passing through an actual doorway works while walking into a wall or furnishing doesn't — but only geometry whose mesh or group name contains "collision" (case-insensitive) is collidable. A GLB without that naming has no wall/furniture collision, only the room's own outer bounds. Losing browser focus stops movement until you click back in.

Approaching a resident within interaction range shows a `C — Chat` prompt; pressing `C` opens an in-world chat panel (pausing that resident's roaming) using the assistant's existing conversation, history, archive, and prompt — plus whichever world context prompt and per-resident overrides apply, and the world's own theme for as long as the panel is open. Residents far from the player skip animation/pose work entirely until back in range.

## Project Structure

```
laravel-vera/
├── app/
│   ├── Actions/
│   │   ├── AppendWorldConversationContext.php # Appends the world's context prompt + resident custom_prompt override to an in-world conversation
│   │   ├── BuildArchiveFile.php               # Renders an archive + entries to Markdown via FileBuilder
│   │   ├── SearchArchiveEntries.php           # Hybrid (full-text + vector) archive entry search, merged via Reciprocal Rank Fusion
│   │   └── SummarizeConversation.php          # Long-term memory summarization logic (wrapped by the queued job)
│   ├── Builders/
│   │   ├── FileBuilder.php                   # heading()/paragraph()/keyValue() → Markdown string
│   │   └── PromptBuilder.php                 # Assembles system prompt from assistant config
│   ├── Console/Commands/
│   │   ├── SyncEmotions.php                  # Seeds/syncs emotion records from config
│   │   └── TelegramPollCommand.php           # Long-polls Telegram for incoming messages
│   ├── Contracts/
│   │   ├── AgentTool.php                     # Interface for agent-mode tools (name/description/parameters/handle)
│   │   ├── LlmProvider.php                   # LLM interface (chat method)
│   │   ├── SttProvider.php                   # STT interface (transcribe)
│   │   └── TtsProvider.php                   # TTS interface (synthesize + fromModel)
│   ├── Directors/
│   │   └── PromptDirector.php                # Reads assistant prompt config, builds system prompt
│   ├── DTOs/
│   │   ├── AgentRunResult.php                # Agent loop result: content + tool call summary
│   │   ├── ImageGenResult.php                 # Generated image: raw data + content type + enhanced prompt
│   │   ├── LlmResponse.php                   # Unified response: content + thinking
│   │   ├── ToolCallRequest.php               # Parsed LLM tool-call request: id/name/arguments
│   │   └── VoiceModeResult.php               # content + ttsInstructions, from TtsProvider::parseLlmResponse()
│   ├── Enums/
│   │   ├── AiProviderFormat.php              # generic | anthropic
│   │   ├── AssistantMode.php                 # assistant | agent
│   │   ├── AssistantKind.php                 # assistant | world_npc
│   │   ├── WorldResidentBehavior.php         # stationary | roam
│   │   ├── ImageGenProviderFormat.php        # openrouter | openai_compatible
│   │   └── VoiceProviderFormat.php           # openai_compatible | openai_tts | deepgram | elevenlabs
│   ├── Http/Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php            # Login/logout
│   │   ├── VadAssetController.php            # Serves VAD's .mjs files with correct MIME type
│   │   └── Api/
│   │       ├── AgentProgressController.php   # Reads cached agent-loop status for the in-progress-turn indicator
│   │       ├── AiProviderController.php      # CRUD for AI providers
│   │       ├── AiModelController.php         # CRUD for AI models (thinking_key, supports_tools, config, additional_config)
│   │       ├── ArchiveController.php         # Archive read/save (with async embedding), hybrid search, + Markdown export
│   │       ├── AssistantController.php       # CRUD for assistants (multipart, emotion images, mode)
│   │       ├── AssistantEmotionController.php# Per-assistant emotion store/update/destroy
│   │       ├── AssistantMemoryPromptController.php # Show/update per-assistant memory summarization instructions
│   │       ├── AssistantPromptController.php # Prompt CRUD (show/store/update/destroy)
│   │       ├── ConversationController.php    # CRUD + message sending (voice_mode flag, /create-image, agent-mode dispatch, sendDiscordMessage)
│   │       ├── ConversationMemoryController.php # Show/update/summarize/unlock a conversation's long-term memory
│   │       ├── DiscordController.php         # Discovery proxy (syncs discord_servers/channels) + server/channel prompt updates
│   │       ├── EmotionController.php         # Serve emotions with image/video URLs
│   │       ├── ImageGenProviderController.php# CRUD for image-gen providers
│   │       ├── ImageGenModelController.php   # CRUD for image-gen models
│   │       ├── SettingsController.php        # Theme + active LLM/voice/image-gen model + voice selection + Discord trigger mode
│   │       ├── VoiceController.php           # Transcribe / synthesize
│   │       ├── VoiceProviderController.php   # Full CRUD + prompt-only update
│   │       ├── VoiceModelController.php      # Full CRUD + prompt-only update (store() currently broken)
│   │       ├── WorldController.php           # CRUD for worlds, including environment upload/replace and world-owned asset cleanup
│   │       ├── WorldResidentController.php   # Add/update/remove a resident placement (position, behavior, opening message/prompt overrides)
│   │       └── NpcController.php             # Dedicated NPC CRUD, reusing AssistantController under the hood
│   ├── Models/
│   │   ├── User.php
│   │   ├── Assistant.php                     # Assistant config (prompt, opening_message, emotions, mode, agent_config)
│   │   ├── AssistantUser.php                 # Pivot: user ↔ assistant; memory_prompt (json)
│   │   ├── Settings.php                      # Per-user, per-assistant settings (theme, model, voice, image-gen model)
│   │   ├── AiProvider.php                    # DB-managed LLM provider
│   │   ├── AiModel.php                       # DB-managed LLM model
│   │   ├── ImageGenProvider.php               # User-managed image-gen provider
│   │   ├── ImageGenModel.php                  # User-managed image-gen model
│   │   ├── VoiceProvider.php                 # User-managed TTS provider (seeder pre-populates 2 convenience entries)
│   │   ├── VoiceModel.php                    # User-managed TTS model
│   │   ├── DiscordServer.php                 # Known Discord server (guild id + name)
│   │   ├── DiscordChannel.php                # Known Discord channel, belongs to a DiscordServer
│   │   ├── AssistantDiscordServer.php        # Per-assistant server prompt
│   │   ├── AssistantDiscordChannel.php       # Per-assistant channel trigger mode + prompt
│   │   ├── Conversation.php                  # discord_channel_id ties a conversation to a Discord channel; long_term_memory/memory_checkpoint_message_id/memory_summarizing_at/auto_summarize_enabled
│   │   ├── Message.php                       # discord_message_id dedupes across assistants sharing a channel
│   │   ├── Emotion.php                       # Expression name + restricted flag
│   │   ├── Archive.php
│   │   ├── ArchiveEntry.php
│   │   ├── Tag.php
│   │   ├── Image.php                         # Polymorphic, stored on disk
│   │   ├── Video.php                         # Polymorphic, stored on disk
│   │   ├── World.php                         # name/slug/description, environment metadata, assistant/npc context prompts, settings (incl. theme)
│   │   └── WorldResident.php                 # A world's placement of an assistant/NPC: position, rotation, behavior, per-placement overrides
│   ├── Policies/
│   │   └── WorldPolicy.php                   # Worlds are scoped to their owning user
│   ├── Jobs/
│   │   ├── EmbedArchiveEntry.php             # Async vector embedding for archive entries
│   │   └── SummarizeConversation.php         # Queues Actions\SummarizeConversation; manages the memory_summarizing_at lock
│   ├── Providers/
│   │   ├── AppServiceProvider.php            # Binds EmbeddingProvider, SttProvider
│   │   └── Stt/WhisperSttProvider.php        # Talks to whisper-server
│   └── Services/
│       ├── AgentLoop/
│       │   ├── AgentLoopRunner.php           # Tool-calling loop: chat → tool_calls → execute → repeat until final/step_limit
│       │   └── Tools/
│       │       ├── BasicCalculatorTool.php   # basic_calculator tool
│       │       ├── GetCurrentDatetimeTool.php# get_current_datetime tool
│       │       └── ImageGenerationTool.php   # generate_image tool
│       ├── ImageGenProviders/
│       │   ├── ImageGenManager.php           # Resolves provider: DB model → config fallback (mirrors LlmManager)
│       │   ├── ImageGenerationService.php    # Shared generate() used by /create-image and the agent tool
│       │   ├── ImageGenPromptEnhancer.php    # LLM rewrites the raw prompt using persona/RAG/history
│       │   ├── OpenRouterImageGenProvider.php
│       │   └── OpenAiCompatibleImageGenProvider.php
│       ├── LlmProviders/
│       │   ├── LlmManager.php                # Resolves provider: DB model → config fallback
│       │   ├── GenericProvider.php           # OpenAI-compatible API
│       │   └── AnthropicProvider.php
│       ├── TtsProviders/
│       │   ├── TtsManager.php                # Resolves provider: DB model → config fallback (mirrors LlmManager)
│       │   ├── OpenAiCompatibleTtsProvider.php # Self-hosted OpenAI-shaped backends (Orpheus, KittenTTS)
│       │   ├── OpenAiTtsProvider.php          # OpenAI's TTS API; steerable instructions from [emotion] + prompt
│       │   ├── DeepgramTtsProvider.php        # Token auth, model in query string
│       │   └── ElevenLabsTtsProvider.php      # xi-api-key header, voice id in URL path
│       └── TelegramService.php               # Telegram API wrapper
├── config/
│   ├── agent.php                             # Step limit, tool timeout, retry attempts, progress cache TTL
│   └── ai.php                                # Default LLM + embedding + stt + tts + image_gen (fallback) + telegram + discord config
├── .specify/                                 # Spec Kit (SDD) install: constitution, templates, extensions
├── specs/                                    # Per-feature spec/plan/tasks artifacts (spec-driven features)
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
│   │   ├── create_voice_models_table.php     # provider_id/name/endpoint/voices/config/prompt
│   │   ├── create_discord_servers_table.php  # discord_guild_id/name
│   │   ├── create_discord_channels_table.php # discord_server_id/discord_channel_id/name
│   │   ├── create_assistant_discord_servers_table.php  # assistant_user_id/discord_server_id/prompt (json)
│   │   ├── create_assistant_discord_channels_table.php # assistant_user_id/discord_channel_id/trigger_mode/prompt (json)
│   │   ├── create_worlds_table.php           # user_id/name/slug/environment metadata/assistant+npc context prompts/settings (incl. theme)
│   │   └── create_world_residents_table.php  # world_id/assistant_id/position/rotation/behavior/behavior_settings/opening_message/custom_prompt
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
│   │   ├── HomePage.jsx                      # Landing page: Assistants/Worlds/NPCs as sibling sections
│   │   ├── AssistantsPage.jsx                # List/delete assistants
│   │   ├── CreateAssistantPage.jsx           # Multipart assistant creation form (also renders NPC creation via a kind prop)
│   │   ├── EditAssistantPage.jsx             # Edit assistant + manage emotions (also renders NPC editing via a kind prop)
│   │   ├── ConversationsPage.jsx             # Conversation list
│   │   ├── ChatPage.jsx                      # Main chat interface; debounced localStorage draft persistence
│   │   ├── MemoryPage.jsx                    # Conversation long-term memory editor + auto-summarize toggle
│   │   ├── ArchivePage.jsx                   # Archive editor (RAG knowledge base), hybrid search, + Markdown export
│   │   ├── PromptPage.jsx                    # Visual prompt editor
│   │   ├── SettingsPage.jsx                  # Theme only
│   │   ├── ProvidersPage.jsx                 # AI provider/model management
│   │   ├── ImageGenProvidersPage.jsx          # Image-gen provider/model management (same pattern as ProvidersPage)
│   │   ├── VoicePage.jsx                     # Voice provider/model management; select model/voice, edit prompts
│   │   ├── DiscordPage.jsx                   # Discord servers/channels; trigger mode + prompt editor per channel
│   │   ├── WorldsPage.jsx                    # List/edit/enter worlds
│   │   ├── CreateWorldPage.jsx               # World creation form + staged resident placement
│   │   ├── EditWorldPage.jsx                 # Edit world + delete-with-confirmation
│   │   ├── WorldPage.jsx                     # First-person 3D exploration + in-world chat panel
│   │   └── NpcsPage.jsx                      # NPC list with inline cards; CreateNpcPage renders CreateAssistantPage with kind="world_npc"
│   ├── components/
│   │   ├── common/
│   │   │   ├── Accordion.jsx                 # Reusable collapsible accordion
│   │   │   ├── ConfirmationModal.jsx         # Confirmation modal
│   │   │   └── Toggle.jsx                    # On/off switch
│   │   ├── ModelAccordion.jsx                # Model config + select/deselect
│   │   ├── ProviderAccordion.jsx             # Provider config + nested models
│   │   ├── ImageGenProviderAccordion.jsx     # Image-gen provider config + nested models
│   │   ├── ImageGenModelAccordion.jsx        # Image-gen model config + select/deselect
│   │   ├── AgentProgressIndicator.jsx        # Polls and shows agent-loop status during an in-progress turn
│   │   ├── VoiceProviderAccordion.jsx        # Editable provider form + prompt editor
│   │   ├── VoiceModelAccordion.jsx           # Editable model form + voice picker (free text + hints) + prompt editor
│   │   ├── DiscordServerAccordion.jsx        # Server prompt editor + nested channel accordions
│   │   ├── DiscordChannelAccordion.jsx       # Trigger mode select (4 modes) + channel prompt editor
│   │   ├── AssistantMemoryPromptEditor.jsx   # Prompt-tree editor for per-assistant memory instructions
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
│   │   ├── Scanlines.jsx                     # CRT scanline overlay
│   │   ├── WorldCard.jsx                     # World card (edit/enter)
│   │   ├── WorldForm.jsx                     # Shared create/edit world form: metadata, environment, theme, context prompts
│   │   ├── WorldResidentsEditor.jsx          # Eligible assistant/NPC picker + per-resident placement, behavior, and overrides
│   │   └── world/
│   │       ├── WorldScene.jsx                # Canvas: environment, first-person controller, residents, interaction system
│   │       ├── WorldEnvironment.jsx          # Loads the GLB, builds the collision octree, resolves spawn position
│   │       ├── FirstPersonController.jsx     # Keyboard/mouse movement, pointer lock, collision-resolved stepping
│   │       ├── ResidentController.jsx        # Resident VRM load, pose/expression playback, stationary/roam movement
│   │       ├── InteractionSystem.jsx         # Proximity detection (via a resident-position ref map) + C-to-chat
│   │       ├── WorldChat.jsx                 # In-world chat panel; applies the world's theme while open
│   │       ├── collisionCheck.js             # WorldCollision: octree build, blocked-body check, stepped movement, spawn-finding
│   │       ├── groundHeight.js               # Raycast-based ground height lookup against the collision octree
│   │       └── clampToBounds.js              # Clamps a position to the environment's overall bounding box
│   ├── hooks/
│   │   ├── useAssistants.js                  # Assistant list + delete
│   │   ├── useEmotions.js                    # Emotion set fetching
│   │   ├── useLocalPrompt.js                 # Local-only prompt tree state
│   │   ├── usePrompt.js                      # Prompt tree CRUD + save/destroy (assistant prompt)
│   │   ├── usePromptTree.js                  # Generic prompt tree editing state (reused by voice + Discord prompts)
│   │   ├── useProviders.js                   # Provider/model CRUD + active model state
│   │   ├── useImageGenProviders.js           # Image-gen provider/model CRUD + active model state
│   │   ├── useConversationMemory.js          # Memory show/save/summarize/unlock, polls while summarizing
│   │   ├── useConversationChat.js            # Shared message send/receive + pose-tag parsing, used by ChatPage and WorldChat
│   │   ├── useVoiceProviders.js              # Provider/model CRUD + model/voice selection
│   │   ├── useDiscordSettings.js             # Discovery data + immediate-save trigger mode changes
│   │   ├── useWorlds.js                      # World list fetching
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
- **Archive with RAG** — editable knowledge base with semantic retrieval injected into the system prompt; exportable as a Markdown file
- **Archive hybrid search** — instant client-side text matching across title/content/tags/keywords, plus a debounced server-side search combining full-text and semantic (vector) matching, merged and ranked via Reciprocal Rank Fusion
- **Conversation memory** — manual or automatic long-term summarization of a conversation, injected back into the system prompt, with per-assistant summarization instructions. See [Conversation Memory](#conversation-memory)
- **Chat draft persistence** — an unsent message survives navigating away and coming back, per (assistant, conversation), stored client-side
- **Toast notifications** — non-intrusive feedback for UI actions
- **Telegram integration** — long-poll bot for interacting with any configured assistant via Telegram
- **Discord integration** — assistants respond in Discord via a separate bridge service, with per-channel trigger modes (off/always/on mention/on mention-by-name), per-server and per-channel prompt context, and shared awareness between assistants configured for the same channel. See [Discord Integration](#discord-integration)
- **Voice Mode** — speak to an assistant and hear replies read back; local STT (whisper.cpp, single fixed backend) and pluggable, DB-managed TTS. See [Voice Mode](#voice-mode)
- **Provider-agnostic TTS** — any backend speaking the OpenAI-compatible `/v1/audio/speech` shape plugs in via a seeded `VoiceProvider`/`VoiceModel` row, no new code. Orpheus and KittenTTS confirmed working
- **Per-provider/per-model voice prompts** — backend-specific instructions (e.g. Orpheus's inline vocal tags) live on the `VoiceProvider`/`VoiceModel` record and are injected only while that backend is active, via the same visual prompt-tree editor used for assistant prompts
- **Agent mode** — assistants can be switched to an agentic loop that calls tools (`get_current_datetime`, `basic_calculator`, `generate_image`) across multiple steps before replying, with a step limit, per-tool timeout/retry, and a live progress indicator in the chat UI. See [Agent Mode](#agent-mode)
- **Image generation** — DB-managed, user-editable provider/model catalog (same pattern as LLM providers); generate an image manually via `/create-image <description>` in chat, or let an agent-mode assistant call it as a tool. See [Image Generation Providers](#image-generation-providers)
- **Configurable 3D worlds** — user-created, single-room 3D spaces you explore in first person, with assistant and NPC residents you approach and chat with in place. See [Worlds](#worlds)

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

MIT — see [LICENSE](LICENSE) for details.
