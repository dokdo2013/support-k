<?php
declare(strict_types=1);
namespace App\Services\Accounts;

final class RateLimiter
{
    public function allow(string $key, int $limit = 8, int $seconds = 300): bool
    {
        $directory = WRITEPATH . 'cache/rate-limits';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) return false;
        $handle = fopen($directory . '/' . hash('sha256', $key), 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            return false;
        }
        try {
            $state = json_decode(stream_get_contents($handle), true);
            $now = time();
            if (!is_array($state) || ($state['until'] ?? 0) <= $now) $state = ['until' => $now + $seconds, 'count' => 0];
            $allowed = $state['count'] < $limit;
            if ($allowed) ++$state['count'];
            $bytes = json_encode($state);
            if (!rewind($handle) || !ftruncate($handle, 0) || fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) return false;
            return $allowed;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
