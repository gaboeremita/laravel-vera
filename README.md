# VERA — Volatile Emotional Response Architecture

AI-powered conversational interface with dynamic visual expression system, built on Laravel, React, and Ollama.

## Overview

VERA is a local-first AI companion application featuring a cyberpunk CRT terminal aesthetic, dynamic character expressions that respond to conversational context, and a structured personality system driven by a configurable JSON prompt. The application runs entirely on your machine — all LLM inference happens locally through Ollama.

## Tech Stack

- **Backend:** Laravel 13 (PHP 8.5)
- **Frontend:** React (via Vite)
- **LLM:** Ollama (Gemma4 or any compatible model)
- **Database:** MySQL
- **Styling:** Tailwind CSS v4
- **Auth:** Laravel Sanctum (SPA mode)
- **Dev Environment:** Laravel Herd (macOS)

## Prerequisites

- PHP 8.5+
- Composer
- Node.js & npm
- MySQL
- [Ollama](https://ollama.ai) installed and running
- A pulled model (e.g. `ollama pull gemma4`)

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

# Ollama
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=gemma4:latest
```

### Ollama

Verify Ollama is running:

```bash
curl http://localhost:11434
# Should return: "Ollama is running"
```

Check the port if needed:

```bash
lsof -nP -iTCP -sTCP:LISTEN | grep ollama
```

### Prompt Configuration

VERA's personality, appearance, environment, and behavioral rules are defined in `vera_prompt.json` at the project root. This file is read by the prompt builder (`resources/js/utils/promptBuilder.js`) and assembled into the system prompt sent with each LLM request.

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
│   ├── Http/Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php        # Login/logout
│   │   └── Api/
│   │       └── ChatController.php        # LLM communication
│   └── Models/
│       ├── User.php
│       ├── Conversation.php
│       └── Message.php
├── config/
│   └── ai.php                            # Ollama URL and model config
├── database/migrations/
│   ├── create_conversations_table.php
│   └── create_messages_table.php
├── public/
│   └── images/vera/                      # Expression images
│       ├── neutral.jpeg
│       ├── happy.png
│       ├── angry.jpg
│       ├── annoyed.png
│       ├── sad.jpeg
│       ├── surprised.jpg
│       ├── flirty.png
│       ├── embarrassed.png
│       ├── confused.png
│       ├── content.png
│       ├── amused.png
│       └── sultry.jpg
├── resources/js/
│   ├── app.jsx                           # React entry point
│   ├── app.css                           # Custom CSS (CRT effects, animations)
│   ├── Vera.jsx                          # Main application component
│   ├── components/
│   │   ├── Portrait.jsx                  # Expression display with pixelated login state
│   │   ├── ChatMessage.jsx               # Message rendering with formatting
│   │   ├── ThinkingBlock.jsx             # Collapsible LLM thinking display
│   │   ├── BootSequence.jsx              # Terminal boot animation
│   │   └── Scanlines.jsx                 # CRT scanline overlay
│   └── utils/
│       ├── api.js                        # API wrapper (fetch with auth)
│       ├── promptBuilder.js              # Assembles system prompt from JSON
│       ├── formatMessage.jsx             # Text formatting (actions, thoughts, OOC)
│       └── parsers.js                    # Response parsing (emotion tags, metrics)
├── vera_prompt.json                      # Character configuration
└── vite.config.js
```

## Features

### Implemented

- **Terminal-style CRT interface** with scanlines, vignette, and monospace aesthetic
- **Dynamic expression system** — 12 character expressions that change based on LLM emotion tags
- **Authentication** — Sanctum SPA auth with terminal-style login flow and pixelated portrait lockscreen
- **Image sending** — attach and send images for VERA to analyze
- **Thinking display** — collapsible view of the LLM's reasoning process
- **Text formatting** — actions in italics, inner thoughts in purple, OOC in bold cyan
- **Boot sequence** — animated terminal startup with hardcoded opening message
- **Portrait video support** — short intro video before neutral expression
- **Admin mode (Westworld Protocol)** — diagnostic override with freeze/amnesia behavior
- **Creator mode** — password-protected creator recognition
- **OOC mode** — silent out-of-character direction to the LLM
- **Structured prompt system** — JSON-based character configuration
- **Backend LLM proxy** — Laravel controller handles Ollama calls with auth
- **Conversation persistence** — messages stored in MySQL

### Planned / Nice-to-Have

- Conversation management UI (list, switch, delete chats)
- Conversation history loaded from database on session resume
- Affection/trust/comfort/patience metrics system
- Expression gating based on relationship metrics
- Multiple character support with browser-based character creation
- Video loop expressions (replace static images with short animated clips)
- Voice output (TTS integration — ElevenLabs or local via Bark/Kokoro)
- Voice input (Web Speech API)
- Local image generation (ComfyUI/Stable Diffusion integration)
- Alternate outfit system (unlockable at relationship thresholds)
- NPC interaction system for The Bridge
- System prompt migration to backend

## Expression System

VERA has 12 emotional states, each mapped to a character image:

`neutral` · `happy` · `angry` · `annoyed` · `sad` · `surprised` · `flirty` · `embarrassed` · `confused` · `content` · `amused` · `sultry`

The LLM prefixes each response with an emotion tag (e.g. `[annoyed]`) which is parsed and stripped before display. The Portrait component swaps the displayed image based on the current emotion.

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