<?php

namespace App\Services\LlmProviders;

final class PromptParts
{
    public const SEPARATOR = "\n\n";

    /**
     * The text a list of parts reads as when it is sent as one string, so a
     * provider that caches by matching the start of a request sees the same
     * bytes whether or not the parts carry cache points.
     *
     * @param  list<array{type: 'text', text: string, cachePoint?: bool}>  $parts
     */
    public static function join(array $parts): string
    {
        return implode(self::SEPARATOR, array_column($parts, 'text'));
    }

    /**
     * @param  list<array{type: 'text', text: string, cachePoint?: bool}>  $parts
     * @return string|list<array<string, mixed>>
     */
    public static function format(bool $cacheMarks, array $parts): string|array
    {
        return $cacheMarks ? self::blocks($cacheMarks, $parts) : self::join($parts);
    }

    /**
     * @param  list<array{type: 'text', text: string, cachePoint?: bool}>  $parts
     * @return list<array<string, mixed>>
     */
    public static function blocks(bool $cacheMarks, array $parts): array
    {
        return array_map(fn (array $part) => [
            'type' => 'text',
            'text' => $part['text'],
            ...($cacheMarks && ($part['cachePoint'] ?? false) ? ['cache_control' => ['type' => 'ephemeral']] : []),
        ], $parts);
    }
}
