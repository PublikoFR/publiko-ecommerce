<?php

declare(strict_types=1);

namespace Pko\AiCore\Llm\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;

/**
 * Politique de retry commune aux providers LLM.
 *
 * On ne retente QUE les erreurs transitoires : coupure réseau / timeout
 * (`ConnectionException`), 408, 409, 429 (rate limit), 5xx (dont 529
 * « overloaded » côté Anthropic). Une 4xx « définitive » (400 modèle inconnu,
 * 401 clé invalide, 402 crédit épuisé, 403, 404, 413, 422…) ne sera pas plus
 * acceptée au 3ᵉ essai : la retenter coûte 17 s de backoff par ligne importée
 * pour rien. L'en-tête `retry-after` d'un 429 est honoré quand il est présent.
 */
trait RetriesTransientErrors
{
    protected function withTransientRetries(PendingRequest $request): PendingRequest
    {
        return $request->retry(
            (int) config('ai-core.llm.retries', 3),
            fn (int $attempt, \Throwable $e): int => $this->retryDelayMs($attempt, $e),
            fn (\Throwable $e): bool => $this->isTransientError($e),
            throw: false,
        );
    }

    protected function isTransientError(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if (! $e instanceof RequestException) {
            return false;
        }

        $status = $e->response->status();
        $retryable = (array) config('ai-core.llm.retryable_status_codes', [408, 409, 429]);

        return $status >= 500 || in_array($status, $retryable, true);
    }

    protected function retryDelayMs(int $attempt, \Throwable $e): int
    {
        if ($e instanceof RequestException) {
            $retryAfter = $e->response->header('retry-after');
            if (is_numeric($retryAfter) && (float) $retryAfter > 0) {
                // Plafonné : un provider peut annoncer plusieurs minutes.
                return (int) min(60_000, (float) $retryAfter * 1000);
            }
        }

        $backoff = (array) config('ai-core.llm.retry_backoff_ms', [2000, 5000, 10000]);

        return (int) ($backoff[$attempt - 1] ?? end($backoff) ?: 10000);
    }
}
