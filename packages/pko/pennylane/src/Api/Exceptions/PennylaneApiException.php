<?php

declare(strict_types=1);

namespace Pko\Pennylane\Api\Exceptions;

use Illuminate\Http\Client\Response;
use Throwable;

final class PennylaneApiException extends PennylaneException
{
    /**
     * @param  array<string,mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response, string $method, string $endpoint): self
    {
        $body = [];
        try {
            $body = (array) $response->json();
        } catch (Throwable) {
            $body = ['raw' => (string) $response->body()];
        }

        $msg = sprintf(
            'Pennylane API %s %s -> %d : %s',
            strtoupper($method),
            $endpoint,
            $response->status(),
            $body['error'] ?? $body['message'] ?? 'erreur inconnue',
        );

        return new self($msg, $response->status(), $body);
    }

    public function isRetryable(): bool
    {
        return $this->status >= 500 || $this->status === 429;
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }
}
