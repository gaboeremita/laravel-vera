<?php

namespace App\Directors;

use App\Builders\PromptBuilder;

class PromptDirector
{
	/** @var array<string, mixed> */
	private array $config;

	/** @var string[]|null */
	private ?array $keys = null;
	/** @var string[]|null */
	private ?array $excludedKeys = null;

	public function __construct(?string $path = null)
	{
		$path = $path ?? base_path('vera_prompt.json');
		$this->config = json_decode(file_get_contents($path), true);
	}

	/**
	 * Restrict which sections to include in the prompt.
	 *
	 * @param  string[]  $keys
	 */
	public function only(array $keys): static
	{
		$this->keys = $keys;

		return $this;
	}

	/**
	 * Exclude specific sections from the prompt.
	 *
	 * @param  string[]  $keys
	 */
	public function except(array $keys): static
	{
		$this->excludedKeys = $keys;

		return $this;
	}

	public function build(): string
	{
		$builder = new PromptBuilder();
		$sections = $this->keys
			? array_intersect_key($this->config, array_flip($this->keys))
			: $this->config;

		if ($this->excludedKeys) {
			$sections = array_diff_key($sections, array_flip($this->excludedKeys));
		}
		
		foreach ($sections as $key => $value) {
			$builder->section($key, $value);
		}

		return $builder->build();
	}
}