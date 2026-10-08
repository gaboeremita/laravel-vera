<?php

namespace App\DTOs;

class VideoGenSupportedSettings
{
    /**
     * @param  list<int>  $durations
     * @param  list<string>  $aspectRatios
     */
    public function __construct(
        public readonly array $durations,
        public readonly array $aspectRatios,
    ) {}
}
