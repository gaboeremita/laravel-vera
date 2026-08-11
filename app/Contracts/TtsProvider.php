<?php

namespace App\Contracts;

use App\DTOs\VoiceModeResult;
use App\Models\VoiceModel;

interface TtsProvider
{
	public static function fromModel(VoiceModel $voiceModel): static;

	public function synthesize(string $text, ?string $voice = null, array $options = []): string;

	public function parseLlmResponse(string $content): VoiceModeResult;

	public function llmOptions(): array;
}
