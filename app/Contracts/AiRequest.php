<?php

namespace App\Contracts;

use InvalidArgumentException;

final readonly class AiRequest
{
    /** @param array<string,scalar|null> $input */
    public function __construct(
        public string $feature,
        public array $input,
        public ExecutionContext $context,
    ) {
        if (trim($feature) === '') {
            throw new InvalidArgumentException('An AI feature is required.');
        }
    }
}
