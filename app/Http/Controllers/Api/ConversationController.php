<?php

namespace App\Http\Controllers\Api;

use App\Actions\AppendExpressionTags;
use App\Actions\AppendWorldConversationContext;
use App\Actions\ApplyResidentZoneAccess;
use App\Actions\BuildFactsPrompt;
use App\Actions\BuildFeelingsPrompt;
use App\Actions\BuildInventoryPrompt;
use App\Actions\BuildQuestsPrompt;
use App\Actions\CreatorModeTags;
use App\Actions\Quests\OfferMoment;
use App\Actions\ResolveInventory;
use App\Actions\ResolveSpotStacking;
use App\Actions\ResolveUserActivity;
use App\Actions\ResolveWorldState;
use App\Actions\SummarizeLearnedFact;
use App\Contracts\AgentTool;
use App\Contracts\SttProvider;
use App\Directors\PromptDirector;
use App\Actions\TermRules\MarkTermRules;
use App\DTOs\LlmResponse;
use App\Enums\AssistantKind;
use App\Enums\AssistantMode;
use App\Enums\AssistantPortraitType;
use App\Enums\Posture;
use App\Enums\TurnMode;
use App\Events\Quests\PlayerTalkedTo;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAvatarBackground;
use App\Jobs\SummarizeConversation;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\DiscordChannel;
use App\Models\Image;
use App\Models\Inventory;
use App\Models\KnownFact;
use App\Models\Message;
use App\Models\Region;
use App\Models\ResidentFeeling;
use App\Models\Settings;
use App\Models\World;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldUser;
use App\Services\AgentLoop\AgentLoopRunner;
use App\Services\AgentLoop\Tools\BasicCalculatorTool;
use App\Services\AgentLoop\Tools\ChangeBackgroundTool;
use App\Services\AgentLoop\Tools\GetCurrentDatetimeTool;
use App\Services\AgentLoop\Tools\ImageGenerationTool;
use App\Services\AgentLoop\Tools\World\AcknowledgeTool;
use App\Services\AgentLoop\Tools\World\ActivityGate;
use App\Services\AgentLoop\Tools\World\AdjustFeelingsTool;
use App\Services\AgentLoop\Tools\World\AskForTool;
use App\Services\AgentLoop\Tools\World\AssessQuestTool;
use App\Services\AgentLoop\Tools\World\CheckHoldsTool;
use App\Services\AgentLoop\Tools\World\CheckOfferConditionTool;
use App\Services\AgentLoop\Tools\World\EditQuestTool;
use App\Services\AgentLoop\Tools\World\EndQuestTool;
use App\Services\AgentLoop\Tools\World\GiveTool;
use App\Services\AgentLoop\Tools\World\GrantFlagTool;
use App\Services\AgentLoop\Tools\World\GrantTool;
use App\Services\AgentLoop\Tools\World\OfferQuestTool;
use App\Services\AgentLoop\Tools\World\RemoveTool;
use App\Services\AgentLoop\Tools\World\ResetQuestTool;
use App\Services\AgentLoop\Tools\World\RevealTool;
use App\Services\AgentLoop\Tools\World\SetBeatTool;
use App\Services\AgentLoop\Tools\World\SetFactKnownTool;
use App\Services\AgentLoop\Tools\World\SetQuestFlagTool;
use App\Services\AgentLoop\Tools\World\SignalQuestionTool;
use App\Services\AgentLoop\Tools\World\StartQuestTool;
use App\Services\AgentLoop\Tools\World\WorldToolbox;
use App\Services\ImageGenProviders\ImageGenerationService;
use App\Services\LlmProviders\LlmManager;
use App\Services\LlmResponseTagParser;
use App\Services\TtsProviders\TtsManager;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    use ResolvesAssistantUser;

    private const MESSAGES_PER_PAGE = 50;

    private const MEMORY_SUMMARY_TRIGGER_COUNT = 50;

    private const IMAGE_GEN_COMMAND = '/create-image ';

    private const COMMANDS = ['/create-image', '/change-background', '/send-voice-message'];

    private const TTS_TRUNCATION_LENGTH = 200;

    private const CREATOR_MODE_ON = 'Creator mode is on.';

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
            'regionId' => ['nullable', 'integer', 'required_with:worldId'],
            'worldSessionId' => ['nullable', 'integer', 'required_with:positions'],
            'positions' => ['nullable', 'array'],
            'positions.user' => ['sometimes', 'array:x,y,z'],
            'positions.user.x' => ['required_with:positions.user', 'numeric'],
            'positions.user.y' => ['required_with:positions.user', 'numeric'],
            'positions.user.z' => ['required_with:positions.user', 'numeric'],
            'positions.residents' => ['sometimes', 'array'],
            'positions.residents.*' => ['array:x,y,z'],
            'positions.residents.*.x' => ['required', 'numeric'],
            'positions.residents.*.y' => ['required', 'numeric'],
            'positions.residents.*.z' => ['required', 'numeric'],
            'residentPosture' => ['nullable', Rule::enum(Posture::class)],
            'occupiedSpots' => ['nullable', 'array'],
            'occupiedSpots.*' => ['string', 'max:100'],
            ...ResolveUserActivity::rules(),
            ...ResolveUserActivity::rules('residentState'),
            ...ResolveSpotStacking::rules(),
        ]);

        $assistantUser = $this->resolveAssistantUser($request, $assistant);

        $conversation = $assistantUser
            ->conversations()
            ->findOrFail($id);

        $lastUserMessage = collect($validated['messages'])->last(fn ($m) => $m['role'] === 'user');

        $unknownCommand = $this->unknownCommand($lastUserMessage['content'] ?? null);
        if ($unknownCommand !== null) {
            return response()->json(['message' => "Unknown command {$unknownCommand}. Available commands: ".implode(', ', self::COMMANDS).'.'], 422);
        }

        $forceVoice = false;
        $voiceCommandContent = $this->extractVoiceMessageCommand($lastUserMessage['content'] ?? null);
        if ($voiceCommandContent !== null) {
            $lastUserMessage['content'] = $voiceCommandContent;
            $forceVoice = true;

            $messages = $validated['messages'];
            for ($i = count($messages) - 1; $i >= 0; $i--) {
                if ($messages[$i]['role'] === 'user') {
                    $messages[$i]['content'] = $voiceCommandContent;
                    break;
                }
            }
            $validated['messages'] = $messages;
        }

        $creatorModeTags = app(CreatorModeTags::class);
        $creatorNotice = null;
        $password = $creatorModeTags->password($lastUserMessage['content'] ?? '');
        if ($password !== null) {
            $creatorNotice = $this->activateCreatorMode($request, $conversation, $password);
        }
        $creatorTurn = $conversation->creator_mode_at !== null && $creatorModeTags->hasCommand($lastUserMessage['content'] ?? '');
        $lastUserIndex = array_key_last(array_filter($validated['messages'], fn (array $message) => $message['role'] === 'user'));
        foreach ($validated['messages'] as $index => $message) {
            if ($message['role'] === 'user') {
                $validated['messages'][$index]['content'] = $creatorModeTags->withoutActivations($message['content'] ?? '', $index === $lastUserIndex && $creatorNotice === self::CREATOR_MODE_ON);
            }
        }
        if ($lastUserMessage) {
            $lastUserMessage['content'] = $validated['messages'][$lastUserIndex]['content'];
        }
        $creatorMode = ['active' => $conversation->creator_mode_at !== null, 'notice' => $creatorNotice];

        if ($password !== null && $creatorNotice !== self::CREATOR_MODE_ON && trim($lastUserMessage['content'] ?? '') === '' && empty($lastUserMessage['images'][0])) {
            return response()->json(['conversation_id' => $conversation->id, 'content' => null, 'userContent' => '', 'creatorMode' => $creatorMode]);
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
                'expression' => Message::expressionFrom($parsed),
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

        $excludedSections = ['opening_message', ...$this->creatorSectionsExcluded($conversation)];

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

        $region = $world?->regions()->findOrFail($validated['regionId']);

        $worldSession = null;
        if ($world !== null && isset($validated['worldSessionId'])) {
            $worldSession = WorldUser::where('world_id', $world->id)->where('user_id', $request->user()->id)->firstOrFail()
                ->sessions()->findOrFail($validated['worldSessionId']);
            abort_if($worldSession->region_id !== null && $worldSession->region_id !== $region->id, 422, 'The session is not in this region.');
        }

        $userActivity = $region !== null ? app(ResolveUserActivity::class)->handle($region, $validated['userState'] ?? null) : null;
        $residentActivity = $region !== null ? app(ResolveUserActivity::class)->handle($region, $validated['residentState'] ?? null, 'residentState') : null;
        $prompt = app(AppendWorldConversationContext::class)->handle($assistantModel, $region, $validated['positions'] ?? null, $worldSession, $userActivity, $validated['stackedSpots'] ?? [], residentActivity: $residentActivity);
        $director = new PromptDirector($prompt);
        app(AppendExpressionTags::class)->handle($director, $assistantModel, $excludedSections, Posture::from($validated['residentPosture'] ?? Posture::Standing->value));

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

        $playerInventory = null;
        $residentInventory = null;
        if ($worldSession !== null) {
            $inventoryResident = $world->residents()->where('assistant_id', $assistantModel->id)->first();
            if ($inventoryResident !== null) {
                $playerInventory = app(ResolveInventory::class)->forPlayer($worldSession);
                $residentInventory = app(ResolveInventory::class)->forResident($worldSession, $inventoryResident);
                $director->append('inventory', app(BuildInventoryPrompt::class)->handle($residentInventory));
            }
        }
        $playerBefore = $playerInventory?->summary();
        $askForTool = null;

        $llmManager = new LlmManager;
        $aiModel = $llmManager->resolveModelForAssistantUser($assistantUser);
        $turnMode = $creatorTurn ? TurnMode::Creator : (app(LlmResponseTagParser::class)->hasOutOfCharacter($lastUserMessage['content'] ?? '') ? TurnMode::OocTurn : TurnMode::InCharacter);
        $factsResident = $worldSession !== null && $aiModel?->supports_tools ? $inventoryResident : null;
        if ($factsResident !== null) {
            $factsPrompt = app(BuildFactsPrompt::class)->handle($worldSession, $factsResident, $turnMode);
            if ($factsPrompt !== null) {
                $director->append('facts', $factsPrompt);
            }
        }
        $factTools = [];
        $questsResident = $worldSession !== null ? $inventoryResident : null;
        if ($questsResident !== null) {
            $questsPrompt = app(BuildQuestsPrompt::class)->handle($worldSession, $questsResident, $turnMode, (bool) $aiModel?->supports_tools);
            if ($questsPrompt !== null) {
                $director->append('quests', $questsPrompt);
            }
            $residentFeeling = ResidentFeeling::of($worldSession, $questsResident);
            $feelingsPrompt = app(BuildFeelingsPrompt::class)->handle($residentFeeling, $turnMode, (bool) $aiModel?->supports_tools);
            if ($feelingsPrompt !== null) {
                $director->append('feelings', $feelingsPrompt);
            }
        }
        $questTools = [];

        $systemPrompt = $director->build();

        $markedMessage = $lastUserIndex !== null ? app(MarkTermRules::class)->forAssistant($assistantModel, $validated['messages'][$lastUserIndex]['content'] ?? '') : null;
        if ($markedMessage !== null) {
            $validated['messages'][$lastUserIndex]['content'] = $markedMessage->text;
        }

        $tts = $voiceModel ? (new TtsManager)->fromModel($voiceModel) : null;
        $agentToolCalls = null;

        try {
            $llm = $aiModel ? $llmManager->fromModel($aiModel) : $llmManager->fromConfig();

            $tools = [];

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

                    if ($assistantModel->portrait_type === AssistantPortraitType::Avatar3D) {
                        $tools[] = new ChangeBackgroundTool($assistantUser, $conversation);
                    }
                }
            }

            $worldToolbox = null;
            if ($region !== null && ! empty($region->layout['zones'])) {
                if ($assistantModel->kind !== AssistantKind::WorldNpc && ! $aiModel?->supports_tools) {
                    return response()->json(['message' => 'Assistants living in a world need a model that supports tool calling. Choose one in this assistant\'s settings.'], 422);
                }

                $resident = $world->residents()->where('assistant_id', $assistantModel->id)->firstOrFail();
                $residentRegion = $creatorTurn ? $region : app(ApplyResidentZoneAccess::class)->handle($region, $resident);
                $residentPoint = $validated['positions']['residents'][$resident->id] ?? null;
                $worldToolbox = new WorldToolbox(
                    $residentRegion,
                    $residentPoint !== null ? app(ResolveWorldState::class)->locate($residentRegion->layout, $residentPoint)['zoneChain'] : [],
                    occupiedSpots: $validated['occupiedSpots'] ?? [],
                    posePostures: $assistantModel->posturesByPoseName(),
                    residentPoint: $residentPoint,
                    staysAtPost: ! $creatorTurn && $resident->staysAtPost(),
                );
                if ($residentInventory !== null && ! $creatorTurn) {
                    $residentIdsInRoom = $residentPoint !== null ? app(ResolveWorldState::class)->residentIdsInRoom($region->layout ?? [], $residentPoint, $validated['positions']['residents'] ?? []) : [];
                    $worldToolbox->withActivityGate(new ActivityGate($region, $residentInventory, $residentIdsInRoom));
                }
                $tools = [...$tools, ...$worldToolbox->tools()];
            }

            if ($residentInventory !== null && $aiModel?->supports_tools) {
                $askForTool = new AskForTool($residentInventory, $conversation);
                $tools = [...$tools, new GiveTool($residentInventory, $playerInventory, 'the user'), $askForTool, new CheckHoldsTool($playerInventory)];
            }

            if ($factsResident !== null) {
                $factTools = $this->factTools($worldSession, $conversation, $factsResident, $turnMode, $region, $validated['positions']['residents'][$factsResident->id] ?? null);
                $questTools = $this->questTools($worldSession, $conversation, $factsResident, $turnMode, new OfferMoment($worldSession, $factsResident, $region, $validated['positions'] ?? null));
                $tools = [...$tools, ...array_values($factTools), ...array_values($questTools), new AdjustFeelingsTool(ResidentFeeling::of($worldSession, $factsResident), $turnMode)];
            }

            if ($tools !== []) {
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
                $response = new LlmResponse(content: $agentResult->content, thinking: $agentResult->thinking);
                $usage = $agentResult->usage;
            } else {
                $response = $llm->chat(
                    messages: [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ...$validated['messages'],
                    ],
                    options: $tts?->llmOptions() ?? [],
                );
                $usage = $response->usage !== null ? [$response->usage] : [];
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $content = $markedMessage?->restore($response->content) ?? $response->content;

        $ttsInstructions = null;
        if ($tts) {
            $result = $tts->parseLlmResponse($content);
            $content = $result->content;
            $ttsInstructions = $result->ttsInstructions;
        }

        $action = $worldToolbox?->chosenAction();

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
            'thinking' => $response->thinking,
            'tool_calls' => $agentToolCalls,
            'expression' => Message::expressionFrom(app(LlmResponseTagParser::class)->parse($content, $assistantModel), $action),
        ]);

        $this->checkpointAutoSummarize($conversation, $assistantMessage->id);

        $learnedFacts = $this->learnedFacts($factTools, $world, $assistantModel, $content);

        $talkedTo = $worldSession !== null ? $world->residents()->where('assistant_id', $assistantModel->id)->first() : null;
        if ($talkedTo !== null) {
            PlayerTalkedTo::dispatch($worldSession->id, $talkedTo->id);
        }

        $audioBase64 = null;
        $audioContentType = null;
        $audioError = null;

        if ($forceVoice) {
            try {
                $ttsManager = app(TtsManager::class);
                $tts = $ttsManager->forAssistantUser($assistantUser);
                $ttsText = mb_substr($this->stripForSpeech($content), 0, self::TTS_TRUNCATION_LENGTH);
                $audioBytes = $tts->synthesize($ttsText, voice: $ttsManager->resolveVoice($assistantUser));
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
            'system_prompt' => $systemPrompt,
            'usage' => $usage,
            'tts_instructions' => $ttsInstructions,
            'tool_calls' => $agentToolCalls,
            'action' => $action,
            'audioBase64' => $audioBase64,
            'audioContentType' => $audioContentType,
            'audioError' => $audioError,
            ...($playerInventory !== null ? ['inventory' => $playerAfter = $playerInventory->summary(), 'changes' => Inventory::changesBetween($playerBefore, $playerAfter)] : []),
            ...($askForTool?->request !== null ? ['handoverRequest' => $askForTool->request->toPayload($playerInventory)] : []),
            ...(($questTools['offer_quest'] ?? null)?->offer !== null ? ['questOffer' => $questTools['offer_quest']->offer->toPayload()] : []),
            'userContent' => $lastUserMessage['content'] ?? null,
            'creatorMode' => $creatorMode,
            ...($worldSession !== null ? ['learnedFacts' => $learnedFacts] : []),
            ...($markedMessage !== null && $markedMessage->matches !== [] && $assistantModel->termRuleSettings()['highlightMissing'] ? ['missingTerms' => $markedMessage->missingTerms($content)] : []),
        ]);
    }

    /**
     * Checks the typed password against the user's creator password and turns
     * creator mode on for this conversation when it matches.
     *
     * @return string the notice the player sees
     */
    private function activateCreatorMode(Request $request, Conversation $conversation, string $password): string
    {
        $hash = $request->user()->creator_password;
        if ($hash === null) {
            return 'Set a creator password in Settings first.';
        }
        if (! Hash::check($password, $hash)) {
            return 'Creator mode didn\'t activate.';
        }

        $conversation->update(['creator_mode_at' => $conversation->creator_mode_at ?? now()]);

        return self::CREATOR_MODE_ON;
    }

    /**
     * The server checks the creator password itself, so the character never
     * gets the section that used to hold it, and gets the creator mode
     * section only while creator mode is on in this conversation.
     *
     * @return array<int, string>
     */
    private function creatorSectionsExcluded(Conversation $conversation): array
    {
        return $conversation->creator_mode_at === null ? ['secret trigger', 'creator mode'] : ['secret trigger'];
    }

    /**
     * The tools for secrets a resident keeps or wants to learn, keyed by name.
     *
     * @param  ?array{x: float, y: float, z: float}  $residentPoint
     * @return array<string, AgentTool>
     */
    private function factTools(WorldSession $session, Conversation $conversation, WorldResident $resident, TurnMode $mode, Region $region, ?array $residentPoint): array
    {
        $zone = $residentPoint !== null ? app(ResolveWorldState::class)->locate($region->layout ?? [], $residentPoint)['zone'] : null;
        $tools = [];

        if ($mode === TurnMode::Creator || $resident->facts()->exists()) {
            $tools['reveal'] = new RevealTool($session, $conversation, $resident, $mode, $region, $zone['name'] ?? $zone['id'] ?? null);
        }

        if ($mode === TurnMode::Creator) {
            return [...$tools, 'set_fact_known' => new SetFactKnownTool($session), 'grant' => new GrantTool($session), 'remove' => new RemoveTool($session)];
        }

        $acknowledge = new AcknowledgeTool($session, $resident);
        if ($acknowledge->knownFacts()->isNotEmpty()) {
            $tools['acknowledge'] = $acknowledge;
        }

        return $tools;
    }

    /**
     * The quest tools a resident has in the player's conversation, keyed by name.
     *
     * @return array<string, AgentTool>
     */
    private function questTools(WorldSession $session, Conversation $conversation, WorldResident $resident, TurnMode $mode, OfferMoment $moment): array
    {
        $tools = [];

        $grantFlag = new GrantFlagTool($session, $resident);
        if ($grantFlag->grantable()->isNotEmpty()) {
            $tools['grant_flag'] = $grantFlag;
        }

        $signalQuestion = new SignalQuestionTool($session, $conversation, $resident);
        if ($signalQuestion->signallable()->isNotEmpty()) {
            $tools['signal_question'] = $signalQuestion;
        }

        $offerQuest = new OfferQuestTool($session, $conversation, $resident, $moment);
        if ($offerQuest->offerable()->isNotEmpty()) {
            $tools['offer_quest'] = $offerQuest;
        }

        $checkOfferCondition = new CheckOfferConditionTool($offerQuest, $moment);
        if ($checkOfferCondition->checkable()->isNotEmpty()) {
            $tools['check_offer_condition'] = $checkOfferCondition;
            $offerQuest->recordChecksOf($checkOfferCondition);
        }

        if ($mode === TurnMode::Creator) {
            foreach ([new StartQuestTool($session), new EndQuestTool($session), new ResetQuestTool($session), new SetBeatTool($session), new SetQuestFlagTool($session), new AssessQuestTool($session), new EditQuestTool($session)] as $tool) {
                $tools[$tool->name()] = $tool;
            }
        }

        return $tools;
    }

    /**
     * The facts the player learned during this reply, with the summary of
     * what the character told them written for those they heard from them.
     *
     * @param  array<string, AgentTool>  $factTools
     * @return array<int, array{factId: int, topic: string, summary: string, sourceName: string, learnedAt: string}>
     */
    private function learnedFacts(array $factTools, ?World $world, Assistant $assistant, string $reply): array
    {
        $learned = [...($factTools['reveal']->learned ?? []), ...($factTools['set_fact_known']->learned ?? [])];
        if ($learned === []) {
            return [];
        }

        $tagParser = app(LlmResponseTagParser::class);
        $inStory = $tagParser->stripOutOfCharacter($tagParser->parse($reply, $assistant)['content']);

        return collect($learned)
            ->map(fn (KnownFact $known) => $known->summary === '' ? app(SummarizeLearnedFact::class)->handle($world, $known, $assistant->name, $inStory) : $known)
            ->map(fn (KnownFact $known) => $known->toPayload())
            ->values()
            ->all();
    }

    private function unknownCommand(?string $content): ?string
    {
        if (! preg_match('/^\/[^\s\/]+/', trim($content ?? ''), $match)) {
            return null;
        }

        return in_array(strtolower($match[0]), self::COMMANDS, true) ? null : $match[0];
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
                $audioBytes = $tts->synthesize($ttsText, voice: $ttsManager->resolveVoice($assistantUser));

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
            'expression' => Message::expressionFrom($parsed),
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
     * Extracts response metadata for flows that do not use the normal chat
     * response payload, such as image reactions, background-change reactions,
     * and Discord.
     *
     * @return array{content: string, emotion: ?string, intimate: bool, pose: ?string}
     */
    private function extractExpressionTag(string $content, Assistant $assistantModel): array
    {
        $parsed = app(LlmResponseTagParser::class)->parse($content, $assistantModel);

        return [
            'content' => $parsed['content'],
            'emotion' => $parsed['emotion'],
            'intimate' => $parsed['intimate'],
            'pose' => $parsed['pose'],
            'tags' => $parsed['tags'],
        ];
    }

    /**
     * Gets Vera's natural in-character reaction to having just generated and sent an image,
     * using the same persona/emotion-tag context as a normal chat reply.
     */
    private function reactToGeneratedImage(Request $request, AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt, string $enhancedPrompt): LlmResponse
    {
        $assistantModel = $assistantUser->assistant;

        $excludedSections = ['opening_message', 'voice mode', ...$this->creatorSectionsExcluded($conversation)];

        if ($conversation->discord_channel_id) {
            // Discord has no UI to render an emotion/pose tag against — same reasoning as the normal Discord reply flow.
            $excludedSections[] = 'emotion tags';
            $excludedSections[] = 'pose tags';
        }

        $director = new PromptDirector($assistantModel->prompt);
        app(AppendExpressionTags::class)->handle($director, $assistantModel, $excludedSections);
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

        $excludedSections = ['opening_message', 'voice mode', ...$this->creatorSectionsExcluded($conversation)];

        if ($conversation->discord_channel_id) {
            $excludedSections[] = 'emotion tags';
            $excludedSections[] = 'pose tags';
        }

        $director = new PromptDirector($assistantModel->prompt);
        app(AppendExpressionTags::class)->handle($director, $assistantModel, $excludedSections);
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
            'audio' => ['nullable', 'string', 'max:2000000'],
            'audioContentType' => ['required_with:audio', 'nullable', 'string'],
            'dm_username' => ['nullable', 'string'],
            'author_username' => ['nullable', 'string'],
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
            $audioBytes = base64_decode($validated['audio'], true);

            if ($audioBytes === false) {
                return response()->json(['message' => 'Invalid audio payload.'], 422);
            }

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

        $authorPrefix = '';
        if (empty($validated['dm_username']) && ! empty($validated['author_username']) && trim($content) !== '') {
            $authorPrefix = "{$validated['author_username']}: ";
            $content = $authorPrefix.$content;
        }

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

        $excludedSections = ['opening_message', 'emotion tags', 'secret trigger', 'creator mode'];
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
            ->whereMorphedTo('owner', $request->user())
            ->where('discord_channel_id', $validated['channel_id'])
            ->where('id', '!=', $conversation->id)
            ->with('counterpart')
            ->get()
            ->flatMap(function (Conversation $sibling) {
                $assistantName = $sibling->counterpart->name;

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

        $markedMessage = app(MarkTermRules::class)->forAssistant($assistantModel, substr($content, strlen($authorPrefix)));
        $triggerIndex = array_key_last(array_filter($history, fn (array $entry) => $entry['role'] === 'user' && $entry['content'] === $content));
        if ($markedMessage !== null && $triggerIndex !== null) {
            $history[$triggerIndex]['content'] = $authorPrefix.$markedMessage->text;
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

        $parsed = $this->extractExpressionTag($markedMessage?->restore($response->content) ?? $response->content, $assistantModel);

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $parsed['content'],
            'thinking' => $response->thinking,
            'expression' => Message::expressionFrom(['emotion' => 'neutral', ...array_filter($parsed, fn (mixed $value) => $value !== null)]),
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
