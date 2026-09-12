<?php

namespace App\Contracts;

use InvalidArgumentException;

final readonly class MailMessage
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $text,
        public string $idempotencyKey,
    ) {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || trim($subject) === '' || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('A valid recipient, subject, and idempotency key are required.');
        }
    }
}
