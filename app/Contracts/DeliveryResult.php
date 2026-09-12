<?php

namespace App\Contracts;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DeliveryResult
{
    private function __construct(
        public string $status,
        public ?string $providerReference = null,
        public ?string $errorCode = null,
        public ?DateTimeImmutable $retryAt = null,
    ) {
        if (! in_array($status, ['success', 'retryable', 'permanent', 'unknown'], true)) {
            throw new InvalidArgumentException('Unsupported delivery status.');
        }
        if ($status === 'retryable' && $retryAt === null) {
            throw new InvalidArgumentException('Retryable results require a retry time.');
        }
    }

    public static function success(?string $providerReference = null): self
    {
        return new self('success', $providerReference);
    }

    public static function retryable(string $errorCode, DateTimeImmutable $retryAt): self
    {
        return new self('retryable', null, $errorCode, $retryAt);
    }

    public static function permanent(string $errorCode): self
    {
        return new self('permanent', null, $errorCode);
    }

    public static function unknown(string $errorCode = 'unknown'): self
    {
        return new self('unknown', null, $errorCode);
    }
}
