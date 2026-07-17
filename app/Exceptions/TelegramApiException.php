<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class TelegramApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $errorCode = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response, string $method): self
    {
        $data = $response->json();

        return new self(
            message: "Telegram {$method} failed: " . ($data['description'] ?? $response->body()),
            errorCode: $data['error_code'] ?? $response->status(),
            retryAfter: $data['parameters']['retry_after'] ?? null,
        );
    }

    public function isRateLimited(): bool
    {
        return $this->errorCode === 429;
    }
}
