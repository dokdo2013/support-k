<?php
declare(strict_types=1);

namespace App\Services\Installation;

use RuntimeException;

final class ProtectionCheck
{
    public function verify(string $baseUrl): void
    {
        $public = realpath(FCPATH);
        $private = realpath(Runtime::directory());
        if (!$public || !$private) {
            throw new RuntimeException('공개/설정 폴더 위치를 확인할 수 없습니다.');
        }
        $requiresPrivateProbe = $private === $public || str_starts_with($private, $public . DIRECTORY_SEPARATOR);
        if (!extension_loaded('curl')) {
            throw new RuntimeException('설치 주소와 설정 폴더 접근을 검사하려면 curl 확장이 필요합니다. 호스팅 설정에서 curl을 켜 주세요.');
        }
        $nonce = bin2hex(random_bytes(24));
        $name = 'support-k-probe-' . bin2hex(random_bytes(8)) . '.txt';
        $control = $public . '/' . $name;
        $protected = $private . '/' . $name;
        try {
            if (file_put_contents($control, $nonce) === false || ($requiresPrivateProbe && file_put_contents($protected, $nonce) === false)) {
                throw new RuntimeException('접근 차단 검사 파일을 만들 수 없습니다. 업로드 폴더 권한을 확인해 주세요.');
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($private, strlen($public) + 1));
            [$publicStatus, $publicBody] = $this->fetch($baseUrl . $name);
            $privateStatus = $requiresPrivateProbe ? $this->fetch($baseUrl . $relative . '/' . $name)[0] : 403;
            if ($publicStatus !== 200 || !hash_equals($nonce, $publicBody) || !in_array($privateStatus, [403, 404], true)) {
                throw new RuntimeException('설정 폴더의 외부 접근 차단을 확인하지 못했습니다. .htaccess 적용과 설치 주소를 확인하거나 웹 루트 밖에 배치해 주세요.');
            }
        } finally {
            foreach ([$control, $protected] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function fetch(string $url): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        return [$status, is_string($body) ? $body : ''];
    }
}
