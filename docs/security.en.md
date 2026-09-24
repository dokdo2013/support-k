[한국어](security.md) | English

# Security

Do not commit secrets or personal data. This includes API keys, webhook URLs with credentials, SMTP passwords, private keys, database passwords, customer conversations, attachments, exported tables, and machine-specific paths.

Use the source audit during development and before packaging:

```sh
php packaging/audit-source.php .
```

The audit catches high-confidence secret formats, populated sensitive settings, private key material, and likely real local or deployment paths. It accepts clearly marked examples and placeholders. A clean result does not prove that a release is safe: inspect Git history, generated archives, dependencies, logs, images, and third-party assets separately.

For a vulnerability report, provide the affected version, impact, reproduction steps, and a suggested contact channel without attaching real credentials or customer records. Keep the report itself suitable for disclosure to the maintainers.
