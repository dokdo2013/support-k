<?php

namespace App\Services\Delivery;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use InvalidArgumentException;

final class DeliveryRetry
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Starts a new bounded attempt cycle after an operator explicitly accepts
     * the duplicate-send risk of retrying an unknown outcome.
     */
    public function request(int $jobId, ?DateTimeImmutable $now = null): bool
    {
        if ($jobId < 1) {
            throw new InvalidArgumentException('Job ID must be positive.');
        }
        $now ??= new DateTimeImmutable('now');

        $this->db->table('delivery_jobs')
            ->where('id', $jobId)
            ->whereIn('status', ['unknown', 'permanent_failure'])
            ->set('status', 'pending')
            ->set('cycle_attempt_count', 0)
            ->set('manual_retry_count', 'manual_retry_count + 1', false)
            ->set('available_at', $now->format('Y-m-d H:i:s'))
            ->set('lease_token', null)
            ->set('lease_started_at', null)
            ->set('lease_expires_at', null)
            ->set('updated_at', $now->format('Y-m-d H:i:s'))
            ->set('completed_at', null)
            ->update();

        return $this->db->affectedRows() === 1;
    }
}
