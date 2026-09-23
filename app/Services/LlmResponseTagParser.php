<?php

namespace App\Services;

use App\Enums\AssistantPortraitType;
use App\Models\Assistant;
use App\Models\World;

class LlmResponseTagParser
{
    /**
     * @return array{
     *     content: string,
     *     emotion: ?string,
     *     pose: ?string,
     *     scene: ?string,
     *     intimate: bool,
     *     tags: array<string, array<string>>
     * }
     */
    public function parse(string $content, Assistant $assistant): array
    {
        $emotion = null;
        $pose = null;
        $scene = null;
        $intimate = false;
        $tags = [];

        $regularEmotions = $assistant->promptEmotionNames()['regular'];
        $intimateEmotions = $assistant->promptEmotionNames()['intimate'];
        $emotionNames = [...$regularEmotions, ...$intimateEmotions];
        $poseNames = $assistant->promptPoseNames();

        $content = preg_replace_callback(
            '/\[(?<identifier>[a-z][a-z0-9 _-]*):\s*(?<value>[^\]\r\n]*)\]/iu',
            function (array $match) use (&$emotion, &$pose, &$scene, &$intimate, &$tags, $assistant, $emotionNames, $poseNames): string {
                $identifier = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $match['identifier'])));

                if ($identifier === 'ooc') {
                    return $match[0];
                }

                $value = $this->unquote(trim($match['value']));
                $tags[$identifier][] = $value;

                if ($identifier === 'emotion' && $assistant->portrait_type === AssistantPortraitType::Image) {
                    $emotion ??= $this->canonicalName($value, $emotionNames);
                } elseif ($identifier === 'pose' && $assistant->portrait_type === AssistantPortraitType::Avatar3D) {
                    $pose ??= $this->canonicalName($value, $poseNames);
                } elseif ($identifier === 'scene' && $value !== '') {
                    $scene ??= $value;
                } elseif ($identifier === 'intimate') {
                    $intimate = $this->isTruthy($value);
                }

                return '';
            },
            $content,
        );

        $content = preg_replace_callback(
            '/\[([^\]\r\n]+)\]/u',
            function (array $match) use (&$emotion, &$pose, &$intimate, &$tags, $assistant, $emotionNames, $poseNames): string {
                $value = trim($match[1]);

                if (preg_match('/^ooc(?:\s*:)?$/i', $value)) {
                    return $match[0];
                }

                if (strcasecmp($value, 'intimate') === 0) {
                    $intimate = true;
                    $tags['intimate'][] = 'true';

                    return '';
                }

                if ($assistant->portrait_type === AssistantPortraitType::Image) {
                    $canonical = $this->canonicalName($value, $emotionNames);
                    if ($canonical !== null) {
                        $emotion ??= $canonical;
                        $tags['emotion'][] = $canonical;

                        return '';
                    }
                }

                if ($assistant->portrait_type === AssistantPortraitType::Avatar3D) {
                    $canonical = $this->canonicalName($value, $poseNames);
                    if ($canonical !== null) {
                        $pose ??= $canonical;
                        $tags['pose'][] = $canonical;

                        return '';
                    }
                }

                return $match[0];
            },
            $content,
        );

        return [
            'content' => $this->cleanContent($content),
            'emotion' => $emotion,
            'pose' => $pose,
            'scene' => $scene,
            'intimate' => $intimate,
            'tags' => $tags,
        ];
    }

    /**
     * @param  array<string>  $names
     */
    private function canonicalName(string $value, array $names): ?string
    {
        foreach ($names as $name) {
            if (strcasecmp($name, $value) === 0) {
                return $name;
            }
        }

        return null;
    }

    private function unquote(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
            return trim(substr($value, 1, -1));
        }

        return $value;
    }

    private function isTruthy(string $value): bool
    {
        return in_array(mb_strtolower($value), ['1', 'true', 'yes', 'on', 'intimate'], true);
    }

    private function cleanContent(string $content): string
    {
        $content = preg_replace('/[ \t]+$/m', '', $content);
        $content = preg_replace('/^[ \t]+/m', '', $content);
        $content = preg_replace('/[ \t]{2,}/', ' ', $content);
        $content = preg_replace('/\s+([,.;!?])/', '$1', $content);
        $content = preg_replace('/(?:\r?\n){3,}/', "\n\n", $content);

        return trim($content);
    }

    /**
     * The first [action: …] tag in a reply, checked against the world's layout.
     *
     * @return ?array{verb: string, target?: ?string, activity?: ?string, reason?: string}
     */
    public function parseAction(string $content, World $world): ?array
    {
        if (preg_match('/\[action:\s*([^\]\r\n]*)\]/iu', $content, $match) !== 1) {
            return null;
        }

        $tokens = preg_split('/\s+/u', trim($match[1]), -1, PREG_SPLIT_NO_EMPTY);
        $verb = mb_strtolower($tokens[0] ?? '');
        $layout = $world->layout ?? [];
        $invalid = fn (string $reason) => ['verb' => 'invalid', 'reason' => $reason];

        return match ($verb) {
            'follow', 'stop', 'stay' => ['verb' => $verb, 'target' => null, 'activity' => null],
            'go_to' => $this->parseGoTo(count($tokens) > 1 ? implode(' ', array_slice($tokens, 1)) : null, $layout, $invalid),
            'use' => $this->parseUse($tokens[1] ?? null, $tokens[2] ?? null, $layout, $invalid),
            'zone' => $this->parseZoneActivity($tokens[1] ?? null, $layout, $invalid),
            default => $invalid(sprintf('"%s" is not an action you can take', $verb)),
        };
    }

    private function parseGoTo(?string $target, array $layout, \Closure $invalid): array
    {
        if ($target === null) {
            return $invalid('go_to needs a place or thing to go to');
        }

        $match = collect($layout['zones'] ?? [])->merge($layout['objects'] ?? [])
            ->first(fn (array $candidate) => $this->sameName($candidate['id'], $target) || $this->sameName($candidate['name'], $target));

        return $match !== null
            ? ['verb' => 'go_to', 'target' => $match['id'], 'activity' => null]
            : $invalid(sprintf('there is no place or thing called "%s" here', $target));
    }

    /**
     * Models write ids loosely ("pool_terrace", "Pool Terrace"), so ids and
     * names compare case-insensitively with underscores and spaces as hyphens.
     */
    private function sameName(string $known, string $written): bool
    {
        $normalize = fn (string $value) => trim(preg_replace('/[\s_-]+/u', '-', mb_strtolower($value)), '-');

        return $normalize($known) === $normalize($written);
    }

    private function parseUse(?string $spotId, ?string $activityId, array $layout, \Closure $invalid): array
    {
        if ($spotId === null || $activityId === null) {
            return $invalid('use needs a spot and an activity');
        }

        $spot = collect($layout['objects'] ?? [])->flatMap(fn (array $object) => $object['spots'])->first(fn (array $candidate) => $this->sameName($candidate['id'], $spotId));
        if ($spot === null) {
            return $invalid(sprintf('there is no spot called "%s" here', $spotId));
        }

        $activity = collect($spot['activities'])->first(fn (array $candidate) => $this->sameName($candidate['id'], $activityId) || $this->sameName($candidate['name'], $activityId));

        return $activity !== null
            ? ['verb' => 'use', 'target' => $spot['id'], 'activity' => $activity['id']]
            : $invalid(sprintf('"%s" cannot be done at "%s"', $activityId, $spot['id']));
    }

    private function parseZoneActivity(?string $activityId, array $layout, \Closure $invalid): array
    {
        if ($activityId === null) {
            return $invalid('zone needs an activity');
        }

        $activity = collect($layout['zones'] ?? [])->flatMap(fn (array $zone) => $zone['activities'])
            ->first(fn (array $candidate) => $this->sameName($candidate['id'], $activityId) || $this->sameName($candidate['name'], $activityId));

        return $activity !== null
            ? ['verb' => 'zone', 'target' => null, 'activity' => $activity['id']]
            : $invalid(sprintf('there is no activity called "%s" here', $activityId));
    }
}
