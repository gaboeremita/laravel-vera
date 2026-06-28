<?php

namespace App\Contracts;

interface EmbeddingProvider
{
	/**
	 * Generate an embedding vector for a single text input.
	 *
	 * @return float[]
	 */
	public function embed(string $text): array;

	/**
	 * Generate embedding vectors for multiple text inputs.
	 *
	 * @parameter string[] $texts
	 * @return float[][]
	 */
	public function embedBatch(array $texts): array;
}