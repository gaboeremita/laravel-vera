<?php

namespace App\Actions\TermRules;

use App\DTOs\TermRule;
use App\Models\Assistant;

class ParseTermRules
{
    private const string ARROW = '->';

    private const string INVARIANT_MARK = '(invariant)';

    private const string CASE_MARK = '(case)';

    /**
     * Read one rule per line containing "->". A line fails when either side has no term.
     * When a source term repeats an earlier rule's, the earlier line keeps it.
     *
     * @return array{rules: list<TermRule>, failingLines: list<array{line: int, text: string}>}
     */
    public function handle(string $sectionText): array
    {
        $rules = [];
        $failingLines = [];
        $claimedSources = [];

        foreach (preg_split('/\R/u', $sectionText) as $index => $text) {
            if (! str_contains($text, self::ARROW)) {
                continue;
            }

            [$left, $right] = explode(self::ARROW, $text, 2);
            [$right, $invariant, $caseSensitive] = $this->withoutMarks($right);

            $sources = $this->terms($left);
            $targets = $this->terms($right);

            if ($sources === [] || $targets === []) {
                $failingLines[] = ['line' => $index + 1, 'text' => trim($text)];

                continue;
            }

            $sourceKey = fn (string $term): string => $caseSensitive ? $term : mb_strtolower($term);
            $sources = array_values(array_filter($sources, fn (string $term) => ! isset($claimedSources[$sourceKey($term)])));
            if ($sources === []) {
                continue;
            }
            foreach ($sources as $term) {
                $claimedSources[$sourceKey($term)] = true;
            }

            $rules[] = new TermRule(
                source: $sources[0],
                sourceVariants: array_slice($sources, 1),
                target: $targets[0],
                targetVariants: array_slice($targets, 1),
                invariant: $invariant,
                caseSensitive: $caseSensitive,
                line: $index + 1,
            );
        }

        return ['rules' => $rules, 'failingLines' => $failingLines];
    }

    /**
     * The rules in the assistant's picked prompt section, or none when the section is unset, missing or not text.
     *
     * @return list<TermRule>
     */
    public function forAssistant(Assistant $assistant): array
    {
        $section = data_get($assistant->agent_config, 'termRules.section');
        $sectionText = $section !== null ? ($assistant->prompt[$section] ?? null) : null;

        return is_string($sectionText) ? $this->handle($sectionText)['rules'] : [];
    }

    /**
     * @return array{string, bool, bool}
     */
    private function withoutMarks(string $right): array
    {
        $invariant = false;
        $caseSensitive = false;
        $right = rtrim($right);

        while (true) {
            if (str_ends_with($right, self::INVARIANT_MARK)) {
                $invariant = true;
                $right = rtrim(substr($right, 0, -strlen(self::INVARIANT_MARK)));
            } elseif (str_ends_with($right, self::CASE_MARK)) {
                $caseSensitive = true;
                $right = rtrim(substr($right, 0, -strlen(self::CASE_MARK)));
            } else {
                return [$right, $invariant, $caseSensitive];
            }
        }
    }

    /**
     * @return list<string>
     */
    private function terms(string $side): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $side)), fn (string $term) => $term !== ''));
    }
}
