<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Inventory;
use App\Models\Item;
use RuntimeException;

class AskForTool implements AgentTool
{
    public ?HandoverRequest $request = null;

    public function __construct(
        private readonly Inventory $asker,
        private readonly Conversation $conversation,
    ) {}

    public function name(): string
    {
        return 'ask_for';
    }

    public function description(): string
    {
        return 'Asks the user to hand you credits or items, for a reason you state, such as a price, a fee or a trade. The user sees the request and accepts or declines it; the result reaches you as their next message. Ask for anything else in your reply.';
    }

    public function parameters(): array
    {
        $names = Item::where('world_id', $this->asker->worldSession->worldUser->world_id)->orderBy('name')->pluck('name')->all();

        return [
            'type' => 'object',
            'properties' => [
                'credits' => ['type' => 'integer', 'minimum' => 0, 'description' => 'How many credits you ask for.'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'item' => ['type' => 'string', ...($names !== [] ? ['enum' => $names] : []), 'description' => 'The name of the item.'],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                        ],
                        'required' => ['item', 'quantity'],
                    ],
                ],
                'reason' => ['type' => 'string', 'description' => 'What it is for, in a few words, e.g. "for the map".'],
            ],
            'required' => ['reason'],
        ];
    }

    public function handle(array $arguments): array
    {
        if ($this->request !== null) {
            throw new RuntimeException('You already asked for something this turn; wait for the answer.');
        }

        $worldId = $this->asker->worldSession->worldUser->world_id;
        $items = collect($arguments['items'] ?? [])->map(function (array $entry) use ($worldId): array {
            $item = Item::where('world_id', $worldId)->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) ($entry['item'] ?? '')))])->first()
                ?? throw new RuntimeException(sprintf('There is no item called "%s" in this world.', $entry['item'] ?? ''));

            return ['itemId' => $item->id, 'quantity' => max(1, (int) ($entry['quantity'] ?? 1))];
        })->values()->all();
        $credits = max(0, (int) ($arguments['credits'] ?? 0));
        if ($credits === 0 && $items === []) {
            throw new RuntimeException('Name the credits or items you ask for.');
        }

        $this->request = HandoverRequest::create([
            'world_session_id' => $this->asker->world_session_id,
            'conversation_id' => $this->conversation->id,
            'inventory_id' => $this->asker->id,
            'credits' => $credits,
            'items' => $items,
            'reason' => trim((string) ($arguments['reason'] ?? '')) ?: 'no reason given',
        ]);

        return ['status' => 'asked', 'note' => 'You asked; they will answer.'];
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
