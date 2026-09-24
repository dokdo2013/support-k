[한국어](AGENTS.md) | English

# Contributing to Support K

Support K is an MIT-licensed application for self-hosted customer support. Keep contributions suitable for a public repository.

## Repository boundaries

- Do not commit credentials, private keys, customer records, production exports, uploaded files, local machine paths, deployment destinations, or service account details.
- Use synthetic fixtures and placeholder values in tests and documentation. Use `example`, `test`, `.invalid`, or clearly marked dummy values for examples.
- Do not add framework, provider, or frontend assets without recording their exact version, license, and source in `THIRD_PARTY_NOTICES.md`.
- Keep user configuration and runtime files out of source control. Runtime output belongs under `writable/`; dependencies belong under `vendor/`.
- Keep the public documentation accurate about what has been tested. Alpha work must not imply that AI providers or a particular hosting environment has been validated.
- Keep user documentation in the default Korean file and its matching `.en.md` English file, with links between them. Do not change the legally authoritative `LICENSE` text.

## PHP and CodeIgniter

- Follow the PHP version and CodeIgniter constraints in `composer.json`.
- Keep framework configuration changes small and explain behavior changes in the relevant documentation.
- Validate request input, escape output, and use the framework's database and URL helpers rather than composing SQL or public URLs by hand.
- Keep external calls behind replaceable contracts. Tests use synthetic data and mocked or local responses; they do not call live providers.

## Verification

Run the unit suite and the source audit before submitting a change:

```sh
composer install
vendor/bin/phpunit
php packaging/audit-source.php .
```

When a check cannot run locally, say why and leave the affected capability marked as unverified. The source audit is an additional signal; it does not replace review of the complete release archive, Git history, dependencies, or hosting behavior.
