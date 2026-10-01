<?php

use App\Actions\ComposeChatRequest;
use App\DTOs\PromptLayout;

/**
 * @return list<array<string, mixed>>
 */
function composeWith(PromptLayout $layout, array $history, ?array $current): array
{
    return (new ComposeChatRequest)->handle($layout, $history, $current);
}

/**
 * @param  list<array<string, mixed>>  $messages
 */
function cachePointCount(array $messages): int
{
    return collect($messages)
        ->flatMap(fn (array $message) => is_array($message['content']) ? $message['content'] : [])
        ->filter(fn (array $part) => $part['cachePoint'] ?? false)
        ->count();
}

it('puts the unchanging part, then the memory parts, in one system message with a cache point after each group', function () {
    $messages = composeWith(new PromptLayout('# IDENTITY'."\n".'Vera.', ["# LONG-TERM MEMORY\nThey met.", "---\n\nThey argued."], ''), [], ['role' => 'user', 'content' => 'Hi.']);

    expect($messages[0]['role'])->toBe('system')
        ->and($messages[0]['content'])->toBe([
            ['type' => 'text', 'text' => "# IDENTITY\nVera.", 'cachePoint' => true],
            ['type' => 'text', 'text' => "# LONG-TERM MEMORY\nThey met.", 'cachePoint' => false],
            ['type' => 'text', 'text' => "---\n\nThey argued.", 'cachePoint' => true],
        ]);
});

it('marks only the unchanging part when there is no memory', function () {
    $messages = composeWith(new PromptLayout('# IDENTITY'."\n".'Vera.', [], ''), [], ['role' => 'user', 'content' => 'Hi.']);

    expect($messages[0]['content'])->toHaveCount(1)
        ->and(cachePointCount($messages))->toBe(1);
});

it('marks the last previous message and puts this turn\'s sections before the user\'s words in the current message', function () {
    $history = [['role' => 'user', 'content' => 'Hello.'], ['role' => 'assistant', 'content' => 'Hi!']];

    $messages = composeWith(new PromptLayout('U', ['M'], "# CURRENT STATE\nIn the library."), $history, ['role' => 'user', 'content' => 'How are you?', 'images' => ['aW1n']]);

    expect($messages[1])->toBe(['role' => 'user', 'content' => 'Hello.'])
        ->and($messages[2]['content'])->toBe([['type' => 'text', 'text' => 'Hi!', 'cachePoint' => true]])
        ->and($messages[3]['content'])->toBe([
            ['type' => 'text', 'text' => "# CURRENT STATE\nIn the library."],
            ['type' => 'text', 'text' => 'How are you?'],
        ])
        ->and($messages[3]['images'])->toBe(['aW1n'])
        ->and(cachePointCount($messages))->toBe(3);
});

it('sends only the user\'s words when there are no turn sections, and never more than three cache points', function () {
    $messages = composeWith(new PromptLayout('U', ['M1', 'M2', 'M3'], ''), [['role' => 'user', 'content' => 'a'], ['role' => 'assistant', 'content' => 'b']], ['role' => 'user', 'content' => 'c']);

    expect(end($messages)['content'])->toBe([['type' => 'text', 'text' => 'c']])
        ->and(cachePointCount($messages))->toBe(3);
});

it('sends this turn\'s sections as their own user message when nobody has spoken yet', function () {
    $messages = composeWith(new PromptLayout('U', [], "# CURRENT STATE\nAt the bar."), [], null);

    expect($messages)->toHaveCount(2)
        ->and($messages[1])->toBe(['role' => 'user', 'content' => [['type' => 'text', 'text' => "# CURRENT STATE\nAt the bar."]]]);
});
