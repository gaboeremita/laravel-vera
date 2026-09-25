<?php

namespace App\Actions;

use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Str;

class RecallResidentMemory
{
    private const MEMORY_LENGTH = 1200;

    private const CONVERSATION_LINES = 8;

    /**
     * One memory picked at random from her sources: her long-term memory of
     * the user, a conversation with another resident, or her archive.
     *
     * @return ?array{from: string, memory: string}
     */
    public function handle(Assistant $assistant, User $user): ?array
    {
        return collect([
            fn () => $this->fromTimeWithUser($assistant, $user),
            fn () => $this->fromResidentConversation($assistant),
            fn () => $this->fromArchive($assistant),
        ])->shuffle()->map(fn (callable $source) => $source())->filter()->first();
    }

    /**
     * @return ?array{from: string, memory: string}
     */
    private function fromTimeWithUser(Assistant $assistant, User $user): ?array
    {
        $summary = Conversation::between($user, $assistant)->whereNotNull('long_term_memory')->inRandomOrder()->value('long_term_memory');
        $passages = collect(preg_split('/\n\s*\n/', (string) $summary))->map(fn (string $passage) => trim($passage))->filter();

        return $passages->isEmpty() ? null : ['from' => 'your time with the user', 'memory' => Str::limit($passages->random(), self::MEMORY_LENGTH)];
    }

    /**
     * @return ?array{from: string, memory: string}
     */
    private function fromResidentConversation(Assistant $assistant): ?array
    {
        $conversation = Conversation::involving($assistant)
            ->where('owner_type', $assistant->getMorphClass())
            ->where('counterpart_type', $assistant->getMorphClass())
            ->whereHas('messages')
            ->with(['owner', 'counterpart'])
            ->inRandomOrder()
            ->first();
        if ($conversation === null) {
            return null;
        }

        $other = $conversation->owner->is($assistant) ? $conversation->counterpart : $conversation->owner;
        $lines = $conversation->messages()->latest('id')->limit(self::CONVERSATION_LINES)->get(['speaker_id', 'content'])->reverse()
            ->map(fn ($message) => ($message->speaker_id === $assistant->id ? 'you: ' : "{$other->name}: ").$message->content)
            ->implode("\n");

        return ['from' => "a conversation with {$other->name}", 'memory' => Str::limit($lines, self::MEMORY_LENGTH)];
    }

    /**
     * @return ?array{from: string, memory: string}
     */
    private function fromArchive(Assistant $assistant): ?array
    {
        $entry = $assistant->archive?->entries()->inRandomOrder()->first(['title', 'content']);

        return $entry === null ? null : ['from' => "your archive: {$entry->title}", 'memory' => Str::limit((string) $entry->content, self::MEMORY_LENGTH)];
    }
}
