<?php

namespace App\Services\Extensions;

use App\Contracts\Extension;
use RuntimeException;
use Throwable;

/**
 * Loads trusted PHP extensions after validating their manifests.
 *
 * Manifest permissions are declarations for review and core API checks. They
 * do not sandbox PHP code, which runs with the application's process rights.
 */
final class ExtensionRegistry
{
    /** @var list<string> */
    private array $roots;

    /** @var array<string,array{manifest:ExtensionManifest,status:string,error:?string}> */
    private array $entries = [];

    /** @var array<string,string> */
    private array $availableVersions = [];

    /** @var array<string,true> */
    private array $duplicateIds = [];

    /** @param list<string> $roots */
    public function __construct(
        array $roots,
        private readonly string $coreVersion,
        private readonly ChannelRegistry $channels,
        private readonly bool $safeMode = false,
    ) {
        $this->roots = array_values(array_unique($roots));
    }

    /** @param list<string> $roots */
    public static function fromSafeModeFlag(
        array $roots,
        string $coreVersion,
        ChannelRegistry $channels,
        string $flagFile,
    ): self {
        return new self($roots, $coreVersion, $channels, is_file($flagFile));
    }

    public function load(): void
    {
        $manifestFiles = $this->manifestFiles();
        foreach ($manifestFiles as $manifestFile) {
            try {
                $manifest = ExtensionManifest::fromFile($manifestFile);
                if (isset($this->availableVersions[$manifest->id])) {
                    $this->duplicateIds[$manifest->id] = true;
                    unset($this->availableVersions[$manifest->id]);
                } elseif (! isset($this->duplicateIds[$manifest->id])) {
                    $this->availableVersions[$manifest->id] = $manifest->version;
                }
            } catch (Throwable) {
                // The normal load pass records a useful invalid-manifest result.
            }
        }
        foreach ($manifestFiles as $manifestFile) {
            $this->loadManifest($manifestFile);
        }
    }

    /** @return array<string,array{manifest:ExtensionManifest,status:string,error:?string}> */
    public function entries(): array
    {
        return $this->entries;
    }

    private function loadManifest(string $manifestFile): void
    {
        try {
            $manifest = ExtensionManifest::fromFile($manifestFile);
            if (isset($this->entries[$manifest->id])) {
                return;
            }
            if (isset($this->duplicateIds[$manifest->id])) {
                $this->entries[$manifest->id] = ['manifest' => $manifest, 'status' => 'invalid', 'error' => 'duplicate_id'];

                return;
            }
            if (! VersionConstraint::matches($this->coreVersion, $manifest->coreConstraint)) {
                $this->entries[$manifest->id] = ['manifest' => $manifest, 'status' => 'incompatible', 'error' => 'core_version'];

                return;
            }
            if (! VersionConstraint::matches(PHP_VERSION, $manifest->phpConstraint)) {
                $this->entries[$manifest->id] = ['manifest' => $manifest, 'status' => 'incompatible', 'error' => 'php_version'];

                return;
            }
            foreach ($manifest->dependencies as $dependencyId => $constraint) {
                $available = $this->availableVersions[$dependencyId] ?? null;
                if ($available === null || ! VersionConstraint::matches($available, $constraint)) {
                    $this->entries[$manifest->id] = [
                        'manifest' => $manifest,
                        'status' => 'incompatible',
                        'error' => 'dependency:' . $dependencyId,
                    ];

                    return;
                }
            }
            if ($this->safeMode) {
                $this->entries[$manifest->id] = ['manifest' => $manifest, 'status' => 'safe_mode', 'error' => null];

                return;
            }

            $directory = dirname($manifestFile);
            $entrypoint = $this->safeEntrypoint($directory, $manifest->entrypointFile);
            require_once $entrypoint;
            if (! class_exists($manifest->entrypointClass)) {
                throw new RuntimeException('Extension entrypoint class was not found.');
            }
            $extension = new ($manifest->entrypointClass)();
            if (! $extension instanceof Extension) {
                throw new RuntimeException('Extension entrypoint must implement the Extension contract.');
            }
            $extension->register(new ExtensionContext($manifest->id, $this->channels));
            $this->entries[$manifest->id] = ['manifest' => $manifest, 'status' => 'active', 'error' => null];
        } catch (Throwable $exception) {
            $id = isset($manifest) ? $manifest->id : 'invalid:' . hash('sha256', $manifestFile);
            if (isset($this->entries[$id])) {
                throw $exception;
            }
            $fallback = $manifest ?? new ExtensionManifest(
                'invalid/' . substr(hash('sha256', $manifestFile), 0, 12),
                'Invalid extension',
                '0.0.0',
                'unknown',
                '*',
                '*',
                'invalid.php',
                'InvalidExtension',
            );
            $this->entries[$id] = ['manifest' => $fallback, 'status' => 'invalid', 'error' => $exception->getMessage()];
        }
    }

    private function safeEntrypoint(string $directory, string $relativePath): string
    {
        $root = realpath($directory);
        $path = realpath($directory . DIRECTORY_SEPARATOR . $relativePath);
        if ($root === false || $path === false || ! str_starts_with($path, $root . DIRECTORY_SEPARATOR) || ! is_file($path)) {
            throw new RuntimeException('Extension entrypoint escapes its extension directory or is missing.');
        }

        return $path;
    }

    /** @return list<string> */
    private function manifestFiles(): array
    {
        $files = [];
        foreach ($this->roots as $root) {
            if (is_file($root . DIRECTORY_SEPARATOR . 'manifest.json')) {
                $files[] = $root . DIRECTORY_SEPARATOR . 'manifest.json';
            }
            foreach (glob(rtrim($root, '/\\') . '/*/manifest.json') ?: [] as $file) {
                $files[] = $file;
            }
            foreach (glob(rtrim($root, '/\\') . '/*/*/manifest.json') ?: [] as $file) {
                $files[] = $file;
            }
        }
        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }
}
