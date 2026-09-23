<?php

namespace App\Contracts;

interface Retriever
{
    /** @return list<KnowledgeDocument> */
    public function search(KnowledgeQuery $query, int $limit = 5): array;
}
