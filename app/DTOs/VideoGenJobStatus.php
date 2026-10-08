<?php

namespace App\DTOs;

use App\Enums\VideoStatus;

class VideoGenJobStatus
{
    public function __construct(
        public readonly VideoStatus $status,
        public readonly ?string $contentUrl = null,
        public readonly ?string $error = null,
    ) {}
}
