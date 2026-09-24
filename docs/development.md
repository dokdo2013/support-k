한국어 | [English](development.en.md)

# 개발 안내

Support K는 공식 CodeIgniter 4 appstarter 4.7.4 배포본을 기반으로 합니다. `composer.lock`에는 프레임워크 커밋과 런타임 패키지가 기록되어 있습니다. 출처와 라이선스는 [서드파티 고지](../THIRD_PARTY_NOTICES.md)에 정리했습니다.

## 현재 검증 범위

현재 알파는 Apache, PHP 8.4.25, MariaDB 10.11에서 소스 체크아웃으로 검증했습니다. HTTP를 통한 설치 마법사의 업로드 확인, DB 설정, Owner 생성, 이메일 없는 고객 문의 등록, 운영자 답변·비공개 메모, 고객 추가 답변을 확인했습니다. 공개·초안·내부 지식 문서의 CRUD, 검색, 오래된 콘텐츠 검사도 통과했습니다.

기존 릴리스 ZIP은 약 1.6MB이며 패키징 중 별도의 `--no-dev` Composer 설치로 잠긴 런타임 패키지 3개(CodeIgniter, Laminas Escaper, PSR Log)를 넣습니다. 매니페스트와 SHA-256 검사를 통과했습니다. ZIP을 웹 루트와 `/support/` 아래에 배치한 로컬 Apache HTTP 검사도 문의 사례 8개, 비공개 영역 차단, 버전별 CSS 로딩을 포함해 통과했습니다. 분리형 ZIP은 빌드와 패키지 검증을 통과했고, PHP 8.2 로컬 검사에서 `/setup` 응답, 비공개 파일 404, 잘못된 문서 루트 503을 확인했습니다. 실제 호스팅 설치와 공개 HTTPS 배포는 검증하지 않았습니다.

이는 제한된 알파 검증입니다. 모든 PHP·DB 버전, 웹 서버, 공유 호스팅 업체에 대한 지원을 뜻하지 않습니다.

## 소스와 패키지의 문서 루트

저장소 루트는 소스·개발 루트입니다. 소스의 웹 문서 루트는 `public/`이며 애플리케이션 코드, 설정, 테스트, 패키징 도구는 그 위에 있습니다. 고정 루트 ZIP은 압축 해제 위치에 `index.php`, `.htaccess`, `assets/`, `_supportk/`가 있습니다. 분리형 ZIP은 `public/index.php`, `public/assets/`가 있고 `_supportk/`는 `public/` 옆에 있습니다. 호스팅은 문서 루트를 `public/`으로 지정해야 합니다. 두 진입점 모두 `_supportk/releases/<version>/`의 활성 릴리스를 로드합니다.

개발 Docker 이미지는 `SUPPORT_K_DOCUMENT_ROOT=/var/www/html/public`을 설정합니다. 소스 루트에서 빌드·실행하세요.

```sh
docker build -f packaging/dev/Dockerfile -t support-k-dev .
docker run --rm -v support-k-writable:/var/www/html/writable --entrypoint sh support-k-dev -c 'for directory in cache logs session uploads extensions; do mkdir -p /var/www/html/writable/$directory; done; chown -R www-data:www-data /var/www/html/writable'
docker run --rm -p 127.0.0.1:8080:8080 -e SUPPORT_K_HTTP_PORT=8080 -e SUPPORT_K_ALLOW_HTTP=1 -v "$PWD":/var/www/html -v support-k-writable:/var/www/html/writable support-k-dev
```

이름 있는 `writable` 볼륨은 런타임 파일을 소스 트리와 분리합니다. 위와 같이 컨테이너의 `www-data` 사용자에게 쓰기 권한을 부여하세요. 개발용 DB도 필요합니다. HTTP 허용 환경 변수는 로컬 테스트 전용이며 공개 운영에는 HTTPS가 필요합니다. Docker 이미지는 소스를 자체 복사하지 않고 바인드 마운트의 작업 트리를 사용합니다.

## 로컬 도구

PHP 8.2 이상과 `intl`, `mbstring`, `fileinfo`, `openssl`, `curl`, `mysqli`를 사용하세요. 소스 루트에서 개발 의존성을 설치합니다.

```sh
composer install
vendor/bin/phpunit --no-coverage
```

`.github/workflows/ci.yml`은 PR과 `main` 변경 시 SQLite 테스트, MariaDB/MySQL 조합, 소스 검사, 설치 ZIP 검증을 실행합니다. Composer와 CLI는 개발·빌드 도구이며 호스팅 운영자는 완성된 ZIP을 설치합니다.

Git 메타데이터가 없는 소스 트리도 검사할 수 있습니다.

```sh
php packaging/audit-source.php .
```

감사는 `vendor/`, `writable/`, `.git/`, `dist/`, `build/`를 건너뜁니다. 확실도가 높은 자격 증명, 개인키, 배포 자격 증명, 기기별 경로, 민감한 런타임 설정, 환경 파일, 데이터 덤프를 찾습니다. 합성 테스트와 문서의 명시적 예시 값은 허용합니다. 감사 통과는 필수 릴리스 검사이지만 사람의 검토를 대신하지 않습니다.

## 릴리스 빌드와 검증

빌드 컴퓨터에는 PHP `zip` 확장이 필요합니다. ZIP을 받는 호스팅에는 이 확장이나 Composer가 필요하지 않습니다.

```sh
composer install --no-interaction
composer package
php packaging/verify.php dist/support-k-0.1.0-alpha.1.zip
php packaging/verify.php dist/support-k-0.1.0-alpha.1-split-root.zip
(cd dist && sha256sum -c support-k-0.1.0-alpha.1.zip.sha256 && sha256sum -c support-k-0.1.0-alpha.1-split-root.zip.sha256)
```

빌드는 `composer.lock`의 런타임 패키지를 사용하고 개발 의존성을 제외합니다. 각 구조의 모든 패키지 파일에 대한 매니페스트, 보호된 런타임 디렉터리, ZIP별 SHA-256 파일을 만듭니다. 검증기는 경로 탈출과 심볼릭 링크, 활성 릴리스 메타데이터, 필수 런타임 디렉터리, lock 파일 기반 구성요소 목록, 매니페스트, 금지된 설정·로그·테스트 런타임·데이터 파일을 검사합니다.

## 구현된 기반과 남은 작업

첫 계정과 설치 흐름, 문의·지식 문서, 내부 도메인 이벤트·전달 작업, 확장 계약과 예제 알림기가 있습니다. 첨부파일, 사용자 정의 양식, Agent 계정 관리 UI, AI 제공자, 실제 이메일 알림, 확장 관리 UI, 앱 내 업데이트, 검증된 상용 공유 호스팅 배포는 미완료입니다. 단위 테스트 통과나 확장 계약 로딩만으로 해당 연동이 준비된 것은 아닙니다.

테스트에는 합성 레코드, 임시 로컬 파일, 모의 응답을 사용하세요. 고객 내보내기, 첨부파일, API 자격 증명, 웹훅 URL, 메일 자격 증명, 로컬 사용자 디렉터리를 커밋하지 마세요.
