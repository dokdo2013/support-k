<?php

declare(strict_types=1);

use App\Services\Installation\InstallationStatus;
use App\Services\Installation\Runtime;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class InstallationStatusTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect('tests');
        $forge = Database::forge('tests');
        $forge->dropTable('installation_state', true);
        $forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 64],
            'status' => ['type' => 'VARCHAR', 'constraint' => 32],
            'schema_version' => ['type' => 'INTEGER'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('installation_state');
    }

    protected function tearDown(): void
    {
        Database::forge('tests')->dropTable('installation_state', true);
        parent::tearDown();
    }

    public function testInstalledFalseRequiresSetupWithoutDatabaseState(): void
    {
        self::assertSame(
            InstallationStatus::SETUP_REQUIRED,
            $this->installationStatus()->check(['installed' => false]),
        );
    }

    public function testMatchingRuntimeAndDatabaseStateIsInstalled(): void
    {
        $this->insertState('installation-a', Runtime::SCHEMA_VERSION);

        self::assertSame(InstallationStatus::INSTALLED, $this->installationStatus()->check($this->runtime('installation-a')));
    }

    public function testDifferentInstallationIdRequiresRecovery(): void
    {
        $this->insertState('installation-b', Runtime::SCHEMA_VERSION);

        self::assertSame(InstallationStatus::RECOVERY_REQUIRED, $this->installationStatus()->check($this->runtime('installation-a')));
    }

    public function testMissingDatabaseStateRequiresRecovery(): void
    {
        self::assertSame(InstallationStatus::RECOVERY_REQUIRED, $this->installationStatus()->check($this->runtime('installation-a')));
    }

    public function testMissingRuntimeInstallationIdRequiresRecovery(): void
    {
        self::assertSame(InstallationStatus::RECOVERY_REQUIRED, $this->installationStatus()->check([
            'installed' => true,
            'schema_version' => Runtime::SCHEMA_VERSION,
        ]));
    }

    public function testIncompleteDatabaseStateRequiresRecovery(): void
    {
        $this->insertState('installation-a', Runtime::SCHEMA_VERSION, 'migrating');

        self::assertSame(InstallationStatus::RECOVERY_REQUIRED, $this->installationStatus()->check($this->runtime('installation-a')));
    }

    public function testRuntimeSchemaMismatchRequiresRecovery(): void
    {
        $this->insertState('installation-a', Runtime::SCHEMA_VERSION);
        $runtime = $this->runtime('installation-a');
        $runtime['schema_version'] = Runtime::SCHEMA_VERSION + 1;

        self::assertSame(InstallationStatus::RECOVERY_REQUIRED, $this->installationStatus()->check($runtime));
    }

    public function testDatabaseSchemaMismatchRequiresRecovery(): void
    {
        $this->insertState('installation-a', Runtime::SCHEMA_VERSION + 1);

        self::assertSame(InstallationStatus::RECOVERY_REQUIRED, $this->installationStatus()->check($this->runtime('installation-a')));
    }

    public function testMissingStateTableRequiresRecoveryInsteadOfThrowing(): void
    {
        Database::forge('tests')->dropTable('installation_state', true);

        self::assertSame(InstallationStatus::RECOVERY_REQUIRED, $this->installationStatus()->check($this->runtime('installation-a')));
    }

    private function installationStatus(): InstallationStatus
    {
        return new InstallationStatus($this->db);
    }

    /** @return array{installed:true,installation_id:string,schema_version:int} */
    private function runtime(string $installationId): array
    {
        return [
            'installed' => true,
            'installation_id' => $installationId,
            'schema_version' => Runtime::SCHEMA_VERSION,
        ];
    }

    private function insertState(string $installationId, int $schemaVersion, string $status = 'complete'): void
    {
        $this->db->table('installation_state')->insert([
            'id' => $installationId,
            'status' => $status,
            'schema_version' => $schemaVersion,
            'created_at' => '2026-09-12 00:00:00',
        ]);
    }
}
