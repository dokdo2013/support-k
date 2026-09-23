<?php

declare(strict_types=1);

namespace SupportK\Packaging;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** A finding produced by the source and release input audit. */
final class SourceFinding
{
    public function __construct(
        public readonly string $code,
        public readonly string $path,
        public readonly string $message,
        public readonly int $line = 0,
        public readonly string $severity = 'error',
    ) {
    }

    /** @return array{code:string,path:string,message:string,line:int,severity:string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'path' => $this->path,
            'message' => $this->message,
            'line' => $this->line,
            'severity' => $this->severity,
        ];
    }
}

/**
 * Audits a directory directly. It deliberately does not use Git so it can
 * inspect an unpacked release or a staging directory as well as a checkout.
 */
final class SourceAuditor
{
    /** @var list<string> */
    public const EXCLUDED_DIRECTORIES = ['vendor', 'writable', '.git', 'dist', 'build'];

    private const MAX_TEXT_FILE_BYTES = 10_000_000;

    /** @return list<SourceFinding> */
    public function audit(string $root): array
    {
        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false || ! is_dir($resolvedRoot)) {
            throw new RuntimeException('Audit root is not a readable directory: ' . $root);
        }

        $findings = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $resolvedRoot,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
            ),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile() || $fileInfo->isLink()) {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            $relativePath = $this->relativePath($resolvedRoot, $absolutePath);
            if ($this->isExcluded($relativePath)) {
                continue;
            }

            $findings = array_merge($findings, $this->auditPath($relativePath));
            if ($this->isBlockedFilePath($relativePath)) {
                continue;
            }

            $contents = @file_get_contents($absolutePath);
            if ($contents === false) {
                $findings[] = new SourceFinding(
                    'UNREADABLE_FILE',
                    $relativePath,
                    'The file could not be read and was not audited.',
                );
                continue;
            }

            if (strlen($contents) > self::MAX_TEXT_FILE_BYTES) {
                $findings[] = new SourceFinding(
                    'LARGE_FILE',
                    $relativePath,
                    'The file is larger than the text-audit limit; inspect it separately.',
                );
                continue;
            }

            // Binary assets are not treated as text. Release inspection should
            // still review their provenance and license separately.
            if (strpos(substr($contents, 0, 8192), "\0") !== false) {
                continue;
            }

            $findings = array_merge($findings, $this->auditText($relativePath, $contents));
        }

        return $findings;
    }

    /** @return list<SourceFinding> */
    private function auditText(string $path, string $contents): array
    {
        $findings = [];
        $lines = preg_split('/\R/', $contents) ?: [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            if (preg_match('/-----BEGIN (?:[A-Z0-9]+ )?PRIVATE KEY-----/', $line) === 1) {
                $findings[] = new SourceFinding(
                    'PRIVATE_KEY',
                    $path,
                    'Private key material must not be present in source or release input.',
                    $lineNumber,
                );
            }

            if (preg_match('~(?<![A-Za-z0-9])(?:sk-(?:proj-)?|rk_(?:live|test)_|gh[pousr]_|xox[baprs]-|AKIA)[A-Za-z0-9_-]{16,}~', $line, $match) === 1
                && ! $this->isPlaceholder($match[0])) {
                $findings[] = new SourceFinding(
                    'CREDENTIAL_FORMAT',
                    $path,
                    'A value matches a high-confidence API or access-token format.',
                    $lineNumber,
                );
            }

            $commentOnly = $this->isCommentOnly($line);
            if (! ($commentOnly && $this->isTemplatePath($path))) {
                foreach ($this->sensitiveAssignments($line) as $value) {
                    if (! $this->isPlaceholder($value)) {
                        $findings[] = new SourceFinding(
                            'SENSITIVE_SETTING',
                            $path,
                            'A sensitive setting contains a non-placeholder value.',
                            $lineNumber,
                        );
                    }
                }
            }

            foreach ($this->realPaths($line) as $realPath) {
                if (! $this->isPlaceholder($realPath)) {
                    $findings[] = new SourceFinding(
                        'REAL_PATH',
                        $path,
                        'A likely machine-specific or deployment data path is present: ' . $realPath,
                        $lineNumber,
                    );
                }
            }

            if (preg_match('~(?i)(?:ftp|sftp)://[^/\s:@]+:[^@\s/]+@~', $line) === 1) {
                $findings[] = new SourceFinding(
                    'DEPLOYMENT_CREDENTIAL',
                    $path,
                    'A deployment URL contains embedded credentials.',
                    $lineNumber,
                );
            }
        }

        return $findings;
    }

    /** @return list<SourceFinding> */
    private function auditPath(string $path): array
    {
        $lowerPath = strtolower($path);
        $base = strtolower(basename($path));
        $findings = [];

        if ($base === '.env' || (str_starts_with($base, '.env.') && ! in_array($base, ['.env.example', '.env.sample', '.env.template', '.env.dist'], true))) {
            $findings[] = new SourceFinding(
                'SENSITIVE_FILE',
                $path,
                'An environment file is not an allowed source example; keep real settings outside the source tree.',
            );
        }

        if (in_array($base, ['config.php', 'credentials.php', 'secrets.php', 'runtime-config.php', 'config.local.php'], true)
            && ! str_starts_with($lowerPath, 'app/config/')) {
            $findings[] = new SourceFinding(
                'RUNTIME_CONFIG',
                $path,
                'A runtime or credential configuration file must not be included in source input.',
            );
        }

        foreach (['.sql', '.sql.gz', '.dump', '.dump.gz', '.sqlite', '.sqlite3', '.db', '.eml', '.eml.gz', '.mbox', '.pst', '.bak'] as $suffix) {
            if (str_ends_with($lowerPath, $suffix)) {
                $findings[] = new SourceFinding(
                    'DATA_DUMP',
                    $path,
                    'A database, mail, backup, or data dump file must not be included in source input.',
                );
                break;
            }
        }

        return $findings;
    }

    /** @return list<string> */
    private function sensitiveAssignments(string $line): array
    {
        $name = '(?:api[_-]?key|access[_-]?token|auth[_-]?token|secret(?:[_-]?key)?|client[_-]?secret|password|passwd|private[_-]?key|webhook[_-]?url|(?:ftp|deploy)[_-]?(?:host|user|username|password|key|token)|smtp[_-]?(?:host|user|username|password))';
        $values = [];

        $key = '(?<![$A-Za-z0-9_])(?:[\'\"]?' . $name . '[\'\"]?)(?=\s*(?:=>|=|:))';

        if (preg_match_all('~' . $key . '\s*(?:=>|=|:)\s*[\'\"]([^\'\"]*)[\'\"]~i', $line, $matches) !== false) {
            foreach ($matches[1] as $value) {
                $values[] = $value;
            }
        }

        // Unquoted environment-style values are common in deployment files.
        if (preg_match_all('~' . $key . '\s*(?:=>|=|:)\s*([^\s,;}]*)~i', $line, $matches) !== false) {
            foreach ($matches[1] as $value) {
                $trimmed = trim($value, "\t\r\n\'\"`]");
                // Values beginning with PHP expression syntax are source code,
                // not credential literals. Quoted values are handled above;
                // keep this branch for simple environment/config scalars.
                if ($trimmed !== ''
                    && ! in_array($trimmed, $values, true)
                    && ! in_array(strtolower($trimmed), ['true', 'false', 'null'], true)
                    && ! preg_match('/^[($\[]|^(?:new|array|function)\b/i', $trimmed)) {
                    $values[] = $trimmed;
                }
            }
        }

        return $values;
    }

    /** @return list<string> */
    private function realPaths(string $line): array
    {
        $patterns = [
            '~(?<![A-Za-z0-9])/(?:home|Users|users)/[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)+~',
            '~(?<![A-Za-z0-9])[A-Za-z]:\\\\Users\\\\[A-Za-z0-9._-]+(?:\\\\[A-Za-z0-9._-]+)+~i',
            '~(?<![A-Za-z0-9])/(?:var/www|srv/www|opt/[A-Za-z0-9._-]+)/(?!html(?:/|$))(?:[A-Za-z0-9._-]+/){1,}[A-Za-z0-9._-]+~',
            '~(?<![A-Za-z0-9])file:///(?:home|Users|users)/[^\s"\']+~i',
        ];
        $paths = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $line, $matches) !== false) {
                foreach ($matches[0] as $match) {
                    $paths[] = rtrim($match, ".,;)]}");
                }
            }
        }

        return array_values(array_unique($paths));
    }

    private function isPlaceholder(string $value): bool
    {
        $normalized = strtolower(trim($value, " \t\r\n\"'`"));
        if ($normalized === '' || in_array($normalized, ['null', 'none', 'false', 'redacted', 'disabled'], true)) {
            return true;
        }

        return preg_match('~(?:example|sample|placeholder|dummy|fake|changeme|change[-_ ]?me|replace[-_ ]?me|insert[-_ ]?here|your[-_ ]?(?:key|token|secret|password|url)|(?:root|ci|local|dev|lookup)[-_ ]?password|test(?:[-_ ]|$)|username|user-name|<[^>]+>|\$\{[^}]+\}|x{4,})~i', $normalized) === 1
            || preg_match('~^/(?:var/www|srv/www)/html(?:/public)?$~', $normalized) === 1;
    }

    private function isCommentOnly(string $line): bool
    {
        return preg_match('~^\s*(?://|#|\*|<!--|/\*)~', $line) === 1;
    }

    private function isTemplatePath(string $path): bool
    {
        $lowerPath = strtolower($path);
        $base = strtolower(basename($path));

        return $base === 'env'
            || in_array($base, ['.env.example', '.env.sample', '.env.template', '.env.dist'], true)
            || $lowerPath === 'app/config/database.php';
    }

    private function isBlockedFilePath(string $path): bool
    {
        $lowerPath = strtolower($path);
        $base = strtolower(basename($path));

        return $base === '.env'
            || (str_starts_with($base, '.env.') && ! in_array($base, ['.env.example', '.env.sample', '.env.template', '.env.dist'], true))
            || (in_array($base, ['config.php', 'credentials.php', 'secrets.php', 'runtime-config.php', 'config.local.php'], true)
                && ! str_starts_with($lowerPath, 'app/config/'))
            || array_reduce(
                ['.sql', '.sql.gz', '.dump', '.dump.gz', '.sqlite', '.sqlite3', '.db', '.eml', '.eml.gz', '.mbox', '.pst', '.bak'],
                static fn (bool $found, string $suffix): bool => $found || str_ends_with($lowerPath, $suffix),
                false,
            );
    }

    private function isExcluded(string $relativePath): bool
    {
        $segments = explode('/', $relativePath);
        foreach (self::EXCLUDED_DIRECTORIES as $excluded) {
            if (in_array($excluded, $segments, true)) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $root, string $path): string
    {
        $relative = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);

        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
}

/** @return never */
function runAuditCli(array $arguments): never
{
    if (isset($arguments[1]) && in_array($arguments[1], ['-h', '--help'], true)) {
        fwrite(STDOUT, "Usage: php packaging/audit-source.php [directory]\n");
        exit(0);
    }

    $root = $arguments[1] ?? dirname(__DIR__);

    try {
        $findings = (new SourceAuditor())->audit($root);
    } catch (RuntimeException $exception) {
        fwrite(STDERR, 'ERROR [AUDIT_ROOT] ' . $exception->getMessage() . PHP_EOL);
        exit(2);
    }

    foreach ($findings as $finding) {
        $line = $finding->line > 0 ? ':' . $finding->line : '';
        fwrite(STDOUT, strtoupper($finding->severity) . ' [' . $finding->code . '] ' . $finding->path . $line . ': ' . $finding->message . PHP_EOL);
    }

    if ($findings === []) {
        fwrite(STDOUT, "OK: no source audit findings (excluded: vendor, writable, .git, dist, build)\n");
        exit(0);
    }

    $errors = count(array_filter($findings, static fn (SourceFinding $finding): bool => $finding->severity === 'error'));
    fwrite(STDOUT, sprintf("Audit completed: %d finding(s), %d error(s).\n", count($findings), $errors));
    exit($errors > 0 ? 1 : 0);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    runAuditCli($argv);
}
