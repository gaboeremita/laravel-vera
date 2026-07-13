<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

class ValidPromptStructure implements ValidationRule
{
	public function validate(string $attribute, mixed $value, Closure $fail): void
	{
		if (! is_array($value)) {
			$fail('The :attribute must be an array.');
			return;
		}

		// Top level must be associative
		if (array_is_list($value)) {
			$fail('The :attribute must be an object with named keys.');
			return;
		}

		foreach ($value as $key => $item) {
			if (! is_string($key) || $key === '') {
				$fail("The :attribute contains an invalid key: \"{$key}\".");
				return;
			}

			$error = $this->validateNode($item, $key);

			if ($error) {
				$fail($error);
				return;
			}
		}
	}

	/**
	 * Recursively validate a node value.
	 * Valid types: string, sequential array of strings, or associative array (recurse).
	 */
	private function validateNode(mixed $value, string $path): ?string
	{
		if (is_string($value)) {
			return null;
		}

		if (! is_array($value)) {
			return "The key \"{$path}\" contains an invalid value type. Expected string, list, or object.";
		}

		// Sequential array — every item must be a string
		if (array_is_list($value)) {
			foreach ($value as $i => $item) {
				if (! is_string($item)) {
					return "The key \"{$path}\" contains a non-string item at index {$i}.";
				}
			}

			return null;
		}

		// Associative array — recurse into each sub-key
		foreach ($value as $subKey => $subValue) {
			if (! is_string($subKey) || $subKey === '') {
				return "The key \"{$path}\" contains an invalid sub-key: \"{$subKey}\".";
			}

			$error = $this->validateNode($subValue, "{$path}.{$subKey}");

			if ($error) {
				return $error;
			}
		}

		return null;
	}
}