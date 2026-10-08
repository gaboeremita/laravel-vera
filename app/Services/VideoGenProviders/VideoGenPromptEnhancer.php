<?php

namespace App\Services\VideoGenProviders;

use App\Directors\PromptDirector;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\VideoGenModel;
use App\Services\LlmProviders\LlmManager;
use Illuminate\Support\Facades\Log;

class VideoGenPromptEnhancer
{
    private const TASK_INSTRUCTION = <<<'PROMPT'
        Your task right now is different from a normal in-character reply: using everything above about who you are, the current scene, and the recent conversation, translate the request below into a single detailed, concrete prompt for a video generation model.
        Describe subject, appearance, setting, motion, camera movement, lighting, and style concretely, staying consistent with the established persona and what is actually happening in the conversation right now.
        A request to depict "you", "yourself", or addressed to you with a nickname or term of endearment means: generate a video of yourself, as described above. Terms of endearment in the request refer to you, and the subject is what the request asks to see.
        Reply with exactly one JSON object and nothing else, in this shape: {"description": string, "duration": integer or null, "aspect_ratio": string or null, "generate_audio": boolean or null}.
        "description" holds the video prompt. "duration" holds the length in seconds the request asks for, "aspect_ratio" the shape it asks for (for example "9:16" for vertical, "16:9" for widescreen, "1:1" for square), and "generate_audio" whether it asks for sound. Each of these three is null when the request leaves it open.
        PROMPT;

    private const HISTORY_LIMIT = 20;

    /**
     * @return array{description: string, duration: ?int, aspectRatio: ?string, generateAudio: ?bool}
     *
     * @throws \RuntimeException if the underlying LLM request fails
     */
    public function enhance(string $rawPrompt, AssistantUser $assistantUser, Conversation $conversation, ?VideoGenModel $videoGenModel = null): array
    {
        $llm = (new LlmManager)->forAssistantUser($assistantUser);

        $systemPrompt = $this->buildSystemPrompt($assistantUser, $conversation, $rawPrompt, $videoGenModel);
        $history = $this->recentHistory($conversation);

        $response = $llm->chat(messages: [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$history,
            ['role' => 'user', 'content' => $rawPrompt],
        ]);

        return $this->parse(trim($response->content), $rawPrompt);
    }

    private function buildSystemPrompt(AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt, ?VideoGenModel $videoGenModel): string
    {
        $director = (new PromptDirector($assistantUser->assistant->prompt))
            ->except(['emotion tags', 'secret trigger', 'creator mode', 'voice mode', 'OOC mode', 'image handling', 'style rules']);

        $archive = $assistantUser->assistant->archive;
        if ($archive) {
            $director->withRetrieval($rawPrompt, $archive->id);
        }

        $director->withLongTermMemory($conversation);

        foreach ($this->additionalPrompts($videoGenModel) as $additionalPrompt) {
            $director->append('video generation instructions', $additionalPrompt);
        }

        return $director->build()->fullText()."\n\n".self::TASK_INSTRUCTION;
    }

    /**
     * @return array{description: string, duration: ?int, aspectRatio: ?string, generateAudio: ?bool}
     */
    private function parse(string $reply, string $rawPrompt): array
    {
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $reply);
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! is_string($decoded['description'] ?? null) || trim($decoded['description']) === '') {
            Log::warning('Video description reply was not the expected JSON; using it as the description.', ['reply' => $reply]);

            return ['description' => $reply ?: $rawPrompt, 'duration' => null, 'aspectRatio' => null, 'generateAudio' => null];
        }

        return [
            'description' => trim($decoded['description']),
            'duration' => is_numeric($decoded['duration'] ?? null) ? (int) $decoded['duration'] : null,
            'aspectRatio' => is_string($decoded['aspect_ratio'] ?? null) ? $decoded['aspect_ratio'] : null,
            'generateAudio' => is_bool($decoded['generate_audio'] ?? null) ? $decoded['generate_audio'] : null,
        ];
    }

    /**
     * Skips the most recent message: it's the request itself, already
     * persisted before this runs, and is re-appended separately as the final turn.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function recentHistory(Conversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->orderByDesc('created_at')
            ->skip(1)
            ->take(self::HISTORY_LIMIT)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content ?? ''])
            ->all();
    }

    /**
     * @return string[]
     */
    private function additionalPrompts(?VideoGenModel $videoGenModel): array
    {
        if (! $videoGenModel?->provider) {
            return [];
        }

        return array_filter([
            $this->stringifyPrompt($videoGenModel->provider->prompt ?? null),
            $this->stringifyPrompt($videoGenModel->prompt ?? null),
        ]);
    }

    private function stringifyPrompt(mixed $prompt): ?string
    {
        if (empty($prompt)) {
            return null;
        }

        return is_array($prompt) ? implode("\n", $prompt) : (string) $prompt;
    }
}
