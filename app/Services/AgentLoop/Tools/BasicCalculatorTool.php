<?php

namespace App\Services\AgentLoop\Tools;

use App\Contracts\AgentTool;

class BasicCalculatorTool implements AgentTool
{
    public function name(): string
    {
        return 'basic_calculator';
    }

    public function description(): string
    {
        return 'Evaluates a basic arithmetic expression (addition, subtraction, multiplication, division, parentheses) and returns the result.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => 'The arithmetic expression to evaluate, e.g. "(4 + 2) / 3".',
                ],
            ],
            'required' => ['expression'],
        ];
    }

    public function handle(array $arguments): array
    {
        if (empty($arguments['expression'])) {
            throw new \RuntimeException('Missing required "expression" argument.');
        }

        return ['result' => $this->evaluate($arguments['expression'])];
    }

    private function evaluate(string $expression): float|int
    {
        $tokens = $this->tokenize($expression);
        $position = 0;

        $result = $this->parseExpression($tokens, $position);

        if ($position < count($tokens)) {
            throw new \RuntimeException("Unexpected token '{$tokens[$position]}' in expression.");
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function tokenize(string $expression): array
    {
        preg_match_all('/\d+\.\d+|\d+|[+\-*\/()]/', $expression, $matches);

        $consumed = implode('', $matches[0]);
        $stripped = preg_replace('/\s+/', '', $expression);

        if ($consumed !== $stripped) {
            throw new \RuntimeException("Invalid character in expression: \"{$expression}\".");
        }

        return $matches[0];
    }

    /**
     * expr := term (('+' | '-') term)*
     *
     * @param  string[]  $tokens
     */
    private function parseExpression(array $tokens, int &$position): float|int
    {
        $value = $this->parseTerm($tokens, $position);

        while (isset($tokens[$position]) && in_array($tokens[$position], ['+', '-'], true)) {
            $operator = $tokens[$position++];
            $right = $this->parseTerm($tokens, $position);
            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    /**
     * term := factor (('*' | '/') factor)*
     *
     * @param  string[]  $tokens
     */
    private function parseTerm(array $tokens, int &$position): float|int
    {
        $value = $this->parseFactor($tokens, $position);

        while (isset($tokens[$position]) && in_array($tokens[$position], ['*', '/'], true)) {
            $operator = $tokens[$position++];
            $right = $this->parseFactor($tokens, $position);

            if ($operator === '/') {
                if ($right == 0) {
                    throw new \RuntimeException('Division by zero.');
                }
                $value = $value / $right;
            } else {
                $value = $value * $right;
            }
        }

        return $value;
    }

    /**
     * factor := NUMBER | '(' expr ')' | '-' factor
     *
     * @param  string[]  $tokens
     */
    private function parseFactor(array $tokens, int &$position): float|int
    {
        if (! isset($tokens[$position])) {
            throw new \RuntimeException('Unexpected end of expression.');
        }

        $token = $tokens[$position];

        if ($token === '-') {
            $position++;

            return -$this->parseFactor($tokens, $position);
        }

        if ($token === '(') {
            $position++;
            $value = $this->parseExpression($tokens, $position);

            if (! isset($tokens[$position]) || $tokens[$position] !== ')') {
                throw new \RuntimeException('Missing closing parenthesis.');
            }

            $position++;

            return $value;
        }

        if (is_numeric($token)) {
            $position++;

            return str_contains($token, '.') ? (float) $token : (int) $token;
        }

        throw new \RuntimeException("Unexpected token '{$token}' in expression.");
    }
}
