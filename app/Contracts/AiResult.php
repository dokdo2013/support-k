<?php

namespace App\Contracts;

use InvalidArgumentException;

final readonly class AiResult
{
    /** @param array<string,scalar|null> $data */
    public function __construct(
        public string $status,
        public array $data = [],
        public ?string $model = null,
        public ?string $errorCode = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public bool $usageConfirmed = false,
    ) {
        if (! in_array($status, ['success', 'refused', 'invalid', 'timeout', 'failed'], true)) {
            throw new InvalidArgumentException('Unsupported AI result status.');
        }
    }
}
