<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Tracking;

use DateTimeImmutable;

final class TrackingStatus
{
    /**
     * @param  array<int, array{at: string, code: string, label: string}>  $events
     */
    public function __construct(
        public readonly string $status,
        public readonly ?DateTimeImmutable $statusUpdatedAt,
        public readonly ?DateTimeImmutable $deliveredAt,
        public readonly array $events,
        public readonly ?string $publicUrl = null,
    ) {}

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['delivered', 'returned', 'failed'], true);
    }
}
