<?php

namespace App\Contracts;

use InvalidArgumentException;

final readonly class ExecutionContext
{
    /**
     * @param list<string>         $permissions
     * @param array<string,string> $secretReferences References such as setting keys, never secret values.
     */
    public function __construct(
        public string $requestId,
        public ?int $actorId = null,
        public array $permissions = [],
        public array $secretReferences = [],
        public ?string $configurationVersion = null,
    ) {
        if (trim($requestId) === '') {
            throw new InvalidArgumentException('A request ID is required.');
        }
    }
}
