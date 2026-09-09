# Data model

The ERDs below represent the final schema after all migrations, not the order in which tables were introduced. Framework infrastructure tables are separated from domain tables so the relationships remain readable.

## Domain overview

```mermaid
erDiagram
    users ||--o{ assistant_user : accesses
    assistants ||--o{ assistant_user : shared_with
    assistant_user o|--o{ conversations : owns
    conversations ||--o{ messages : contains
    archives o|--o{ assistants : linked_by
    users ||--o{ archives : owns
    archives ||--o{ archive_entries : contains
    users ||--o{ settings : configures
    assistants o|--o{ settings : scoped_to
    users ||--o{ world_user : accesses
    worlds ||--o{ world_user : shared_with
    world_user ||--o{ world_sessions : owns
    world_sessions o|--o{ conversations : scopes
    worlds ||--o{ world_residents : contains
    assistants ||--o{ world_residents : placed_as
    users ||--o{ ai_providers : owns
    ai_providers ||--o{ ai_models : offers
    users ||--o{ image_gen_providers : owns
    image_gen_providers ||--o{ image_gen_models : offers
    voice_providers ||--o{ voice_models : offers
```

## Identity, assistants, conversations, and media

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string email UK
        string password
        timestamp email_verified_at
    }
    assistants {
        bigint id PK
        bigint archive_id FK
        string slug UK
        json prompt
        string mode
        string portrait_type
        string kind
        json agent_config
    }
    assistant_user {
        bigint id PK
        bigint assistant_id FK
        bigint user_id
        json memory_prompt
    }
    settings {
        bigint id PK
        bigint user_id FK
        bigint assistant_id FK
        json data
    }
    conversations {
        bigint id PK
        bigint assistant_user_id FK
        bigint world_session_id FK
        string discord_channel_id UK
        text long_term_memory
        bigint memory_checkpoint_message_id
        timestamp memory_summarizing_at
        boolean auto_summarize_enabled
    }
    messages {
        bigint id PK
        bigint conversation_id FK
        string role
        string discord_message_id
        text content
        text thinking
        string emotion
        json tool_calls
    }
    emotions {
        bigint id PK
        bigint assistant_id FK
        string name
        boolean restricted
        json vrm_blendshapes
    }
    poses {
        bigint id PK
        bigint assistant_id FK
        string name
        json vrm_blendshapes
    }
    pose_animation_files {
        bigint id PK
        bigint pose_id FK
        string path
        string disk
    }
    images {
        bigint id PK
        string imageable_type
        bigint imageable_id
        string role
        string path
        string disk
    }
    videos {
        bigint id PK
        string videoable_type
        bigint videoable_id
        string path
        string disk
    }
    vrm_files {
        bigint id PK
        string vrmable_type
        bigint vrmable_id
        string path
        string disk
    }

    users ||--o{ assistant_user : has
    assistants ||--o{ assistant_user : has
    users ||--o{ settings : has
    assistants ||--o{ settings : has
    assistant_user o|--o{ conversations : has
    conversations ||--o{ messages : has
    assistants o|--o{ emotions : has
    assistants ||--o{ poses : has
    poses ||--o| pose_animation_files : has
    assistants ||--o| vrm_files : vrmable
    assistants ||--o| images : card_image
    emotions ||--o| images : imageable
    emotions ||--o| videos : videoable
    messages ||--o| images : imageable
```

`images`, `videos`, and `vrm_files` are polymorphic; the diagram names only model types used by current code. `memory_checkpoint_message_id` is an unsigned ID marker but has no database foreign-key constraint.

## Archives, tags, and providers

```mermaid
erDiagram
    users {
        bigint id PK
    }
    archives {
        bigint id PK
        bigint user_id FK
        string name
        text description
    }
    archive_entries {
        bigint id PK
        bigint archive_id FK
        string title
        text content
        vector embedding
        json keywords
    }
    tags {
        bigint id PK
        bigint user_id FK
        string name
    }
    taggables {
        bigint tag_id FK
        string taggable_type
        bigint taggable_id
    }
    ai_providers {
        bigint id PK
        bigint user_id FK
        string name
        string url
        text api_key
        string format
        json config_schema
    }
    ai_models {
        bigint id PK
        bigint provider_id FK
        string name
        string endpoint
        string thinking_key
        json config
        json additional_config
        boolean supports_tools
    }
    image_gen_providers {
        bigint id PK
        bigint user_id FK
        string name
        string url
        text api_key
        string format
        json prompt
        json config_schema
    }
    image_gen_models {
        bigint id PK
        bigint provider_id FK
        string name
        string endpoint
        json prompt
        json config
        json additional_config
    }
    voice_providers {
        bigint id PK
        string name UK
        string url
        text api_key
        string format
        text instructions
        json prompt
    }
    voice_models {
        bigint id PK
        bigint provider_id FK
        string name
        string endpoint
        json voices
        json config
        json prompt
    }

    users ||--o{ archives : owns
    archives ||--o{ archive_entries : contains
    users ||--o{ tags : owns
    tags ||--o{ taggables : tags
    archive_entries ||--o{ taggables : tagged_as
    users ||--o{ ai_providers : owns
    ai_providers ||--o{ ai_models : has
    users ||--o{ image_gen_providers : owns
    image_gen_providers ||--o{ image_gen_models : has
    voice_providers ||--o{ voice_models : has
```

Voice providers are global in the schema; LLM and image providers belong to users. API keys use Laravel's encrypted cast and are hidden from serialized models.

## Worlds and Discord

```mermaid
erDiagram
    users {
        bigint id PK
    }
    assistants {
        bigint id PK
    }
    assistant_user {
        bigint id PK
        bigint assistant_id FK
        bigint user_id FK
    }
    worlds {
        bigint id PK
        string slug
        string environment_disk
        string environment_path
        text assistant_context_prompt
        text npc_context_prompt
        json settings
    }
    world_user {
        bigint id PK
        bigint world_id FK
        bigint user_id FK
    }
    world_sessions {
        bigint id PK
        bigint world_user_id FK
        string title
        json position
    }
    world_residents {
        bigint id PK
        bigint world_id FK
        bigint assistant_id FK
        json position
        json rotation
        string behavior
        json behavior_settings
        text opening_message
        text custom_prompt
    }
    conversations {
        bigint id PK
        bigint assistant_user_id FK
        bigint world_session_id FK
        string discord_channel_id
    }
    discord_servers {
        bigint id PK
        string discord_guild_id UK
        string name
    }
    discord_channels {
        bigint id PK
        bigint discord_server_id FK
        string discord_channel_id UK
        string name
    }
    assistant_discord_servers {
        bigint id PK
        bigint assistant_user_id FK
        bigint discord_server_id FK
        json prompt
    }
    assistant_discord_channels {
        bigint id PK
        bigint assistant_user_id FK
        bigint discord_channel_id FK
        string trigger_mode
        json prompt
    }
    tracks {
        bigint id PK
        string trackable_type
        bigint trackable_id
        string path
    }
    images {
        bigint id PK
        string imageable_type
        bigint imageable_id
        string role
    }

    users ||--o{ assistant_user : accesses
    assistants ||--o{ assistant_user : shared_with
    users ||--o{ world_user : accesses
    worlds ||--o{ world_user : shared_with
    world_user ||--o{ world_sessions : owns
    world_sessions o|--o{ conversations : scopes
    assistant_user o|--o{ conversations : owns
    worlds ||--o{ world_residents : contains
    assistants ||--o{ world_residents : placed_as
    discord_servers ||--o{ discord_channels : contains
    assistant_user ||--o{ assistant_discord_servers : configures
    discord_servers ||--o{ assistant_discord_servers : configured_for
    assistant_user ||--o{ assistant_discord_channels : configures
    discord_channels ||--o{ assistant_discord_channels : configured_for
    worlds ||--o| tracks : trackable
    worlds ||--o{ images : card_or_portrait
```

DM rows are represented by `discord_channels.discord_server_id = null`. A Discord conversation stores the external channel snowflake directly; it does not foreign-key to `discord_channels`.

## Framework infrastructure

Laravel also creates the following infrastructure tables. They support session auth, Sanctum tokens, cached progress/settings, and the default database queue, but are not domain entities.

```mermaid
erDiagram
    users {
        bigint id PK
    }
    sessions {
        string id PK
        bigint user_id FK
        text payload
        int last_activity
    }
    personal_access_tokens {
        bigint id PK
        string tokenable_type
        bigint tokenable_id
        string token UK
        text abilities
        timestamp expires_at
    }
    password_reset_tokens {
        string email PK
        string token
    }
    cache {
        string key PK
        text value
        bigint expiration
    }
    cache_locks {
        string key PK
        string owner
        bigint expiration
    }
    jobs {
        bigint id PK
        string queue
        text payload
        int attempts
        int available_at
    }
    job_batches {
        string id PK
        string name
        int total_jobs
        int pending_jobs
        int failed_jobs
        text options
    }
    failed_jobs {
        bigint id PK
        string uuid UK
        string connection
        string queue
        text payload
        text exception
    }

    users o|--o{ sessions : authenticates
    users o|--o{ personal_access_tokens : tokenable
```

The two user edges are logical Eloquent/authentication relationships rather than declared database foreign keys. `personal_access_tokens` is polymorphic even though `User` is the current authenticatable model. `password_reset_tokens.email` is likewise a logical link to users. Cache and queue tables have no domain foreign keys.

---

[Previous](02-conversation-runtime.md) · [Index](README.md) · [Next: RAG and memory](04-rag-and-memory.md)
