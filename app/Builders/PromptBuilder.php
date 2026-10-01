<?php

namespace App\Builders;

class PromptBuilder
{
    /** @var string[] */
    private array $sections = [];

    /**
     * Add a named top-level section under its own "# TITLE" heading. Content is
     * processed recursively based on type:
     * - string → used as-is
     * - sequential array → comma-separated
     * - associative array → keys become labels, values recurse
     * A "title" key overrides the heading. A section with no content is left out.
     */
    public function section(string $name, mixed $content): static
    {
        $body = $this->processBody($content);

        if ($body !== '') {
            $heading = mb_strtoupper(is_array($content) && isset($content['title']) ? $content['title'] : $this->formatLabel($name));
            $this->sections[] = "# {$heading}\n{$body}";
        }

        return $this;
    }

    /**
     * Add a named entry inside a section that already has a heading: it renders
     * without a heading of its own, with an associative array starting with a
     * "Title:" line.
     */
    public function entry(string $name, mixed $content): static
    {
        $entry = $this->processSection($name, $content, 0);

        if ($entry !== '') {
            $this->sections[] = $entry;
        }

        return $this;
    }

    public function build(): string
    {
        return implode("\n\n", $this->sections);
    }

    private function processBody(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content) && array_is_list($content)) {
            return implode(', ', $content);
        }

        unset($content['title']);

        $parts = [];
        foreach ($content as $key => $value) {
            $parts[] = $this->processSection($key, $value, 1);
        }

        return implode("\n", $parts);
    }

    private function processSection(string $label, mixed $content, int $depth): string
    {
        if (is_string($content)) {
            return $depth === 0
                ? $content
                : "{$this->formatLabel($label)}: {$content}";
        }

        if (is_array($content) && array_is_list($content)) {
            $joined = implode(', ', $content);

            return $depth === 0
                ? $joined
                : "{$this->formatLabel($label)}: {$joined}";
        }

        $title = $content['title'] ?? $this->formatLabel($label);
        unset($content['title']);

        $parts = ["{$title}:"];

        foreach ($content as $key => $value) {
            $parts[] = $this->processSection($key, $value, $depth + 1);
        }

        return implode("\n", $parts);
    }

    private function formatLabel(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
