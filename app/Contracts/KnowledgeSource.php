<?php

namespace App\Contracts;

interface KnowledgeSource
{
    public function id(): string;

    /** @return iterable<KnowledgeDocument> */
    public function documents(KnowledgeQuery $query): iterable;
}
