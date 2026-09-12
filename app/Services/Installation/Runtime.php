<?php
declare(strict_types=1);

namespace App\Services\Installation;

use RuntimeException;

final class Runtime
{
    public const SCHEMA_VERSION = 1;

    public static function directory(): string
    {
        $directory = (string) (getenv('SUPPORT_K_SHARED') ?: (defined('SUPPORT_K_SHARED') ? SUPPORT_K_SHARED : ROOTPATH . 'writable'));
        $absolute = str_starts_with($directory, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/D', $directory);
        $resolved = realpath($directory);
        if (!$absolute || $resolved === false || dirname($resolved) === $resolved || !is_dir($resolved)) {
            throw new RuntimeException('설정 폴더는 존재하는 전용 폴더의 절대 경로여야 합니다.');
        }
        return rtrim($resolved, '/\\') . DIRECTORY_SEPARATOR;
    }

    public static function read(): array
    {
        $path = self::directory() . 'config.php';
        if (!is_file($path)) {
            return [];
        }
        $config = require $path;
        require_once ROOTPATH . 'bootstrap/runtime-config-schema.php';
        if (!\support_k_runtime_config_is_valid($config, self::SCHEMA_VERSION)) {
            throw new RuntimeException('설치 설정을 읽을 수 없습니다. 백업한 설정 파일을 복원해 주세요.');
        }
        return $config;
    }

    public static function write(array $config): void
    {
        require_once ROOTPATH . 'bootstrap/runtime-config-schema.php';
        if (!\support_k_runtime_config_is_valid($config, self::SCHEMA_VERSION)) {
            throw new RuntimeException('유효하지 않은 설치 설정은 저장할 수 없습니다.');
        }
        $directory = self::directory();
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('설정 폴더에 쓸 수 없습니다. writable 또는 shared 폴더의 권한을 확인해 주세요.');
        }
        $temporary = tempnam($directory, '.config-');
        if ($temporary === false) {
            throw new RuntimeException('설정 파일을 만들 수 없습니다.');
        }
        try {
            chmod($temporary, 0600);
            $bytes = "<?php\n// Server-only installation settings. Never commit this file.\nreturn " . var_export($config + ['format' => 1], true) . ";\n";
            if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !rename($temporary, $directory . 'config.php')) {
                throw new RuntimeException('설정 파일 저장에 실패했습니다.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public static function installed(): bool
    {
        return (self::read()['installed'] ?? false) === true;
    }
}
