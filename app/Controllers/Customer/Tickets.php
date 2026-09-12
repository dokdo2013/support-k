<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Services\Accounts\RateLimiter;
use App\Services\Tickets\TicketAccess;
use App\Services\Tickets\TicketException;
use App\Services\Tickets\TicketService;

class Tickets extends BaseController
{
    public function newTicket(): string
    {
        return view('customer/tickets/new', ['requestKey' => $this->requestKey()]);
    }

    public function create()
    {
        if (! $this->limiter()->allow('ticket:create:' . $this->clientIp(), 5, 300)) {
            return redirect()->to(site_url('tickets/new'))->withInput()->with('error', '잠시 후 다시 시도해 주세요.');
        }
        try {
            $ticket = $this->tickets()->create([
                'request_key' => $this->request->getPost('request_key'),
                'requester_name' => $this->request->getPost('requester_name'),
                'requester_email' => $this->request->getPost('requester_email'),
                'subject' => $this->request->getPost('subject'),
                'lookup_password' => $this->request->getPost('lookup_password'),
                'body' => $this->request->getPost('body'),
            ]);
            $this->access()->grant($ticket);

            return redirect()->to(site_url('tickets/' . $ticket['number']))
                ->with('message', '문의가 접수되었습니다. 문의 번호와 조회 비밀번호를 안전하게 보관해 주세요.');
        } catch (TicketException $exception) {
            return redirect()->to(site_url('tickets/new'))->withInput()->with('error', $exception->getMessage());
        }
    }

    public function lookupForm(): string
    {
        return view('customer/tickets/lookup');
    }

    public function lookup()
    {
        $number = (string) $this->request->getPost('number');
        if (! $this->limiter()->allow('ticket:lookup:' . $this->clientIp() . ':' . strtoupper(trim($number)), 8, 300)) {
            return redirect()->to(site_url('tickets/lookup'))->withInput()->with('error', '잠시 후 다시 시도해 주세요.');
        }

        $password = (string) $this->request->getPost('lookup_password');
        $ticket = $this->tickets()->lookup($number, $password);
        if ($ticket === null) {
            return redirect()->to(site_url('tickets/lookup'))->withInput()->with('error', '문의 번호 또는 조회 비밀번호를 확인해 주세요.');
        }

        $this->access()->grant($ticket);

        return redirect()->to(site_url('tickets/' . $ticket['number']));
    }

    public function show(string $number)
    {
        try {
            $ticket = $this->tickets()->ticketForCustomer($number);
            if (! $this->access()->allows((int) $ticket['id'])) {
                return redirect()->to(site_url('tickets/lookup'))->with('error', '문의 내용을 보려면 조회가 필요합니다.');
            }
            unset($ticket['lookup_password_hash'], $ticket['client_request_key']);

            return view('customer/tickets/show', [
                'ticket' => $ticket,
                'messages' => $this->tickets()->publicMessages((int) $ticket['id']),
                'requestKey' => $this->requestKey(),
            ]);
        } catch (TicketException) {
            return redirect()->to(site_url('tickets/lookup'))->with('error', '문의 내용을 찾을 수 없습니다.');
        }
    }

    public function reply(string $number)
    {
        try {
            $ticket = $this->tickets()->ticketForCustomer($number);
            if (! $this->access()->allows((int) $ticket['id'])) {
                return redirect()->to(site_url('tickets/lookup'))->with('error', '문의 내용을 보려면 조회가 필요합니다.');
            }
            $this->tickets()->customerReply((int) $ticket['id'], [
                'request_key' => $this->request->getPost('request_key'),
                'body' => $this->request->getPost('body'),
            ]);

            return redirect()->to(site_url('tickets/' . $ticket['number']))->with('message', '추가 답글을 등록했습니다. 담당자가 확인할 예정입니다.');
        } catch (TicketException $exception) {
            return redirect()->to(site_url('tickets/' . rawurlencode($number)))->withInput()->with('error', $exception->getMessage());
        }
    }

    private function tickets(): TicketService
    {
        return new TicketService(db_connect());
    }

    private function access(): TicketAccess
    {
        return new TicketAccess(session());
    }

    private function requestKey(): string
    {
        return bin2hex(random_bytes(24));
    }

    private function limiter(): RateLimiter
    {
        return new RateLimiter();
    }

    private function clientIp(): string
    {
        return $this->request->getIPAddress();
    }
}
