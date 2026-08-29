<?php

namespace App\Services\AvatarBackground;

use App\Directors\PromptDirector;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Services\LlmProviders\LlmManager;
use Illuminate\Support\Facades\Log;

class AvatarBackgroundPromptEnhancer
{
    // The surroundings aspect ratio (3:2) below matches the backdrop cylinder
    // in resources/js/components/VrmAvatar.jsx, where BACKDROP_THETA_LENGTH is
    // derived as (BACKDROP_HEIGHT * 1.5) / BACKDROP_RADIUS specifically so the
    // visible arc's width-to-height ratio (radius * thetaLength / height)
    // always works out to 1.5:1 regardless of how those two are tuned — the
    // two aren't otherwise linked, so if that ratio ever changes, this needs
    // updating to match.
    private const TASK_INSTRUCTION = <<<'PROMPT'
        Your task right now is different from a normal in-character reply: using everything above about who you are, the current scene, and the recent conversation, translate the setting description below into two separate, detailed, concrete image-generation prompts for the same location — staying consistent with the established persona and what is actually happening in the conversation right now. Both prompts call for anime-style illustration artwork, matching the visual style of the character described above.
        The first prompt is for the FLOOR: the flat ground surface the character stands on. Describe it as a flat, two-dimensional graphic texture design of that material (tile, stone, wood, metal panel, or whatever fits the scene) — like a game texture asset or wallpaper pattern rather than a photograph — viewed from directly overhead and from far enough away that the complete pattern reads as one self-contained image filling the entire frame edge to edge, with every line and edge running perfectly parallel across the frame. Light it with perfectly flat, uniform illumination, so the whole surface reads as one consistent plane of color and pattern with the same brightness everywhere.
        The second prompt describes the wide surrounding environment as seen from that location — the walls, windows, architecture, and scenery of the place itself, filling the entire frame from eye-level upward — framed from a standing person's eye-level, looking straight ahead at a natural, level, horizontal angle, at a comfortably close distance that fills the frame with detail rather than a wide establishing shot of the whole room, in a landscape aspect ratio of about 3:2 (roughly 1.57:1, wider than tall). Populate it exactly as the setting itself calls for — a crowd if it's a busy public place, one or two other people if that fits, or nobody at all if it's private or solitary — matching what's actually happening in the conversation right now. This is the view from the observer's own eyes, so whoever appears is someone other than the observer and other than the character themselves, since both are already accounted for separately. The floor this environment sits on is handled by a separate image entirely, so this one stays focused on what's at eye-level and above.
        Write your response as two labeled sections, giving each prompt as much descriptive detail as it needs. Start the first section on its own line with FLOOR:, followed by that prompt's full text. Start the second section on its own line with SURROUNDINGS:, followed by that prompt's full text.
        PROMPT;

    private const HISTORY_LIMIT = 20;

    /**
     * @return array{floor: string, surroundings: string}
     *
     * @throws \RuntimeException if the underlying LLM request fails
     */
    public function enhance(string $rawDescription, AssistantUser $assistantUser, Conversation $conversation): array
    {
        $llm = (new LlmManager)->forAssistantUser($assistantUser);

        $systemPrompt = $this->buildSystemPrompt($assistantUser, $conversation, $rawDescription);
        $history = $this->recentHistory($conversation);

        // Ending on a system-role instruction (not a trailing "user" turn) matters here:
        // by the time this async job runs, history already contains the completed
        // request-and-reply exchange that triggered it (e.g. the /change-background
        // command and the in-character reaction to it), so a bare final "user" message
        // reads as scene dialogue to continue rather than a distinct task — mirrors the
        // same fix ConversationController::reactToGeneratedImage() already relies on.
        $history[] = [
            'role' => 'system',
            'content' => self::TASK_INSTRUCTION."\n\nSetting description: \"{$rawDescription}\"",
        ];

        $response = $llm->chat(messages: [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$history,
        ]);

        $prompts = $this->parsePrompts($response->content, $rawDescription);

        Log::info('Avatar background prompts', [
            'raw_description' => $rawDescription,
            'llm_response' => $response->content,
            'floor_prompt' => $prompts['floor'],
            'surroundings_prompt' => $prompts['surroundings'],
        ]);

        return $prompts;
    }

    private function buildSystemPrompt(AssistantUser $assistantUser, Conversation $conversation, string $rawDescription): string
    {
        $director = (new PromptDirector($assistantUser->assistant->prompt))
            ->except(['emotion tags', 'secret trigger', 'voice mode', 'OOC mode', 'image handling', 'style rules', 'background tags']);

        $archive = $assistantUser->assistant->archive;
        if ($archive) {
            $director->withRetrieval($rawDescription, $archive->id);
        }

        $director->withLongTermMemory($conversation);

        return $director->build();
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function recentHistory(Conversation $conversation): array
    {
        return $conversation->messages()
            ->orderByDesc('created_at')
            ->take(self::HISTORY_LIMIT)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content ?? ''])
            ->all();
    }

    /**
     * @return array{floor: string, surroundings: string}
     */
    private function parsePrompts(string $content, string $fallback): array
    {
        $floor = $this->extractSection($content, 'FLOOR', 'SURROUNDINGS');
        $surroundings = $this->extractSection($content, 'SURROUNDINGS', null);

        return [
            'floor' => $floor ?: $fallback,
            'surroundings' => $surroundings ?: $fallback,
        ];
    }

    /**
     * Extracts everything after a "LABEL:" marker up to the next labeled
     * section (or the end of the response), so the LLM can write as many
     * lines as a prompt needs rather than being forced onto one line.
     */
    private function extractSection(string $content, string $label, ?string $nextLabel): ?string
    {
        $boundary = $nextLabel ? '(?=\n?\s*'.$nextLabel.':)' : '\z';
        $pattern = '/'.$label.':\s*(.*?)'.$boundary.'/is';

        if (preg_match($pattern, $content, $match)) {
            return trim($match[1]);
        }

        return null;
    }
}
