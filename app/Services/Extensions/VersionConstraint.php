<?php

namespace App\Services\Extensions;

use InvalidArgumentException;

final class VersionConstraint
{
    public static function matches(string $version, string $constraint): bool
    {
        if (preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) !== 1) {
            return false;
        }

        $parts = preg_split('/\s+/', trim($constraint)) ?: [];
        if ($parts === [] || $constraint === '*') {
            return true;
        }
        foreach ($parts as $part) {
            if ($part === '' || $part === '*') {
                continue;
            }
            if (! self::matchesPart($version, $part)) {
                return false;
            }
        }

        return true;
    }

    private static function matchesPart(string $version, string $constraint): bool
    {
        if (preg_match('/^(>=|<=|>|<|=)?(\d+(?:\.\d+){0,2})$/', $constraint, $matches) === 1) {
            return version_compare($version, self::normalize($matches[2]), $matches[1] ?: '=');
        }
        if (preg_match('/^\^(\d+(?:\.\d+){0,2})$/', $constraint, $matches) === 1) {
            $floor = self::normalize($matches[1]);
            [$major, $minor, $patch] = array_map('intval', explode('.', $floor));
            $ceiling = $major > 0 ? ($major + 1) . '.0.0' : ($minor > 0 ? '0.' . ($minor + 1) . '.0' : '0.0.' . ($patch + 1));

            return version_compare($version, $floor, '>=') && version_compare($version, $ceiling, '<');
        }
        if (preg_match('/^~(\d+(?:\.\d+){0,2})$/', $constraint, $matches) === 1) {
            $floor = self::normalize($matches[1]);
            [$major, $minor] = array_map('intval', array_slice(explode('.', $floor), 0, 2));
            $ceiling = $major . '.' . ($minor + 1) . '.0';

            return version_compare($version, $floor, '>=') && version_compare($version, $ceiling, '<');
        }

        throw new InvalidArgumentException("Unsupported version constraint: {$constraint}");
    }

    private static function normalize(string $version): string
    {
        $parts = explode('.', $version);
        while (count($parts) < 3) {
            $parts[] = '0';
        }

        return implode('.', $parts);
    }
}
