<?php

namespace App\Builders;

class PromptBuilder
{
	/** @var string[] */
	private array $sections = [];

	/**
	 * Add a named section. Content is processed recursively based on type:
	 * - string → used as-is
	 * - sequential array → comma-separated
	 * - associative array → keys become labels, values recurse
	 * A "title" key overrides the section header.
	 */
	public function section(string $name, mixed $content): static
	{
		$this->sections[] = $this->processSection($name, $content, 0);

		return $this;
	}

	public function build(): string
	{
		return implode("\n\n", $this->sections);
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