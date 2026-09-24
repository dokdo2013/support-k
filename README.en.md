[한국어](README.md) | English

# Support K

Support K is an MIT-licensed, self-hosted customer support application for PHP hosting. The first alpha provides a small support inbox, knowledge content, and the installation and storage foundations for replaceable delivery and extension connections.

## Alpha status

`0.1.0-alpha.1` has a bounded end-to-end proof. The current proof used a source checkout on Apache with PHP 8.4.25 and MariaDB 10.11:

- the browser installer completed its upload proof, database setup, Owner creation, and recovery-code display;
- a customer submitted a ticket without email delivery, staff replied, staff added a private note, and the customer sent a follow-up;
- public, draft, and internal knowledge records passed create, update, delete, search, and stale-content HTTP checks;
- the original release ZIP is about 1.6 MB, installs its three runtime packages separately with `composer install --no-dev`, and passes the package manifest and SHA-256 checks;
- the ZIP passed local Apache HTTP installation checks both at the document root and below `/support/`, including eight ticket cases, private-area blocking, and versioned CSS loading.

These HTTP checks used PHP 8.4.25 and MariaDB 10.11. A second ZIP with a separate `public/` document root builds and passes package verification; a local PHP 8.2 smoke test reached `/setup`, denied a private file, and rejected a wrong document root. It has not yet had a real-host installation check. Public HTTPS deployment remains unverified. The proof does not establish support for every PHP host, web server, database version, or hosting plan.

This alpha is suitable for development evaluation. Do not put production customer data into it until the target host has been tested and a backup and recovery procedure has been rehearsed.

## What is implemented

The foundation currently includes the browser installation flow, the first Owner account and one-time recovery code, customer tickets and staff replies and private notes, knowledge content with public, draft, and internal visibility, internal domain events and delivery jobs, extension contracts, and the example notifier extension. The release builder, source audit, and ZIP verifier are part of the development and release checks.

The following remain outside this alpha: attachments, custom forms, an Agent account management UI, AI provider connections, real email notifications, extension management UI, in-app updating, and a tested commercial shared-host deployment. The existing delivery and extension contracts do not mean that a provider or hosting integration is ready for use.

## Requirements

For a host installation use PHP 8.2 or newer with `intl`, `mbstring`, `fileinfo`, `openssl`, `curl`, and `mysqli`. MariaDB 10.11 is the database combination covered by the current installation proof. Other MySQL and MariaDB versions need their own check.

Use HTTPS for an installation and for normal operation. The application rejects a public HTTP installation. `SUPPORT_K_ALLOW_HTTP=1` is a local development exception for an HTTP test server; it is not an operating recommendation and does not make a public deployment safe. The `zip` PHP extension is required on the machine that builds a release ZIP. A host that installs a completed ZIP does not need Composer or the build-time `zip` extension.

## Install a release ZIP

Start with [the installation guide](docs/installation.en.md). Choose the split-root ZIP when the host lets you set the document root to `public/`; choose the original fixed-root ZIP for Apache hosting that applies the included `.htaccess`. Upload the ZIP by FTP or the host file manager, create an empty MariaDB database and user, and open `/setup` over HTTPS. The installer asks for a one-time upload proof, database details, the site URL, and the first Owner credentials. It displays a one-time recovery code after a successful install.

The split-root ZIP keeps `_supportk/` beside `public/`, outside the web document root. The original ZIP uses its extracted root as the document root and relies on Apache rules to block `_supportk/`. Keep every packaged file and disable directory listings. Both layouts need URL rewriting to `index.php`.

## Development

The source document root is `public/`, while the repository root contains the application and development tools. The development Docker image sets `SUPPORT_K_DOCUMENT_ROOT=/var/www/html/public`. The fixed-root ZIP serves from its extracted root; the split-root ZIP serves from its `public/` directory.

```sh
docker build -f packaging/dev/Dockerfile -t support-k-dev .
docker run --rm -v support-k-writable:/var/www/html/writable --entrypoint sh support-k-dev -c 'for directory in cache logs session uploads extensions; do mkdir -p /var/www/html/writable/$directory; done; chown -R www-data:www-data /var/www/html/writable'
docker run --rm -p 127.0.0.1:8080:8080 -e SUPPORT_K_HTTP_PORT=8080 -e SUPPORT_K_ALLOW_HTTP=1 -v "$PWD":/var/www/html -v support-k-writable:/var/www/html/writable support-k-dev
```

The named `writable` volume keeps local runtime files out of the source tree. Prepare it with write permission for the container's `www-data` user as shown. A development database is still required; configure a local MariaDB/MySQL instance before opening the installer.

For a local source checkout, install development dependencies and run the tests:

```sh
composer install
vendor/bin/phpunit --no-coverage
```

Composer and the CLI are development tools. They are not required on a host that receives a completed release ZIP.

The source audit can inspect a checkout or unpacked directory without Git metadata:

```sh
php packaging/audit-source.php .
```

It excludes `vendor/`, `writable/`, `.git/`, `dist/`, and `build/`, and reports high-confidence secrets, private keys, deployment credentials, machine paths, sensitive files, and populated runtime settings. Placeholder values used in documentation and synthetic tests are allowed. A clean audit is one release check, not a replacement for review.

## License and third-party software

Support K and the original framework attribution are distributed under the MIT License in [LICENSE](LICENSE). A [Korean reference translation](LICENSE.ko.md) is available; the English license text remains authoritative. Runtime and development dependencies, versions, licenses, and upstream sources are listed in [third-party notices](THIRD_PARTY_NOTICES.en.md).

Security reports should describe the affected version and a reproducible report without including customer data or credentials. See the [security guide](docs/security.en.md).
