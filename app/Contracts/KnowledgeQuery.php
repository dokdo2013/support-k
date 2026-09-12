<?php

namespace App\Contracts;

use InvalidArgumentException;

final readonly class KnowledgeQuery
{
    /** @param list<string> $allowedScopes */
    public function __construct(
        public string $text,
        public array $allowedScopes,
        public ?int $ticketId = null,
    ) {
        if (trim($text) === '' || $allowedScopes === []) {
            throw new InvalidArgumentException('A query and at least one allowed scope are required.');
        }
    }
}
