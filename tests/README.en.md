[한국어](README.md) | English

# Running tests

This repository uses PHPUnit 10 and `phpunit.dist.xml`. Install PHP 8.2 or newer and Composer, then run from the repository root:

```sh
composer install
vendor/bin/phpunit --no-coverage
```

For a quick unit-only run:

```sh
vendor/bin/phpunit --no-coverage tests/unit
```

Some integration tests need a running test database. Configure the connection with `database.tests.*` environment variables and use a dedicated database without real customer data. CI checks SQLite, MariaDB 10.11 and 11.4, and MySQL 8.4. See the [CI workflow](../.github/workflows/ci.yml) for configuration examples. If you have no local database, run the unit tests and mark integration tests as unverified.

Run the source audit as well:

```sh
php packaging/audit-source.php .
```

Coverage collection needs a separate coverage driver. The project test configuration writes reports to `build/logs/`. Put tests under `tests/` and give test methods descriptive names beginning with `test`. Database tests must use synthetic data and a dedicated database.

For framework details, see the [CodeIgniter 4 testing guide](https://codeigniter.com/user_guide/testing/index.html) and [PHPUnit documentation](https://phpunit.de/documentation.html).
