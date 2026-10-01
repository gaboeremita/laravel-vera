<?php

use App\Actions\BuildConversationHistory;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Pose;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function historyBuilder(): BuildConversationHistory
{
    return app(BuildConversationHistory::class);
}

function seedTurns(Conversation $conversation, int $count, string $prefix = 'line'): void
{
    foreach (range(1, $count) as $number) {
        $conversation->messages()->create(['role' => $number % 2 === 1 ? 'user' : 'assistant', 'content' => "{$prefix} {$number}"]);
    }
}

it('starts the history at a fixed message and jumps forward in steps of the kept size', function (int $count, int $jump, int $start) {
    $items = range(1, $count);

    expect(historyBuilder()->window($items, $jump))->toBe(array_slice($items, $start));
})->with([
    'empty' => [0, 50, 0],
    '49 messages' => [49, 50, 0],
    '99 messages' => [99, 50, 0],
    '100 messages' => [100, 50, 50],
    '149 messages' => [149, 50, 50],
    '150 messages' => [150, 50, 100],
    '230 messages' => [230, 50, 150],
    'resident 59 messages' => [59, 30, 0],
    'resident 60 messages' => [60, 30, 30],
    'resident 95 messages' => [95, 30, 60],
]);

it('keeps the same first message on consecutive turns until the history jumps', function () {
    [, $assistant, $conversation] = setUpAgentAssistant('assistant');
    seedTurns($conversation, 120);

    $before = historyBuilder()->handle($conversation, $assistant);
    seedTurns($conversation, 2, 'later');
    $after = historyBuilder()->handle($conversation, $assistant);

    expect($before)->toHaveCount(70)
        ->and($after)->toHaveCount(72)
        ->and($after[0])->toBe($before[0])
        ->and($before[0]['content'])->toBe('line 51');
});

it('sends the assistant\'s earlier replies without their expression tags and the user\'s words as typed', function () {
    [, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'wave']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Hi [there]']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => '[pose: wave] Hello!']);
    $current = $conversation->messages()->create(['role' => 'user', 'content' => 'And now?']);

    expect(historyBuilder()->handle($conversation, $assistant, $current->id))->toBe([
        ['role' => 'user', 'content' => 'Hi [there]'],
        ['role' => 'assistant', 'content' => 'Hello!'],
    ]);
});

it('merges the other assistants of a Discord channel into the history once each, without the triggering message', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');
    $conversation->update(['discord_channel_id' => 'chan-1']);
    $other = Assistant::factory()->create(['name' => 'Yinlin']);
    $sibling = Conversation::factory()->create([
        'owner_type' => $user->getMorphClass(), 'owner_id' => $user->id,
        'counterpart_type' => $other->getMorphClass(), 'counterpart_id' => $other->id,
        'discord_channel_id' => 'chan-1',
    ]);

    $conversation->messages()->create(['role' => 'user', 'content' => 'gabe: hello all', 'discord_message_id' => 'd1']);
    $sibling->messages()->create(['role' => 'user', 'content' => 'gabe: hello all', 'discord_message_id' => 'd1']);
    $sibling->messages()->create(['role' => 'assistant', 'content' => 'Hi everyone.', 'discord_message_id' => 'd2']);
    $trigger = $conversation->messages()->create(['role' => 'user', 'content' => 'gabe: and you?', 'discord_message_id' => 'd3']);
    $sibling->messages()->create(['role' => 'user', 'content' => 'gabe: and you?', 'discord_message_id' => 'd3']);

    expect(historyBuilder()->forDiscordChannel($conversation->fresh(), $assistant, $user, $trigger))->toBe([
        ['role' => 'user', 'content' => 'gabe: hello all'],
        ['role' => 'user', 'content' => 'Yinlin: Hi everyone.'],
    ]);
});

it('gives a conversation between residents its own 60 and 30 history limit', function () {
    [, , $conversation] = setUpAgentAssistant('assistant');
    seedTurns($conversation, 70);

    $messages = historyBuilder()->residentMessages($conversation);

    expect($messages)->toHaveCount(40)
        ->and($messages[0]->content)->toBe('line 31');
});
