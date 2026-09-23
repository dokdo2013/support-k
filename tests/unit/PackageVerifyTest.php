<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SupportK\Packaging\PackageVerifier;

require_once dirname(__DIR__, 2) . '/packaging/verify.php';

/**
 * @internal
 */
final class PackageVerifyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/support-k-package-verify-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        parent::tearDown();
    }

    public function testAcceptsBuildShapedPackageAndChecksManifest(): void
    {
        $archive = $this->createPackage();

        (new PackageVerifier())->verify($archive);
        $this->assertFileExists($archive);
    }

    public function testAcceptsSplitDocumentRootPackage(): void
    {
        $archive = $this->createPackage(true);

        (new PackageVerifier())->verify($archive);
        $this->assertFileExists($archive);
    }

    public function testRejectsSplitPackageWithPublicPrivateEntry(): void
    {
        $archive = $this->createPackage(true);
        $zip = $this->open($archive);
        $this->assertTrue($zip->addFromString('index.php', '<?php'));
        $zip->close();

        $this->assertRejected($archive, 'exactly one public entry point');
    }

    public function testRejectsPathTraversalEntry(): void
    {
        $archive = $this->createPackage();
        $zip = $this->open($archive);
        $this->assertTrue($zip->addFromString('../escape.txt', 'outside'));
        $zip->close();

        $this->assertRejected($archive, 'traversal');
    }

    public function testRejectsManifestHashMismatch(): void
    {
        $archive = $this->createPackage();
        $zip = $this->open($archive);
        $this->assertTrue($zip->deleteName('index.php'));
        $this->assertTrue($zip->addFromString('index.php', '<?php echo "changed";'));
        $zip->close();

        $this->assertRejected($archive, 'SHA-256 mismatch');
    }

    public function testRejectsUnknownFileNotListedInManifest(): void
    {
        $archive = $this->createPackage();
        $zip = $this->open($archive);
        $this->assertTrue($zip->addFromString('unexpected.txt', 'unknown'));
        $zip->close();

        $this->assertRejected($archive, 'Manifest file list');
    }

    private function createPackage(bool $splitRoot = false): string
    {
        $version = '0.1.0-alpha.1';
        $releaseRoot = '_supportk/releases/' . $version . '/';
        $packages = [
            [
                'name' => 'codeigniter4/framework',
                'version' => 'v4.7.4',
                'license' => ['MIT'],
                'source' => ['type' => 'git', 'url' => 'https://example.invalid/framework.git', 'reference' => 'framework-ref'],
            ],
            [
                'name' => 'laminas/laminas-escaper',
                'version' => '2.18.0',
                'license' => ['BSD-3-Clause'],
                'source' => ['type' => 'git', 'url' => 'https://example.invalid/escaper.git', 'reference' => 'escaper-ref'],
            ],
            [
                'name' => 'psr/log',
                'version' => '3.0.2',
                'license' => ['MIT'],
                'source' => ['type' => 'git', 'url' => 'https://example.invalid/log.git', 'reference' => 'log-ref'],
            ],
        ];
        $lock = ['packages' => $packages];
        $components = array_map(static fn (array $package): array => [
            'name' => $package['name'],
            'version' => $package['version'],
            'license' => $package['license'],
            'source' => $package['source'],
        ], $packages);

        $files = [
            '_supportk/.htaccess' => 'Require all denied',
            '_supportk/shared/.htaccess' => 'Require all denied',
            '_supportk/active.json' => json_encode(['version' => $version], JSON_THROW_ON_ERROR),
            $releaseRoot . 'composer.lock' => json_encode($lock, JSON_THROW_ON_ERROR),
            $releaseRoot . 'release.json' => json_encode([
                'product' => 'Support K',
                'version' => $version,
                'php' => '>=8.2',
                'components' => $components,
            ], JSON_THROW_ON_ERROR),
            $releaseRoot . 'vendor/autoload.php' => '<?php',
        ];
        if ($splitRoot) {
            $files['public/index.php'] = '<?php require dirname(__DIR__) . "/_supportk/entry.php";';
            $files['public/.htaccess'] = 'DirectoryIndex index.php';
            $files['_supportk/entry.php'] = '<?php echo "Support K";';
        } else {
            $files['index.php'] = '<?php echo "Support K";';
            $files['.htaccess'] = 'DirectoryIndex index.php';
        }
        $manifest = [];
        foreach ($files as $name => $contents) {
            $manifest[$name] = hash('sha256', $contents);
        }
        ksort($manifest);

        $archive = $this->root . '/support-k-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = $this->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ([
            '_supportk/',
            '_supportk/shared/',
            '_supportk/shared/cache/',
            '_supportk/shared/logs/',
            '_supportk/shared/session/',
            '_supportk/shared/uploads/',
            '_supportk/shared/extensions/',
            '_supportk/releases/',
            $releaseRoot,
            $releaseRoot . 'vendor/',
        ] as $directory) {
            $this->assertTrue($zip->addEmptyDir($directory));
        }
        foreach ($files as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }
        $this->assertTrue($zip->addFromString('_supportk/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)));
        $this->assertTrue($zip->close());

        return $archive;
    }

    private function open(string $archive, int $flags = ZipArchive::CREATE): ZipArchive
    {
        $zip = new ZipArchive();
        $this->assertSame(true, $zip->open($archive, $flags));

        return $zip;
    }

    private function assertRejected(string $archive, string $message): void
    {
        try {
            (new PackageVerifier())->verify($archive);
            $this->fail('The package verifier accepted an invalid archive.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsStringIgnoringCase($message, $exception->getMessage());
        }
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
