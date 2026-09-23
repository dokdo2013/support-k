<?php
// This file intentionally runs before Composer and CodeIgniter.
require_once __DIR__ . '/runtime-config-schema.php';
$supportKFailures = array();
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $supportKFailures[] = 'PHP 8.2 이상이 필요합니다. 호스팅 설정에서 PHP 버전을 변경해 주세요.';
}
foreach (array('intl', 'mbstring', 'fileinfo', 'openssl') as $supportKExtension) {
    if (!extension_loaded($supportKExtension)) {
        $supportKFailures[] = '필요한 PHP 확장이 없습니다: ' . $supportKExtension;
    }
}
if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
    $supportKFailures[] = '필수 파일이 없습니다. vendor가 포함된 Support K 설치 ZIP을 다시 업로드해 주세요.';
}
if (PHP_SAPI !== 'cli' && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') && getenv('SUPPORT_K_ALLOW_HTTP') !== '1') {
    $supportKFailures[] = 'HTTPS 주소로 접속해 주세요. 호스팅에서 SSL 인증서를 설정한 뒤 설치할 수 있습니다.';
}
$supportKShared = rtrim((string) (getenv('SUPPORT_K_SHARED') ?: (defined('SUPPORT_K_SHARED') ? SUPPORT_K_SHARED : __DIR__ . '/../writable')), '/\\');
if ((strpos($supportKShared, '/') !== 0 && !preg_match('/^[A-Za-z]:[\\\\\/]/D', $supportKShared)) || !is_dir($supportKShared) || realpath($supportKShared) === DIRECTORY_SEPARATOR) {
    $supportKFailures[] = '설정 폴더의 위치가 잘못되었습니다. 존재하는 전용 폴더의 절대 경로로 설정해 주세요.';
}
if (version_compare(PHP_VERSION, '8.2.0', '>=') && is_file($supportKShared . '/config.php')) {
    try {
        $supportKConfig = require $supportKShared . '/config.php';
        if (!support_k_runtime_config_is_valid($supportKConfig, 1)) {
            $supportKFailures[] = '설정 파일이 손상되었습니다. 백업한 config.php를 같은 비공개 폴더에 복원해 주세요. 재설치로 기존 DB를 덮어쓰지 마세요.';
        }
    } catch (Throwable $supportKError) {
        $supportKFailures[] = '설정 파일을 읽을 수 없습니다. 백업한 config.php를 복원한 뒤 새로고침해 주세요.';
    }
}
if ($supportKFailures) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Support K 환경 확인</title><body><main><h1>설치를 준비해 주세요</h1><ul>';
    foreach ($supportKFailures as $supportKFailure) {
        echo '<li>' . htmlspecialchars($supportKFailure, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul><p>호스팅 관리 화면에서 설정한 뒤 이 페이지를 새로고침해 주세요.</p></main></body></html>';
    exit;
}
unset($supportKFailures, $supportKExtension, $supportKShared, $supportKConfig, $supportKError);
