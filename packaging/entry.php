<?php
// Stable entry point for FTP installations. Application code stays private.
$supportKPublic = defined('SUPPORT_K_PACKAGE_PUBLIC') ? SUPPORT_K_PACKAGE_PUBLIC : __DIR__;
$supportKPrivate = defined('SUPPORT_K_PACKAGE_PUBLIC') ? __DIR__ : __DIR__ . '/_supportk';
$supportKActive = json_decode((string) @file_get_contents($supportKPrivate . '/active.json'), true);
$supportKVersion = is_array($supportKActive) ? ($supportKActive['version'] ?? '') : '';
if (!is_string($supportKVersion) || !preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.]+)?$/D', $supportKVersion)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Support K 버전 정보를 읽을 수 없습니다. 설치 ZIP의 파일이 모두 업로드되었는지 확인해 주세요.');
}
$supportKRelease = $supportKPrivate . '/releases/' . $supportKVersion;
define('SUPPORT_K_SHARED', $supportKPrivate . '/shared');
define('SUPPORT_K_RELEASE', $supportKVersion);
define('FCPATH', $supportKPublic . DIRECTORY_SEPARATOR);
require $supportKRelease . '/bootstrap/preflight.php';
require $supportKRelease . '/app/Config/Paths.php';
$paths = new \Config\Paths();
require $paths->systemDirectory . '/Boot.php';
exit(\CodeIgniter\Boot::bootWeb($paths));
