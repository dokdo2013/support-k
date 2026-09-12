<?php

namespace App\Services\Delivery;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class Outbox
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Records an event inside the caller's current transaction.
     *
     * This method deliberately neither begins nor commits a transaction.
     * Payloads contain references only; message content and personal data belong
     * in their authoritative tables and are loaded later with access checks.
     *
     * @param array<string,int|string> $payload
     */
    public function record(string $event, array $payload, ?string $dedupeKey = null): string
    {
        $event = trim($event);
        if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)+$/', $event) !== 1 || strlen($event) > 100) {
            throw new InvalidArgumentException('Event name is invalid.');
        }
        $this->assertReferencePayload($payload);

        $dedupeKey = $dedupeKey === null ? null : trim($dedupeKey);
        if ($dedupeKey === '' || ($dedupeKey !== null && strlen($dedupeKey) > 160)) {
            throw new InvalidArgumentException('Dedupe key is invalid.');
        }

        if ($dedupeKey !== null) {
            $existing = $this->db->table('domain_events')->select('id')->where('dedupe_key', $dedupeKey)->get()->getRowArray();
            if ($existing !== null) {
                return (string) $existing['id'];
            }
        }

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Event payload cannot be encoded.', previous: $exception);
        }

        $id = self::uuid();
        $builder = $this->db->table('domain_events');
        if ($dedupeKey !== null) {
            $builder->ignore(true);
        }
        if (! $builder->insert([
            'id' => $id,
            'event_name' => $event,
            'payload' => $encoded,
            'dedupe_key' => $dedupeKey,
            'status' => 'pending',
            'lease_token' => null,
            'lease_expires_at' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'fanned_out_at' => null,
        ])) {
            throw new RuntimeException('Unable to record the domain event.');
        }

        if ($dedupeKey !== null) {
            $stored = $this->db->table('domain_events')->select('id')->where('dedupe_key', $dedupeKey)->get()->getRowArray();
            if ($stored === null) {
                throw new RuntimeException('Unable to resolve the recorded domain event.');
            }

            return (string) $stored['id'];
        }

        return $id;
    }

    /** @param array<string,int|string> $payload */
    private function assertReferencePayload(array $payload): void
    {
        if ($payload === []) {
            throw new InvalidArgumentException('Event payload must contain references.');
        }
        foreach ($payload as $key => $value) {
            if (preg_match('/^[a-z][a-z0-9_]*(?:_id|_version)$/', $key) !== 1) {
                throw new InvalidArgumentException("Event payload field {$key} is not an ID or version reference.");
            }
            if (! is_int($value) && ! is_string($value)) {
                throw new InvalidArgumentException("Event payload field {$key} must be an integer or string.");
            }
            if (is_int($value) && $value < 1) {
                throw new InvalidArgumentException("Event payload field {$key} must be positive.");
            }
            if (is_string($value) && (trim($value) === '' || strlen($value) > 190)) {
                throw new InvalidArgumentException("Event payload field {$key} is invalid.");
            }
            if (str_ends_with($key, '_version') && (! is_int($value) || $value < 1)) {
                throw new InvalidArgumentException("Event payload field {$key} must be a positive integer version.");
            }
        }
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
