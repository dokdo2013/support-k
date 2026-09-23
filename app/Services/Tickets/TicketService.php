<?php

namespace App\Services\Tickets;

use App\Services\Delivery\Outbox;
use CodeIgniter\Database\BaseConnection;
use Throwable;

class TicketService
{
    private const CUSTOMER = 'customer';
    private const STAFF = 'staff';
    private const NOTE = 'note';
    private const STATUS_OPEN = 'open';
    private const STATUS_PENDING = 'pending';
    private const STATUS_CLOSED = 'closed';

    private readonly Outbox $outbox;

    public function __construct(private readonly BaseConnection $db, ?Outbox $outbox = null)
    {
        $this->outbox = $outbox ?? new Outbox($db);
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $requestKey = $this->requestKey($input['request_key'] ?? null);
        $lookupPassword = $this->lookupPassword($input['lookup_password'] ?? null);
        $existing = $this->ticketByRequestKey($requestKey);
        if ($existing !== null) {
            $this->verifyExistingCreatePassword($existing, $lookupPassword);
            return $this->withoutSecrets($existing);
        }

        $name = $this->optionalShortText($input['requester_name'] ?? null, '이름', 100);
        $email = $this->email($input['requester_email'] ?? null);
        $subject = $this->shortText($input['subject'] ?? null, '제목', 200);
        $body = $this->body($input['body'] ?? null);
        $now = gmdate('Y-m-d H:i:s');

        if (! $this->db->transStart()) {
            throw new TicketException('문의 저장을 시작할 수 없습니다. 잠시 후 다시 시도해 주세요.');
        }
        $completed = false;
        try {
            $number = $this->newNumber();
            $this->db->table('tickets')->insert([
                'number' => $number,
                'lookup_password_hash' => password_hash($lookupPassword, PASSWORD_DEFAULT),
                'requester_name' => $name,
                'requester_email' => $email,
                'subject' => $subject,
                'status' => self::STATUS_OPEN,
                'version' => 1,
                'client_request_key' => $requestKey,
                'last_message_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $ticketId = (int) $this->db->insertID();
            $this->db->table('ticket_messages')->insert([
                'ticket_id' => $ticketId,
                'kind' => self::CUSTOMER,
                'body' => $body,
                'author_user_id' => null,
                'client_request_key' => $requestKey,
                'version' => 1,
                'created_at' => $now,
            ]);
            $messageId = (int) $this->db->insertID();
            $this->outbox->record('ticket.created', [
                'ticket_id' => $ticketId,
                'ticket_version' => 1,
                'message_id' => $messageId,
                'message_version' => 1,
            ], 'ticket:' . $ticketId . ':created');
            $completed = $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        if (! $completed || ! $this->db->transStatus()) {
            $existing = $this->ticketByRequestKey($requestKey);
            if ($existing !== null) {
                $this->verifyExistingCreatePassword($existing, $lookupPassword);
                return $this->withoutSecrets($existing);
            }
            throw new TicketException('문의 저장에 실패했습니다. 잠시 후 다시 시도해 주세요.');
        }

        return $this->withoutSecrets($this->requireTicket($ticketId));
    }

    /** @return array<string, mixed>|null */
    public function lookup(string $number, string $lookupPassword): ?array
    {
        try {
            $lookupPassword = $this->lookupPassword($lookupPassword);
        } catch (TicketValidationException) {
            return null;
        }
        $ticket = $this->ticketByNumber($number);
        if ($ticket === null || ! password_verify($lookupPassword, (string) $ticket['lookup_password_hash'])) {
            return null;
        }

        return $this->withoutSecrets($ticket);
    }

    /** @return array<string, mixed> */
    public function ticketForCustomer(string $number): array
    {
        return $this->withoutSecrets($this->requireTicketByNumber($number));
    }

    /** @return array<string, mixed> */
    public function ticketForAdmin(int $ticketId): array
    {
        return $this->withoutSecrets($this->requireTicket($ticketId));
    }

    /** @return list<array<string, mixed>> */
    public function publicMessages(int $ticketId): array
    {
        return $this->db->table('ticket_messages')
            ->select('id, ticket_id, kind, body, version, created_at')
            ->where('ticket_id', $ticketId)
            ->whereIn('kind', [self::CUSTOMER, self::STAFF])
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function allMessages(int $ticketId): array
    {
        return $this->db->table('ticket_messages')
            ->select('ticket_messages.id, ticket_messages.ticket_id, ticket_messages.kind, ticket_messages.body, ticket_messages.author_user_id, ticket_messages.version, ticket_messages.created_at, users.display_name AS author_name')
            ->join('users', 'users.id = ticket_messages.author_user_id', 'left')
            ->where('ticket_messages.ticket_id', $ticketId)
            ->orderBy('ticket_messages.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function customerReply(int $ticketId, array $input): array
    {
        return $this->postMessage($ticketId, self::CUSTOMER, $this->body($input['body'] ?? null), $this->requestKey($input['request_key'] ?? null), null, self::STATUS_OPEN);
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function staffReply(int $ticketId, int $staffId, array $input): array
    {
        if ($staffId < 1) {
            throw new TicketValidationException('로그인 정보를 확인할 수 없습니다.');
        }

        return $this->postMessage($ticketId, self::STAFF, $this->body($input['body'] ?? null), $this->requestKey($input['request_key'] ?? null), $staffId, self::STATUS_PENDING);
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function addPrivateNote(int $ticketId, int $staffId, array $input): array
    {
        if ($staffId < 1) {
            throw new TicketValidationException('로그인 정보를 확인할 수 없습니다.');
        }

        return $this->postMessage($ticketId, self::NOTE, $this->body($input['body'] ?? null), $this->requestKey($input['request_key'] ?? null), $staffId, null);
    }

    /** @return array<string, mixed> */
    public function changeStatus(int $ticketId, string $status, int $staffId): array
    {
        if (! in_array($status, [self::STATUS_OPEN, self::STATUS_PENDING, self::STATUS_CLOSED], true)) {
            throw new TicketValidationException('올바르지 않은 상태입니다.');
        }
        if ($staffId < 1) {
            throw new TicketValidationException('로그인 정보를 확인할 수 없습니다.');
        }

        $ticket = $this->requireTicket($ticketId);
        if ($ticket['status'] === $status) {
            return $this->withoutSecrets($ticket);
        }

        $version = (int) $ticket['version'] + 1;
        if (! $this->db->transStart()) {
            throw new TicketException('상태 변경을 시작할 수 없습니다. 잠시 후 다시 시도해 주세요.');
        }
        $completed = false;
        try {
            $this->db->table('tickets')->where('id', $ticketId)->update([
                'status' => $status,
                'version' => $version,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->outbox->record('ticket.status_changed', [
                'ticket_id' => $ticketId,
                'ticket_version' => $version,
                'staff_id' => $staffId,
            ], 'ticket:' . $ticketId . ':status:' . $version);
            $completed = $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        if (! $completed || ! $this->db->transStatus()) {
            throw new TicketException('상태 변경에 실패했습니다.');
        }

        return $this->withoutSecrets($this->requireTicket($ticketId));
    }

    /** @return list<array<string, mixed>> */
    public function list(string $status = '', string $query = '', int $limit = 50): array
    {
        $builder = $this->db->table('tickets')->select('id, number, requester_name, requester_email, subject, status, version, last_message_at, created_at');
        if (in_array($status, [self::STATUS_OPEN, self::STATUS_PENDING, self::STATUS_CLOSED], true)) {
            $builder->where('status', $status);
        }
        if ($query !== '') {
            $builder->groupStart()->like('number', $query)->orLike('subject', $query)->orLike('requester_name', $query)->groupEnd();
        }

        return $builder->orderBy('last_message_at', 'DESC')->limit(max(1, min(100, $limit)))->get()->getResultArray();
    }

    /** @return array<string, mixed> */
    private function postMessage(int $ticketId, string $kind, string $body, string $requestKey, ?int $staffId, ?string $nextStatus): array
    {
        $existing = $this->messageByRequestKey($ticketId, $requestKey);
        if ($existing !== null) {
            return $existing;
        }
        $ticket = $this->requireTicket($ticketId);
        $version = (int) $ticket['version'] + 1;
        $now = gmdate('Y-m-d H:i:s');

        if (! $this->db->transStart()) {
            throw new TicketException('답글 저장을 시작할 수 없습니다. 잠시 후 다시 시도해 주세요.');
        }
        $completed = false;
        try {
            $this->db->table('ticket_messages')->insert([
                'ticket_id' => $ticketId,
                'kind' => $kind,
                'body' => $body,
                'author_user_id' => $staffId,
                'client_request_key' => $requestKey,
                'version' => 1,
                'created_at' => $now,
            ]);
            $messageId = (int) $this->db->insertID();
            $ticketChange = ['version' => $version, 'updated_at' => $now];
            if ($kind !== self::NOTE) {
                $ticketChange['last_message_at'] = $now;
            }
            if ($nextStatus !== null) {
                $ticketChange['status'] = $nextStatus;
            }
            $this->db->table('tickets')->where('id', $ticketId)->update($ticketChange);
            $event = match ($kind) {
                self::CUSTOMER => 'ticket.customer_replied',
                self::STAFF => 'ticket.staff_replied',
                self::NOTE => 'ticket.private_note_added',
            };
            $this->outbox->record($event, [
                'ticket_id' => $ticketId,
                'ticket_version' => $version,
                'message_id' => $messageId,
                'message_version' => 1,
            ], 'ticket:' . $ticketId . ':message:' . $messageId);
            $completed = $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        if (! $completed || ! $this->db->transStatus()) {
            $existing = $this->messageByRequestKey($ticketId, $requestKey);
            if ($existing !== null) {
                return $existing;
            }
            throw new TicketException('답글 저장에 실패했습니다. 잠시 후 다시 시도해 주세요.');
        }

        return $this->requireMessage($messageId);
    }

    /** @return array<string, mixed> */
    private function requireTicket(int $ticketId): array
    {
        $ticket = $this->db->table('tickets')->where('id', $ticketId)->get()->getRowArray();
        if ($ticket === null) {
            throw new TicketNotFoundException('문의를 찾을 수 없습니다.');
        }
        return $ticket;
    }

    /** @return array<string, mixed> */
    private function requireTicketByNumber(string $number): array
    {
        $ticket = $this->ticketByNumber($number);
        if ($ticket === null) {
            throw new TicketNotFoundException('문의를 찾을 수 없습니다.');
        }
        return $ticket;
    }

    /** @return array<string, mixed>|null */
    private function ticketByNumber(string $number): ?array
    {
        $ticket = $this->db->table('tickets')->where('number', strtoupper(trim($number)))->get()->getRowArray();
        return $ticket ?: null;
    }

    /** @return array<string, mixed>|null */
    private function ticketByRequestKey(string $requestKey): ?array
    {
        $ticket = $this->db->table('tickets')->where('client_request_key', $requestKey)->get()->getRowArray();
        return $ticket ?: null;
    }

    /** @return array<string, mixed>|null */
    private function messageByRequestKey(int $ticketId, string $requestKey): ?array
    {
        $message = $this->db->table('ticket_messages')->where('ticket_id', $ticketId)->where('client_request_key', $requestKey)->get()->getRowArray();
        return $message ?: null;
    }

    /** @return array<string, mixed> */
    private function requireMessage(int $messageId): array
    {
        $message = $this->db->table('ticket_messages')->where('id', $messageId)->get()->getRowArray();
        if ($message === null) {
            throw new TicketException('답글을 찾을 수 없습니다.');
        }
        return $message;
    }

    private function requestKey(mixed $value): string
    {
        $key = is_string($value) ? trim($value) : '';
        if (! preg_match('/^[A-Za-z0-9_-]{20,80}$/', $key)) {
            throw new TicketValidationException('요청을 확인할 수 없습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.');
        }
        return $key;
    }

    private function lookupPassword(mixed $value): string
    {
        $password = is_string($value) ? $value : '';
        if ($this->length($password) < 8 || strlen($password) > 72) {
            throw new TicketValidationException('조회 비밀번호는 8글자 이상, 72바이트 이하로 입력해 주세요.');
        }
        return $password;
    }

    private function shortText(mixed $value, string $label, int $limit): string
    {
        $text = trim(is_string($value) ? $value : '');
        if ($text === '' || $this->length($text) > $limit) {
            throw new TicketValidationException($label . '을(를) 1자 이상 ' . $limit . '자 이하로 입력해 주세요.');
        }
        return $text;
    }

    private function optionalShortText(mixed $value, string $label, int $limit): string
    {
        $text = trim(is_string($value) ? $value : '');
        if ($this->length($text) > $limit) {
            throw new TicketValidationException($label . '을(를) ' . $limit . '자 이하로 입력해 주세요.');
        }
        return $text;
    }

    private function email(mixed $value): ?string
    {
        $email = trim(is_string($value) ? $value : '');
        if ($email === '') {
            return null;
        }
        if ($this->length($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new TicketValidationException('이메일 주소 형식을 확인해 주세요.');
        }
        return $email;
    }

    private function body(mixed $value): string
    {
        $body = trim(is_string($value) ? $value : '');
        if ($body === '' || $this->length($body) > 10000) {
            throw new TicketValidationException('내용을 1자 이상 10,000자 이하로 입력해 주세요.');
        }
        return $body;
    }

    private function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }
        $count = preg_match_all('/./us', $value);

        return $count === false ? strlen($value) : $count;
    }

    private function newNumber(): string
    {
        return 'SK-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }

    /** @param array<string, mixed> $ticket
     *  @return array<string, mixed>
     */
    private function withoutSecrets(array $ticket): array
    {
        unset($ticket['lookup_password_hash'], $ticket['client_request_key']);

        return $ticket;
    }

    /** @param array<string, mixed> $ticket */
    private function verifyExistingCreatePassword(array $ticket, string $lookupPassword): void
    {
        if (! password_verify($lookupPassword, (string) $ticket['lookup_password_hash'])) {
            throw new TicketValidationException('문의 접수 확인에 실패했습니다. 입력 내용을 다시 확인해 주세요.');
        }
    }
}
