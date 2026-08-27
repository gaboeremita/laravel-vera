<?php

namespace App\Contracts;

interface AgentTool
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema for this tool's arguments.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when the tool cannot produce a result.
     */
    public function handle(array $arguments): array;
}
