한국어 | [English](README.en.md)

# 테스트 실행

이 저장소는 PHPUnit 10과 `phpunit.dist.xml`을 사용합니다. PHP 8.2 이상과 Composer를 설치한 뒤 저장소 루트에서 실행하세요.

```sh
composer install
vendor/bin/phpunit --no-coverage
```

단위 테스트만 빠르게 실행하려면 다음을 사용합니다.

```sh
vendor/bin/phpunit --no-coverage tests/unit
```

일부 통합 테스트에는 실행 중인 테스트 DB가 필요합니다. `database.tests.*` 환경 변수로 연결을 설정하고 실제 고객 데이터가 없는 전용 DB를 사용하세요. CI는 SQLite, MariaDB 10.11·11.4, MySQL 8.4 조합을 검사합니다. 설정 예시는 [CI 워크플로](../.github/workflows/ci.yml)에 있습니다. 로컬 DB가 없다면 단위 테스트만 실행하고 통합 테스트는 미검증으로 기록하세요.

소스 감사도 함께 실행하세요.

```sh
php packaging/audit-source.php .
```

코드 커버리지는 별도의 커버리지 드라이버가 있을 때만 수집할 수 있습니다. 이 프로젝트의 테스트 구성은 보고서를 `build/logs/`에 출력합니다. 테스트 파일은 `tests/` 아래에 두고, 테스트 이름은 `test`로 시작하는 설명적인 이름을 사용하세요. DB 테스트는 합성 데이터와 전용 DB만 사용해야 합니다.

프레임워크 기능의 자세한 설명은 [CodeIgniter 4 테스트 가이드](https://codeigniter.com/user_guide/testing/index.html)와 [PHPUnit 문서](https://phpunit.de/documentation.html)를 참고하세요.
