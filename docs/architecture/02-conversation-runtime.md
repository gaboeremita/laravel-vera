# Conversation runtime

This page follows a normal browser turn first, then shows channel-specific and optional branches. The sequence distinguishes persisted messages from client-supplied history: browser chat submits its visible history, while Telegram and Discord rebuild history server-side.

## End-to-end browser conversation

```mermaid
sequenceDiagram
    autonumber
    actor User
    participant SPA as React SPA
    participant Auth as Sanctum and ownership
    participant VC as VoiceController
    participant CC as ConversationController
    participant DB as Database
    participant PD as PromptDirector
    participant EP as Embedding provider
    participant STT as Whisper STT
    participant PM as Provider manager
    participant AI as LLM provider
    participant Q as Queue
    participant TTS as TTS provider

    User->>SPA: Enter text, image, or recorded speech
    opt Microphone input
        SPA->>Auth: POST voice transcribe with audio
        Auth->>VC: Authorized assistant request
        VC->>STT: Multipart inference request
        STT-->>VC: Transcript
        VC-->>SPA: Transcript JSON
    end
    SPA->>Auth: POST conversation messages
    Auth->>CC: User and assistant membership resolved
    CC->>DB: Persist last user message and optional image
    CC->>DB: Load assistant, archive, settings, conversation memory
    CC->>PD: Base prompt plus world context when present
    PD->>PD: Exclude mode-inapplicable sections
    opt Linked archive and non-empty text
        PD->>EP: Embed current query
        EP-->>PD: Query vector
        PD->>DB: Top five vector-similar archive entries
    end
    PD->>DB: Read long-term memory
    PD-->>CC: Built system prompt
    CC->>PM: Resolve selected model or config fallback
    PM-->>CC: Normalized LLM provider
    alt Assistant mode
        CC->>AI: Chat with system prompt and submitted history
        AI-->>CC: Content, thinking, optional expression tag
    else Agent mode with tool-capable selected model
        CC->>AI: Chat with tool definitions
        loop Until final response, step limit, or failure limit
            AI-->>CC: Tool calls or final content
            CC->>CC: Execute tool with timeout and retries
            CC->>DB: Persist tool-call audit message
            CC->>AI: Tool result
        end
    end
    CC->>DB: Persist assistant message
    opt Auto-summary enabled and 50 pending messages
        CC->>DB: Acquire memory timestamp lock
        CC-->>Q: Dispatch summary job
    end
    opt Forced voice reply command
        CC->>TTS: Synthesize first 200 speech-cleaned characters
        TTS-->>CC: Audio bytes or degradable error
    end
    CC-->>SPA: Content, thinking, tools, pose or emotion, optional audio
    SPA-->>User: Render reply and play or animate optional media
```

Browser voice mode is a two-request pipeline: VAD creates a WAV blob, `VoiceController::transcribe` returns text, then the normal message endpoint receives `voice_mode: true`. The reply is normally synthesized by a separate `VoiceController::synthesize` call from the SPA; `/send-voice-message` is the exceptional server-synthesized branch.

## Message branch routing

```mermaid
flowchart TD
    incoming["Validated incoming turn"] --> persist["Persist user message and optional image"]
    persist --> imageCommand{"Starts with create-image command?"}
    imageCommand -->|"Yes"| enhance["LLM prompt enhancement with persona, RAG, memory, history"]
    enhance --> imageProvider["Resolve image provider and generate"]
    imageProvider --> reaction["LLM creates in-character reaction"]
    reaction --> saveImage["Persist image on assistant carrier message"]

    imageCommand -->|"No"| backgroundCommand{"Starts with change-background command?"}
    backgroundCommand -->|"Yes and avatar3d"| queueBackground["Queue two-image background generation"]
    queueBackground --> sceneReaction["LLM creates immediate scene-change reaction"]

    backgroundCommand -->|"No"| mode{"Assistant mode?"}
    mode -->|"Standard"| singleChat["Single provider chat call"]
    mode -->|"Agent"| eligibility{"Selected model supports tools?"}
    eligibility -->|"No"| reject["Return 422"]
    eligibility -->|"Yes"| loop["Agent loop with calculator, datetime, optional image tool"]
    singleChat --> persistReply["Persist reply"]
    loop --> persistReply
    sceneReaction --> persistReply
    saveImage --> response["Channel-specific response"]
    persistReply --> response

    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    classDef async fill:#fef3c7,stroke:#d97706,color:#451a03
    classDef danger fill:#ffe4e6,stroke:#e11d48,color:#4c0519
    class incoming,persist,enhance,imageProvider,reaction,saveImage,sceneReaction,singleChat,loop,persistReply,response core
    class queueBackground async
    class reject danger
```

## Channel adapter differences

```mermaid
flowchart LR
    web["Web SPA - session and CSRF"] --> webHistory["Client sends visible history"] --> core["Prompt, provider, persistence core"]
    telegram["Telegram Bot API"] --> poller["Artisan long poller - configured user and assistant"] --> telegramHistory["Load one active DB conversation"] --> core
    discord["Discord Gateway"] --> bridge["External node-discord-api - trigger decision and bot token"] --> internalApi["Sanctum-protected internal POST"] --> discordHistory["Merge own and sibling-assistant channel history - deduplicate Discord message IDs"] --> core
    core --> webDelivery["JSON to SPA"]
    core --> telegramDelivery["Telegram sendMessage - Markdown then plain-text retry"]
    core --> discordDelivery["JSON to bridge - optional base64 voice and image URL"]

    classDef client fill:#dbeafe,stroke:#2563eb,color:#172554
    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    classDef external fill:#ffe4e6,stroke:#e11d48,color:#4c0519
    class web,webHistory,webDelivery client
    class poller,telegramHistory,internalApi,discordHistory,core core
    class telegram,discord,bridge,telegramDelivery,discordDelivery external
```

Discord server/channel prompts and sibling assistant names are injected by `PromptDirector::withDiscordEnvironment`. Telegram does RAG but currently does not inject long-term memory and does not use the browser/Discord auto-summary checkpoint.

---

[Previous](01-system-context.md) · [Index](README.md) · [Next: Data model](03-data-model.md)
