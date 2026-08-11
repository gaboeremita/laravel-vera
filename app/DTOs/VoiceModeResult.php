<?php

namespace App\DTOs;

readonly class VoiceModeResult
{
	public function __construct(
		public string $content,
		public ?string $ttsInstructions = null,
	) {}
}
