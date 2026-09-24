<?php
declare(strict_types=1);

// Run on a development/build machine. Hosting users receive the completed ZIP.
$root = dirname(__DIR__);
$version = $argv[1] ?? '0.1.0-alpha.1';
if (!preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.]+)?$/D', $version)) throw new RuntimeException('Invalid release version.');
if (!extension_loaded('zip')) throw new RuntimeException('The build machine needs the zip extension.');
require __DIR__ . '/audit-source.php';
$findings = (new \SupportK\Packaging\SourceAuditor())->audit($root);
if ($findings !== []) throw new RuntimeException('Source audit failed. Run composer audit:source for details.');

$dist = $root . '/dist';
if (!is_dir($dist)) mkdir($dist, 0755, true);
$stage = $dist . '/stage-' . bin2hex(random_bytes(8));
$private = $stage . '/_supportk';
$release = $private . '/releases/' . $version;
mkdir($release, 0755, true);

function copyTree(string $from, string $to): void
{
    if (is_link($from)) throw new RuntimeException('Release input must not contain symlinks.');
    if (is_file($from)) {
        if (!is_dir(dirname($to))) mkdir(dirname($to), 0755, true);
        if (!copy($from, $to)) throw new RuntimeException('Could not copy release input.');
        return;
    }
    if (!is_dir($to)) mkdir($to, 0755, true);
    foreach (new DirectoryIterator($from) as $file) {
        if ($file->isDot() || $file->getFilename() === '.gitkeep') continue;
        copyTree($file->getPathname(), $to . '/' . $file->getFilename());
    }
}

function removeStage(string $path): void
{
    foreach (new FilesystemIterator($path) as $file) {
        if ($file->isDir() && !$file->isLink()) removeStage($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($path);
}

function writePackage(string $stage, string $zipPath): void
{
    $manifestPath = $stage . '/_supportk/manifest.json';
    if (is_file($manifestPath)) unlink($manifestPath);
    $manifest = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file->isFile()) continue;
        $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($stage) + 1));
        if (preg_match('~(?:^|/)(?:\.git|tests|config\.php|installed\.lock|\.env)(?:/|$)~', $path)) throw new RuntimeException('Forbidden release file: ' . $path);
        $manifest[$path] = hash_file('sha256', $file->getPathname());
    }
    ksort($manifest);
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create ZIP.');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $file) {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($stage) + 1));
        if ($file->isDir()) $zip->addEmptyDir($relative);
        else $zip->addFile($file->getPathname(), $relative);
    }
    if (!$zip->close()) throw new RuntimeException('Could not finish ZIP.');
    file_put_contents($zipPath . '.sha256', hash_file('sha256', $zipPath) . '  ' . basename($zipPath) . "\n");
    fwrite(STDOUT, 'Built ' . basename($zipPath) . ' (' . filesize($zipPath) . " bytes)\n");
}

try {
    // Positive input list. Runtime, tests, original sources and history are never copied.
    foreach (['app', 'bootstrap', 'modules', 'composer.json', 'composer.lock', 'LICENSE', 'LICENSE.ko.md', 'THIRD_PARTY_NOTICES.md', 'THIRD_PARTY_NOTICES.en.md'] as $path) {
        if (file_exists($root . '/' . $path)) copyTree($root . '/' . $path, $release . '/' . $path);
    }
    $process = proc_open(['composer', 'install', '--no-dev', '--no-interaction', '--no-scripts', '--prefer-dist', '--optimize-autoloader'], [STDIN, STDOUT, STDERR], $pipes, $release);
    if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Production dependencies could not be installed.');
    // Framework distributions also include their own development tests.
    foreach (['vendor/codeigniter4/framework/tests', 'vendor/codeigniter4/framework/.github'] as $developmentDirectory) {
        if (is_dir($release . '/' . $developmentDirectory)) removeStage($release . '/' . $developmentDirectory);
    }
    copyTree(__DIR__ . '/entry.php', $stage . '/index.php');
    copyTree(__DIR__ . '/public.htaccess', $stage . '/.htaccess');
    copyTree($root . '/public/assets', $stage . '/assets/' . $version);
    copyTree($root . '/LICENSE', $stage . '/LICENSE');
    copyTree($root . '/LICENSE.ko.md', $stage . '/LICENSE.ko.md');
    copyTree($root . '/THIRD_PARTY_NOTICES.md', $stage . '/THIRD_PARTY_NOTICES.md');
    copyTree($root . '/THIRD_PARTY_NOTICES.en.md', $stage . '/THIRD_PARTY_NOTICES.en.md');
    $installKo = (string) file_get_contents($root . '/docs/installation.md');
    $installEn = (string) file_get_contents($root . '/docs/installation.en.md');
    file_put_contents($stage . '/INSTALL.md', str_replace('[English](installation.en.md)', '[English](INSTALL.en.md)', $installKo));
    file_put_contents($stage . '/INSTALL.en.md', str_replace('[한국어](installation.md)', '[한국어](INSTALL.md)', $installEn));
    foreach (['cache', 'logs', 'session', 'uploads', 'extensions'] as $directory) mkdir($private . '/shared/' . $directory, 0750, true);
    file_put_contents($private . '/.htaccess', "Require all denied\n");
    file_put_contents($private . '/shared/.htaccess', "Require all denied\n");
    file_put_contents($private . '/active.json', json_encode(['version' => $version], JSON_PRETTY_PRINT) . "\n");
    $lock = json_decode(file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    $components = array_map(static fn (array $package) => ['name' => $package['name'], 'version' => $package['version'], 'license' => $package['license'] ?? [], 'source' => $package['source'] ?? []], $lock['packages']);
    file_put_contents($release . '/release.json', json_encode(['product' => 'Support K', 'version' => $version, 'php' => '>=8.2', 'components' => $components], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    writePackage($stage, $dist . '/support-k-' . $version . '.zip');

    $splitStage = $dist . '/stage-' . bin2hex(random_bytes(8));
    try {
        mkdir($splitStage, 0755, true);
        copyTree($private, $splitStage . '/_supportk');
        copyTree($stage . '/assets', $splitStage . '/public/assets');
        copyTree($stage . '/.htaccess', $splitStage . '/public/.htaccess');
        copyTree(__DIR__ . '/split-entry.php', $splitStage . '/public/index.php');
        copyTree(__DIR__ . '/entry.php', $splitStage . '/_supportk/entry.php');
        foreach (['LICENSE', 'LICENSE.ko.md', 'THIRD_PARTY_NOTICES.md', 'THIRD_PARTY_NOTICES.en.md', 'INSTALL.md', 'INSTALL.en.md'] as $path) {
            if (is_file($stage . '/' . $path)) copyTree($stage . '/' . $path, $splitStage . '/' . $path);
        }
        writePackage($splitStage, $dist . '/support-k-' . $version . '-split-root.zip');
    } finally {
        if (is_dir($splitStage)) removeStage($splitStage);
    }
} finally {
    removeStage($stage);
}
