<?php

declare(strict_types=1);

use App\Services\Tickets\TicketAccess;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TicketAccessTest extends CIUnitTestCase
{
    public function testLookupGrantOnlyAllowsTheVerifiedTicket(): void
    {
        $access = new TicketAccess(service('session'));
        $access->grant(['id' => 42]);

        $stored = session('customer_ticket_access');

        $this->assertTrue($access->allows(42));
        $this->assertFalse($access->allows(43));
        $this->assertIsArray($stored);
        $this->assertSame(['ticket_id', 'verifier'], array_keys($stored));
        $this->assertArrayNotHasKey('lookup_password', $stored);
    }
}
