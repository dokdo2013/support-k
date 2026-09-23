<?php

namespace App\Services\Extensions;

use InvalidArgumentException;

final readonly class SettingsNamespace
{
    private string $prefix;

    public function __construct(string $extensionId)
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $extensionId) !== 1) {
            throw new InvalidArgumentException('Invalid extension ID.');
        }
        $this->prefix = 'extensions.' . str_replace(['/', '-'], ['.', '_'], $extensionId);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function key(string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9_.]*$/', $name) !== 1) {
            throw new InvalidArgumentException('Invalid extension setting name.');
        }

        return $this->prefix . '.' . $name;
    }
}
