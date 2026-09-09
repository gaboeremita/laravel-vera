# Deployment and operations

The repository does not contain Docker, Kubernetes, systemd, Horizon, or a production process manifest. This topology shows the runtime roles required by code and config; the exact hosts and supervisors remain deployment choices.

## Runtime topology

```mermaid
flowchart TB
    browser["User browser - built Vite assets, session cookie, WebGL, microphone"]
    web["Web ingress and PHP runtime - Laravel HTTP application"]
    queueWorker["Laravel queue worker"]
    telegramWorker["Long-running Artisan process - services:telegram"]
    scheduler["Scheduler - no application schedules registered"]
    discordBridge["External node-discord-api process"]

    db[("SQL database - app data, sessions, cache, queue, failed jobs")]
    publicStorage[("Public filesystem - VRM, GLB, images, tracks, generated scenes")]
    privateStorage[("Private filesystem - temporary archive exports")]
    secrets["Environment and encrypted provider keys"]

    llm["LLM services"]
    ollama["Ollama embeddings"]
    whisper["whisper.cpp STT"]
    tts["TTS services"]
    imageGen["Image generation services"]
    telegram["Telegram Bot API"]
    discord["Discord Gateway and API"]

    browser <--> web
    web <--> db
    web <--> publicStorage
    web <--> privateStorage
    web --> llm
    web --> ollama
    web --> whisper
    web --> tts
    web --> imageGen
    secrets --> web

    db --> queueWorker
    queueWorker --> db
    queueWorker --> ollama
    queueWorker --> llm
    queueWorker --> imageGen
    queueWorker --> publicStorage
    secrets --> queueWorker

    telegramWorker <--> telegram
    telegramWorker --> db
    telegramWorker --> llm
    secrets --> telegramWorker

    discord <--> discordBridge
    discordBridge <--> web
    secrets --> discordBridge
    scheduler -. "Currently no scheduled VERA tasks" .-> web

    classDef client fill:#dbeafe,stroke:#2563eb,color:#172554
    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    classDef async fill:#fef3c7,stroke:#d97706,color:#451a03
    classDef store fill:#dcfce7,stroke:#16a34a,color:#052e16
    classDef external fill:#ffe4e6,stroke:#e11d48,color:#4c0519
    class browser client
    class web,secrets core
    class queueWorker,telegramWorker,scheduler async
    class db,publicStorage,privateStorage store
    class discordBridge,llm,ollama,whisper,tts,imageGen,telegram,discord external
```

### Required runtime roles

- The HTTP role serves Laravel and the built SPA. Public storage must be exposed through Laravel's storage link or an equivalent shared asset URL.
- A queue worker is required for archive embeddings, memory summaries, and generated avatar backgrounds. The default queue, session, and cache drivers in `.env.example` are database-backed.
- `services:telegram` is a separate, continuously supervised process when Telegram is enabled.
- Discord requires the separately deployed Node bridge, its bot tokens, and the same internal secret configured on both sides.
- AI services may be local or remote. Code assumes only their HTTP contracts, timeouts, and credentials.
- No application task is registered with Laravel's scheduler in `routes/console.php`.

## Queue job behavior

```mermaid
flowchart TD
    dispatch["Job dispatch"] --> job{"Job type"}
    job -->|"EmbedArchiveEntry"| embed["3 tries, 10 second backoff"]
    job -->|"SummarizeConversation"| summary["3 tries, 10 second backoff, 180 second timeout"]
    job -->|"GenerateAvatarBackground"| background["1 try, 180 second timeout"]

    embed --> execute["Call external provider and persist result"]
    summary --> execute
    background --> execute
    execute --> success{"Succeeded?"}
    success -->|"Yes"| complete["Update domain state and clear progress or lock"]
    success -->|"No and attempts remain"| retry["Release with configured backoff"] --> execute
    success -->|"No attempts remain"| failed[("failed_jobs and application log")]
    failed --> cleanup["Job-specific failure cleanup"]
    cleanup --> embedLog["Embedding logs entry ID"]
    cleanup --> memoryUnlock["Summary clears matching lock"]
    cleanup --> backgroundLog["Background logs and progress expires or clears"]

    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    classDef async fill:#fef3c7,stroke:#d97706,color:#451a03
    classDef store fill:#dcfce7,stroke:#16a34a,color:#052e16
    classDef danger fill:#ffe4e6,stroke:#e11d48,color:#4c0519
    class dispatch,job,embed,summary,background,execute,retry async
    class success,complete,cleanup,embedLog,memoryUnlock,backgroundLog core
    class failed danger
```

## Request and provider failure paths

```mermaid
stateDiagram-v2
    [*] --> Request
    Request --> Unauthorized: Missing session or membership
    Request --> Invalid: Validation or incompatible assistant mode
    Request --> ProviderCall: Valid and authorized
    ProviderCall --> Persisted: Provider succeeds
    ProviderCall --> BadGateway: Runtime provider exception
    ProviderCall --> TextFallback: Forced TTS fails after LLM reply
    TextFallback --> Persisted
    Persisted --> ClientRetryCheck
    BadGateway --> ClientRetryCheck
    ClientRetryCheck --> Request: Browser classifies timeout and attempt below 3
    ClientRetryCheck --> Delivered: Success
    ClientRetryCheck --> Failed: Non-timeout or retries exhausted
    Unauthorized --> [*]
    Invalid --> [*]
    Delivered --> [*]
    Failed --> [*]
```

The browser hook retries failures whose message looks like a timeout, execution-time error, 502, or 504, up to three attempts with a two-second pause. Because the user message is persisted before the provider call, a retried POST can create duplicate persisted user turns. This is current behavior, not an idempotent retry protocol.

## Agent tool failure state

```mermaid
stateDiagram-v2
    [*] --> AskModel
    AskModel --> Final: No tool calls
    AskModel --> Execute: Tool call returned
    Execute --> AskModel: Tool succeeds and result appended
    Execute --> Execute: Attempt fails and tool retries remain
    Execute --> AskModel: Tool exhausts retries but consecutive failure limit not reached
    Execute --> GracefulFailure: Consecutive failures reach configured limit
    AskModel --> FinalSummary: Step limit reached
    Final --> [*]
    GracefulFailure --> [*]
    FinalSummary --> [*]
```

Agent tool timeout enforcement requires the PHP `pcntl` extension. Each call is persisted as a `messages.role = tool_call` audit record and filtered out of normal message-history responses.

## Channel recovery behavior

- Telegram polling catches transport/API errors, respects Telegram `retry_after` when supplied, sleeps, and resumes. Per-update failures are isolated so the outer loop continues. Reply delivery retries once without Markdown parsing.
- Discord discovery returns a specific 502 when the bridge is unreachable and a non-error empty result when no bot is configured. Message processing failures are returned to the bridge as HTTP errors; bridge retry policy is outside this repository.
- TTS connection failures normally return 502. `/send-voice-message` and Discord voice-response synthesis instead report an audio error while preserving the text reply.
- Background generation deliberately does not fail the initiating chat request, even when a synchronous queue driver surfaces the job exception.

---

[Previous](06-avatar-and-world.md) · [Index](README.md) · [Next: Code-grounded caveats](08-code-grounded-caveats.md)
