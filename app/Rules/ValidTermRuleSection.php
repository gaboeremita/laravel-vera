<?php

namespace App\Rules;

use App\Actions\TermRules\ParseTermRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidTermRuleSection implements ValidationRule
{
    /**
     * @param  array<string, mixed>  $prompt
     * @param  bool  $sectionMustExist  false when checking a prompt edit, where renaming or removing the picked section is allowed
     */
    public function __construct(
        private readonly array $prompt,
        private readonly ?string $section,
        private readonly bool $sectionMustExist = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->section === null) {
            return;
        }

        $sectionText = $this->prompt[$this->section] ?? null;

        if (! is_string($sectionText)) {
            if ($this->sectionMustExist) {
                $fail("The prompt has no text section named \"{$this->section}\".");
            }

            return;
        }

        foreach (app(ParseTermRules::class)->handle($sectionText)['failingLines'] as $failingLine) {
            $fail("Line {$failingLine['line']} is not a valid rule: \"{$failingLine['text']}\"");
        }
    }
}
