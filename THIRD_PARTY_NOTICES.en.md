[한국어](THIRD_PARTY_NOTICES.md) | English

# Third-party notices

Support K is distributed under the MIT License. This file records the third-party software selected by the current Composer lock file. Each package keeps its own copyright and license terms; consult the package source for the complete notice text when redistributing a release.

## Framework foundation

The application skeleton was obtained from the official [CodeIgniter 4 appstarter distribution](https://github.com/codeigniter4/appstarter). The current lock resolves `codeigniter4/framework` to **v4.7.4**, commit `67ead895b7491703e5e5bc17436778806192008f`, from the official [CodeIgniter4 framework repository](https://github.com/codeigniter4/CodeIgniter4). The upstream appstarter attribution and CodeIgniter Foundation notice remain in `LICENSE`. Support K application code is maintained separately and does not imply that the CodeIgniter Foundation endorses Support K.

## Runtime dependencies

These packages are in the `packages` section of `composer.lock` and are part of the application runtime:

| Package | Locked version | License | Source |
| --- | --- | --- | --- |
| `codeigniter4/framework` | `v4.7.4` | MIT | [github.com/codeigniter4/CodeIgniter4](https://github.com/codeigniter4/CodeIgniter4) |
| `laminas/laminas-escaper` | `2.18.0` | BSD-3-Clause | [github.com/laminas/laminas-escaper](https://github.com/laminas/laminas-escaper) |
| `psr/log` | `3.0.2` | MIT | [github.com/php-fig/log](https://github.com/php-fig/log) |

The versions, distribution references, and package metadata above are authoritative in `composer.lock`. A release builder must generate its dependency notices from the exact lock used for that release and must include any package license files required by the package terms.

## Development dependencies

The `packages-dev` section is used for local tests and is not required by the intended hosting installation. It currently contains Faker, PHPUnit 10.5, VFS Stream, and their transitive packages. Their locked versions and license declarations are recorded in `composer.lock`; the principal package sources are [Faker](https://github.com/FakerPHP/Faker), [PHPUnit](https://github.com/sebastianbergmann/phpunit), and [VFS Stream](https://github.com/bovigo/vfsStream). Release packaging must not silently bundle development-only dependencies.

The current development license families are MIT and BSD-3-Clause. This summary does not replace the license files shipped by the packages or a release-specific dependency audit.

## Fonts, JavaScript, CSS, and other assets

No third-party frontend bundle, font, icon set, or remote asset is declared as part of this foundation. Any future asset must be pinned to a version or immutable source, reviewed for redistribution, and added here before it enters a release archive. Runtime downloads from a CDN are not a substitute for this notice or for reproducible packaging.
