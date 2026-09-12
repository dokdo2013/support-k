<?php

namespace App\Contracts;

use InvalidArgumentException;

final readonly class Notification
{
    /** @param array<string,int|string> $references */
    public function __construct(
        public string $eventId,
        public string $eventName,
        public int $destinationId,
        public array $references,
        public string $idempotencyKey,
    ) {
        if ($eventId === '' || $eventName === '' || $destinationId < 1 || $idempotencyKey === '') {
            throw new InvalidArgumentException('Notification identity fields are required.');
        }
    }
}
