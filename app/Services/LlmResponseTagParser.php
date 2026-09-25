<?php

namespace App\Services;

use App\Enums\AssistantPortraitType;
use App\Models\Assistant;

class LlmResponseTagParser
{
    private const STRAY_TAG = '/\[\s*[\p{L}][\p{L}\'-]*(?:\s+[\p{L}][\p{L}\'-]*){0,2}\s*\]/u';

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
        $poseNames = $assistant->poseNames();

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

    /**
     * Removes bracketed tags of up to three words that parsing left behind,
     * such as a mood the model made up ([amused]), for lines spoken aloud in
     * a world, where brackets have no other use.
     */
    public function stripStrayTags(string $content): string
    {
        return $this->cleanContent(preg_replace(self::STRAY_TAG, '', $content));
    }

    /**
     * The words inside the tags stripStrayTags() removes, so they can be
     * recorded with the line.
     *
     * @return array<int, string>
     */
    public function strayTags(string $content): array
    {
        preg_match_all(self::STRAY_TAG, $content, $matches);

        return array_map(fn (string $tag) => trim($tag, "[] \t"), $matches[0]);
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
}
