# System context and containers

VERA is a Laravel-hosted React SPA with three conversation entry paths: the browser, a Laravel-owned Telegram long poller, and an external Discord Gateway bridge. Model, speech, image, and embedding engines are HTTP dependencies; VERA owns conversation state and uploaded/generated assets.

## System context

```mermaid
flowchart LR
    person["Authenticated user"]
    telegramUser["Telegram user"]
    discordUser["Discord user"]

    vera["VERA - AI assistant platform"]
    telegram["Telegram Bot API"]
    bridge["node-discord-api - external Gateway bridge"]
    discord["Discord Gateway and API"]
    llm["LLM APIs - OpenAI-compatible or Anthropic"]
    speech["Speech services - whisper.cpp and TTS APIs"]
    imageGen["Image-generation APIs"]
    embeddings["Ollama embedding API"]
    data[("Application database")]
    assets[("Public and private asset storage")]

    person -->|"HTTPS and session cookie"| vera
    telegramUser --> telegram -->|"Long-polled updates"| vera
    vera -->|"Replies"| telegram
    discordUser <--> discord
    discord <--> bridge
    bridge -->|"Sanctum-authenticated message callback"| vera
    vera -->|"Shared-secret discovery and reply payload"| bridge

    vera --> llm
    vera --> speech
    vera --> imageGen
    vera --> embeddings
    vera <--> data
    vera <--> assets

    classDef actor fill:#dbeafe,stroke:#2563eb,color:#172554
    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    classDef external fill:#ffe4e6,stroke:#e11d48,color:#4c0519
    classDef store fill:#dcfce7,stroke:#16a34a,color:#052e16
    class person,telegramUser,discordUser actor
    class vera core
    class telegram,bridge,discord,llm,speech,imageGen,embeddings external
    class data,assets store
```

The bridge is referenced and called by this repository but its Node source and deployment definition are not present here. Telegram is different: `services:telegram` is a long-running Artisan command inside this codebase.

## Container and component architecture

```mermaid
flowchart TB
    browser["Browser"]
    react["React 19 SPA - React Router, hooks, Three.js"]
    laravel["Laravel 13 HTTP application"]

    subgraph apiLayer["HTTP and authorization"]
        webRoutes["SPA and login routes"]
        apiRoutes["Sanctum-protected JSON routes"]
        auth["Session auth, CSRF, policies, ownership resolvers"]
        controllers["API controllers and resources"]
    end

    subgraph domainLayer["Conversation and domain services"]
        prompt["PromptDirector and PromptBuilder"]
        conversation["Conversation orchestration"]
        agent["AgentLoopRunner and tools"]
        rag["Archive retrieval and memory summarization"]
        providers["LLM, TTS, image managers"]
        worlds["World, resident, session services"]
    end

    subgraph asyncLayer["Asynchronous and long-running work"]
        dbQueue[("Database queue")]
        worker["Laravel queue worker"]
        telegramPoller["services:telegram process"]
        jobs["Embedding, summary, background jobs"]
    end

    postgres[("SQL database - vector and full-text features required for archive search")]
    cache[("Configured cache - progress and voice settings")]
    storage[("Filesystem disks - VRM, GLB, images, audio metadata")]
    bridge["External Discord bridge"]
    engines["External AI, speech, and image services"]

    browser --> react --> laravel
    laravel --> webRoutes
    laravel --> apiRoutes --> auth --> controllers
    controllers --> conversation
    controllers --> worlds
    conversation --> prompt
    conversation --> agent
    conversation --> rag
    conversation --> providers
    prompt --> rag
    providers --> engines
    controllers --> postgres
    conversation --> postgres
    worlds --> postgres
    controllers --> storage
    conversation --> storage
    conversation --> cache

    controllers -.-> dbQueue
    dbQueue --> worker --> jobs
    jobs --> postgres
    jobs --> storage
    jobs --> engines
    jobs --> cache

    telegramPoller --> postgres
    telegramPoller --> prompt
    telegramPoller --> providers
    bridge --> apiRoutes

    classDef client fill:#dbeafe,stroke:#2563eb,color:#172554
    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    classDef async fill:#fef3c7,stroke:#d97706,color:#451a03
    classDef store fill:#dcfce7,stroke:#16a34a,color:#052e16
    classDef external fill:#ffe4e6,stroke:#e11d48,color:#4c0519
    class browser,react client
    class laravel,webRoutes,apiRoutes,auth,controllers,prompt,conversation,agent,rag,providers,worlds core
    class dbQueue,worker,telegramPoller,jobs async
    class postgres,cache,storage store
    class bridge,engines external
```

### Frontend boundaries

The SPA owns navigation, optimistic chat state, client retry-on-timeout, microphone VAD, audio playback, and the Three.js world. It does not call model providers directly. All persisted domain state and provider credentials stay behind Laravel.

### Backend boundaries

Controllers enforce the Sanctum boundary and resolve user-assistant or user-world membership. The conversation controller currently performs the central orchestration itself; managers normalize provider selection while `PromptDirector` assembles DB-authored prompt sections and injected context.

---

[Index](README.md) · [Next: Conversation runtime](02-conversation-runtime.md)
