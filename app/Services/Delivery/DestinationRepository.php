<?php

namespace App\Services\Delivery;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class DestinationRepository
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function register(string $channel, string $settingsNamespace, bool $enabled = true): int
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $channel) !== 1) {
            throw new InvalidArgumentException('Destination channel ID is invalid.');
        }
        if (preg_match('/^extensions\.[a-z0-9][a-z0-9._]*$/', $settingsNamespace) !== 1) {
            throw new InvalidArgumentException('Destination settings namespace is invalid.');
        }

        $existing = $this->find($channel, $settingsNamespace);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('notification_destinations')->ignore(true)->insert([
            'channel' => $channel,
            'settings_namespace' => $settingsNamespace,
            'enabled' => $enabled ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $stored = $this->find($channel, $settingsNamespace);
        if ($stored === null) {
            throw new RuntimeException('Unable to register notification destination.');
        }

        return (int) $stored['id'];
    }

    /** @return list<int> */
    public function activeIds(): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->db->table('notification_destinations')->select('id')->where('enabled', 1)->orderBy('id', 'ASC')->get()->getResultArray(),
        );
    }

    public function setEnabled(int $destinationId, bool $enabled, ?DateTimeImmutable $now = null): bool
    {
        if ($destinationId < 1) {
            throw new InvalidArgumentException('Destination ID must be positive.');
        }
        $existing = $this->db->table('notification_destinations')
            ->select('id')
            ->where('id', $destinationId)
            ->get()
            ->getRowArray();
        if ($existing === null) {
            return false;
        }
        $nowText = ($now ?? new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start the destination update transaction.');
        }
        try {
            $this->db->table('notification_destinations')->where('id', $destinationId)->update([
                'enabled' => $enabled ? 1 : 0,
                'updated_at' => $nowText,
            ]);
            $jobs = $this->db->table('delivery_jobs')->where('destination_id', $destinationId);
            if ($enabled) {
                $jobs->where('status', 'paused')->update([
                    'status' => 'pending',
                    'available_at' => $nowText,
                    'updated_at' => $nowText,
                ]);
            } else {
                $jobs->whereIn('status', ['pending', 'retry_wait'])->update([
                    'status' => 'paused',
                    'updated_at' => $nowText,
                ]);
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit the destination update.');
            }

            return true;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    private function find(string $channel, string $settingsNamespace): ?array
    {
        return $this->db->table('notification_destinations')
            ->where('channel', $channel)
            ->where('settings_namespace', $settingsNamespace)
            ->get()
            ->getRowArray();
    }
}
