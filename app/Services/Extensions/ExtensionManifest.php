<?php

namespace App\Services\Extensions;

use InvalidArgumentException;
use JsonException;

final readonly class ExtensionManifest
{
    /**
     * @param array<string,mixed>  $settings
     * @param list<string>         $provides
     * @param list<string>         $events
     * @param list<string>         $permissions
     * @param array<string,string> $dependencies
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $license,
        public string $coreConstraint,
        public string $phpConstraint,
        public string $entrypointFile,
        public string $entrypointClass,
        public array $settings = [],
        public array $provides = [],
        public array $events = [],
        public array $permissions = [],
        public array $dependencies = [],
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $id) !== 1) {
            throw new InvalidArgumentException('Extension ID must use the vendor/name format.');
        }
        if (trim($name) === '' || trim($license) === '' || trim($version) === '') {
            throw new InvalidArgumentException('Extension name, version, and license are required.');
        }
        if (preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) !== 1) {
            throw new InvalidArgumentException('Extension version must be semantic.');
        }
        if ($entrypointFile === '' || str_contains($entrypointFile, '..') || str_starts_with($entrypointFile, '/')) {
            throw new InvalidArgumentException('Extension entrypoint file must be a safe relative path.');
        }
        $classParts = explode('\\', $entrypointClass);
        if ($classParts === [] || array_filter(
            $classParts,
            static fn (string $part): bool => preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) !== 1,
        ) !== []) {
            throw new InvalidArgumentException('Extension entrypoint class is invalid.');
        }
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('Extension manifest does not exist.');
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Extension manifest is not valid JSON.', previous: $exception);
        }
        if (! is_array($data)) {
            throw new InvalidArgumentException('Extension manifest root must be an object.');
        }

        $requires = self::map($data['requires'] ?? null, 'requires');
        $entrypoint = self::map($data['entrypoint'] ?? null, 'entrypoint');

        return new self(
            self::text($data, 'id'),
            self::text($data, 'name'),
            self::text($data, 'version'),
            self::text($data, 'license'),
            self::text($requires, 'core'),
            self::text($requires, 'php'),
            self::text($entrypoint, 'file'),
            self::text($entrypoint, 'class'),
            self::map($data['settings'] ?? [], 'settings'),
            self::stringList($data['provides'] ?? [], 'provides'),
            self::stringList($data['events'] ?? [], 'events'),
            self::stringList($data['permissions'] ?? [], 'permissions'),
            self::stringMap($data['dependencies'] ?? [], 'dependencies'),
        );
    }

    /** @param array<string,mixed> $data */
    private static function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Manifest field {$key} must be a non-empty string.");
        }

        return trim($value);
    }

    /** @return array<string,mixed> */
    private static function map(mixed $value, string $field): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException("Manifest field {$field} must be an object.");
        }

        return $value;
    }

    /** @return list<string> */
    private static function stringList(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException("Manifest field {$field} must be a list.");
        }
        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException("Manifest field {$field} contains an invalid value.");
            }
        }

        return array_values($value);
    }

    /** @return array<string,string> */
    private static function stringMap(mixed $value, string $field): array
    {
        $map = self::map($value, $field);
        foreach ($map as $key => $item) {
            if (! is_string($key) || ! is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException("Manifest field {$field} contains an invalid value.");
            }
        }

        /** @var array<string,string> $map */
        return $map;
    }
}
