<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidModelConfig implements ValidationRule
{
    /**
     * @param  array<int, array<string, mixed>>  $schema
     */
    public function __construct(private readonly array $schema) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($this->schema)) {
            return;
        }

        $config = is_array($value) ? $value : [];

        $this->validateAgainstSchema($attribute, $config, $this->schema, $fail);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, array<string, mixed>>  $schema
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function validateAgainstSchema(string $prefix, array $config, array $schema, Closure $fail): void
    {
        $allowedKeys = array_column($schema, 'name');

        foreach (array_keys($config) as $key) {
            if (! in_array($key, $allowedKeys, strict: true)) {
                $fail("The {$prefix}.{$key} field is not allowed.");
            }
        }

        foreach ($schema as $definition) {
            $name = $definition['name'] ?? null;

            if (! $name) {
                continue;
            }

            $field = "{$prefix}.{$name}";
            $present = array_key_exists($name, $config);
            $value = $config[$name] ?? null;

            if (! $present) {
                if ($definition['required'] ?? false) {
                    $fail("The {$field} field is required.");
                }

                continue;
            }

            $this->validateType($field, $value, $definition, $fail);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function validateType(string $field, mixed $value, array $definition, Closure $fail): void
    {
        $type = $definition['type'] ?? 'string';

        match ($type) {
            'integer' => $this->validateInteger($field, $value, $definition, $fail),
            'float'   => $this->validateFloat($field, $value, $definition, $fail),
            'boolean' => $this->validateBoolean($field, $value, $fail),
            'enum'    => $this->validateEnum($field, $value, $definition, $fail),
            'object'  => $this->validateObject($field, $value, $definition, $fail),
            default   => null,
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function validateInteger(string $field, mixed $value, array $definition, Closure $fail): void
    {
        if (! is_numeric($value) || (int) $value != $value) {
            $fail("The {$field} field must be an integer.");

            return;
        }

        $value = (int) $value;

        if (isset($definition['min']) && $value < $definition['min']) {
            $fail("The {$field} field must be at least {$definition['min']}.");
        }

        if (isset($definition['max']) && $value > $definition['max']) {
            $fail("The {$field} field must not exceed {$definition['max']}.");
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function validateFloat(string $field, mixed $value, array $definition, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail("The {$field} field must be a number.");

            return;
        }

        $value = (float) $value;

        if (isset($definition['min']) && $value < $definition['min']) {
            $fail("The {$field} field must be at least {$definition['min']}.");
        }

        if (isset($definition['max']) && $value > $definition['max']) {
            $fail("The {$field} field must not exceed {$definition['max']}.");
        }
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function validateBoolean(string $field, mixed $value, Closure $fail): void
    {
        if (! in_array($value, [true, false, 0, 1, '0', '1'], strict: true)) {
            $fail("The {$field} field must be a boolean.");
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function validateEnum(string $field, mixed $value, array $definition, Closure $fail): void
    {
        $options = $definition['options'] ?? [];

        if (! empty($options) && ! in_array($value, $options, strict: true)) {
            $fail("The {$field} field must be one of: ".implode(', ', $options).'.');
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function validateObject(string $field, mixed $value, array $definition, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail("The {$field} field must be an object.");

            return;
        }

        $children = $definition['children'] ?? [];

        if (! empty($children)) {
            $this->validateAgainstSchema($field, $value, $children, $fail);
        }
    }
}
