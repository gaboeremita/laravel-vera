<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\TransferInventory;
use App\Contracts\AgentTool;
use App\Models\Inventory;
use App\Models\Item;
use RuntimeException;

/**
 * The answer to an item offered with offer_item: accepting puts it in the
 * resident's inventory.
 */
class AnswerOfferTool implements AgentTool
{
    public ?bool $accepted = null;

    public function __construct(
        public readonly Item $item,
        private readonly Inventory $receiver,
        private readonly string $offererName,
    ) {}

    public function name(): string
    {
        return 'answer_offer';
    }

    public function description(): string
    {
        return "Accepts or refuses {$this->item->name}, which {$this->offererName} has just offered you. You decide in character, from everything you know and feel about {$this->offererName}. Call it in this reply, and give your answer in your words as well.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'accept' => ['type' => 'boolean', 'description' => 'Whether you accept it.'],
            ],
            'required' => ['accept'],
        ];
    }

    public function handle(array $arguments): array
    {
        if ($this->accepted !== null) {
            throw new RuntimeException('You have already answered.');
        }

        $this->accepted = (bool) ($arguments['accept'] ?? false);

        if (! $this->accepted) {
            return ['status' => 'refused', 'note' => "You refuse {$this->item->name}."];
        }

        app(TransferInventory::class)->handle(null, $this->receiver, 0, [$this->item->id => 1], 'offer');

        return ['status' => 'accepted', 'note' => "You accept {$this->item->name}. It is yours now."];
    }

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    public function retryAttempts(): int
    {
        return 1;
    }
}
