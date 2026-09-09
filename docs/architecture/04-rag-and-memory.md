# RAG and long-term memory

VERA has two related but independent context systems. Archives provide assistant-linked knowledge through embeddings. Conversation memory condenses older turns into a per-conversation narrative. Both are inserted into the system prompt as explicitly untrusted context blocks.

## Archive ingestion and retrieval

```mermaid
flowchart TB
    editor["Archive editor"] --> save["ArchiveController save transaction"]
    save --> archive[("archives and archive_entries")]
    save --> tags[("tags and taggables")]
    save -. "New entry or changed content" .-> queue[("Database queue")]
    queue --> embedJob["EmbedArchiveEntry - 3 tries, 10 second backoff"]
    embedJob --> compose["Compose title, tag names, and content"]
    compose --> ollama["Ollama embedding API"]
    ollama --> vector["768-dimension embedding"]
    vector --> archive

    message["Current user text"] --> queryEmbed["Embed query"] --> ollama
    queryEmbed --> vectorSearch["Vector similarity - minimum 0.50, limit 5"]
    archive --> vectorSearch
    vectorSearch --> context["retrieved_context block"]
    context --> prompt["PromptDirector system prompt"]

    searchUi["Archive search request"] --> fulltext["PostgreSQL full-text matching IDs - ordered by ID, limit 20"]
    searchUi --> semantic["Vector similarity rank list - minimum 0.50, limit 20"]
    archive --> fulltext
    archive --> semantic
    fulltext --> rrf["Reciprocal Rank Fusion - K equals 60"]
    semantic --> rrf
    rrf --> ids["Ranked entry IDs and scores"]

    classDef client fill:#dbeafe,stroke:#2563eb,color:#172554
    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    classDef async fill:#fef3c7,stroke:#d97706,color:#451a03
    classDef store fill:#dcfce7,stroke:#16a34a,color:#052e16
    classDef external fill:#ffe4e6,stroke:#e11d48,color:#4c0519
    class editor,message,searchUi client
    class save,compose,queryEmbed,vectorSearch,context,prompt,fulltext,semantic,rrf,ids core
    class queue,embedJob async
    class archive,tags,vector store
    class ollama external
```

Conversation-time RAG is vector-only. The hybrid full-text plus vector RRF action powers the archive search endpoint and editor UX; it is not used by `PromptDirector::withRetrieval`.

## Long-term memory state and job flow

```mermaid
stateDiagram-v2
    [*] --> Idle
    Idle --> Eligible: Manual summarize with pending messages
    Idle --> Eligible: Auto-summary enabled and pending count at least 50
    Eligible --> Locked: Atomic null-to-timestamp update succeeds
    Eligible --> Idle: No pending messages or lock already held
    Locked --> Queued: Dispatch SummarizeConversation
    Queued --> Summarizing: Worker starts job
    Summarizing --> Summarizing: Process next batch of at most 50
    Summarizing --> Completed: Checkpoint reaches captured upper message ID
    Summarizing --> Abandoned: Lock changed or force-unlocked
    Summarizing --> Failed: Exception after job attempts
    Completed --> Idle: Release matching timestamp lock
    Failed --> Idle: Release matching timestamp lock and log
    Abandoned --> Idle
```

```mermaid
sequenceDiagram
    participant Trigger as Controller or user
    participant DB as Conversation and messages
    participant Queue as Queue worker
    participant Action as SummarizeConversation action
    participant Manager as LlmManager
    participant LLM as Resolved LLM

    Trigger->>DB: Capture highest message ID and current checkpoint
    Trigger->>DB: Atomically set memory_summarizing_at if null
    Trigger-->>Queue: Dispatch job with upper ID and lock timestamp
    Queue->>Action: Run since_last or full mode
    loop Up to 50 messages per batch
        Action->>DB: Verify exact lock timestamp still held
        Action->>DB: Read bounded transcript batch
        Action->>Manager: Resolve assistant-user LLM
        Manager->>LLM: Existing memory plus new scene
        LLM-->>Action: Updated narrative summary
        Action->>DB: Prepend summary and advance checkpoint
    end
    Action->>DB: Clear lock only if timestamp still matches
```

`since_last` considers at most the latest 50 pending messages; `full` resets the checkpoint and permits a backlog of up to 200. The action prepends each new summary above existing memory. On subsequent web, world, image-enhancement, background, and Discord turns, `PromptDirector` injects that text in a `long_term_memory` block.

### Context assembly order

```mermaid
flowchart LR
    base["Assistant prompt JSON"] --> world["Optional world and resident context"] --> modes["Mode-specific section filtering"] --> voice["Optional voice provider and model prompts"] --> rag["Optional retrieved_context"] --> memory["Optional long_term_memory"] --> build["PromptBuilder plain-text system prompt"]

    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    class base,world,modes,voice,rag,memory,build core
```

---

[Previous](03-data-model.md) · [Index](README.md) · [Next: Provider resolution](05-provider-resolution.md)
