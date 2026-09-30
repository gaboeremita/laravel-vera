<?php

namespace App\Actions\TermRules;

use App\DTOs\MarkedMessage;
use App\DTOs\TermRule;
use App\Models\Assistant;

class MarkTermRules
{
    public function __construct(private readonly ParseTermRules $parseTermRules) {}

    /**
     * The model-facing copy of a message with the assistant's term rules applied, or null when none apply.
     */
    public function forAssistant(Assistant $assistant, string $text): ?MarkedMessage
    {
        $settings = $assistant->termRuleSettings();
        if (! $settings['markTerms'] && ! $settings['swapInvariant'] && ! $settings['highlightMissing']) {
            return null;
        }

        $rules = $this->parseTermRules->forAssistant($assistant);

        return $rules === [] ? null : $this->handle($text, $rules, $settings['markTerms'], $settings['swapInvariant']);
    }

    /**
     * Find every rule term in the message and build the copy the model receives: matched terms
     * annotated as "[occurrence -> target]" when marking, invariant terms replaced by "⟦n⟧" when swapping.
     *
     * @param  list<TermRule>  $rules
     */
    public function handle(string $text, array $rules, bool $markTerms, bool $swapInvariant): MarkedMessage
    {
        $matches = $this->matches($text, $rules);
        $markedText = '';
        $placeholders = [];
        $position = 0;

        foreach ($matches as $match) {
            $occurrence = substr($text, $match['start'], $match['length']);
            $markedText .= substr($text, $position, $match['start'] - $position);

            if ($swapInvariant && $match['rule']->invariant) {
                $placeholder = '⟦'.(count($placeholders) + 1).'⟧';
                $placeholders[$placeholder] = $match['rule']->target;
                $markedText .= $placeholder;
            } elseif ($markTerms) {
                $markedText .= "[{$occurrence} -> {$match['rule']->target}]";
            } else {
                $markedText .= $occurrence;
            }

            $position = $match['start'] + $match['length'];
        }

        return new MarkedMessage($text, $markedText.substr($text, $position), $placeholders, $matches);
    }

    /**
     * A pattern matching any of the terms as whole words, longest first.
     *
     * @param  list<string>  $terms
     */
    public static function pattern(array $terms, bool $caseSensitive): string
    {
        usort($terms, fn (string $a, string $b) => strlen($b) <=> strlen($a));
        $alternation = implode('|', array_map(fn (string $term) => preg_quote($term, '/'), $terms));

        return '/(?<![\p{L}\p{N}])(?:'.$alternation.')(?![\p{L}\p{N}])/u'.($caseSensitive ? '' : 'i');
    }

    /**
     * Matches in the order they appear, keeping the longest where two overlap.
     *
     * @param  list<TermRule>  $rules
     * @return list<array{rule: TermRule, start: int, length: int}>
     */
    private function matches(string $text, array $rules): array
    {
        $candidates = [];

        foreach ([true, false] as $caseSensitive) {
            $rulesByTerm = [];
            foreach ($rules as $rule) {
                if ($rule->caseSensitive === $caseSensitive) {
                    foreach ($rule->sourceTerms() as $term) {
                        $rulesByTerm[$caseSensitive ? $term : mb_strtolower($term)] ??= $rule;
                    }
                }
            }

            if ($rulesByTerm === []) {
                continue;
            }

            preg_match_all(self::pattern(array_map('strval', array_keys($rulesByTerm)), $caseSensitive), $text, $found, PREG_OFFSET_CAPTURE);

            foreach ($found[0] as [$occurrence, $start]) {
                $rule = $rulesByTerm[$caseSensitive ? $occurrence : mb_strtolower($occurrence)] ?? null;
                if ($rule !== null) {
                    $candidates[] = ['rule' => $rule, 'start' => $start, 'length' => strlen($occurrence)];
                }
            }
        }

        usort($candidates, fn (array $a, array $b) => [$b['length'], $a['start']] <=> [$a['length'], $b['start']]);

        $kept = [];
        foreach ($candidates as $candidate) {
            $overlaps = array_filter($kept, fn (array $match) => $candidate['start'] < $match['start'] + $match['length'] && $match['start'] < $candidate['start'] + $candidate['length']);
            if ($overlaps === []) {
                $kept[] = $candidate;
            }
        }

        usort($kept, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $kept;
    }
}
