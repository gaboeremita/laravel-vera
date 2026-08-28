<?php

namespace App\Services\AgentLoop\Tools;

use App\Contracts\AgentTool;
use Illuminate\Support\Carbon;

class GetCurrentDatetimeTool implements AgentTool
{
    public function name(): string
    {
        return 'get_current_datetime';
    }

    public function description(): string
    {
        return 'Returns the current date and time.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass,
        ];
    }

    public function handle(array $arguments): array
    {
        $timezone = config('app.timezone');

        return [
            'datetime' => Carbon::now($timezone)->toIso8601String(),
            'timezone' => $timezone,
        ];
    }

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    public function retryAttempts(): int
    {
        return config('agent.tool_retry_attempts');
    }
}
