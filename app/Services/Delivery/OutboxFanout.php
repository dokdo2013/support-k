<?php

namespace App\Services\Delivery;

use CodeIgniter\Database\BaseConnection;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class OutboxFanout
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly int $leaseSeconds = 30,
    ) {
        if ($leaseSeconds < 5 || $leaseSeconds > 300) {
            throw new InvalidArgumentException('Fan-out lease must be between 5 and 300 seconds.');
        }
    }

    public function fanOutRegistered(int $limit = 20, ?DateTimeImmutable $now = null): int
    {
        return $this->fanOut((new DestinationRepository($this->db))->activeIds(), $limit, $now);
    }

    /** @param list<int> $destinationIds */
    public function fanOut(array $destinationIds, int $limit = 20, ?DateTimeImmutable $now = null): int
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Fan-out batch limit must be between 1 and 100.');
        }
        $destinationIds = array_values(array_unique($destinationIds));
        foreach ($destinationIds as $destinationId) {
            if ($destinationId < 1) {
                throw new InvalidArgumentException('Destination IDs must be positive integers.');
            }
        }
        if ($destinationIds !== []) {
            $found = $this->db->table('notification_destinations')
                ->select('id')
                ->whereIn('id', $destinationIds)
                ->get()
                ->getResultArray();
            if (count($found) !== count($destinationIds)) {
                throw new InvalidArgumentException('A fan-out destination does not exist.');
            }
        }

        $processed = 0;
        $now ??= new DateTimeImmutable('now');
        while ($processed < $limit && $this->fanOutOne($destinationIds, $now)) {
            $processed++;
        }

        return $processed;
    }

    /** @param list<int> $destinationIds */
    private function fanOutOne(array $destinationIds, DateTimeImmutable $now): bool
    {
        $nowText = $now->format('Y-m-d H:i:s');
        $token = bin2hex(random_bytes(16));
        $expires = $now->add(new DateInterval('PT' . $this->leaseSeconds . 'S'))->format('Y-m-d H:i:s');

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start the fan-out transaction.');
        }
        try {
            $event = $this->claimableEvents($nowText)->limit(1)->get()->getRowArray();
            if ($event === null) {
                if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                    throw new RuntimeException('Unable to commit the empty fan-out claim.');
                }

                return false;
            }

            $builder = $this->db->table('domain_events')->where('id', $event['id']);
            $this->claimableWhere($builder, $nowText)->update([
                'status' => 'leased',
                'lease_token' => $token,
                'lease_expires_at' => $expires,
            ]);
            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return true;
            }

            foreach ($destinationIds as $destinationId) {
                $this->db->table('delivery_jobs')->insert([
                    'event_id' => $event['id'],
                    'destination_id' => $destinationId,
                    'status' => 'pending',
                    'attempt_count' => 0,
                    'cycle_attempt_count' => 0,
                    'manual_retry_count' => 0,
                    'available_at' => $nowText,
                    'lease_token' => null,
                    'lease_started_at' => null,
                    'lease_expires_at' => null,
                    'last_result' => null,
                    'last_error' => null,
                    'created_at' => $nowText,
                    'updated_at' => $nowText,
                    'completed_at' => null,
                ]);
            }

            $this->db->table('domain_events')
                ->where('id', $event['id'])
                ->where('status', 'leased')
                ->where('lease_token', $token)
                ->update([
                    'status' => 'fanned_out',
                    'lease_token' => null,
                    'lease_expires_at' => null,
                    'fanned_out_at' => $nowText,
                ]);
            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return true;
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit the fan-out jobs.');
            }

            return true;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function claimableEvents(string $now): \CodeIgniter\Database\BaseBuilder
    {
        $builder = $this->db->table('domain_events')->select('id');
        $this->claimableWhere($builder, $now);

        return $builder->orderBy('created_at', 'ASC')->orderBy('id', 'ASC');
    }

    private function claimableWhere(\CodeIgniter\Database\BaseBuilder $builder, string $now): \CodeIgniter\Database\BaseBuilder
    {
        return $builder->groupStart()
            ->where('status', 'pending')
            ->orGroupStart()
            ->where('status', 'leased')
            ->where('lease_expires_at <', $now)
            ->groupEnd()
            ->groupEnd();
    }
}
