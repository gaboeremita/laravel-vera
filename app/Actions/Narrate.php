<?php

namespace App\Actions;

use App\Contracts\LlmProvider;
use App\Exceptions\NarratorUnavailable;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Region;
use App\Models\World;
use App\Services\AgentLoop\Tools\World\NarrateTool;
use App\Services\LlmProviders\LlmManager;
use InvalidArgumentException;
use RuntimeException;

/**
 * Judges a plain-language requirement and narrates what happens, for objects
 * and items, which have no character of their own to decide.
 */
class Narrate
{
    /**
     * @param  array<string, string>  $situation  labelled facts about the attempt
     * @return array{succeeded: bool, narration: string}
     *
     * @throws NarratorUnavailable when the world has no narrator model and the app has no default one
     */
    public function handle(World $world, ?Region $region, array $situation): array
    {
        $tool = new NarrateTool;
        $system = collect([
            "You are the narrator of {$world->name}, a role-playing world. {$world->description}",
            $region !== null ? "The scene is {$region->name}: {$region->description}" : null,
            'You judge whether an attempt succeeds, fairly and in the spirit of the world, from what the one trying carries, where they are and what they do. You describe what happens in two to four vivid sentences in second person, revealing any information the outcome gives. Answer by calling the narrate tool.',
        ])->filter()->implode("\n\n");
        $details = collect($situation)->map(fn (string $value, string $label) => "{$label}: {$value}")->implode("\n");

        $response = $this->llm($world)->chat(
            messages: [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $details]],
            tools: [['name' => $tool->name(), 'description' => $tool->description(), 'parameters' => $tool->parameters()]],
        );

        $call = collect($response->toolCalls)->firstWhere('name', $tool->name());
        if ($call === null) {
            throw new RuntimeException('The narrator did not give a verdict. Try again.');
        }

        return $tool->handle($call->arguments);
    }

    /**
     * What someone carries, as a line for the narrator.
     */
    public function holdings(Inventory $inventory): string
    {
        $items = $inventory->items()->with('item')->get()
            ->map(fn (InventoryItem $held) => ($held->quantity === null ? 'plenty of ' : ($held->quantity > 1 ? "{$held->quantity} " : '')).$held->item->name.' ('.$held->item->description.')');
        $credits = $inventory->credits === null ? 'unlimited credits' : "{$inventory->credits} credits";

        return $items->isEmpty() ? $credits : $credits.'; '.$items->implode('; ');
    }

    private function llm(World $world): LlmProvider
    {
        $llmManager = new LlmManager;
        $model = $world->narratorModel()->with('provider')->first();
        if ($model !== null) {
            return $llmManager->fromModel($model);
        }

        try {
            return $llmManager->fromConfig();
        } catch (InvalidArgumentException) {
            throw new NarratorUnavailable('No narrator model is set for this world. Choose one in the world\'s configuration.');
        }
    }
}
