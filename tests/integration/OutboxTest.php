<?php

use App\Database\Migrations\CreateDelivery;
use App\Services\Delivery\DestinationRepository;
use App\Services\Delivery\Outbox;
use App\Services\Delivery\OutboxFanout;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

require_once APPPATH . 'Database/Migrations/2026-09-12-000003_CreateDelivery.php';

final class OutboxTest extends CIUnitTestCase
{
    private CreateDelivery $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect('tests');
        $this->migration = new CreateDelivery(Database::forge('tests'));
        $this->migration->down();
        $this->migration->up();
    }

    protected function tearDown(): void
    {
        $this->migration->down();
        parent::tearDown();
    }

    public function testRecordParticipatesInCallerTransactionAndDoesNotCommitIt(): void
    {
        $this->db->transBegin();
        (new Outbox($this->db))->record('ticket.created', ['ticket_id' => 4, 'ticket_version' => 1]);
        $this->db->transRollback();

        self::assertSame(0, $this->db->table('domain_events')->countAllResults());
    }

    public function testDedupeIsStableAndPayloadRejectsContent(): void
    {
        $outbox = new Outbox($this->db);
        $first = $outbox->record('ticket.created', ['ticket_id' => 4, 'ticket_version' => 1], 'ticket:4:created');
        $second = $outbox->record('ticket.created', ['ticket_id' => 4, 'ticket_version' => 1], 'ticket:4:created');

        self::assertSame($first, $second);
        self::assertSame(1, $this->db->table('domain_events')->countAllResults());

        $this->expectException(InvalidArgumentException::class);
        $outbox->record('ticket.created', ['ticket_id' => 5, 'body' => 'content must stay out']);
    }

    public function testFanoutCreatesOneJobPerDestinationOnlyOnce(): void
    {
        $destinations = new DestinationRepository($this->db);
        $first = $destinations->register('example/one', 'extensions.example.one');
        $second = $destinations->register('example/two', 'extensions.example.two');
        (new Outbox($this->db))->record('ticket.created', ['ticket_id' => 9, 'ticket_version' => 1]);

        $fanout = new OutboxFanout($this->db);
        self::assertSame(1, $fanout->fanOut([$first, $second]));
        self::assertSame(0, $fanout->fanOut([$first, $second]));
        self::assertSame(2, $this->db->table('delivery_jobs')->countAllResults());
    }

    public function testEventCompletesFanoutWhenNoModulesAreRegistered(): void
    {
        $eventId = (new Outbox($this->db))->record('ticket.created', ['ticket_id' => 22, 'ticket_version' => 1]);

        self::assertSame(1, (new OutboxFanout($this->db))->fanOutRegistered());
        self::assertSame(0, $this->db->table('delivery_jobs')->countAllResults());
        self::assertSame(
            'fanned_out',
            $this->db->table('domain_events')->select('status')->where('id', $eventId)->get()->getRow()->status,
        );
    }

    public function testMigrationUsesConfiguredDatabasePrefix(): void
    {
        $tables = $this->db->listTables();

        self::assertContains($this->db->getPrefix() . 'domain_events', $tables);
        self::assertContains($this->db->getPrefix() . 'delivery_jobs', $tables);
    }
}
