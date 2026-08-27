<?php

return [

    'tool_timeout' => (int) env('AGENT_TOOL_TIMEOUT', 60),

    'step_limit' => (int) env('AGENT_STEP_LIMIT', 10),

    'tool_retry_attempts' => (int) env('AGENT_TOOL_RETRY_ATTEMPTS', 3),

    'progress_cache_ttl' => (int) env('AGENT_PROGRESS_CACHE_TTL', 10),

];
