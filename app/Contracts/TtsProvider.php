<?php

namespace App\Contracts;

interface TtsProvider
{
	/**
	 * Synthesize text into raw audio bytes.
	 */
	public function synthesize(string $text, ?string $voice = null): string;
}
