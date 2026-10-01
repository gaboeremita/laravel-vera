<?php

use App\Actions\TermRules\MarkTermRules;
use App\Actions\TermRules\ParseTermRules;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * @param  array{markTerms?: bool, swapInvariant?: bool, highlightMissing?: bool}  $settings
 * @return array{User, Assistant, Conversation}
 */
function termRulesChat(string $rules, array $settings, string $mode = 'assistant'): array
{
    return setUpAgentAssistant($mode, [
        'prompt' => ['identity' => 'An interpreter.', 'termRuleSection' => $rules],
        'agent_config' => ['termRules' => ['section' => 'termRuleSection', ...$settings]],
    ]);
}

/**
 * Stores every message but the last as the conversation so far, then sends the last one.
 *
 * @param  list<array{role: string, content: string}>  $messages
 */
function sendTermRulesMessage(User $user, Assistant $assistant, Conversation $conversation, array $messages): TestResponse
{
    $current = array_pop($messages);
    foreach ($messages as $message) {
        $conversation->messages()->create($message);
    }

    return test()->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => $current['content']]],
    );
}

function fakeTermRulesReply(string $reply): void
{
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse($reply))]);
}

/**
 * @return list<array{role: string, content: string}>
 */
function sentModelMessages(): array
{
    return Http::recorded()->first()[0]['messages'];
}

function sentLastUserContent(): string
{
    return collect(sentModelMessages())->last(fn (array $message) => $message['role'] === 'user')['content'];
}

describe('marking', function () {
    it('annotates the matched term only', function () {
        [$user, $assistant, $conversation] = termRulesChat("alpha -> uno\nbeta -> dos", ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'the alpha here']])->assertOk();

        expect(sentLastUserContent())->toBe('the [alpha -> uno] here');
    });

    it('annotates every occurrence of every matched term', function () {
        [$user, $assistant, $conversation] = termRulesChat("alpha -> uno\nbeta -> dos", ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'alpha and alpha and beta']]);

        expect(sentLastUserContent())->toBe('[alpha -> uno] and [alpha -> uno] and [beta -> dos]');
    });

    it('annotates a variant with the rule\'s target', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha, alphas -> uno, unos', ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'two alphas']]);

        expect(sentLastUserContent())->toBe('two [alphas -> uno]');
    });

    it('leaves a term inside a longer word unmarked', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> uno', ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'alphabet ñalpha alphaé']]);

        expect(sentLastUserContent())->toBe('alphabet ñalpha alphaé');
    });

    it('prefers the longest match where terms overlap', function () {
        [$user, $assistant, $conversation] = termRulesChat("court -> tribunal\nsupreme court -> corte suprema", ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'the supreme court and the court']]);

        expect(sentLastUserContent())->toBe('the [supreme court -> corte suprema] and the [court -> tribunal]');
    });

    it('matches case-insensitively unless the rule is marked case-sensitive', function () {
        [$user, $assistant, $conversation] = termRulesChat("alpha -> uno\nBeta -> dos (case)", ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'ALPHA beta Beta']]);

        expect(sentLastUserContent())->toBe('[ALPHA -> uno] beta [Beta -> dos]');
    });

    it('sends a message without rule terms unchanged', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> uno', ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'nothing to see']]);

        expect(sentLastUserContent())->toBe('nothing to see');
    });

    it('sends the message as typed with marking off, and the rules still reach the prompt', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> uno', []);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'the alpha here']]);

        $systemPrompt = collect(sentModelMessages())->firstWhere('role', 'system')['content'];
        expect(sentLastUserContent())->toBe('the alpha here')
            ->and($systemPrompt)->toContain('alpha -> uno');
    });

    it('marks only the newest user message', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> uno', ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [
            ['role' => 'user', 'content' => 'first alpha'],
            ['role' => 'assistant', 'content' => 'reply'],
            ['role' => 'user', 'content' => 'second alpha'],
        ]);

        $userMessages = collect(sentModelMessages())->where('role', 'user')->pluck('content')->values()->all();
        expect($userMessages)->toBe(['first alpha', 'second [alpha -> uno]']);
    });

    it('stores and returns the message as typed', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> uno', ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'the alpha here']])
            ->assertJsonPath('userContent', 'the alpha here');

        expect($conversation->messages()->where('role', 'user')->value('content'))->toBe('the alpha here');
    });

    it('sends the message unchanged when the picked section is missing', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> uno', ['markTerms' => true]);
        $assistant->update(['agent_config' => ['termRules' => ['section' => 'gone', 'markTerms' => true]]]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'the alpha here']]);

        expect(sentLastUserContent())->toBe('the alpha here');
    });

    it('uses the edited rules on the next message', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> uno', ['markTerms' => true]);
        fakeTermRulesReply('ok');
        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'alpha']]);

        $assistant->update(['prompt' => [...$assistant->prompt, 'termRuleSection' => 'alpha -> eins']]);
        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'alpha']]);

        $secondRequest = Http::recorded()->get(1)[0]['messages'];
        expect(collect($secondRequest)->last(fn (array $message) => $message['role'] === 'user')['content'])->toBe('[alpha -> eins]');
    });

    it('annotates a target-side term with the source term', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha, alphas -> uno, unos', ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'tengo unos aquí']]);

        expect(sentLastUserContent())->toBe('tengo [unos -> alpha] aquí');
    });

    it('reads a term on both sides of different rules as a source term', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> beta
beta -> gamma', ['markTerms' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'beta']]);

        expect(sentLastUserContent())->toBe('[beta -> gamma]');
    });

    it('parses and marks a message against 500 rules in under 50 ms', function () {
        $sectionText = collect(range(1, 500))->map(fn (int $index) => "term{$index}, terms{$index} -> target{$index}")->implode("\n");
        $message = collect(range(1, 40))->map(fn (int $index) => "word term{$index} other terms{$index}")->implode(' ');

        $startedAt = hrtime(true);
        $rules = app(ParseTermRules::class)->handle($sectionText)['rules'];
        $marked = app(MarkTermRules::class)->handle($message, $rules, markTerms: true, swapInvariant: false);
        $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;

        expect($marked->matches)->toHaveCount(80)
            ->and($elapsedMilliseconds)->toBeLessThan(50);
    });
});

describe('exact swap', function () {
    it('sends placeholders for invariant terms and swaps the exact target back', function () {
        [$user, $assistant, $conversation] = termRulesChat("acme -> ACME Corp (invariant)\nzeta -> Zeta Ltd (invariant)", ['swapInvariant' => true]);
        fakeTermRulesReply('⟦1⟧ and ⟦2⟧');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'visit acme and zeta']])
            ->assertJsonPath('content', 'ACME Corp and Zeta Ltd');

        expect(sentLastUserContent())->toBe('visit ⟦1⟧ and ⟦2⟧')
            ->and($conversation->messages()->where('role', 'assistant')->value('content'))->toBe('ACME Corp and Zeta Ltd');
    });

    it('swaps a target-side invariant term back to the exact source text', function () {
        [$user, $assistant, $conversation] = termRulesChat('acme -> ACME Corp (invariant)', ['swapInvariant' => true]);
        fakeTermRulesReply('visit ⟦1⟧');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'visite ACME Corp']])
            ->assertJsonPath('content', 'visit acme');

        expect(sentLastUserContent())->toBe('visite ⟦1⟧');
    });

    it('returns a reply without the placeholder as written', function () {
        [$user, $assistant, $conversation] = termRulesChat('acme -> ACME Corp (invariant)', ['swapInvariant' => true]);
        fakeTermRulesReply('something else');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'visit acme']])
            ->assertJsonPath('content', 'something else');
    });

    it('annotates non-invariant rules while swapping invariant ones', function () {
        [$user, $assistant, $conversation] = termRulesChat("acme -> ACME Corp (invariant)\nalpha -> uno", ['markTerms' => true, 'swapInvariant' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'alpha at acme']]);

        expect(sentLastUserContent())->toBe('[alpha -> uno] at ⟦1⟧');
    });

    it('adds no text of its own to the system prompt', function () {
        [$user, $assistant, $conversation] = termRulesChat('acme -> ACME Corp (invariant)', ['swapInvariant' => true]);
        fakeTermRulesReply('ok');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'visit acme']]);

        expect(collect(sentModelMessages())->firstWhere('role', 'system')['content'])->not->toContain('⟦');
    });

    it('swaps the target back on the agent loop too', function () {
        [$user, $assistant, $conversation] = termRulesChat('acme -> ACME Corp (invariant)', ['swapInvariant' => true], 'agent');
        fakeTermRulesReply('see ⟦1⟧');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'visit acme']])
            ->assertJsonPath('content', 'see ACME Corp');
    });

    it('marks the triggering Discord message and swaps the target back', function () {
        [$user, $assistant] = termRulesChat("acme -> ACME Corp (invariant)\nalpha -> uno", ['markTerms' => true, 'swapInvariant' => true]);
        fakeTermRulesReply('see ⟦1⟧');

        $this->actingAs($user)
            ->postJson(route('conversations.sendDiscordMessage', $assistant), [
                'channel_id' => 'channel-1',
                'content' => 'alpha at acme',
                'author_username' => 'someone',
            ])
            ->assertOk();

        $storedReply = Conversation::where('discord_channel_id', 'channel-1')->firstOrFail()->messages()->where('role', 'assistant')->value('content');
        expect(sentLastUserContent())->toBe('someone: [alpha -> uno] at ⟦1⟧')
            ->and($storedReply)->toBe('see ACME Corp');
    });
});

describe('missing-term highlight', function () {
    it('lists a missing target with its range in UTF-16 code units', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> uno', ['highlightMissing' => true]);
        fakeTermRulesReply('something else');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'é😀 alpha']])
            ->assertJsonPath('missingTerms', [['target' => 'uno', 'ranges' => [[4, 5]]]]);
    });

    it('returns an empty list when the target or a target variant is present', function (string $reply) {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> uno, unos', ['highlightMissing' => true]);
        fakeTermRulesReply($reply);

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'alpha']])
            ->assertJsonPath('missingTerms', []);
    })->with(['target' => 'here is uno', 'variant' => 'here are unos']);

    it('lists a missing source term for a target-side match', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha, alphas -> uno, unos', ['highlightMissing' => true]);
        fakeTermRulesReply('something else');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'dos unos']])
            ->assertJsonPath('missingTerms', [['target' => 'alpha', 'ranges' => [[4, 4]]]]);
    });

    it('checks the target\'s case exactly for case-sensitive rules', function () {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> Uno (case)', ['highlightMissing' => true]);
        fakeTermRulesReply('here is uno');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'alpha']])
            ->assertJsonPath('missingTerms.0.target', 'Uno');
    });

    it('counts a swapped-back placeholder as present', function () {
        [$user, $assistant, $conversation] = termRulesChat('acme -> ACME Corp (invariant)', ['swapInvariant' => true, 'highlightMissing' => true]);
        fakeTermRulesReply('see ⟦1⟧');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => 'visit acme']])
            ->assertJsonPath('missingTerms', []);
    });

    it('sends no missing terms when the highlight is off or nothing matched', function (array $settings, string $message) {
        [$user, $assistant, $conversation] = termRulesChat('alpha -> uno', $settings);
        fakeTermRulesReply('something else');

        sendTermRulesMessage($user, $assistant, $conversation, [['role' => 'user', 'content' => $message]])
            ->assertJsonMissingPath('missingTerms');
    })->with([
        'highlight off' => [['markTerms' => true], 'alpha'],
        'no match' => [['highlightMissing' => true], 'nothing'],
    ]);
});
