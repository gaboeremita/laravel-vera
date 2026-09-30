<?php

namespace App\DTOs;

use App\Actions\TermRules\MarkTermRules;

class MarkedMessage
{
    /**
     * @param  array<string, string>  $placeholders  placeholder => the rule's exact target text
     * @param  list<array{rule: TermRule, start: int, length: int}>  $matches  byte offsets into $originalText
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
     * The matched rules whose target term (or a target variant) is absent from the reply, with the
     * ranges of their source terms in the message as typed.
     *
     * @return list<array{target: string, ranges: list<array{int, int}>}>
     */
    public function missingTerms(string $reply): array
    {
        $missing = [];

        foreach ($this->matchesByRule() as $ruleMatches) {
            $rule = $ruleMatches[0]['rule'];

            if (preg_match(MarkTermRules::pattern($rule->targetTerms(), $rule->caseSensitive), $reply) === 1) {
                continue;
            }

            $missing[] = [
                'target' => $rule->target,
                'ranges' => array_map(fn (array $match) => [
                    $this->utf16Length(substr($this->originalText, 0, $match['start'])),
                    $this->utf16Length(substr($this->originalText, $match['start'], $match['length'])),
                ], $ruleMatches),
            ];
        }

        return $missing;
    }

    /**
     * @return list<list<array{rule: TermRule, start: int, length: int}>>
     */
    private function matchesByRule(): array
    {
        $grouped = [];

        foreach ($this->matches as $match) {
            $grouped[spl_object_id($match['rule'])][] = $match;
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
