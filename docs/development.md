# Development

Support K is built from the official CodeIgniter 4 appstarter 4.7.4 distribution. `composer.lock` records the framework commit and the runtime packages. The source and license details are summarized in [THIRD_PARTY_NOTICES.md](../THIRD_PARTY_NOTICES.md).

## Current verification boundary

The current alpha proof used Apache, PHP 8.4.25, and MariaDB 10.11 against a source checkout. It passed the installer upload proof, database setup, Owner creation, no-email ticket submission, staff reply, private note, and customer follow-up over HTTP. Public, draft, and internal knowledge CRUD, search, and stale-content checks also passed.

The original release ZIP is about 1.6 MB and installs the three locked runtime packages (CodeIgniter framework, Laminas Escaper, and PSR Log) in a separate `--no-dev` Composer step during packaging. The package manifest and SHA-256 checks pass. Local Apache HTTP checks with the original ZIP at its document root and below `/support/` also passed, including eight ticket cases, private-area blocking, and versioned CSS loading. The split-root ZIP builds and passes package verification; a local PHP 8.2 smoke test reached `/setup`, returned 404 for a private file, and returned 503 for a wrong document root. A real-host installation and public HTTPS deployment remain unverified.

This evidence is a bounded alpha check. It does not establish support for every PHP version, database version, web server, or shared-hosting provider.

## Source and package document roots

The repository root is the source and development root. The source web document root is `public/`; application code, configuration, tests, and packaging tools stay above it. The fixed-root release ZIP contains `index.php`, `.htaccess`, `assets/`, and `_supportk/` at its extracted root. The split-root ZIP contains `public/index.php` and `public/assets/`, with `_supportk/` beside `public/`; the host must set its document root to `public/`. Both entry points load the active release from `_supportk/releases/<version>/`.

The development Docker image sets `SUPPORT_K_DOCUMENT_ROOT=/var/www/html/public`. Build and run it from the source root:

```sh
docker build -f packaging/dev/Dockerfile -t support-k-dev .
docker run --rm -v support-k-writable:/var/www/html/writable --entrypoint sh support-k-dev -c 'for directory in cache logs session uploads extensions; do mkdir -p /var/www/html/writable/$directory; done; chown -R www-data:www-data /var/www/html/writable'
docker run --rm -p 127.0.0.1:8080:8080 -e SUPPORT_K_HTTP_PORT=8080 -e SUPPORT_K_ALLOW_HTTP=1 -v "$PWD":/var/www/html -v support-k-writable:/var/www/html/writable support-k-dev
```

The named `writable` volume keeps local runtime files out of the source tree. Prepare it with write permission for the container's `www-data` user as shown. A development database is still required. The environment variable is for a local HTTP test only; public and hosted operation require HTTPS. The Docker image does not copy source into the image; the bind mount supplies the working tree.

## Local toolchain

Use PHP 8.2 or newer with `intl`, `mbstring`, `fileinfo`, `openssl`, `curl`, and `mysqli`. Install development dependencies from the source root:

```sh
composer install
vendor/bin/phpunit --no-coverage
```

The GitHub Actions workflow at `.github/workflows/ci.yml` runs SQLite tests, a MariaDB/MySQL matrix, source checks, and install ZIP verification for pull requests and changes to `main`. Composer and the CLI are development and build tools; a host operator installs a completed release ZIP and does not need Composer.

To review the source tree without Git metadata:

```sh
php packaging/audit-source.php .
```

The audit skips `vendor/`, `writable/`, `.git/`, `dist/`, and `build/`. It checks source and staging files for high-confidence credentials, private keys, deployment credentials, machine-specific paths, sensitive runtime settings, environment files, and data dumps. Placeholder values used in documentation and synthetic fixtures are allowed. A clean audit is required before packaging but does not replace a human release review.

## Build and verify a release

The build machine needs the PHP `zip` extension. A host that installs the resulting ZIP does not need that extension or Composer.

```sh
composer install --no-interaction
composer package
php packaging/verify.php dist/support-k-0.1.0-alpha.1.zip
php packaging/verify.php dist/support-k-0.1.0-alpha.1-split-root.zip
(cd dist && sha256sum -c support-k-0.1.0-alpha.1.zip.sha256 && sha256sum -c support-k-0.1.0-alpha.1-split-root.zip.sha256)
```

The build uses the runtime packages from `composer.lock`, excludes development dependencies from the release install, writes a manifest for every packaged file in each layout, creates protected runtime directories, and emits a SHA-256 sidecar for each ZIP. The verifier checks traversal and symlink safety, the active release metadata, required runtime directories, the lock-based component list, the file manifest, and forbidden configuration, log, test-runtime, and data files.

## Implemented foundations and open work

The codebase has the first account and installation flow, ticket and knowledge workflows, internal domain events and delivery jobs, extension contracts, and an example notifier extension. Attachments, custom forms, Agent account management UI, AI providers, real email notifications, extension management UI, in-app updating, and a tested Cafe24 deployment remain incomplete. Passing unit tests or loading an extension contract does not imply that those integrations are ready.

Use synthetic records, local temporary files, and mock responses in tests. Never commit customer exports, attachments, API credentials, webhook URLs, mail credentials, or local user directories.
