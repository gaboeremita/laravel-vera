<?php

namespace App\DTOs;

use App\Actions\TermRules\MarkTermRules;

class MarkedMessage
{
    /**
     * @param  array<string, string>  $placeholders  placeholder => the exact text of the rule's other side
     * @param  list<array{rule: TermRule, reverse: bool, start: int, length: int}>  $matches  byte offsets into $originalText
     */
    public function __construct(
        public readonly string $originalText,
        public readonly string $text,
        public readonly array $placeholders,
        public readonly array $matches,
    ) {}

    public function restore(string $reply): string
    {
        return strtr($reply, $this->placeholders);
    }

    /**
     * The matched rules whose rendering on the other side (or one of its variants) is absent from the
     * reply, with the ranges of the matched terms in the message as typed.
     *
     * @return list<array{target: string, ranges: list<array{int, int}>}>
     */
    public function missingTerms(string $reply): array
    {
        $missing = [];

        foreach ($this->matchesByRule() as $ruleMatches) {
            $rule = $ruleMatches[0]['rule'];
            $reverse = $ruleMatches[0]['reverse'];
            $renderings = $reverse ? $rule->sourceTerms() : $rule->targetTerms();

            if (preg_match(MarkTermRules::pattern($renderings, $rule->caseSensitive), $reply) === 1) {
                continue;
            }

            $missing[] = [
                'target' => $renderings[0],
                'ranges' => array_map(fn (array $match) => [
                    $this->utf16Length(substr($this->originalText, 0, $match['start'])),
                    $this->utf16Length(substr($this->originalText, $match['start'], $match['length'])),
                ], $ruleMatches),
            ];
        }

        return $missing;
    }

    /**
     * @return list<list<array{rule: TermRule, reverse: bool, start: int, length: int}>>
     */
    private function matchesByRule(): array
    {
        $grouped = [];

        foreach ($this->matches as $match) {
            $grouped[spl_object_id($match['rule']).($match['reverse'] ? 'r' : 'f')][] = $match;
        }

        return array_values($grouped);
    }

    /**
     * The browser indexes strings in UTF-16 code units, so ranges sent to it are measured that way.
     */
    private function utf16Length(string $text): int
    {
        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }
}
