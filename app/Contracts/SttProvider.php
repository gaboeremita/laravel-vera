<?php

namespace App\Contracts;

interface SttProvider
{
	/**
	 * Transcribe raw audio bytes into text.
	 */
	public function transcribe(string $audio): string;
}
