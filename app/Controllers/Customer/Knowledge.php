<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Services\Knowledge\KnowledgeService;
use CodeIgniter\Exceptions\PageNotFoundException;

class Knowledge extends BaseController
{
    public function index(): string
    {
        $query = trim((string) $this->request->getGet('q'));

        return view('customer/knowledge/index', [
            'title' => '도움말',
            'query' => $query,
            'results' => $query === '' ? [] : $this->knowledge()->searchPublicFaq($query),
            'documents' => $query === '' ? $this->knowledge()->publicDocuments() : [],
        ]);
    }

    public function show(int $id): string
    {
        $document = $this->knowledge()->publicDocument($id);
        if ($document === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('customer/knowledge/show', [
            'title' => $document['title'],
            'document' => $document,
        ]);
    }

    private function knowledge(): KnowledgeService
    {
        return new KnowledgeService(db_connect());
    }
}
