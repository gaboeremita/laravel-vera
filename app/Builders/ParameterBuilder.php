<?php

namespace App\Builders;

use InvalidArgumentException;

class ParameterBuilder
{
    /**
     * Build an API payload from a provider's config_schema and a model's config values.
     *
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function build(array $schema, array $config): array
    {
        if (empty($schema)) {
            return [];
        }

        $payload = [];

        foreach ($schema as $definition) {
            $name = $definition['name'] ?? null;

            if (! $name) {
                continue;
            }

            $hasValue = array_key_exists($name, $config);
            $value = $config[$name] ?? null;

            if (! $hasValue) {
                if (array_key_exists('default', $definition)) {
                    $value = $definition['default'];
                } elseif ($definition['required'] ?? false) {
                    throw new InvalidArgumentException("Required parameter \"{$name}\" has no value and no default.");
                } else {
                    continue;
                }
            }

            if ($value === null && ! ($definition['required'] ?? false)) {
                continue;
            }

            $payload[$name] = $this->cast($name, $value, $definition);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function cast(string $name, mixed $value, array $definition): mixed
    {
        return match ($definition['type'] ?? 'string') {
            'integer' => $this->castInteger($name, $value, $definition),
            'float'   => $this->castFloat($name, $value, $definition),
            'boolean' => $this->castBoolean($value),
            'enum'    => $this->castEnum($name, $value, $definition),
            'object'  => $this->castObject($name, $value, $definition),
            default   => (string) $value,
        };
    }

    private function castBoolean(mixed $value): bool
    {
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true') {
                return true;
            }
            if ($lower === 'false') {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function castInteger(string $name, mixed $value, array $definition): int
    {
        $value = (int) $value;
        $this->assertRange($name, $value, $definition);

        return $value;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function castFloat(string $name, mixed $value, array $definition): float
    {
        $value = (float) $value;
        $this->assertRange($name, $value, $definition);

        return $value;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function castEnum(string $name, mixed $value, array $definition): mixed
    {
        $options = $definition['options'] ?? [];

        if (! empty($options) && ! in_array($value, $options, strict: true)) {
            throw new InvalidArgumentException(
                "Parameter \"{$name}\" value \"{$value}\" is not one of: ".implode(', ', $options).'.'
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function castObject(string $name, mixed $value, array $definition): array
    {
        return $this->build(
            $definition['children'] ?? [],
            is_array($value) ? $value : [],
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertRange(string $name, int|float $value, array $definition): void
    {
        if (isset($definition['min']) && $value < $definition['min']) {
            throw new InvalidArgumentException(
                "Parameter \"{$name}\" value {$value} is below minimum {$definition['min']}."
            );
        }

        if (isset($definition['max']) && $value > $definition['max']) {
            throw new InvalidArgumentException(
                "Parameter \"{$name}\" value {$value} exceeds maximum {$definition['max']}."
            );
        }
    }
}
