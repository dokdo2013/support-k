<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\Installation\Runtime;

require_once ROOTPATH . 'bootstrap/runtime-config-schema.php';

final class RuntimeConfigSchemaTest extends CIUnitTestCase
{
    private string $runtimeDirectory;
    private ?string $previousSharedDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $previous = getenv('SUPPORT_K_SHARED');
        $this->previousSharedDirectory = $previous === false ? null : $previous;
        $this->runtimeDirectory = sys_get_temp_dir() . '/support-k-runtime-' . bin2hex(random_bytes(8));
        mkdir($this->runtimeDirectory, 0700, true);
        putenv('SUPPORT_K_SHARED=' . $this->runtimeDirectory);
    }

    protected function tearDown(): void
    {
        $configPath = $this->runtimeDirectory . '/config.php';
        if (is_file($configPath)) {
            unlink($configPath);
        }
        rmdir($this->runtimeDirectory);
        if ($this->previousSharedDirectory === null) {
            putenv('SUPPORT_K_SHARED');
        } else {
            putenv('SUPPORT_K_SHARED=' . $this->previousSharedDirectory);
        }
        parent::tearDown();
    }

    public function testValidPendingAndInstalledRuntimeConfigurationsAreAccepted(): void
    {
        $pending = $this->validConfig();
        $installed = $pending;
        $installed['installed'] = true;

        self::assertTrue(support_k_runtime_config_is_valid($pending, 1));
        self::assertTrue(support_k_runtime_config_is_valid($installed, 1));
    }

    public function testLocalHttpAndExternalHttpsAreAccepted(): void
    {
        foreach (['http://localhost:8080/support/', 'http://127.0.0.1/', 'https://support.example.test/help/'] as $baseUrl) {
            $config = $this->validConfig();
            $config['base_url'] = $baseUrl;
            self::assertTrue(support_k_runtime_config_is_valid($config, 1), $baseUrl);
        }
    }

    public function testRuntimeReadAndWriteReuseTheSchemaValidator(): void
    {
        $config = $this->validConfig();
        Runtime::write($config);
        self::assertSame($config, Runtime::read());

        $config['base_url'] = ['invalid'];
        file_put_contents(
            $this->runtimeDirectory . '/config.php',
            "<?php\nreturn " . var_export($config, true) . ";\n",
        );

        $this->expectException(RuntimeException::class);
        Runtime::read();
    }

    public function testRuntimeRefusesToWriteMalformedConfiguration(): void
    {
        $config = $this->validConfig();
        $config['database'] = 'invalid';

        $this->expectException(RuntimeException::class);
        try {
            Runtime::write($config);
        } finally {
            self::assertFileDoesNotExist($this->runtimeDirectory . '/config.php');
        }
    }

    /** @dataProvider invalidConfigProvider */
    public function testMalformedRequiredRuntimeValuesAreRejected(callable $change): void
    {
        $config = $this->validConfig();
        $change($config);

        self::assertFalse(support_k_runtime_config_is_valid($config, 1));
    }

    public static function invalidConfigProvider(): iterable
    {
        yield 'format type' => [static function (array &$config): void { $config['format'] = '1'; }];
        yield 'installed type' => [static function (array &$config): void { $config['installed'] = 1; }];
        yield 'schema version mismatch' => [static function (array &$config): void { $config['schema_version'] = 2; }];
        yield 'missing installation id' => [static function (array &$config): void { unset($config['installation_id']); }];
        yield 'invalid installation id' => [static function (array &$config): void { $config['installation_id'] = str_repeat('g', 32); }];
        yield 'invalid key' => [static function (array &$config): void { $config['key'] = str_repeat('a', 63); }];
        yield 'missing site name' => [static function (array &$config): void { unset($config['site_name']); }];
        yield 'site name type' => [static function (array &$config): void { $config['site_name'] = ['support']; }];
        yield 'empty site name' => [static function (array &$config): void { $config['site_name'] = '   '; }];
        yield 'site name over 100 Unicode characters' => [static function (array &$config): void { $config['site_name'] = str_repeat('고', 101); }];
        yield 'database is not an array' => [static function (array &$config): void { $config['database'] = 'mysql'; }];
        yield 'database port type' => [static function (array &$config): void { $config['database']['port'] = '3306'; }];
        yield 'database driver' => [static function (array &$config): void { $config['database']['DBDriver'] = 'SQLite3'; }];
        yield 'database prefix' => [static function (array &$config): void { $config['database']['DBPrefix'] = '../'; }];
        yield 'external http' => [static function (array &$config): void { $config['base_url'] = 'http://support.example.test/'; }];
        yield 'url credentials' => [static function (array &$config): void { $config['base_url'] = 'https://user@example.test/'; }];
        yield 'url query' => [static function (array &$config): void { $config['base_url'] = 'https://example.test/?a=1'; }];
        yield 'url without trailing slash' => [static function (array &$config): void { $config['base_url'] = 'https://example.test/help'; }];
    }

    /** @return array<string,mixed> */
    private function validConfig(): array
    {
        return [
            'format' => 1,
            'installed' => false,
            'schema_version' => 1,
            'installation_id' => str_repeat('a', 32),
            'base_url' => 'https://support.example.test/',
            'site_name' => '고객센터',
            'database' => [
                'hostname' => 'localhost',
                'port' => 3306,
                'database' => 'supportk',
                'username' => 'supportk',
                'password' => '',
                'DBDriver' => 'MySQLi',
                'DBPrefix' => 'sk_',
            ],
            'key' => str_repeat('b', 64),
        ];
    }
}
