<?php

declare(strict_types=1);

namespace App\Services\Installation;

use CodeIgniter\Database\BaseConnection;
use Throwable;

final class InstallationStatus
{
    public const SETUP_REQUIRED = 'setup_required';
    public const INSTALLED = 'installed';
    public const RECOVERY_REQUIRED = 'recovery_required';

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function check(array $runtime): string
    {
        if (($runtime['installed'] ?? false) !== true) {
            return self::SETUP_REQUIRED;
        }

        $installationId = $runtime['installation_id'] ?? null;
        if (
            ! is_string($installationId)
            || trim($installationId) === ''
            || strlen($installationId) > 64
            || ! is_int($runtime['schema_version'] ?? null)
            || $runtime['schema_version'] !== Runtime::SCHEMA_VERSION
        ) {
            return self::RECOVERY_REQUIRED;
        }

        try {
            $state = $this->db->table('installation_state')
                ->select('status, schema_version')
                ->where('id', $installationId)
                ->get()
                ->getRowArray();
        } catch (Throwable) {
            return self::RECOVERY_REQUIRED;
        }

        if ($state === null || ($state['status'] ?? null) !== 'complete') {
            return self::RECOVERY_REQUIRED;
        }

        $databaseSchemaVersion = $state['schema_version'] ?? null;
        if (
            ! is_int($databaseSchemaVersion)
            && (! is_string($databaseSchemaVersion) || preg_match('/^[0-9]+$/D', $databaseSchemaVersion) !== 1)
        ) {
            return self::RECOVERY_REQUIRED;
        }

        return (int) $databaseSchemaVersion === Runtime::SCHEMA_VERSION
            ? self::INSTALLED
            : self::RECOVERY_REQUIRED;
    }
}
