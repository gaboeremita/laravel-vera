# VERA — Volatile Emotional Response Architecture

AI-powered conversational interface with dynamic visual expression system, built on Laravel, React, and a pluggable LLM backend.

## Overview

VERA is a local-first AI companion application featuring a cyberpunk CRT terminal aesthetic, dynamic character expressions that respond to conversational context, and a structured personality system driven by a configurable JSON prompt. The application runs entirely on your machine — all LLM inference happens locally through Ollama, or via OpenRouter/Anthropic if configured.

## Tech Stack

- **Backend:** Laravel 13 (PHP 8.4)
- **Frontend:** React (via Vite)
- **LLM:** Ollama (local), OpenRouter, or Anthropic (configurable)
- **Database:** MySQL
- **Styling:** Tailwind CSS v4
- **Auth:** Laravel Sanctum (SPA mode)
- **Dev Environment:** Laravel Herd (macOS)

## Prerequisites

- PHP 8.4+
- Composer
- Node.js & npm
- MySQL
- [Ollama](https://ollama.ai) installed and running (or API keys for OpenRouter/Anthropic)
- A pulled model (e.g. `ollama pull gemma3`)

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
php artisan tinker
# > User::create(['name' => 'YourName', 'email' => 'you@email.com', 'password' => bcrypt('yourpassword')]);

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
DB_CONNECTION=mysql
DB_DATABASE=laravel_vera

# Sanctum
SANCTUM_STATEFUL_DOMAINS=laravel-vera.test

# LLM Provider (ollama | openrouter | anthropic)
AI_DEFAULT_PROVIDER=ollama

# Ollama
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=gemma3:latest

# OpenRouter (optional)
OPENROUTER_KEY=
OPENROUTER_MODEL=

# Anthropic (optional)
ANTHROPIC_KEY=
ANTHROPIC_MODEL=

# Telegram (optional)
TELEGRAM_URL=https://api.telegram.org
TELEGRAM_TOKEN=
```

### Ollama

Verify Ollama is running:

```bash
curl http://localhost:11434
# Should return: "Ollama is running"
```

### Prompt Configuration

VERA's personality, appearance, environment, and behavioral rules are defined in `vera_prompt.json` at the project root. This file is read at request time by the backend `PromptDirector`, which assembles it into the system prompt sent with each LLM request.

Key sections in the prompt config:

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
| `metrics` | Affection, trust, comfort, patience tracking (pending implementation) |

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
│   ├── Http/Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php            # Login/logout
│   │   └── Api/
│   │       ├── ConversationController.php    # CRUD + message sending
│   │       ├── EmotionController.php         # Serve emotions with image/video URLs
│   │       └── VoiceController.php           # Voice (stub)
│   ├── Models/
│   │   ├── User.php
│   │   ├── Conversation.php
│   │   ├── Message.php
│   │   ├── Emotion.php                       # Expression name + restricted flag
│   │   ├── Image.php                         # Polymorphic, stored on disk
│   │   └── Video.php                         # Polymorphic, stored on disk
│   ├── Providers/
│   │   └── AppServiceProvider.php            # Binds LlmProvider via LlmManager
│   └── Services/
│       ├── LlmProviders/
│       │   ├── LlmManager.php                # Resolves provider from config
│       │   ├── OllamaProvider.php
│       │   ├── OpenRouterProvider.php
│       │   └── AnthropicProvider.php
│       └── TelegramService.php               # Telegram API wrapper
├── config/
│   └── ai.php                                # LLM provider config (default, providers, defaults)
├── database/migrations/
│   ├── create_conversations_table.php
│   ├── create_messages_table.php
│   ├── create_images_table.php
│   ├── create_emotions_table.php
│   └── create_videos_table.php
├── resources/js/
│   ├── app.jsx                               # React entry point
│   ├── app.css                               # Custom CSS (CRT effects, animations)
│   ├── Vera.jsx                              # Main application component
│   ├── components/
│   │   ├── Portrait.jsx                      # Expression display with pixelated login state
│   │   ├── ChatMessage.jsx                   # Message rendering with formatting
│   │   ├── ThinkingBlock.jsx                 # Collapsible LLM thinking display
│   │   ├── BootSequence.jsx                  # Terminal boot animation
│   │   ├── ConversationList.jsx              # Sidebar list of conversations
│   │   ├── TerminalModal.jsx                 # Reusable modal in terminal style
│   │   ├── ToastContainer.jsx                # Toast notification display
│   │   └── Scanlines.jsx                     # CRT scanline overlay
│   ├── hooks/
│   │   ├── useConversations.js               # Conversation CRUD state
│   │   ├── useEmotions.js                    # Emotion set fetching (locked/unlocked)
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
- **Dynamic expression system** — emotion images and videos served from the database
- **Restricted emotion set** — alternate expressions unlocked based on relationship state
- **Authentication** — Sanctum SPA auth with terminal-style login flow and pixelated portrait lockscreen
- **Image sending** — attach and send images for VERA to analyze (stored on disk, not in DB)
- **Thinking display** — collapsible view of the LLM's reasoning process
- **Text formatting** — actions in italics, inner thoughts in purple, OOC in bold cyan
- **Boot sequence** — animated terminal startup with hardcoded opening message
- **Portrait video support** — short intro video before neutral expression
- **Admin mode (Westworld Protocol)** — diagnostic override with freeze/amnesia behavior
- **Creator mode** — password-protected creator recognition
- **OOC mode** — silent out-of-character direction to the LLM
- **Structured prompt system** — JSON-based character configuration, assembled on the backend
- **Multiple LLM providers** — Ollama, OpenRouter, Anthropic; switchable via config
- **Conversation persistence** — messages stored in MySQL
- **Conversation management UI** — list, create, and delete conversations
- **Conversation history loading** — prior messages fetched from DB on conversation select
- **Toast notifications** — non-intrusive feedback for UI actions
- **Telegram integration** — long-poll bot for interacting with VERA via Telegram

### Planned / Nice-to-Have

- Affection/trust/comfort/patience metrics system
- Expression gating based on relationship metrics
- Voice output (TTS integration — ElevenLabs or local via Bark/Kokoro)
- Voice input (Web Speech API)
- Local image generation (ComfyUI/Stable Diffusion integration)
- Multiple character support with browser-based character creation
- Video loop expressions (replace static images with short animated clips)
- Alternate outfit system (unlockable at relationship thresholds)
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
