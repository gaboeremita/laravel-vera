<?php

namespace App\Actions;

use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\LlmResponseTagParser;
use Illuminate\Support\Collection;

/**
 * The previous messages of a conversation, read from storage, as the model
 * receives them on every path.
 */
class BuildConversationHistory
{
    public const CHAT_JUMP = 50;

    public const RESIDENT_JUMP = 30;

    private const COLUMNS = ['id', 'role', 'content', 'discord_message_id', 'created_at', 'speaker_type', 'speaker_id'];

    public function __construct(private readonly LlmResponseTagParser $tagParser) {}

    /**
     * @return list<array{role: string, content: string}>
     */
    public function handle(Conversation $conversation, Assistant $assistant, ?int $excludeMessageId = null): array
    {
        $messages = $this->stored($conversation, $excludeMessageId)
            ->map(fn (Message $message) => $this->forChat($message, $assistant))
            ->all();

        return $this->window($messages, self::CHAT_JUMP);
    }

    /**
     * A Discord channel's history: this conversation merged with the other
     * assistants' conversations in the same channel, each message once.
     *
     * @return list<array{role: string, content: string}>
     */
    public function forDiscordChannel(Conversation $conversation, Assistant $assistant, User $owner, Message $trigger): array
    {
        $own = $this->stored($conversation, $trigger->id)
            ->map(fn (Message $message) => [...$this->forChat($message, $assistant), 'discord_message_id' => $message->discord_message_id, 'created_at' => $message->created_at]);

        $siblings = Conversation::query()
            ->whereMorphedTo('owner', $owner)
            ->where('discord_channel_id', $conversation->discord_channel_id)
            ->where('id', '!=', $conversation->id)
            ->with('counterpart')
            ->get()
            ->flatMap(fn (Conversation $sibling) => $this->stored($sibling)->map(fn (Message $message) => [
                'role' => 'user',
                'content' => $message->role === 'assistant' ? "{$sibling->counterpart->name}: {$message->content}" : $message->content,
                'discord_message_id' => $message->discord_message_id,
                'created_at' => $message->created_at,
            ]));

        $seenDiscordMessageIds = $trigger->discord_message_id !== null ? [$trigger->discord_message_id] : [];

        $messages = $own->concat($siblings)
            ->sortBy('created_at')
            ->filter(function (array $message) use (&$seenDiscordMessageIds): bool {
                if ($message['discord_message_id'] === null) {
                    return true;
                }

                if (in_array($message['discord_message_id'], $seenDiscordMessageIds, true)) {
                    return false;
                }

                $seenDiscordMessageIds[] = $message['discord_message_id'];

                return true;
            })
            ->map(fn (array $message) => ['role' => $message['role'], 'content' => $message['content']])
            ->values()
            ->all();

        return $this->window($messages, self::CHAT_JUMP);
    }

    /**
     * The messages of a conversation between two residents, for the caller to
     * map to each speaker's point of view.
     *
     * @return list<Message>
     */
    public function residentMessages(Conversation $conversation): array
    {
        return $this->window($this->stored($conversation)->all(), self::RESIDENT_JUMP);
    }

    /**
     * Keeps the messages from the current history start on. The start only moves
     * forward in jumps of $jump once the history reaches twice that, so between
     * jumps every turn begins with the same message and the provider can reuse it.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    public function window(array $items, int $jump): array
    {
        $start = intdiv(max(0, count($items) - $jump), $jump) * $jump;

        return array_slice($items, $start);
    }

    /**
     * @return Collection<int, Message>
     */
    private function stored(Conversation $conversation, ?int $excludeMessageId = null): Collection
    {
        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->where(fn ($query) => $query
                ->where(fn ($withText) => $withText->whereNotNull('content')->where('content', '!=', ''))
                ->orWhereHas('video'))
            ->when($excludeMessageId !== null, fn ($query) => $query->where('id', '!=', $excludeMessageId))
            ->with('video')
            ->orderBy('id')
            ->get(self::COLUMNS)
            ->values();
    }

    /**
     * @return array{role: string, content: string}
     */
    private function forChat(Message $message, Assistant $assistant): array
    {
        $content = $message->role === 'assistant' ? $this->tagParser->parse($message->content ?? '', $assistant)['content'] : $message->content;

        if ($message->video !== null) {
            $content = trim("{$content}\n{$message->video->historyNote()}");
        }

        return [
            'role' => $message->role,
            'content' => $content,
        ];
    }
}
