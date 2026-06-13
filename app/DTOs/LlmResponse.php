<?php

namespace App\DTOs;

class LlmResponse
{
	public function __construct(
		public readonly string $content,
		public readonly ?string $thinking = null,
	) {}
}