[한국어](installation.md) | English

# Install Support K 0.1.0-alpha.1

This guide covers two release ZIP layouts. Local Apache HTTP checks with PHP 8.4.25 and MariaDB 10.11 passed for the original ZIP at the document root and below `/support/`, including eight ticket cases, private-area blocking, and versioned CSS loading. A local PHP 8.2 smoke test of the split-root ZIP reached `/setup`, returned 404 for `/_supportk/active.json`, and rejected a wrong document root with 503. The split-root ZIP has not yet been tested on a real host. Public HTTPS deployment remains unverified.

## Before you upload

Prepare:

- PHP 8.2 or newer with `intl`, `mbstring`, `fileinfo`, `openssl`, `curl`, and `mysqli` enabled;
- an empty MariaDB database and a database user with permission to create and alter tables in that database;
- an HTTPS address for the customer center; and
- FTP or file-manager access to the host files, plus the ability to set the document root or use Apache `.htaccess` rules.

The `zip` extension and Composer are build-machine requirements. They are not required on a host that receives this ZIP. Do not upload a source checkout and do not run Composer on a shared host as part of this procedure.

HTTPS is required for a public install. `SUPPORT_K_ALLOW_HTTP=1` is reserved for a local development server and is not a production setting.

## Choose a package layout

| ZIP | Host capability | Web document root | Private files |
| --- | --- | --- | --- |
| `support-k-0.1.0-alpha.1-split-root.zip` | Can set the site's document root to the extracted `public/` directory | `public/` | Sibling `_supportk/`, outside the web root |
| `support-k-0.1.0-alpha.1.zip` | Fixed web root on Apache with working `.htaccess` and rewrite rules | Extracted ZIP root | `_supportk/` inside the web root, blocked by Apache rules |

Choose the split-root ZIP when the host allows a custom document root. This layout does not rely on `.htaccess` to hide application code and runtime files. The host must still route non-file requests such as `/setup` to `public/index.php`: Apache can use the included `.htaccess` when overrides are enabled; nginx or another web server needs an equivalent front-controller rule supplied by the host. Do not use the fixed-root ZIP on a server that ignores `.htaccess`.

The split-root ZIP will return a setup error if PHP's `DOCUMENT_ROOT` does not resolve to its `public/` directory. If the host does not expose a usable document-root setting, use a host that does or validate a server-specific configuration before installing. A custom document root and URL rewrite capability are separate requirements.

## Upload and start the installer

1. Back up the database and the current web directory if this is an existing site. For a new installation, create the empty database and record its host, port, database name, user, and password.
2. For the split-root ZIP, set the host's document root to the target `public/` directory before exposing the uploaded files. Upload the chosen ZIP by FTP or the host file manager and extract it without changing its directory structure. The split-root extracted root contains `public/` and `_supportk/`; only `public/` should be served. For the fixed-root ZIP, extract `index.php`, `.htaccess`, `assets/`, and `_supportk/` into the Apache document root after its access rules are enabled. Preserve `_supportk/active.json` and all other packaged files.
3. Configure PHP 8.2+, HTTPS, and URL rewriting to `index.php` for requests that are not existing files or directories. For the fixed-root ZIP, verify that Apache applies the included `.htaccess` and denies direct requests to `/_supportk/`; do not enable directory listings. For the split-root ZIP, confirm a direct request to `/_supportk/active.json` cannot retrieve the private file. If either check fails, stop before entering database or Owner details.
4. Open `https://your-host.example/setup`. The preflight checks PHP extensions, writable runtime storage, HTTPS, and the private-area access rule.
5. Click **확인 파일 받기**. Upload the downloaded `support-k-install-proof.txt` beside the served `index.php` (inside `public/` for the split-root ZIP), then return to the browser and click the upload confirmation. The application deletes this proof file after a successful check.

The proof is deliberate: it demonstrates that the person completing the setup can write to the selected document root. If the check fails, verify FTP ownership and permissions, upload the file beside the correct `index.php`, and confirm that the web server is serving the package root.

## Enter the database and Owner details

After the upload proof, enter:

1. database host and port, database name, database user, and database password;
2. a table prefix that starts with a lowercase letter and ends in `_` (the default is `sk_`);
3. the customer center name and its complete HTTPS base URL, including a subdirectory if applicable; and
4. the first Owner email address and a password of 12 or more characters and no more than 72 bytes. A multibyte password can reach the byte limit before it reaches 72 characters.

The installer does not overwrite tables with the selected prefix. If an earlier attempt was interrupted, retry with the same database settings so the recorded installation state can resume safely. Do not delete `installed.lock` or the private runtime settings to force a second install.

On success, the page displays a one-time recovery code. Store it in a password manager or another protected offline record before leaving the page. The code is not sent by email in this alpha.

## Verify the first round trip

1. Open **운영자 로그인** and sign in with the Owner credentials.
2. Open the customer ticket form and submit a synthetic ticket. The current proof intentionally uses no email delivery.
3. From the staff ticket view, send a reply and add a private note.
4. Open the customer lookup page and send a follow-up. Confirm that the private note is not visible to the customer.
5. In the knowledge area, check public, draft, and internal records, then exercise create, update, delete, search, and stale-content behavior.

This confirms the alpha workflow without claiming that email, AI, or another external provider is configured.

## Recover an Owner password

Open `/admin/recovery`, enter the Owner email, the recovery code saved during installation, and a new password of 12 or more characters and no more than 72 bytes. The code is stored as a hash and is marked used when the password change succeeds; it can be used once.

If the code is lost, do not remove `installed.lock`, edit runtime settings, or drop application tables. Restore the database and protected runtime files from a known-good backup, or use the host's controlled recovery process before attempting another change. Keep a fresh backup before any database recovery operation.

## Subdirectory and web-server notes

The installer accepts a base URL containing a path such as `https://your-host.example/support/`. The local root and `/support/` checks passed, but public HTTPS and a real commercial shared-host environment remain unverified. Keep the exact URL entered in the installer, including its trailing path, when configuring another host.

Both layouts keep release code under `_supportk/releases/` and runtime directories under `_supportk/shared/`. The fixed-root package uses Apache `.htaccess` to deny access to that area. The split-root package places the area outside the configured document root. If a host cannot meet either layout's requirements, do not install there until its server configuration has been checked.

## Not included in this alpha

Attachments, custom forms, Agent account management, AI providers, real email notifications, extension management UI, in-app updating, and a tested deployment on a commercial shared host are not available as supported installation features. The extension contracts and example notifier are development foundations; they do not provide a ready-to-use integration.
