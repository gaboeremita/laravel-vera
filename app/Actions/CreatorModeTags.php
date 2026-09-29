<?php

namespace App\Actions;

/**
 * Reads the creator mode tags in a message the player sends:
 * [creator mode: "<password>"] activates it, [creator mode: <instruction>]
 * gives a command while it is active.
 */
class CreatorModeTags
{
    private const TAG = '/\[\s*creator\s+mode\s*:\s*(?<value>[^\]\r\n]*)\]/iu';

    private const QUOTED = '/^(["\'])(?<password>.*)\1$/su';

    public const MARKER = '[creator mode]';

    /**
     * The password of the first activation in the message, if any.
     */
    public function password(string $content): ?string
    {
        preg_match_all(self::TAG, $content, $matches);
        foreach ($matches['value'] as $value) {
            if (preg_match(self::QUOTED, trim($value), $quoted) === 1) {
                return $quoted['password'];
            }
        }

        return null;
    }

    public function hasCommand(string $content): bool
    {
        preg_match_all(self::TAG, $content, $matches);

        return collect($matches['value'])->contains(fn (string $value) => trim($value) !== '' && preg_match(self::QUOTED, trim($value)) !== 1);
    }

    /**
     * The message with every activation removed, or replaced by a marker the
     * character notices when this one succeeded.
     */
    public function withoutActivations(string $content, bool $activated = false): string
    {
        $marked = false;

        return trim(preg_replace_callback(self::TAG, function (array $match) use ($activated, &$marked): string {
            if (preg_match(self::QUOTED, trim($match['value'])) !== 1) {
                return $match[0];
            }
            if ($activated && ! $marked) {
                $marked = true;

                return self::MARKER;
            }

            return '';
        }, $content));
    }

    /**
     * The message with every creator mode tag removed, for anything that
     * judges the story, where the creator's directions are no part of it.
     */
    public function withoutCommands(string $content): string
    {
        return trim(preg_replace(self::TAG, '', $content));
    }
}
