<?php

namespace App\Http\Controllers\Api;

use App\Actions\AppendWorldConversationContext;
use App\Contracts\SttProvider;
use App\Directors\PromptDirector;
use App\DTOs\LlmResponse;
use App\Enums\AssistantMode;
use App\Enums\AssistantPortraitType;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAvatarBackground;
use App\Jobs\SummarizeConversation;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\DiscordChannel;
use App\Models\Image;
use App\Models\Settings;
use App\Models\WorldUser;
use App\Services\AgentLoop\AgentLoopRunner;
use App\Services\AgentLoop\Tools\AvatarBackgroundTool;
use App\Services\AgentLoop\Tools\BasicCalculatorTool;
use App\Services\AgentLoop\Tools\GetCurrentDatetimeTool;
use App\Services\AgentLoop\Tools\ImageGenerationTool;
use App\Services\ImageGenProviders\ImageGenerationService;
use App\Services\LlmProviders\LlmManager;
use App\Services\TtsProviders\TtsManager;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConversationController extends Controller
{
    use ResolvesAssistantUser;

    private const MESSAGES_PER_PAGE = 50;

    private const MEMORY_SUMMARY_TRIGGER_COUNT = 50;

    private const IMAGE_GEN_COMMAND = '/create-image ';

    private const TTS_TRUNCATION_LENGTH = 200;

    private const BACKGROUND_TAG_INSTRUCTION = 'When the scene/setting you and the user are in has just clearly changed to a new location, prefix your reply with a tag describing the new setting: [scene: <short description of the new location>]. Only include this tag when the setting has actually changed, never when it is unchanged. Never mention the tag itself.';

    public function index(Request $request, int $assistant): JsonResponse
    {

        $assistantUser = $this->resolveAssistantUser($request, $assistant);

        $conversations = $assistantUser
            ->conversations()
            ->when($request->filled('worldSessionId'), fn ($query) => $query->where('world_session_id', $request->integer('worldSessionId')))
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        return response()->json($conversations);
    }

    public function show(Request $request, int $assistant, int $id): JsonResponse
    {
        $assistantUser = $this->resolveAssistantUser($request, $assistant);

        $conversation = $assistantUser
            ->conversations()
            ->findOrFail($id);

        if (! $request->has('before')
            && $assistantUser->assistant->portrait_type === AssistantPortraitType::Avatar3D
            && Cache::get(GenerateAvatarBackground::cacheKeyFor($conversation->id)) === null
            && Cache::get(GenerateAvatarBackground::progressKeyFor($conversation->id)) === null) {
            GenerateAvatarBackground::dispatchFor($assistantUser, $conversation, 'Infer the current setting from the conversation so far.');
        }

        $limit = self::MESSAGES_PER_PAGE;

        $query = $conversation->messages()
            ->where('role', '!=', 'tool_call')
            ->with('image')
            ->orderByDesc('created_at');

        if ($request->has('before')) {
            $query->where('id', '<', (int) $request->input('before'));
        }

        // Fetch one extra to determine if older messages exist
        $messages = $query->take($limit + 1)->get();
        $hasMore = $messages->count() > $limit;

        // Trim to limit, reverse to chronological order
        $messages = $messages->take($limit)->reverse()->values();

        $messages->transform(function ($message) {
            $message->image_url = $message->image?->url;
            unset($message->image);

            return $message;
        });

        return response()->json([
            'messages' => $messages,
            'has_more' => $hasMore,
        ]);
    }

    public function store(Request $request, int $assistant): JsonResponse
    {
        $validated = $request->validate([
            'worldId' => ['nullable', 'integer', 'exists:worlds,id'],
            'worldSessionId' => ['nullable', 'integer', 'exists:world_sessions,id'],
        ]);

        $assistantUser = $this->resolveAssistantUser($request, $assistant);

        $world = isset($validated['worldId'])
            ? $request->user()->worlds()->find($validated['worldId'])
            : null;
        $resident = $world?->residents()->where('assistant_id', $assistant)->first();

        $worldSessionId = null;
        if (isset($validated['worldSessionId'])) {
            $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $request->user()->id)->firstOrFail();
            $worldSessionId = $worldUser->sessions()->findOrFail($validated['worldSessionId'])->id;

            $existing = $assistantUser->conversations()->where('world_session_id', $worldSessionId)->first();
            if ($existing !== null) {
                return response()->json($existing, 201);
            }
        }

        $conversation = $assistantUser
            ->conversations()
            ->create(['title' => 'New conversation', 'world_session_id' => $worldSessionId]);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $world !== null
                ? ($resident?->opening_message ?? '')
                : ($assistantUser->assistant->opening_message ?? ''),
        ]);

        if ($world === null
            && $assistantUser->assistant->portrait_type === AssistantPortraitType::Avatar3D
            && ! empty($assistantUser->assistant->opening_message)) {
            GenerateAvatarBackground::dispatchFor($assistantUser, $conversation, $assistantUser->assistant->opening_message);
        }

        return response()->json($conversation, 201);
    }

    public function destroy(Request $request, int $assistant, int $id): JsonResponse
    {
        $assistantUser = $this->resolveAssistantUser($request, $assistant);

        $conversation = $assistantUser
            ->conversations()
            ->findOrFail($id);

        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted']);
    }

    public function update(Request $request, int $assistant, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
        ]);

        $conversation = $this->resolveAssistantUser($request, $assistant)
            ->conversations()
            ->findOrFail($id);

        $conversation->update($validated);

        return response()->json($conversation);
    }

    public function sendMessage(Request $request, int $assistant, int $id): JsonResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['nullable', 'string'],
            'messages.*.images' => ['sometimes', 'array'],
            'voice_mode' => ['sometimes', 'boolean'],
            'worldId' => ['nullable', 'integer', 'exists:worlds,id'],
        ]);

        $assistantUser = $this->resolveAssistantUser($request, $assistant);

        $conversation = $assistantUser
            ->conversations()
            ->findOrFail($id);

        $lastUserMessage = collect($validated['messages'])->last(fn ($m) => $m['role'] === 'user');

        $forceVoice = false;
        $voiceCommandContent = $this->extractVoiceMessageCommand($lastUserMessage['content'] ?? null);
        if ($voiceCommandContent !== null) {
            $lastUserMessage['content'] = $voiceCommandContent;
            $forceVoice = true;
        }

        if ($lastUserMessage) {
            $message = $conversation->messages()->create([
                'role' => 'user',
                'content' => $lastUserMessage['content'] ?? '',
            ]);

            if (! empty($lastUserMessage['images'][0])) {
                $storagePath = "messages/{$request->user()->id}/{$conversation->id}";
                Image::storeFromBase64($lastUserMessage['images'][0], $message, $storagePath);
            }

            if ($assistantUser->assistant->portrait_type === AssistantPortraitType::Avatar3D
                && empty($assistantUser->assistant->opening_message)
                && $conversation->messages()->where('role', 'user')->count() === 1) {
                GenerateAvatarBackground::dispatchFor($assistantUser, $conversation, $lastUserMessage['content'] ?? '');
            }
        }

        $imageGenPrompt = $this->extractImageGenPrompt($lastUserMessage['content'] ?? null);

        if ($imageGenPrompt !== null) {
            if ($imageGenPrompt === '') {
                return response()->json(['message' => 'Describe what image to generate after /create-image.'], 422);
            }

            try {
                $generated = $this->generateImageMessage($request, $assistantUser, $conversation, $imageGenPrompt);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 502);
            }

            return response()->json([
                'conversation_id' => $conversation->id,
                'content' => $generated['content'],
                'image_url' => $generated['image_url'],
                'thinking' => $generated['enhanced_prompt'],
                'emotion' => $generated['emotion'],
                'intimate' => $generated['intimate'],
                'pose' => $generated['pose'],
                'tts_instructions' => null,
            ]);
        }

        $avatarBackgroundPrompt = $this->extractAvatarBackgroundPrompt($lastUserMessage['content'] ?? null);

        if ($avatarBackgroundPrompt !== null) {
            if ($avatarBackgroundPrompt === '') {
                return response()->json(['message' => 'Describe the background to change to after /change-background.'], 422);
            }

            if ($assistantUser->assistant->portrait_type !== AssistantPortraitType::Avatar3D) {
                return response()->json(['message' => 'Background changes are only available for 3D avatar assistants.'], 422);
            }

            GenerateAvatarBackground::dispatchFor($assistantUser, $conversation, $avatarBackgroundPrompt);

            try {
                $reaction = $this->reactToBackgroundChange($assistantUser, $conversation, $avatarBackgroundPrompt);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 502);
            }

            $parsed = $this->extractExpressionTag($reaction->content, $assistantUser->assistant);

            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $parsed['content'],
                'emotion' => $parsed['emotion'],
            ]);

            $this->checkpointAutoSummarize($conversation, $assistantMessage->id);

            return response()->json([
                'conversation_id' => $conversation->id,
                'content' => $parsed['content'],
                'pose' => $parsed['pose'],
                'thinking' => null,
                'tts_instructions' => null,
                'tool_calls' => null,
            ]);
        }

        $assistantModel = $assistantUser->assistant;

        $archive = $assistantModel->archive;

        $excludedSections = ['opening_message'];

        if (! empty($validated['voice_mode'])) {
            $excludedSections[] = 'style rules';
            $excludedSections[] = 'OOC mode';

            if (empty($lastUserMessage['images'][0])) {
                $excludedSections[] = 'image handling';
            }
        } elseif (! $forceVoice) {
            $excludedSections[] = 'voice mode';
        }

        $world = isset($validated['worldId'])
            ? $request->user()->worlds()->findOrFail($validated['worldId'])
            : null;
        $prompt = app(AppendWorldConversationContext::class)->handle($assistantModel, $world);
        $director = new PromptDirector($prompt);
        $this->appendExpressionTags($director, $assistantModel, $excludedSections);

        if ($assistantModel->portrait_type === AssistantPortraitType::Avatar3D) {
            $director->append('background tags', self::BACKGROUND_TAG_INSTRUCTION);
        }

        $director->except($excludedSections);

        $voiceModel = null;

        if (! empty($validated['voice_mode'])) {
            $voiceModel = (new TtsManager)->resolveVoiceModel($assistantUser);

            $voiceSections = [];
            if ($voiceModel?->provider->prompt) {
                $voiceSections['voice provider prompt'] = $voiceModel->provider->prompt;
            }
            if ($voiceModel?->prompt) {
                $voiceSections['voice model prompt'] = $voiceModel->prompt;
            }

            if ($voiceSections) {
                $director->insertAfter('identity', $voiceSections);
            }
        }

        if ($archive && ! empty($lastUserMessage['content'])) {
            $director->withRetrieval($lastUserMessage['content'], $archive->id);
        }

        $director->withLongTermMemory($conversation);

        $systemPrompt = $director->build();

        $tts = $voiceModel ? (new TtsManager)->fromModel($voiceModel) : null;
        $agentToolCalls = null;

        try {
            $llmManager = new LlmManager;
            $aiModel = $llmManager->resolveModelForAssistantUser($assistantUser);
            $llm = $aiModel ? $llmManager->fromModel($aiModel) : $llmManager->fromConfig();

            if ($assistantModel->mode === AssistantMode::Agent) {
                if (! $aiModel) {
                    return response()->json(['message' => 'This assistant is in agent mode and requires an explicitly selected AI model that supports tool-calling.'], 422);
                }

                if (! $aiModel->supports_tools) {
                    return response()->json(['message' => 'This assistant is in agent mode and requires a model that supports tool-calling.'], 422);
                }

                $imageGenerationService = new ImageGenerationService;
                $tools = [
                    new GetCurrentDatetimeTool,
                    new BasicCalculatorTool,
                ];

                if ($imageGenerationService->isAvailableFor($assistantUser)) {
                    $tools[] = new ImageGenerationTool($imageGenerationService, $assistantUser, $conversation);
                }

                if ($assistantModel->portrait_type === AssistantPortraitType::Avatar3D && $imageGenerationService->isAvailableFor($assistantUser)) {
                    $tools[] = new AvatarBackgroundTool($assistantUser, $conversation);
                }

                $runner = new AgentLoopRunner($llm, $tools);

                $agentResult = $runner->run(
                    assistant: $assistantModel,
                    messages: [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ...$validated['messages'],
                    ],
                    conversation: $conversation,
                );

                $agentToolCalls = $agentResult->toolCalls;
                $response = new LlmResponse(content: $agentResult->content);
            } else {
                $response = $llm->chat(
                    messages: [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ...$validated['messages'],
                    ],
                    options: $tts?->llmOptions() ?? [],
                );
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        [$content, $sceneDescription] = $this->extractSceneTag($response->content);

        $ttsInstructions = null;
        if ($tts) {
            $result = $tts->parseLlmResponse($content);
            $content = $result->content;
            $ttsInstructions = $result->ttsInstructions;
        }

        if ($sceneDescription !== null && $assistantModel->portrait_type === AssistantPortraitType::Avatar3D) {
            GenerateAvatarBackground::dispatchFor($assistantUser, $conversation, $sceneDescription);
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
            'thinking' => $response->thinking,
        ]);

        $this->checkpointAutoSummarize($conversation, $assistantMessage->id);

        $audioBase64 = null;
        $audioContentType = null;
        $audioError = null;

        if ($forceVoice) {
            try {
                $ttsManager = app(TtsManager::class);
                $tts = $ttsManager->forAssistantUser($assistantUser);
                $ttsText = mb_substr($this->stripForSpeech($content), 0, self::TTS_TRUNCATION_LENGTH);
                $audioBytes = $tts->synthesize($ttsText);
                $audioBase64 = base64_encode($audioBytes);
                $audioContentType = $tts->contentType();
            } catch (\Throwable $e) {
                report($e);
                $audioError = 'Voice synthesis failed — sent as text instead.';
            }
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'content' => $content,
            'thinking' => $response->thinking,
            'tts_instructions' => $ttsInstructions,
            'tool_calls' => $agentToolCalls,
            'audioBase64' => $audioBase64,
            'audioContentType' => $audioContentType,
            'audioError' => $audioError,
        ]);
    }

    private function extractImageGenPrompt(?string $content): ?string
    {
        $content = trim($content ?? '');

        if (! preg_match('/^\/create-image(?:\s+|$)/i', $content, $match)) {
            return null;
        }

        return trim(substr($content, strlen($match[0])));
    }

    private function extractAvatarBackgroundPrompt(?string $content): ?string
    {
        $content = trim($content ?? '');

        if (! preg_match('/^\/change-background(?:\s+|$)/i', $content, $match)) {
            return null;
        }

        return trim(substr($content, strlen($match[0])));
    }

    private function extractVoiceMessageCommand(?string $content): ?string
    {
        $content = trim($content ?? '');

        if (! preg_match('/^\/send-voice-message(?:\s+|$)/i', $content, $match)) {
            return null;
        }

        return trim(substr($content, strlen($match[0])));
    }

    private function buildDiscordVoiceResponse(
        string $content,
        AssistantUser $assistantUser,
        bool $hasAudio,
        bool $forceVoice,
    ): array {
        $settings = Settings::where('user_id', $assistantUser->user_id)
            ->where('assistant_id', $assistantUser->assistant_id)
            ->first();

        $voiceMode = $settings?->data['discordVoiceResponseMode'] ?? 'both';

        $shouldSynthesize = $forceVoice
            || ($hasAudio && in_array($voiceMode, ['both', 'voiceOnly'], true));

        $audioBase64 = null;
        $audioContentType = null;
        $audioError = null;

        if ($shouldSynthesize) {
            try {
                $ttsManager = app(TtsManager::class);
                $tts = $ttsManager->forAssistantUser($assistantUser);

                $ttsText = mb_substr($this->stripForSpeech($content), 0, self::TTS_TRUNCATION_LENGTH);
                $audioBytes = $tts->synthesize($ttsText);

                $audioBase64 = base64_encode($audioBytes);
                $audioContentType = $tts->contentType();
            } catch (\Throwable $e) {
                report($e);
                $audioError = 'Voice synthesis failed — sent as text instead.';
            }
        }

        $returnContent = $content;

        if ($voiceMode === 'voiceOnly' && $hasAudio && ! $forceVoice && $audioBase64 !== null) {
            $returnContent = null;
        }

        return [
            'content' => $returnContent,
            'audioBase64' => $audioBase64,
            'audioContentType' => $audioContentType,
            'audioError' => $audioError,
        ];
    }

    private function mimeToExtension(string $mimeType): string
    {
        $baseMime = trim(explode(';', $mimeType)[0]);

        return match ($baseMime) {
            'audio/ogg' => 'ogg',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'audio/mp4' => 'mp4',
            default => 'wav',
        };
    }

    /**
     * @return array{0: string, 1: ?string} [content with the tag stripped, extracted description or null]
     */
    private function extractSceneTag(string $content): array
    {
        if (! preg_match('/^\[scene:\s*([^\]]+)\]/i', $content, $match)) {
            return [$content, null];
        }

        $description = trim($match[1]);
        $remaining = trim(substr($content, strlen($match[0])));

        return [$remaining, $description];
    }

    /**
     * Appends the assistant's expressive-signal prompt section — pose tags
     * for 3D avatar assistants (poses are their only expression/action
     * system, so emotion tags never apply), emotion tags for image-mode
     * assistants — and excludes whichever section doesn't apply from
     * $excludedSections, so a stale section baked into the assistant's
     * stored prompt (e.g. from before it was in this mode) never renders
     * alongside it and confuses the model with two competing tag formats.
     */
    private function appendExpressionTags(PromptDirector $director, Assistant $assistantModel, array &$excludedSections): void
    {
        if ($assistantModel->portrait_type === AssistantPortraitType::Avatar3D) {
            $excludedSections[] = 'emotion tags';

            $poses = $assistantModel->promptPoseNames();

            if (! empty($poses)) {
                $director->append('pose tags', ['available poses' => $poses]);
            }

            return;
        }

        $excludedSections[] = 'pose tags';

        $emotions = $assistantModel->promptEmotionNames();
        $director->append('emotion tags', ['available emotions' => $emotions]);
    }

    /**
     * Runs the full /create-image pipeline (enhance -> generate -> in-character reaction -> persist),
     * shared by every channel (web, Discord, ...). Callers format their own channel-specific response.
     *
     * @return array{content: string, emotion: ?string, intimate: bool, image_url: string, enhanced_prompt: string}
     *
     * @throws \RuntimeException if enhancement, generation, or the reaction LLM call fails
     */
    private function generateImageMessage(Request $request, AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt): array
    {
        $result = (new ImageGenerationService)->generate($assistantUser, $conversation, $rawPrompt);
        $enhancedPrompt = $result['enhancedPrompt'];

        $reaction = $this->reactToGeneratedImage($request, $assistantUser, $conversation, $rawPrompt, $enhancedPrompt);
        $parsed = $this->extractExpressionTag($reaction->content, $assistantUser->assistant);

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $parsed['content'],
            'emotion' => $parsed['emotion'],
        ]);

        $storagePath = "messages/{$request->user()->id}/{$conversation->id}";
        $image = Image::storeFromBase64($result['imageData'], $assistantMessage, $storagePath);

        $this->checkpointAutoSummarize($conversation, $assistantMessage->id);

        return [
            'content' => $parsed['content'],
            'emotion' => $parsed['emotion'],
            'intimate' => $parsed['intimate'],
            'pose' => $parsed['pose'],
            'image_url' => $image->url,
            'enhanced_prompt' => $enhancedPrompt,
        ];
    }

    private function stripForSpeech(string $text): string
    {
        $text = preg_replace('/\A(?:\s*\[[^\]\r\n]+\])+\s*/u', ' ', $text);
        $text = preg_replace('/\*+[^*]+\*+/', ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{2,}/', "\n", $text);

        return trim($text);
    }

    /**
     * Strips the assistant's leading expression tag from content — a bare
     * [name] tag means a pose for 3D avatar assistants, or an emotion
     * (optionally followed by [intimate]) for image-mode assistants. Only
     * one format is ever attempted per assistant: the two are mutually
     * exclusive by portrait type, so there's no ambiguity to resolve and
     * nothing for the model to disambiguate — used by the server-side-parsed
     * reply flows (image-gen reaction, background-change reaction, Discord),
     * which don't go through the frontend's client-side parsers.
     *
     * @return array{content: string, emotion: ?string, intimate: bool, pose: ?string}
     */
    private function extractExpressionTag(string $content, Assistant $assistantModel): array
    {
        if ($assistantModel->portrait_type === AssistantPortraitType::Avatar3D) {
            $pose = null;

            // Unlike emotion names, pose names aren't restricted to a single
            // letters-only word (e.g. "deer_dance", "happy hands") — match
            // anything up to the closing ], not [a-zA-Z]+. Only strip it when
            // it actually matches one of the assistant's configured poses —
            // otherwise a reply that happens to start with an unrelated
            // bracketed aside (e.g. "[Note] ...") would have that content
            // silently eaten. Matched case-insensitively but resolved to the
            // pose's actual stored name, since the frontend looks it up with
            // an exact match.
            if (preg_match('/^\[([^\]]+)\]/', $content, $match)) {
                $matchedText = trim($match[1]);
                $canonical = collect($assistantModel->promptPoseNames())
                    ->first(fn (string $name) => strcasecmp($name, $matchedText) === 0);

                if ($canonical !== null) {
                    $pose = $canonical;
                    $content = trim(substr($content, strlen($match[0])));
                }
            }

            return ['content' => $content, 'emotion' => null, 'intimate' => false, 'pose' => $pose];
        }

        $emotion = null;
        $intimate = false;

        if (preg_match('/^\[([a-zA-Z]+)\]/', $content, $match)) {
            $emotion = $match[1];
            $content = trim(substr($content, strlen($match[0])));
        }

        if (preg_match('/^\[intimate\]/i', $content, $match)) {
            $intimate = true;
            $content = trim(substr($content, strlen($match[0])));
        }

        return ['content' => $content, 'emotion' => $emotion, 'intimate' => $intimate, 'pose' => null];
    }

    /**
     * Gets Vera's natural in-character reaction to having just generated and sent an image,
     * using the same persona/emotion-tag context as a normal chat reply.
     */
    private function reactToGeneratedImage(Request $request, AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt, string $enhancedPrompt): LlmResponse
    {
        $assistantModel = $assistantUser->assistant;

        $excludedSections = ['opening_message', 'voice mode'];

        if ($conversation->discord_channel_id) {
            // Discord has no UI to render an emotion/pose tag against — same reasoning as the normal Discord reply flow.
            $excludedSections[] = 'emotion tags';
            $excludedSections[] = 'pose tags';
        }

        $director = new PromptDirector($assistantModel->prompt);
        $this->appendExpressionTags($director, $assistantModel, $excludedSections);
        $director->except($excludedSections);

        $archive = $assistantModel->archive;
        if ($archive) {
            $director->withRetrieval($rawPrompt, $archive->id);
        }

        $director->withLongTermMemory($conversation);

        $history = $conversation->messages()
            ->orderByDesc('created_at')
            ->take(self::MESSAGES_PER_PAGE)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content ?? ''])
            ->toArray();

        $history[] = [
            'role' => 'system',
            'content' => "[You just generated and are sending an image. What it depicts: \"{$enhancedPrompt}\"]",
        ];

        $llm = (new LlmManager)->forAssistantUser($assistantUser);

        return $llm->chat(messages: [
            ['role' => 'system', 'content' => $director->build()],
            ...$history,
        ]);
    }

    /**
     * Gets Vera's natural in-character reaction to the scene having just moved
     * to a new location, using the same persona/emotion-tag context as a
     * normal chat reply.
     */
    private function reactToBackgroundChange(AssistantUser $assistantUser, Conversation $conversation, string $description): LlmResponse
    {
        $assistantModel = $assistantUser->assistant;

        $excludedSections = ['opening_message', 'voice mode'];

        if ($conversation->discord_channel_id) {
            $excludedSections[] = 'emotion tags';
            $excludedSections[] = 'pose tags';
        }

        $director = new PromptDirector($assistantModel->prompt);
        $this->appendExpressionTags($director, $assistantModel, $excludedSections);
        $director->except($excludedSections);

        $archive = $assistantModel->archive;
        if ($archive) {
            $director->withRetrieval($description, $archive->id);
        }

        $director->withLongTermMemory($conversation);

        $history = $conversation->messages()
            ->orderByDesc('created_at')
            ->take(self::MESSAGES_PER_PAGE)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content ?? ''])
            ->toArray();

        $history[] = [
            'role' => 'system',
            'content' => "[The scene has just moved to a new location: \"{$description}\"]",
        ];

        $llm = (new LlmManager)->forAssistantUser($assistantUser);

        return $llm->chat(messages: [
            ['role' => 'system', 'content' => $director->build()],
            ...$history,
        ]);
    }

    public function sendDiscordMessage(Request $request, int $assistant): JsonResponse
    {
        $validated = $request->validate([
            'channel_id' => ['required', 'string'],
            'message_id' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'images' => ['sometimes', 'array'],
            'audio' => ['nullable', 'string'],
            'audioContentType' => ['required_with:audio', 'nullable', 'string'],
            'dm_username' => ['nullable', 'string'],
        ]);

        $assistantUser = $this->resolveAssistantUser($request, $assistant);

        if (! empty($validated['dm_username'])) {
            DiscordChannel::updateOrCreate(
                ['discord_channel_id' => $validated['channel_id']],
                ['discord_server_id' => null, 'name' => $validated['dm_username']],
            );
        }

        $forceVoice = false;
        $voiceCommandContent = $this->extractVoiceMessageCommand($validated['content'] ?? null);

        if ($voiceCommandContent !== null) {
            $forceVoice = true;
            $validated['content'] = $voiceCommandContent;
        }

        $content = $validated['content'] ?? '';
        $hasAudio = ! empty($validated['audio']);

        if ($hasAudio) {
            $audioBytes = base64_decode($validated['audio']);
            $filename = 'audio.'.$this->mimeToExtension($validated['audioContentType']);

            try {
                $stt = app(SttProvider::class);
                $transcription = $stt->transcribe($audioBytes, $filename);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 502);
            }

            if (trim($transcription) === '') {
                return response()->json(['content' => "Sorry, I couldn't make out what you said. Could you try again?"]);
            }

            $content = trim($content) !== ''
                ? trim($content).' '.$transcription
                : $transcription;
        }

        $conversation = $assistantUser->conversations()->firstOrCreate(
            ['discord_channel_id' => $validated['channel_id']],
            ['title' => 'New conversation'],
        );

        $message = $conversation->messages()->create([
            'role' => 'user',
            'discord_message_id' => $validated['message_id'] ?? null,
            'content' => $content,
        ]);

        if (! empty($validated['images'][0])) {
            $storagePath = "messages/{$request->user()->id}/{$conversation->id}";
            Image::storeFromBase64($validated['images'][0], $message, $storagePath);
        }

        $imageGenPrompt = $this->extractImageGenPrompt($validated['content'] ?? null);

        if ($imageGenPrompt !== null) {
            if ($imageGenPrompt === '') {
                return response()->json(['message' => 'Describe what image to generate after /create-image.'], 422);
            }

            try {
                $generated = $this->generateImageMessage($request, $assistantUser, $conversation, $imageGenPrompt);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 502);
            }

            if ($conversation->title === 'New conversation') {
                $conversation->update([
                    'title' => str($validated['content'] ?? '')->limit(50)->toString(),
                ]);
            }

            return response()->json([
                'content' => $generated['content'],
                'image_url' => $generated['image_url'],
            ]);
        }

        $assistantModel = $assistantUser->assistant;
        $archive = $assistantModel->archive;

        $settings = Settings::where('user_id', $assistantUser->user_id)
            ->where('assistant_id', $assistantUser->assistant_id)
            ->first();
        $voiceMode = $settings?->data['discordVoiceResponseMode'] ?? 'both';
        $willSynthesize = $forceVoice
            || ($hasAudio && in_array($voiceMode, ['both', 'voiceOnly'], true));

        $excludedSections = ['opening_message', 'emotion tags'];
        if (! $willSynthesize) {
            $excludedSections[] = 'voice mode';
        }

        $director = (new PromptDirector($assistantModel->prompt))
            ->except($excludedSections);

        if ($archive && ! empty($content)) {
            $director->withRetrieval($content, $archive->id);
        }

        $director->withLongTermMemory($conversation);
        $director->withDiscordEnvironment($conversation, $assistantUser);

        $systemPrompt = $director->build();

        $ownMessages = $conversation->messages()
            ->orderBy('created_at')
            ->get(['role', 'content', 'discord_message_id', 'created_at'])
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
                'discord_message_id' => $m->discord_message_id,
                'created_at' => $m->created_at,
            ]);

        $siblingMessages = Conversation::query()
            ->whereHas('assistantUser', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('discord_channel_id', $validated['channel_id'])
            ->where('id', '!=', $conversation->id)
            ->with('assistantUser.assistant')
            ->get()
            ->flatMap(function (Conversation $sibling) {
                $assistantName = $sibling->assistantUser->assistant->name;

                return $sibling->messages()
                    ->get(['role', 'content', 'discord_message_id', 'created_at'])
                    ->map(fn ($m) => [
                        'role' => 'user',
                        'content' => $m->role === 'assistant' ? "{$assistantName}: {$m->content}" : $m->content,
                        'discord_message_id' => $m->discord_message_id,
                        'created_at' => $m->created_at,
                    ]);
            });

        $seenDiscordMessageIds = [];

        $history = $ownMessages
            ->concat($siblingMessages)
            ->sortBy('created_at')
            ->values()
            ->filter(function ($m) use (&$seenDiscordMessageIds) {
                if (! $m['discord_message_id']) {
                    return true;
                }

                if (in_array($m['discord_message_id'], $seenDiscordMessageIds, true)) {
                    return false;
                }

                $seenDiscordMessageIds[] = $m['discord_message_id'];

                return true;
            })
            ->map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']])
            ->values()
            ->toArray();

        if (! empty($validated['images'][0]) && count($history) > 0) {
            $lastIndex = array_key_last($history);
            $history[$lastIndex]['images'] = [$validated['images'][0]];
        }

        try {
            $llm = (new LlmManager)->forAssistantUser($assistantUser);
            $response = $llm->chat(messages: [
                ['role' => 'system', 'content' => $systemPrompt],
                ...$history,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $parsed = $this->extractExpressionTag($response->content, $assistantModel);

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $parsed['content'],
            'thinking' => $response->thinking,
            'emotion' => $parsed['emotion'] ?? 'neutral',
        ]);

        if ($conversation->title === 'New conversation') {
            $conversation->update([
                'title' => str($content)->limit(50)->toString(),
            ]);
        }

        $this->checkpointAutoSummarize($conversation, $assistantMessage->id);

        $responseData = $this->buildDiscordVoiceResponse(
            $parsed['content'],
            $assistantUser,
            $hasAudio,
            $forceVoice,
        );

        return response()->json($responseData);
    }

    private function checkpointAutoSummarize(Conversation $conversation, int $assistantMessageId): void
    {
        $checkpoint = $conversation->memory_checkpoint_message_id ?? 0;
        $pendingCount = $conversation->messages()->where('id', '>', $checkpoint)->count();

        if ($conversation->auto_summarize_enabled && $pendingCount >= self::MEMORY_SUMMARY_TRIGGER_COUNT) {
            $lockedAt = now()->toDateTimeString();

            $locked = $conversation->newQuery()
                ->whereKey($conversation->id)
                ->whereNull('memory_summarizing_at')
                ->update(['memory_summarizing_at' => $lockedAt]);

            if ($locked === 1) {
                SummarizeConversation::dispatch($conversation, $assistantMessageId, $lockedAt);
            }
        }
    }
}
