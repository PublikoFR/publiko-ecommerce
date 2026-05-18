<?php

declare(strict_types=1);

namespace Pko\Pennylane\Api;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
use Pko\Pennylane\Api\Exceptions\PennylaneNotConfiguredException;
use Pko\Secrets\Facades\Secrets;

final class PennylaneClient
{
    /**
     * @param  array<string,mixed>  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {}

    public function isConfigured(): bool
    {
        return ! empty($this->resolveToken());
    }

    private function resolveToken(): ?string
    {
        $token = $this->config['api_token'] ?? null;
        if (! empty($token)) {
            return (string) $token;
        }

        $fromSecrets = $this->fromSecrets('api_token');
        if (! empty($fromSecrets)) {
            return (string) $fromSecrets;
        }

        return null;
    }

    public function resolveTemplateId(): ?int
    {
        $id = $this->config['customer_invoice_template_id'] ?? null;
        if (! empty($id)) {
            return (int) $id;
        }

        $fromSecrets = $this->fromSecrets('invoice_template_id');
        if (! empty($fromSecrets)) {
            return (int) $fromSecrets;
        }

        return null;
    }

    private function fromSecrets(string $key): ?string
    {
        if (! class_exists(Secrets::class)) {
            return null;
        }

        try {
            $value = Secrets::get('pennylane', $key);
        } catch (\Throwable) {
            return null;
        }

        return $value === '' || $value === null ? null : (string) $value;
    }

    /**
     * @param  array<string,mixed>  $query
     */
    public function get(string $endpoint, array $query = []): Response
    {
        return $this->send('get', $endpoint, query: $query);
    }

    /**
     * @param  array<string,mixed>  $body
     */
    public function post(string $endpoint, array $body = []): Response
    {
        return $this->send('post', $endpoint, body: $body);
    }

    /**
     * @param  array<string,mixed>  $body
     */
    public function put(string $endpoint, array $body = []): Response
    {
        return $this->send('put', $endpoint, body: $body);
    }

    public function delete(string $endpoint): Response
    {
        return $this->send('delete', $endpoint);
    }

    /**
     * @param  array<string,mixed>  $query
     * @param  array<string,mixed>  $body
     */
    private function send(string $method, string $endpoint, array $query = [], array $body = []): Response
    {
        if (! $this->isConfigured()) {
            throw PennylaneNotConfiguredException::missingToken();
        }

        $request = $this->buildRequest();
        $url = $this->url($endpoint);

        $response = match ($method) {
            'get' => $request->get($url, $query),
            'post' => $request->post($url, $body),
            'put' => $request->put($url, $body),
            'delete' => $request->delete($url),
            default => throw new \InvalidArgumentException("Méthode HTTP invalide: {$method}"),
        };

        if ($response->failed()) {
            throw PennylaneApiException::fromResponse($response, $method, $endpoint);
        }

        return $response;
    }

    private function buildRequest(): PendingRequest
    {
        $http = $this->config['http'] ?? [];

        return $this->http
            ->withToken((string) ($this->resolveToken() ?? ''))
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($http['timeout'] ?? 15))
            ->retry(
                (int) ($http['retry_times'] ?? 3),
                (int) ($http['retry_sleep_ms'] ?? 300),
                function (\Throwable $exception, PendingRequest $request): bool {
                    if ($exception instanceof RequestException) {
                        $status = $exception->response->status();

                        return $status >= 500 || $status === 429;
                    }

                    return true;
                },
                throw: false,
            );
    }

    private function url(string $endpoint): string
    {
        $base = rtrim((string) $this->config['base_url'], '/');
        $path = '/'.ltrim($endpoint, '/');

        return $base.$path;
    }

    /**
     * @return array<string,mixed>
     */
    public function config(): array
    {
        return $this->config;
    }
}
