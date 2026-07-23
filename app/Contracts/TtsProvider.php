<?php

namespace App\Contracts;

use App\Models\VoiceModel;

interface TtsProvider
{
	public static function fromModel(VoiceModel $voiceModel): static;

	/**
	 * Synthesize text into raw audio bytes.
	 */
	public function synthesize(string $text, ?string $voice = null): string;
}
