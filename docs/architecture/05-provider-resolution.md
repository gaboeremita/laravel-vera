# Provider resolution

LLM, TTS, and image generation share the same two-level pattern: a selected database model wins, otherwise `config/ai.php` creates an in-memory provider/model pair from environment values. Embeddings and STT are container-bound singletons with no per-user selection.

## Resolution matrix

| Capability | Selection scope | Database formats | Config fallback | Failure if unavailable |
| --- | --- | --- | --- | --- |
| LLM | `settings.data.ai_model_id` per user and assistant | `generic`, `anthropic` | `AI_DEFAULT_*` | invalid-argument exception; controller commonly returns 502 |
| Agent LLM | Same as LLM, but explicit model required | Model must have `supports_tools` | None | 422 before loop |
| Embeddings | Application-wide binding | None | `AI_EMBEDDING_URL`, `AI_EMBEDDING_MODEL` | runtime exception from Ollama provider |
| STT | Application-wide binding | None | `AI_STT_URL`, `AI_STT_MODEL` | 502 from voice or Discord endpoint |
| TTS | `settings.data.tts_model_id` and `tts_voice` per user and assistant | `openai_compatible`, `openai_tts`, `deepgram`, `elevenlabs` | `AI_TTS_*` | 502, except forced replies degrade to text |
| Image generation | `settings.data.image_gen_model_id` per user and assistant | `openrouter`, `openai_compatible` | `IMAGE_GEN_*` | 502 or unavailable agent tool |

## Resolver flow

```mermaid
flowchart TD
    request["Capability request for AssistantUser"] --> capability{"Capability"}

    capability -->|"LLM"| llmSetting["Read ai_model_id from Settings JSON"]
    llmSetting --> llmSelected{"Selected ID?"}
    llmSelected -->|"Yes"| aiModel["Load AiModel and AiProvider"]
    aiModel --> aiFormat{"Provider format"}
    aiFormat -->|"generic"| generic["GenericProvider - OpenAI-compatible messages"]
    aiFormat -->|"anthropic"| anthropic["AnthropicProvider - Messages API"]
    llmSelected -->|"No"| llmConfig["Build transient model from ai.default"] --> aiFormat

    capability -->|"TTS"| voiceSetting["Read tts_model_id and tts_voice"]
    voiceSetting --> voiceSelected{"Selected ID?"}
    voiceSelected -->|"Yes"| voiceModel["Load VoiceModel and VoiceProvider"]
    voiceSelected -->|"No"| voiceConfig["Build transient model from ai.tts"]
    voiceModel --> voiceFormat{"Voice format"}
    voiceConfig --> voiceFormat
    voiceFormat --> openCompat["OpenAI-compatible TTS"]
    voiceFormat --> openAiTts["OpenAI TTS"]
    voiceFormat --> deepgram["Deepgram TTS"]
    voiceFormat --> elevenlabs["ElevenLabs TTS"]

    capability -->|"Image"| imageSetting["Read image_gen_model_id"]
    imageSetting --> imageSelected{"Selected ID owned by user?"}
    imageSelected -->|"Yes"| imageModel["Load ImageGenModel and provider"]
    imageSelected -->|"No"| imageConfig["Build transient model from ai.image_gen"]
    imageModel --> imageFormat{"Image format"}
    imageConfig --> imageFormat
    imageFormat --> openrouter["OpenRouter image API"]
    imageFormat --> imageCompat["OpenAI-compatible image API"]

    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    classDef external fill:#ffe4e6,stroke:#e11d48,color:#4c0519
    class request,capability,llmSetting,llmSelected,aiModel,aiFormat,llmConfig,voiceSetting,voiceSelected,voiceModel,voiceConfig,voiceFormat,imageSetting,imageSelected,imageModel,imageConfig,imageFormat core
    class generic,anthropic,openCompat,openAiTts,deepgram,elevenlabs,openrouter,imageCompat external
```

## Prompt and parameter composition

```mermaid
flowchart LR
    schema["Provider config_schema"] --> parameterBuilder["ParameterBuilder - defaults, required values, type casts, ranges"]
    modelConfig["Model config"] --> parameterBuilder
    additional["Model additional_config"] --> merge["Merge raw additional parameters"]
    parameterBuilder --> merge --> payload["Provider HTTP payload"]

    assistantPrompt["Assistant prompt"] --> promptDirector["PromptDirector"]
    voiceProviderPrompt["Selected voice provider prompt"] -. "Voice mode only" .-> promptDirector
    voiceModelPrompt["Selected voice model prompt"] -. "Voice mode only" .-> promptDirector
    imageProviderPrompt["Selected image provider prompt"] -. "Image enhancement only" .-> imageEnhancer["Image prompt enhancer"]
    imageModelPrompt["Selected image model prompt"] -. "Image enhancement only" .-> imageEnhancer

    classDef core fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    class schema,parameterBuilder,modelConfig,additional,merge,payload,assistantPrompt,promptDirector,voiceProviderPrompt,voiceModelPrompt,imageProviderPrompt,imageModelPrompt,imageEnhancer core
```

The selected voice model is resolved twice in normal voice mode: once to inject provider/model prompt sections and derive LLM options, then later by the synthesize endpoint. A forever cache stores only `{tts_model_id, tts_voice}` and is explicitly refreshed by voice-setting updates.

---

[Previous](04-rag-and-memory.md) · [Index](README.md) · [Next: Avatar and World](06-avatar-and-world.md)
