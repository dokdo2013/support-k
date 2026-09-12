<?php

namespace App\Services\Delivery;

use App\Contracts\DeliveryResult;
use App\Contracts\Notification;
use App\Contracts\NotificationChannel;
use Closure;
use CodeIgniter\Database\BaseConnection;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class DeliveryExecutor
{
    private readonly Closure $resolveChannel;

    /** @param callable(string):(?NotificationChannel) $resolveChannel */
    public function __construct(
        private readonly BaseConnection $db,
        callable $resolveChannel,
        private readonly int $maxAttempts = 3,
        private readonly int $leaseSeconds = 60,
        private readonly int $maxBatch = 25,
        private readonly int $maxWallClockMilliseconds = 2000,
        private readonly int $providerTimeoutSeconds = 30,
    ) {
        if ($maxAttempts < 1 || $maxAttempts > 10) {
            throw new InvalidArgumentException('Maximum attempts must be between 1 and 10.');
        }
        if ($leaseSeconds < 5 || $leaseSeconds > 600) {
            throw new InvalidArgumentException('Delivery lease must be between 5 and 600 seconds.');
        }
        if ($maxBatch < 1 || $maxBatch > 100) {
            throw new InvalidArgumentException('Maximum batch must be between 1 and 100.');
        }
        if ($maxWallClockMilliseconds < 100 || $maxWallClockMilliseconds > 30000) {
            throw new InvalidArgumentException('Wall-clock budget must be between 100 and 30000 milliseconds.');
        }
        if ($providerTimeoutSeconds < 1 || $providerTimeoutSeconds >= $leaseSeconds) {
            throw new InvalidArgumentException('Provider timeout must be positive and shorter than the delivery lease.');
        }
        $this->resolveChannel = Closure::fromCallable($resolveChannel);
    }

    /** @return array{processed:int,succeeded:int,retry_wait:int,permanent_failure:int,unknown:int,paused:int} */
    public function run(int $limit = 10, ?DateTimeImmutable $now = null): array
    {
        if ($limit < 1 || $limit > $this->maxBatch) {
            throw new InvalidArgumentException('Requested batch is outside the configured bound.');
        }
        if ($this->db->transDepth !== 0) {
            throw new RuntimeException('Delivery execution requires a connection without an active transaction.');
        }
        $now ??= new DateTimeImmutable('now');
        $deadline = hrtime(true) + ($this->maxWallClockMilliseconds * 1_000_000);
        $this->quarantineExpiredLeases($limit, $now);
        $summary = ['processed' => 0, 'succeeded' => 0, 'retry_wait' => 0, 'permanent_failure' => 0, 'unknown' => 0, 'paused' => 0];

        while ($summary['processed'] < $limit && hrtime(true) < $deadline) {
            $job = $this->claim($now);
            if ($job === null) {
                break;
            }
            if (! $this->destinationEnabled((int) $job['destination_id'])) {
                if ($this->pause($job, $now)) {
                    $summary['processed']++;
                    $summary['paused']++;
                }
                continue;
            }
            $result = $this->deliver($job);
            $status = $this->complete($job, $result, $now);
            if ($status === null) {
                continue;
            }
            $summary['processed']++;
            $summary[$status]++;
        }

        return $summary;
    }

    public function quarantineExpiredLeases(int $limit = 25, ?DateTimeImmutable $now = null): int
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Lease quarantine limit must be between 1 and 100.');
        }
        $now ??= new DateTimeImmutable('now');
        $nowText = $now->format('Y-m-d H:i:s');
        $expired = $this->db->table('delivery_jobs')
            ->select('id, attempt_count, lease_token, lease_started_at, lease_expires_at')
            ->where('status', 'leased')
            ->where('lease_expires_at <', $nowText)
            ->orderBy('lease_expires_at', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        $count = 0;
        foreach ($expired as $job) {
            if (! $this->db->transBegin()) {
                throw new RuntimeException('Unable to start the expired-lease transaction.');
            }
            try {
                $this->db->table('delivery_jobs')
                    ->where('id', $job['id'])
                    ->where('status', 'leased')
                    ->where('lease_token', $job['lease_token'])
                    ->where('lease_expires_at <', $nowText)
                    ->update([
                        'status' => 'unknown',
                        'lease_token' => null,
                        'lease_started_at' => null,
                        'lease_expires_at' => null,
                        'last_result' => 'unknown',
                        'last_error' => 'lease_expired',
                        'updated_at' => $nowText,
                        'completed_at' => $nowText,
                    ]);
                if ($this->db->affectedRows() !== 1) {
                    $this->db->transRollback();
                    continue;
                }
                $this->db->table('delivery_attempts')->ignore(true)->insert([
                    'job_id' => $job['id'],
                    'attempt_no' => $job['attempt_count'],
                    'result' => 'unknown',
                    'provider_reference' => null,
                    'error_code' => 'lease_expired',
                    'retry_at' => null,
                    'started_at' => $job['lease_started_at'] ?? $job['lease_expires_at'],
                    'finished_at' => $nowText,
                ]);
                if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                    throw new RuntimeException('Unable to commit the expired-lease quarantine.');
                }
                $count++;
            } catch (Throwable $exception) {
                $this->db->transRollback();
                throw $exception;
            }
        }

        return $count;
    }

    /** @return array<string,mixed>|null */
    private function claim(DateTimeImmutable $now): ?array
    {
        $nowText = $now->format('Y-m-d H:i:s');
        $token = bin2hex(random_bytes(16));
        $expires = $now->add(new DateInterval('PT' . $this->leaseSeconds . 'S'))->format('Y-m-d H:i:s');

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start the delivery claim transaction.');
        }
        try {
            $candidate = $this->availableJobs($nowText)->limit(1)->get()->getRowArray();
            if ($candidate === null) {
                if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                    throw new RuntimeException('Unable to commit the empty delivery claim.');
                }

                return null;
            }
            $builder = $this->db->table('delivery_jobs')->where('id', $candidate['id']);
            $this->availableWhere($builder, $nowText)
                ->set('status', 'leased')
                ->set('lease_token', $token)
                ->set('lease_started_at', $nowText)
                ->set('lease_expires_at', $expires)
                ->set('attempt_count', 'attempt_count + 1', false)
                ->set('cycle_attempt_count', 'cycle_attempt_count + 1', false)
                ->set('updated_at', $nowText)
                ->update();
            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return null;
            }

            $job = $this->db->table('delivery_jobs')
                ->select('delivery_jobs.*, domain_events.event_name, domain_events.payload, notification_destinations.channel')
                ->join('domain_events', 'domain_events.id = delivery_jobs.event_id')
                ->join('notification_destinations', 'notification_destinations.id = delivery_jobs.destination_id')
                ->where('delivery_jobs.id', $candidate['id'])
                ->get()
                ->getRowArray();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit the delivery claim.');
            }
            if ($job === null) {
                return null;
            }
            $job['claimed_at'] = $nowText;

            return $job;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /** @param array<string,mixed> $job */
    private function deliver(array $job): DeliveryResult
    {
        try {
            $references = json_decode((string) $job['payload'], true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($references)) {
                return DeliveryResult::permanent('invalid_event_payload');
            }
            $channel = ($this->resolveChannel)((string) $job['channel']);
            if (! $channel instanceof NotificationChannel) {
                return DeliveryResult::permanent('channel_unavailable');
            }

            /** @var array<string,int|string> $references */
            return $channel->deliver(new Notification(
                (string) $job['event_id'],
                (string) $job['event_name'],
                (int) $job['destination_id'],
                $references,
                'delivery-job:' . $job['id'],
            ));
        } catch (JsonException) {
            return DeliveryResult::permanent('invalid_event_payload');
        } catch (Throwable) {
            // A channel can throw after the provider accepted a request. Treating
            // that outcome as unknown prevents an automatic duplicate send.
            return DeliveryResult::unknown('channel_exception');
        }
    }

    /** @param array<string,mixed> $job */
    private function complete(array $job, DeliveryResult $result, DateTimeImmutable $now): ?string
    {
        $nowText = $now->format('Y-m-d H:i:s');
        $status = match ($result->status) {
            'success' => 'succeeded',
            'permanent' => 'permanent_failure',
            'unknown' => 'unknown',
            'retryable' => (int) $job['cycle_attempt_count'] >= $this->maxAttempts ? 'permanent_failure' : 'retry_wait',
        };
        $error = $result->errorCode;
        if ($result->status === 'retryable' && $status === 'permanent_failure') {
            $error = 'retry_exhausted';
        }
        $retryAt = $status === 'retry_wait' ? $result->retryAt?->format('Y-m-d H:i:s') : null;

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start the delivery completion transaction.');
        }
        try {
            $this->db->table('delivery_jobs')
                ->where('id', $job['id'])
                ->where('status', 'leased')
                ->where('lease_token', $job['lease_token'])
                ->update([
                    'status' => $status,
                    'available_at' => $retryAt ?? $nowText,
                    'lease_token' => null,
                    'lease_started_at' => null,
                    'lease_expires_at' => null,
                    'last_result' => $result->status,
                    'last_error' => $this->short($error, 120),
                    'updated_at' => $nowText,
                    'completed_at' => $status === 'retry_wait' ? null : $nowText,
                ]);
            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return null;
            }
            $this->db->table('delivery_attempts')->insert([
                'job_id' => $job['id'],
                'attempt_no' => $job['attempt_count'],
                'result' => $result->status,
                'provider_reference' => $this->short($result->providerReference, 190),
                'error_code' => $this->short($result->errorCode, 120),
                'retry_at' => $retryAt,
                'started_at' => $job['claimed_at'],
                'finished_at' => $nowText,
            ]);
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit the delivery result.');
            }

            return $status;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function availableJobs(string $now): \CodeIgniter\Database\BaseBuilder
    {
        $builder = $this->db->table('delivery_jobs')
            ->select('delivery_jobs.id')
            ->join('notification_destinations', 'notification_destinations.id = delivery_jobs.destination_id')
            ->where('notification_destinations.enabled', 1);
        $this->availableWhere($builder, $now);

        return $builder->orderBy('delivery_jobs.available_at', 'ASC')->orderBy('delivery_jobs.id', 'ASC');
    }

    private function availableWhere(\CodeIgniter\Database\BaseBuilder $builder, string $now): \CodeIgniter\Database\BaseBuilder
    {
        return $builder->whereIn('status', ['pending', 'retry_wait'])
            ->where('available_at <=', $now);
    }

    private function short(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }

    private function destinationEnabled(int $destinationId): bool
    {
        $destination = $this->db->table('notification_destinations')
            ->select('enabled')
            ->where('id', $destinationId)
            ->get()
            ->getRowArray();

        return $destination !== null && (int) $destination['enabled'] === 1;
    }

    /** @param array<string,mixed> $job */
    private function pause(array $job, DateTimeImmutable $now): bool
    {
        $this->db->table('delivery_jobs')
            ->where('id', $job['id'])
            ->where('status', 'leased')
            ->where('lease_token', $job['lease_token'])
            ->set('status', 'paused')
            ->set('attempt_count', 'attempt_count - 1', false)
            ->set('cycle_attempt_count', 'cycle_attempt_count - 1', false)
            ->set('lease_token', null)
            ->set('lease_started_at', null)
            ->set('lease_expires_at', null)
            ->set('updated_at', $now->format('Y-m-d H:i:s'))
            ->update();

        return $this->db->affectedRows() === 1;
    }
}
