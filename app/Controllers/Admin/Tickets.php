<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Tickets\TicketException;
use App\Services\Tickets\TicketNotFoundException;
use App\Services\Tickets\TicketService;
use CodeIgniter\Exceptions\PageNotFoundException;

class Tickets extends BaseController
{
    public function index(): string
    {
        $this->requireStaff();
        $status = (string) $this->request->getGet('status');
        $query = trim((string) $this->request->getGet('q'));

        return view('admin/tickets/index', [
            'tickets' => $this->tickets()->list($status, $query),
            'status' => $status,
            'query' => $query,
        ]);
    }

    public function show(int $id): string
    {
        $this->requireStaff();
        try {
            return view('admin/tickets/show', [
                'ticket' => $this->tickets()->ticketForAdmin($id),
                'messages' => $this->tickets()->allMessages($id),
                'replyRequestKey' => $this->requestKey(),
                'noteRequestKey' => $this->requestKey(),
            ]);
        } catch (TicketNotFoundException) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    public function reply(int $id)
    {
        $staffId = $this->requireStaff();
        try {
            $this->tickets()->staffReply($id, $staffId, [
                'request_key' => $this->request->getPost('request_key'),
                'body' => $this->request->getPost('body'),
            ]);
            return redirect()->to(site_url('admin/tickets/' . $id))->with('message', '고객 답변을 등록했습니다.');
        } catch (TicketException $exception) {
            return redirect()->to(site_url('admin/tickets/' . $id))->withInput()->with('error', $exception->getMessage());
        }
    }

    public function note(int $id)
    {
        $staffId = $this->requireStaff();
        try {
            $this->tickets()->addPrivateNote($id, $staffId, [
                'request_key' => $this->request->getPost('request_key'),
                'body' => $this->request->getPost('body'),
            ]);
            return redirect()->to(site_url('admin/tickets/' . $id))->with('message', '내부 메모를 등록했습니다.');
        } catch (TicketException $exception) {
            return redirect()->to(site_url('admin/tickets/' . $id))->withInput()->with('error', $exception->getMessage());
        }
    }

    public function status(int $id)
    {
        $staffId = $this->requireStaff();
        try {
            $this->tickets()->changeStatus($id, (string) $this->request->getPost('status'), $staffId);
            return redirect()->to(site_url('admin/tickets/' . $id))->with('message', '상태를 변경했습니다.');
        } catch (TicketException $exception) {
            return redirect()->to(site_url('admin/tickets/' . $id))->with('error', $exception->getMessage());
        }
    }

    private function tickets(): TicketService
    {
        return new TicketService(db_connect());
    }

    private function requireStaff(): int
    {
        $staffId = (int) session('staff_id');
        $role = session('staff_role');
        if ($staffId < 1 || ! in_array($role, ['owner', 'agent'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $staffId;
    }

    private function requestKey(): string
    {
        return bin2hex(random_bytes(24));
    }
}
