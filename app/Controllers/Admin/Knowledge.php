<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Knowledge\KnowledgeException;
use App\Services\Knowledge\KnowledgeNotFoundException;
use App\Services\Knowledge\KnowledgeService;
use CodeIgniter\Exceptions\PageNotFoundException;

class Knowledge extends BaseController
{
    public function index(): string
    {
        $this->requireStaff();
        $kind = (string) $this->request->getGet('kind');
        $status = (string) $this->request->getGet('status');
        $visibility = (string) $this->request->getGet('visibility');
        $query = trim((string) $this->request->getGet('q'));

        return view('admin/knowledge/index', [
            'title' => '지식 문서',
            'documents' => $this->knowledge()->listForAdmin($kind, $status, $visibility, $query),
            'kind' => $kind,
            'status' => $status,
            'visibility' => $visibility,
            'query' => $query,
        ]);
    }

    public function newForm(): string
    {
        $this->requireStaff();

        return view('admin/knowledge/form', [
            'title' => '지식 문서 작성',
            'document' => ['kind' => 'faq', 'title' => '', 'body' => '', 'visibility' => 'internal', 'status' => 'draft'],
            'action' => site_url('admin/knowledge'),
            'submitLabel' => '문서 만들기',
        ]);
    }

    public function create()
    {
        $this->requireStaff();
        try {
            $document = $this->knowledge()->create($this->documentInput());

            return redirect()->to(site_url('admin/knowledge/' . $document['id']))->with('message', '문서를 만들었습니다.');
        } catch (KnowledgeException $exception) {
            return redirect()->to(site_url('admin/knowledge/new'))->withInput()->with('error', $exception->getMessage());
        }
    }

    public function edit(int $id): string
    {
        $this->requireStaff();
        try {
            return view('admin/knowledge/form', [
                'title' => '지식 문서 편집',
                'document' => $this->knowledge()->adminDocument($id),
                'action' => site_url('admin/knowledge/' . $id),
                'submitLabel' => '변경 저장',
            ]);
        } catch (KnowledgeNotFoundException) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    public function update(int $id)
    {
        $this->requireStaff();
        try {
            $this->knowledge()->update($id, (int) $this->request->getPost('expected_version'), $this->documentInput());

            return redirect()->to(site_url('admin/knowledge/' . $id))->with('message', '문서를 저장했습니다.');
        } catch (KnowledgeNotFoundException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (KnowledgeException $exception) {
            return redirect()->to(site_url('admin/knowledge/' . $id))->withInput()->with('error', $exception->getMessage());
        }
    }

    public function delete(int $id)
    {
        $this->requireStaff();
        try {
            $this->knowledge()->delete($id, (int) $this->request->getPost('expected_version'));

            return redirect()->to(site_url('admin/knowledge'))->with('message', '문서를 삭제했습니다.');
        } catch (KnowledgeNotFoundException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (KnowledgeException $exception) {
            return redirect()->to(site_url('admin/knowledge/' . $id))->with('error', $exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function documentInput(): array
    {
        return [
            'kind' => $this->request->getPost('kind'),
            'title' => $this->request->getPost('title'),
            'body' => $this->request->getPost('body'),
            'visibility' => $this->request->getPost('visibility'),
            'status' => $this->request->getPost('status'),
        ];
    }

    private function knowledge(): KnowledgeService
    {
        return new KnowledgeService(db_connect());
    }

    private function requireStaff(): int
    {
        $staffId = (int) session('staff_id');
        if ($staffId < 1 || ! in_array(session('staff_role'), ['owner', 'agent'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $staffId;
    }
}
