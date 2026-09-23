<?php

declare(strict_types=1);

use App\Services\Tickets\TicketService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class TicketServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;

    public function testDuplicateCreateUsesTheOriginalTicketAndOneEvent(): void
    {
        $service = new TicketService($this->db);
        $input = $this->ticketInput('create-once');

        $first = $service->create($input);
        $again = $service->create($input);

        $this->assertSame($first['id'], $again['id']);
        $this->assertArrayNotHasKey('lookup_password_hash', $first);
        $this->assertArrayNotHasKey('client_request_key', $first);
        $this->assertSame(1, $this->db->table('tickets')->countAllResults());
        $this->assertSame(1, $this->db->table('ticket_messages')->countAllResults());
        $this->assertSame(1, $this->db->table('domain_events')->countAllResults());
    }

    public function testDuplicateCreateKeyDoesNotGrantAccessWithoutTheLookupPassword(): void
    {
        $service = new TicketService($this->db);
        $input = $this->ticketInput('protected-create-key');
        $service->create($input);
        $input['lookup_password'] = 'incorrect-password';

        $this->expectException(\App\Services\Tickets\TicketValidationException::class);
        $service->create($input);
    }

    public function testLookupPasswordOverBcryptByteLimitIsRejectedWithoutCreatingATicket(): void
    {
        $service = new TicketService($this->db);
        $input = $this->ticketInput('password-byte-limit');
        $input['lookup_password'] = str_repeat('a', 73);

        $this->expectException(\App\Services\Tickets\TicketValidationException::class);
        try {
            $service->create($input);
        } finally {
            $this->assertSame(0, $this->db->table('tickets')->countAllResults());
        }
    }

    public function testPrivateNoteIsNotReturnedToTheCustomer(): void
    {
        $staffId = $this->staffId();
        $service = new TicketService($this->db);
        $ticket = $service->create($this->ticketInput('note-ticket'));
        $service->addPrivateNote((int) $ticket['id'], $staffId, [
            'request_key' => $this->key('private-note'),
            'body' => '고객에게 보이면 안 되는 내부 메모입니다.',
        ]);

        $customerMessages = $service->publicMessages((int) $ticket['id']);
        $staffMessages = $service->allMessages((int) $ticket['id']);

        $this->assertCount(1, $customerMessages);
        $this->assertSame('customer', $customerMessages[0]['kind']);
        $this->assertCount(2, $staffMessages);
        $this->assertSame('note', $staffMessages[1]['kind']);
        $this->assertSame('고객에게 보이면 안 되는 내부 메모입니다.', $staffMessages[1]['body']);
    }

    public function testPlainTextTicketPreservesCodeForEscapedRendering(): void
    {
        $service = new TicketService($this->db);
        $input = $this->ticketInput('code-snippet');
        $input['body'] = '<script>const example = "문의 코드";</script>';
        $ticket = $service->create($input);

        $messages = $service->publicMessages((int) $ticket['id']);

        $this->assertSame('<script>const example = "문의 코드";</script>', $messages[0]['body']);
    }

    public function testCustomerReplyReopensClosedTicketAndIsIdempotent(): void
    {
        $staffId = $this->staffId();
        $service = new TicketService($this->db);
        $ticket = $service->create($this->ticketInput('reopen-ticket'));
        $service->changeStatus((int) $ticket['id'], 'closed', $staffId);
        $reply = ['request_key' => $this->key('customer-reply'), 'body' => '추가 확인이 필요합니다.'];

        $first = $service->customerReply((int) $ticket['id'], $reply);
        $again = $service->customerReply((int) $ticket['id'], $reply);
        $reloaded = $service->ticketForAdmin((int) $ticket['id']);

        $this->assertSame($first['id'], $again['id']);
        $this->assertSame('open', $reloaded['status']);
        $this->assertSame(2, $this->db->table('ticket_messages')->where('ticket_id', $ticket['id'])->countAllResults());
    }

    public function testEventFailureRollsBackTicketAndMessage(): void
    {
        $table = $this->db->prefixTable('domain_events');
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query("CREATE TRIGGER ticket_event_failure BEFORE INSERT ON {$table} BEGIN SELECT RAISE(ABORT, 'event write failed'); END");
        } elseif ($this->db->DBDriver === 'MySQLi') {
            $this->db->query("CREATE TRIGGER ticket_event_failure BEFORE INSERT ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event write failed'");
        } else {
            $this->markTestSkipped('현재 DB 드라이버의 트리거 문법을 추가해야 합니다.');
        }
        $service = new TicketService($this->db);

        $failed = false;
        try {
            $service->create($this->ticketInput('event-failure'));
        } catch (Throwable) {
            $failed = true;
        }
        $this->assertTrue($failed, '이벤트 저장 실패가 문의 저장을 중단해야 합니다.');
        $this->assertSame(0, $this->db->table('tickets')->countAllResults());
        $this->assertSame(0, $this->db->table('ticket_messages')->countAllResults());
    }

    /** @return array<string, string> */
    private function ticketInput(string $suffix): array
    {
        return [
            'request_key' => $this->key($suffix),
            'requester_name' => '홍길동',
            'requester_email' => 'customer@example.test',
            'subject' => '설치 문의',
            'lookup_password' => 'lookup-password-123',
            'body' => '설치 과정에서 도움이 필요합니다.',
        ];
    }

    private function staffId(): int
    {
        $this->db->table('users')->insert([
            'email' => 'agent@example.test',
            'password_hash' => password_hash('a-safe-test-password', PASSWORD_DEFAULT),
            'display_name' => '담당자',
            'role' => 'agent',
            'auth_version' => 1,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function key(string $suffix): string
    {
        return str_pad($suffix, 20, 'x');
    }
}
