<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Image;
use App\Models\Inventory;
use App\Models\Item;
use App\Services\ImageGenProviders\ImageGenerationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MakeItemTool implements AgentTool
{
    public function __construct(
        private readonly ImageGenerationService $imageGenerationService,
        private readonly AssistantUser $assistantUser,
        private readonly Conversation $conversation,
        private readonly Inventory $maker,
    ) {}

    public function name(): string
    {
        return 'make_item';
    }

    public function description(): string
    {
        $made = Item::where('world_session_id', $this->maker->world_session_id)->where('made_by_resident_id', $this->maker->world_resident_id)->orderBy('id')->pluck('name');

        return 'Makes something new with your own hands, such as a painting, a drawing or a sketch, and keeps it with you. Its picture comes from the picture you describe. Make something when you feel like it; it stays yours until you give it away.'
            .($made->isNotEmpty() ? ' What you have made so far: '.$made->implode(', ').'.' : '');
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'A short name for what you make, unique in this world.'],
                'description' => ['type' => 'string', 'description' => 'One or two sentences on what it is and what it shows, as whoever holds it sees it.'],
                'picture' => ['type' => 'string', 'description' => 'A description of the picture of it: subject, composition, colours and style.'],
            ],
            'required' => ['name', 'description', 'picture'],
        ];
    }

    public function handle(array $arguments): array
    {
        $name = mb_substr(trim((string) ($arguments['name'] ?? '')), 0, 255);
        $description = trim((string) ($arguments['description'] ?? ''));
        $picture = trim((string) ($arguments['picture'] ?? ''));
        if ($name === '' || $description === '' || $picture === '') {
            throw new RuntimeException('make_item needs a name, a description and a picture.');
        }

        $session = $this->maker->worldSession;
        if (Item::availableIn($session)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            throw new RuntimeException(sprintf('Something called "%s" already exists in this world; choose another name.', $name));
        }

        $result = $this->imageGenerationService->generate($this->assistantUser, $this->conversation, $picture);

        DB::transaction(function () use ($session, $name, $description, $result): void {
            $item = Item::create([
                'world_id' => $session->worldUser->world_id,
                'world_session_id' => $session->id,
                'made_by_resident_id' => $this->maker->world_resident_id,
                'name' => $name,
                'description' => $description,
            ]);
            Image::storeFromBase64($result['imageData'], $item, "worlds/{$item->world_id}/items/{$item->id}/card", 'card');
            $this->maker->items()->create(['item_id' => $item->id, 'quantity' => 1]);
        });

        return ['status' => 'made', 'note' => "You made {$name} and keep it with you; it goes by that name when you give it."];
    }

    public function timeoutSeconds(): int
    {
        return $this->imageGenerationService->resolveTimeoutFor($this->assistantUser) + 30;
    }

    public function retryAttempts(): int
    {
        return 1;
    }
}
