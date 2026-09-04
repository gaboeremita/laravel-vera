<?php

namespace App\Contracts;

interface SttProvider
{
    public function transcribe(string $audio, string $filename = 'audio.wav'): string;
}
