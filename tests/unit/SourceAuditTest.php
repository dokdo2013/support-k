<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SupportK\Packaging\SourceAuditor;

require_once dirname(__DIR__, 2) . '/packaging/audit-source.php';

/**
 * @internal
 */
final class SourceAuditTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/support-k-source-audit-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        parent::tearDown();
    }

    public function testFindsCredentialsPathsAndDeploymentValues(): void
    {
        $fixture = <<<'PHP'
<?php
__SENSITIVE_NAME__ = '__SENSITIVE_VALUE__';
$apiKey = '__TOKEN__';
$private = '__PRIVATE_KEY__';
$uploadPath = '__REAL_PATH__';
$deploy = '__DEPLOYMENT_URL__';
PHP;
        $this->write('config/settings.php', str_replace(
            ['__SENSITIVE_NAME__', '__SENSITIVE_VALUE__', '__TOKEN__', '__PRIVATE_KEY__', '__REAL_PATH__', '__DEPLOYMENT_URL__'],
            [
                'password',
                'a-real-looking-password-value',
                'sk-' . 'proj-' . str_repeat('a', 24),
                '-----BEGIN ' . 'PRIVATE KEY-----',
                '/var/www/' . 'customer-site/shared/uploads',
                'sftp://deploy:' . 'secret' . '@example.invalid/release',
            ],
            $fixture,
        ));

        $findings = (new SourceAuditor())->audit($this->root);
        $codes = array_map(static fn ($finding): string => $finding->code, $findings);

        $this->assertContains('CREDENTIAL_FORMAT', $codes);
        $this->assertContains('SENSITIVE_SETTING', $codes);
        $this->assertContains('PRIVATE_KEY', $codes);
        $this->assertContains('REAL_PATH', $codes);
        $this->assertContains('DEPLOYMENT_CREDENTIAL', $codes);
    }

    public function testAllowsExamplesAndSkipsRuntimeAndDependencyDirectories(): void
    {
        $this->write('docs/example.md', <<<'MD'
```env
API_KEY=your-key-here
PASSWORD: "example-password"
```

# password = your-password-here
MD);

        foreach (['vendor', 'writable', '.git', 'dist', 'build'] as $excluded) {
            $this->write($excluded . '/secret.php', "<?php\n\$apiKey = 'sk-' . 'proj-' . str_repeat('a', 24);\n");
        }

        $findings = (new SourceAuditor())->audit($this->root);

        $this->assertSame([], $findings);
    }

    public function testFindsCredentialsInCommentsAndQuotedPhpKeys(): void
    {
        $fixture = <<<'PHP'
<?php
// __TOKEN__
# __PRIVATE__
$config = ['__KEY__' => '__PASSWORD__'];
PHP;
        $this->write('config/commented.php', str_replace(
            ['__TOKEN__', '__PRIVATE__', '__KEY__', '__PASSWORD__'],
            [
                'sk-' . 'proj-' . str_repeat('b', 24),
                '-----BEGIN ' . 'PRIVATE KEY-----',
                'password',
                'a-real-looking-password-value',
            ],
            $fixture,
        ));

        $findings = (new SourceAuditor())->audit($this->root);
        $codes = array_map(static fn ($finding): string => $finding->code, $findings);

        $this->assertContains('CREDENTIAL_FORMAT', $codes);
        $this->assertContains('PRIVATE_KEY', $codes);
        $this->assertContains('SENSITIVE_SETTING', $codes);
    }

    public function testBlocksSensitiveFilesAndDataDumpsByPath(): void
    {
        $this->write('.env', "APP_KEY=runtime-value\n");
        $this->write('settings/runtime-config.php', "<?php return [];\n");
        $this->write('exports/database.sql.gz', "\0compressed\n");
        $this->write('exports/mail.eml.gz', "\0compressed\n");
        $this->write('exports/state.sqlite', "\0sqlite\n");

        $findings = (new SourceAuditor())->audit($this->root);
        $codes = array_map(static fn ($finding): string => $finding->code, $findings);

        $this->assertContains('SENSITIVE_FILE', $codes);
        $this->assertContains('RUNTIME_CONFIG', $codes);
        $this->assertSame(3, count(array_filter(
            $codes,
            static fn (string $code): bool => $code === 'DATA_DUMP',
        )));
    }

    public function testAllowsConfigurationDefaultsAndExamples(): void
    {
        $this->write('env', "# password = root-password\n");
        $this->write('.env.example', "PASSWORD=your-password-here\n");
        $this->write('app/Config/Database.php', "<?php\nreturn ['password' => ''];\n");

        $this->assertSame([], (new SourceAuditor())->audit($this->root));
    }

    public function testCanAuditDirectoryWithoutGitMetadata(): void
    {
        $this->write('README.md', 'A synthetic source directory.');

        $this->assertSame([], (new SourceAuditor())->audit($this->root));
    }

    private function write(string $relativePath, string $contents): void
    {
        $path = $this->root . '/' . $relativePath;
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($path, $contents);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path) && ! is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
