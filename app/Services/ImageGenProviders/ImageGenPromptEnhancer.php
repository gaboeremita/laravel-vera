<?php

namespace App\Services\ImageGenProviders;

use App\Directors\PromptDirector;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\ImageGenModel;
use App\Services\LlmProviders\LlmManager;

class ImageGenPromptEnhancer
{
    private const TASK_INSTRUCTION = <<<'PROMPT'
        Your task right now is different from a normal in-character reply: using everything above about who you are, the current scene, and the recent conversation, translate the request below into a single detailed, concrete prompt for an image generation model.
        Describe subject, appearance, setting, composition, lighting, and style concretely, staying consistent with the established persona and what is actually happening in the conversation right now.
        A request to depict "you", "yourself", or addressed to you with a nickname or term of endearment means: generate an image of yourself, as described above. Terms of endearment used to address you are not the image subject.
        Respond with the enhanced image prompt only. No in-character reply, no preamble, no explanation.
        PROMPT;

    private const HISTORY_LIMIT = 20;

    /**
     * @throws \RuntimeException if the underlying LLM request fails
     */
    public function enhance(string $rawPrompt, AssistantUser $assistantUser, Conversation $conversation, ?ImageGenModel $imageGenModel = null): string
    {
        $llm = (new LlmManager)->forAssistantUser($assistantUser);

        $systemPrompt = $this->buildSystemPrompt($assistantUser, $conversation, $rawPrompt, $imageGenModel);
        $history = $this->recentHistory($conversation);

        $response = $llm->chat(messages: [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$history,
            ['role' => 'user', 'content' => $rawPrompt],
        ]);

        return trim($response->content) ?: $rawPrompt;
    }

    private function buildSystemPrompt(AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt, ?ImageGenModel $imageGenModel): string
    {
        $director = (new PromptDirector($assistantUser->assistant->prompt))
            ->except(['emotion tags', 'secret trigger', 'voice mode', 'OOC mode', 'image handling', 'style rules']);

        $archive = $assistantUser->user->archives()->first();
        if ($archive) {
            $director->withRetrieval($rawPrompt, $archive->id);
        }

        $director->withLongTermMemory($conversation);

        foreach ($this->additionalPrompts($imageGenModel) as $additionalPrompt) {
            $director->append('image generation instructions', $additionalPrompt);
        }

        return $director->build()."\n\n".self::TASK_INSTRUCTION;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    /**
     * Skips the most recent message: it's the raw /create-image command itself,
     * already persisted before this runs, and is re-appended separately as the final turn.
     */
    private function recentHistory(Conversation $conversation): array
    {
        return $conversation->messages()
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
    private function additionalPrompts(?ImageGenModel $imageGenModel): array
    {
        if (! $imageGenModel) {
            return [];
        }

        return array_filter([
            $this->stringifyPrompt($imageGenModel->provider->prompt ?? null),
            $this->stringifyPrompt($imageGenModel->prompt ?? null),
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
