<?php

declare(strict_types=1);

namespace SupportK\Packaging;

use RuntimeException;
use ZipArchive;

/**
 * Verifies the immutable install archive produced by packaging/build.php.
 *
 * The verifier reads entries in place. It never extracts an untrusted archive,
 * which keeps traversal and symlink checks effective on every host filesystem.
 */
final class PackageVerifier
{
    private const VERSION_PATTERN = '/^\d+\.\d+\.\d+(?:-[a-z0-9.]+)?$/D';

    /** @var list<string> */
    private const RUNTIME_DIRECTORIES = [
        '_supportk/shared/cache/',
        '_supportk/shared/logs/',
        '_supportk/shared/session/',
        '_supportk/shared/uploads/',
        '_supportk/shared/extensions/',
    ];

    /**
     * @throws RuntimeException when the archive is unsafe or incomplete.
     */
    public function verify(string $archivePath): void
    {
        if (! is_file($archivePath)) {
            throw new RuntimeException('Package does not exist: ' . $archivePath);
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Package is not a readable ZIP archive.');
        }

        try {
            $entries = $this->indexEntries($zip);
            $this->verifyLayout($zip, $entries);
            $this->verifyManifest($zip, $entries);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, array{index:int,directory:bool}>
     */
    private function indexEntries(ZipArchive $zip): array
    {
        $entries = [];
        $count = $zip->numFiles;
        for ($index = 0; $index < $count; $index++) {
            $name = $zip->getNameIndex($index);
            if ($name === false || $name === '') {
                throw new RuntimeException('ZIP contains an empty entry name.');
            }
            $this->assertSafeEntryName($name);

            if (isset($entries[$name])) {
                throw new RuntimeException('ZIP contains a duplicate entry: ' . $name);
            }

            $this->assertNotSymlink($zip, $index, $name);
            $entries[$name] = [
                'index' => $index,
                'directory' => str_ends_with($name, '/'),
            ];
        }

        return $entries;
    }

    private function assertSafeEntryName(string $name): void
    {
        if (str_contains($name, "\0") || str_contains($name, '\\')) {
            throw new RuntimeException('ZIP entry uses an unsafe separator: ' . $name);
        }
        if (str_starts_with($name, '/') || str_starts_with($name, '//') || preg_match('/^[A-Za-z]:/', $name) === 1) {
            throw new RuntimeException('ZIP entry is an absolute path: ' . $name);
        }

        $withoutTrailingSlash = rtrim($name, '/');
        if ($withoutTrailingSlash === '' || str_contains($withoutTrailingSlash, '//')) {
            throw new RuntimeException('ZIP entry has an invalid path: ' . $name);
        }
        foreach (explode('/', $withoutTrailingSlash) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('ZIP entry contains path traversal: ' . $name);
            }
        }
    }

    private function assertNotSymlink(ZipArchive $zip, int $index, string $name): void
    {
        if (! method_exists($zip, 'getExternalAttributesIndex')) {
            return;
        }

        $opsys = 0;
        $attributes = 0;
        if (! $zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return;
        }

        if ($opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
            throw new RuntimeException('ZIP contains a symlink entry: ' . $name);
        }
    }

    /**
     * @param array<string, array{index:int,directory:bool}> $entries
     */
    private function verifyLayout(ZipArchive $zip, array $entries): void
    {
        $splitRoot = isset($entries['public/index.php']);
        if ($splitRoot && isset($entries['index.php'])) {
            throw new RuntimeException('Package must have exactly one public entry point.');
        }
        $publicFiles = $splitRoot
            ? ['public/index.php', 'public/.htaccess', '_supportk/entry.php']
            : ['index.php', '.htaccess'];
        foreach (array_merge($publicFiles, ['_supportk/.htaccess', '_supportk/shared/.htaccess', '_supportk/active.json', '_supportk/manifest.json']) as $required) {
            $this->requireFile($entries, $required);
        }
        foreach (self::RUNTIME_DIRECTORIES as $required) {
            $this->requireDirectory($entries, $required);
        }

        $active = $this->readJsonObject($zip, $entries, '_supportk/active.json');
        $version = $active['version'] ?? null;
        if (! is_string($version) || preg_match(self::VERSION_PATTERN, $version) !== 1) {
            throw new RuntimeException('active.json contains an invalid release version.');
        }

        $releaseRoot = '_supportk/releases/' . $version . '/';
        foreach (['composer.lock', 'release.json', 'vendor/autoload.php'] as $required) {
            $this->requireFile($entries, $releaseRoot . $required);
        }

        foreach ($entries as $name => $entry) {
            if ($entry['directory']) {
                continue;
            }
            $this->assertAllowedReleaseFile($name);
        }

        $release = $this->readJsonObject($zip, $entries, $releaseRoot . 'release.json');
        if (($release['product'] ?? null) !== 'Support K' || ($release['version'] ?? null) !== $version) {
            throw new RuntimeException('release.json does not describe the active Support K release.');
        }

        $lock = $this->readJsonObject($zip, $entries, $releaseRoot . 'composer.lock');
        $this->verifyRuntimeComponents($release, $lock);
    }

    /**
     * @param array<string, array{index:int,directory:bool}> $entries
     */
    private function verifyManifest(ZipArchive $zip, array $entries): void
    {
        $manifest = $this->readJsonObject($zip, $entries, '_supportk/manifest.json');
        $actualFiles = [];
        foreach ($entries as $name => $entry) {
            if (! $entry['directory'] && $name !== '_supportk/manifest.json') {
                $actualFiles[$name] = $entry['index'];
            }
        }

        foreach ($manifest as $name => $hash) {
            if (! is_string($name) || ! is_string($hash) || preg_match('/^[a-f0-9]{64}$/i', $hash) !== 1) {
                throw new RuntimeException('Manifest contains an invalid file or SHA-256 value.');
            }
            if (! isset($actualFiles[$name])) {
                throw new RuntimeException('Manifest references a missing or forbidden file: ' . $name);
            }
        }

        $expectedNames = array_keys($manifest);
        $actualNames = array_keys($actualFiles);
        sort($expectedNames);
        sort($actualNames);
        if ($expectedNames !== $actualNames) {
            throw new RuntimeException('Manifest file list does not exactly match the ZIP file list.');
        }

        foreach ($actualFiles as $name => $index) {
            $contents = $zip->getFromIndex($index);
            if ($contents === false) {
                throw new RuntimeException('Could not read manifest file: ' . $name);
            }
            if (! hash_equals(strtolower($manifest[$name]), hash('sha256', $contents))) {
                throw new RuntimeException('Manifest SHA-256 mismatch: ' . $name);
            }
        }
    }

    /**
     * @param array<string, array{index:int,directory:bool}> $entries
     */
    private function requireFile(array $entries, string $name): void
    {
        if (! isset($entries[$name]) || $entries[$name]['directory']) {
            throw new RuntimeException('Required package file is missing: ' . $name);
        }
    }

    /**
     * @param array<string, array{index:int,directory:bool}> $entries
     */
    private function requireDirectory(array $entries, string $name): void
    {
        if (! isset($entries[$name]) || ! $entries[$name]['directory']) {
            throw new RuntimeException('Required runtime directory is missing: ' . $name);
        }
    }

    /**
     * @param array<string, array{index:int,directory:bool}> $entries
     * @return array<string, mixed>
     */
    private function readJsonObject(ZipArchive $zip, array $entries, string $name): array
    {
        $this->requireFile($entries, $name);
        $contents = $zip->getFromIndex($entries[$name]['index']);
        if ($contents === false) {
            throw new RuntimeException('Could not read required JSON file: ' . $name);
        }
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Invalid JSON in ' . $name . ': ' . $exception->getMessage(), 0, $exception);
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('JSON object expected in ' . $name . '.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $release
     * @param array<string, mixed> $lock
     */
    private function verifyRuntimeComponents(array $release, array $lock): void
    {
        if (! isset($release['components']) || ! is_array($release['components']) || ! array_is_list($release['components'])) {
            throw new RuntimeException('release.json components must be an array.');
        }
        if (! isset($lock['packages']) || ! is_array($lock['packages'])) {
            throw new RuntimeException('Release composer.lock has no runtime package list.');
        }

        $expected = [];
        foreach ($lock['packages'] as $package) {
            if (! is_array($package) || ! is_string($package['name'] ?? null) || ! is_string($package['version'] ?? null)) {
                throw new RuntimeException('Release composer.lock contains an invalid runtime package.');
            }
            $expected[$package['name']] = [
                'name' => $package['name'],
                'version' => $package['version'],
                'license' => $package['license'] ?? [],
                'source' => $package['source'] ?? [],
            ];
        }

        $actual = [];
        foreach ($release['components'] as $component) {
            if (! is_array($component) || ! is_string($component['name'] ?? null)) {
                throw new RuntimeException('release.json contains an invalid runtime component.');
            }
            if (isset($actual[$component['name']])) {
                throw new RuntimeException('release.json contains a duplicate runtime component.');
            }
            $actual[$component['name']] = $component;
        }

        $expectedNames = array_keys($expected);
        $actualNames = array_keys($actual);
        sort($expectedNames);
        sort($actualNames);
        if ($expectedNames !== $actualNames) {
            throw new RuntimeException('release.json runtime components do not match composer.lock.');
        }
        foreach ($expected as $name => $component) {
            foreach (['version', 'license', 'source'] as $field) {
                if (($actual[$name][$field] ?? null) !== $component[$field]) {
                    throw new RuntimeException('release.json component does not match composer.lock: ' . $name);
                }
            }
        }

        foreach (['codeigniter4/framework', 'laminas/laminas-escaper', 'psr/log'] as $required) {
            if (! isset($actual[$required])) {
                throw new RuntimeException('Required framework runtime component is missing: ' . $required);
            }
        }
    }

    private function assertAllowedReleaseFile(string $name): void
    {
        $base = strtolower(basename($name));
        $segments = explode('/', $name);
        $frameworkSystemFile = str_contains($name, '/vendor/codeigniter4/framework/system/');
        if (($base === 'config.php' && ! $frameworkSystemFile)
            || $base === 'installed.lock'
            || str_starts_with($base, '.env')) {
            throw new RuntimeException('Forbidden runtime configuration file in package: ' . $name);
        }
        if (in_array('tests', $segments, true) || in_array('logs', $segments, true) && str_contains($name, '_supportk/shared/')) {
            throw new RuntimeException('Forbidden test or log runtime file in package: ' . $name);
        }
        if (preg_match('~\.(?:sql|sql\.gz|dump|dump\.gz|sqlite|sqlite3|db|eml|eml\.gz|mbox|pst|bak|log)$~i', $name) === 1) {
            throw new RuntimeException('Forbidden data dump or log file in package: ' . $name);
        }
        if (str_contains($name, '_supportk/shared/')) {
            foreach (self::RUNTIME_DIRECTORIES as $directory) {
                if (str_starts_with($name, $directory)) {
                    throw new RuntimeException('Runtime directory must be empty: ' . $name);
                }
            }
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $archive = $argv[1] ?? null;
    if ($archive === null || in_array($archive, ['-h', '--help'], true)) {
        fwrite(STDOUT, "Usage: php packaging/verify.php dist/support-k-0.1.0-alpha.1.zip\n");
        exit($archive === null ? 2 : 0);
    }

    try {
        (new PackageVerifier())->verify($archive);
        fwrite(STDOUT, 'OK: package verified: ' . $archive . PHP_EOL);
        exit(0);
    } catch (RuntimeException $exception) {
        fwrite(STDERR, 'ERROR [PACKAGE_VERIFY] ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
