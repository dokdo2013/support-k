<?php

namespace App\Contracts;

use InvalidArgumentException;

final readonly class KnowledgeDocument
{
    public function __construct(
        public string $id,
        public int $version,
        public string $scope,
        public string $title,
        public string $content,
    ) {
        if ($id === '' || $version < 1 || $scope === '') {
            throw new InvalidArgumentException('Knowledge document identity is invalid.');
        }
    }
}
