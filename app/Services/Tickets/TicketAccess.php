<?php

namespace App\Services\Tickets;

use CodeIgniter\Session\Session;

/** Keeps the lookup proof in the session without retaining its password. */
class TicketAccess
{
    private const SESSION_KEY = 'customer_ticket_access';

    public function __construct(private readonly Session $session)
    {
    }

    /** @param array{id: int|string} $ticket */
    public function grant(array $ticket): void
    {
        $this->session->set(self::SESSION_KEY, [
            'ticket_id' => (int) $ticket['id'],
            'verifier' => bin2hex(random_bytes(24)),
        ]);
    }

    public function allows(int $ticketId): bool
    {
        $access = $this->session->get(self::SESSION_KEY);

        return is_array($access)
            && isset($access['ticket_id'], $access['verifier'])
            && is_string($access['verifier'])
            && hash_equals((string) $ticketId, (string) $access['ticket_id']);
    }

    public function revoke(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }
}
