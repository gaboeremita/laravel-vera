<?php

namespace App\Builders;

class FileBuilder
{
	/** @var string[] */
	private array $lines = [];

	public function heading(string $text, int $level = 1): static
	{
		$this->lines[] = str_repeat('#', $level).' '.$text;

		return $this;
	}

	public function paragraph(string $text): static
	{
		$this->lines[] = $text;

		return $this;
	}

	public function keyValue(string $label, string $value): static
	{
		$this->lines[] = "**{$label}:** {$value}";

		return $this;
	}

	public function build(): string
	{
		return implode("\n\n", $this->lines)."\n";
	}
}
