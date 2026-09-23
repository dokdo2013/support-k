<?php

use App\Contracts\DeliveryResult;
use App\Contracts\Notification;
use App\Contracts\NotificationChannel;
use App\Database\Migrations\CreateDelivery;
use App\Services\Delivery\DeliveryExecutor;
use App\Services\Delivery\DeliveryRetry;
use App\Services\Delivery\DestinationRepository;
use App\Services\Delivery\Outbox;
use App\Services\Delivery\OutboxFanout;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

require_once APPPATH . 'Database/Migrations/2026-09-12-000003_CreateDelivery.php';

final class DeliveryExecutorTest extends CIUnitTestCase
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

    public function testRetryableResultWaitsAndThenSucceedsWithinBound(): void
    {
        $now = new DateTimeImmutable('2026-09-12 00:00:00 UTC');
        $channel = new DeliverySequenceChannel([
            DeliveryResult::retryable('busy', $now->modify('+30 seconds')),
            DeliveryResult::success('provider-test-1'),
        ]);
        $this->queueOne();
        $executor = new DeliveryExecutor($this->db, static fn (string $id) => $id === $channel->id() ? $channel : null);

        $first = $executor->run(1, $now);
        $tooEarly = $executor->run(1, $now->modify('+20 seconds'));
        $second = $executor->run(1, $now->modify('+30 seconds'));

        self::assertSame(1, $first['retry_wait']);
        self::assertSame(0, $tooEarly['processed']);
        self::assertSame(1, $second['succeeded']);
        self::assertSame(2, $channel->calls);
        self::assertSame($channel->idempotencyKeys[0], $channel->idempotencyKeys[1]);
        self::assertSame(2, $this->db->table('delivery_attempts')->countAllResults());
    }

    public function testExpiredLeaseBecomesUnknownWithoutBlindSend(): void
    {
        $jobId = $this->queueOne();
        $this->db->table('delivery_jobs')->where('id', $jobId)->update([
            'status' => 'leased',
            'attempt_count' => 1,
            'cycle_attempt_count' => 1,
            'lease_token' => 'expired-lease-token',
            'lease_expires_at' => '2026-09-11 23:59:00',
        ]);
        $channel = new DeliverySequenceChannel([DeliveryResult::success()]);
        $executor = new DeliveryExecutor($this->db, static fn () => $channel);

        $summary = $executor->run(1, new DateTimeImmutable('2026-09-12 00:00:00 UTC'));
        $job = $this->db->table('delivery_jobs')->where('id', $jobId)->get()->getRowArray();

        self::assertSame(0, $summary['processed']);
        self::assertSame('unknown', $job['status']);
        self::assertSame(0, $channel->calls);
    }

    public function testUnknownOutcomeNeedsExplicitManualRetry(): void
    {
        $jobId = $this->queueOne();
        $channel = new DeliverySequenceChannel([
            DeliveryResult::unknown('connection_lost'),
            DeliveryResult::success('provider-test-2'),
        ]);
        $executor = new DeliveryExecutor($this->db, static fn () => $channel);
        $now = new DateTimeImmutable('2026-09-12 00:00:00 UTC');

        self::assertSame(1, $executor->run(1, $now)['unknown']);
        self::assertSame(0, $executor->run(1, $now->modify('+1 hour'))['processed']);
        self::assertTrue((new DeliveryRetry($this->db))->request($jobId, $now->modify('+1 hour')));
        self::assertSame(1, $executor->run(1, $now->modify('+1 hour'))['succeeded']);
        self::assertSame(2, $channel->calls);
    }

    public function testDisabledDestinationPausesThenResumesWithoutAnAttempt(): void
    {
        $jobId = $this->queueOne();
        $destinationId = (int) $this->db->table('delivery_jobs')->select('destination_id')->where('id', $jobId)->get()->getRow()->destination_id;
        $destinations = new DestinationRepository($this->db);
        $now = new DateTimeImmutable('2026-09-12 00:00:00 UTC');
        self::assertTrue($destinations->setEnabled($destinationId, false, $now));

        $channel = new DeliverySequenceChannel([DeliveryResult::success()]);
        $executor = new DeliveryExecutor($this->db, static fn () => $channel);
        self::assertSame(0, $executor->run(1, $now)['processed']);
        self::assertSame('paused', $this->db->table('delivery_jobs')->select('status')->where('id', $jobId)->get()->getRow()->status);
        self::assertSame(0, $this->db->table('delivery_attempts')->countAllResults());

        self::assertTrue($destinations->setEnabled($destinationId, true, $now->modify('+1 second')));
        self::assertSame(1, $executor->run(1, $now->modify('+1 second'))['succeeded']);
    }

    public function testExecutorRefusesToSendInsideAnOuterTransaction(): void
    {
        $this->queueOne();
        $channel = new DeliverySequenceChannel([DeliveryResult::success()]);
        $executor = new DeliveryExecutor($this->db, static fn () => $channel);

        self::assertTrue($this->db->transBegin());
        try {
            $executor->run(1, new DateTimeImmutable('2026-09-12 00:00:00 UTC'));
            self::fail('Expected delivery execution to reject an outer transaction.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('active transaction', $exception->getMessage());
            self::assertSame(0, $channel->calls);
        } finally {
            $this->db->transRollback();
        }
    }

    private function queueOne(): int
    {
        $destination = (new DestinationRepository($this->db))->register('example/test-channel', 'extensions.example.test_channel');
        (new Outbox($this->db))->record('ticket.created', ['ticket_id' => 17, 'ticket_version' => 1]);
        (new OutboxFanout($this->db))->fanOut([$destination], 20, new DateTimeImmutable('2026-09-12 00:00:00 UTC'));

        return (int) $this->db->table('delivery_jobs')->select('id')->get()->getRow()->id;
    }
}

final class DeliverySequenceChannel implements NotificationChannel
{
    public int $calls = 0;

    /** @var list<string> */
    public array $idempotencyKeys = [];

    /** @param list<DeliveryResult> $results */
    public function __construct(private array $results)
    {
    }

    public function id(): string
    {
        return 'example/test-channel';
    }

    public function deliver(Notification $notification): DeliveryResult
    {
        $this->idempotencyKeys[] = $notification->idempotencyKey;
        $result = $this->results[$this->calls] ?? DeliveryResult::permanent('unexpected_call');
        $this->calls++;

        return $result;
    }
}
