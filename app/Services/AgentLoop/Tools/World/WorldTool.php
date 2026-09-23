<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;

abstract class WorldTool implements AgentTool
{
    public function __construct(protected readonly WorldToolbox $toolbox) {}

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    /**
     * World tools answer from the layout, so a failed call fails the same way every time.
     */
    public function retryAttempts(): int
    {
        return 1;
    }
}
