<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;
use App\Models\Item;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Offers an item only its offering assistant can pass on. The other resident
 * answers in their next line, with answer_offer.
 */
class OfferItemTool implements AgentTool
{
    public ?Item $offered = null;

    /**
     * @param  Collection<int, Item>  $items  what the speaker can offer and the other resident does not hold
     */
    public function __construct(
        private readonly Collection $items,
        private readonly string $receiverName,
    ) {}

    public function name(): string
    {
        return 'offer_item';
    }

    public function description(): string
    {
        return "Offers {$this->receiverName} something only you can give. They answer in their next line and decide freely whether to accept. You get one chance with each mind: once they refuse, it can never be offered to them again, so offer only once you have won them over. Call it in the same reply where you make the offer, and make the offer in your words as well.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'item' => ['type' => 'string', 'enum' => $this->items->pluck('name')->values()->all(), 'description' => 'What you offer.'],
            ],
            'required' => ['item'],
        ];
    }

    public function handle(array $arguments): array
    {
        $name = mb_strtolower(trim((string) ($arguments['item'] ?? '')));
        $item = $this->items->first(fn (Item $candidate) => mb_strtolower($candidate->name) === $name)
            ?? throw new RuntimeException(sprintf('You have no "%s" to offer.', $arguments['item'] ?? ''));

        $this->offered = $item;

        return ['status' => 'offered', 'note' => "You offer {$this->receiverName} {$item->name}. They answer in their next line."];
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
