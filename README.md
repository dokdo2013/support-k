한국어 | [English](README.en.md)

# Support K

Support K는 PHP 웹호스팅에 직접 설치하는 MIT 라이선스 고객센터 애플리케이션입니다. 첫 알파에는 문의함, 지식 문서, 설치·저장소 기반과 알림·확장 기능을 교체할 수 있는 계약이 포함됩니다.

## 알파 상태

`0.1.0-alpha.1`의 핵심 흐름은 제한된 환경에서 검증했습니다. Apache, PHP 8.4.25, MariaDB 10.11의 소스 체크아웃에서 다음을 확인했습니다.

- 브라우저 설치 마법사의 업로드 확인, DB 설정, 첫 Owner 계정 생성, 복구 코드 표시
- 이메일 발송 없이 고객 문의 등록, 운영자 답변과 비공개 메모, 고객 추가 답변
- 공개·초안·내부 지식 문서의 생성, 수정, 삭제, 검색 및 오래된 콘텐츠 처리
- 약 1.6MB의 기존 설치 ZIP, `composer install --no-dev`로 별도 설치한 런타임 패키지 3개, 패키지 매니페스트와 SHA-256 검사
- ZIP을 웹 루트와 `/support/` 하위 경로에 둔 로컬 Apache HTTP 설치 검사: 문의 사례 8개, 비공개 영역 차단, 버전별 CSS 로딩

분리형 ZIP도 빌드·패키지 검증을 통과했습니다. PHP 8.2 로컬 HTTP 검사에서 `/setup` 접속, 비공개 파일 차단, 잘못된 문서 루트 거부를 확인했습니다. 실제 웹호스팅 설치와 공개 HTTPS 배포는 아직 검증하지 않았습니다. 모든 PHP 호스팅, 웹 서버, DB 버전, 요금제에 대한 지원을 뜻하지 않습니다.

이 알파는 개발·평가용입니다. 대상 호스팅에서 설치를 검증하고 백업·복구 절차를 연습하기 전에는 실제 고객 데이터를 넣지 마세요.

## 구현된 기능

브라우저 설치, 첫 Owner 계정과 일회용 복구 코드, 고객 문의·운영자 답변·비공개 메모, 공개·초안·내부 지식 문서, 내부 도메인 이벤트와 전달 작업, 확장 계약, 예제 알림 확장이 구현되어 있습니다. 릴리스 빌더, 소스 감사, ZIP 검증기도 포함됩니다.

첨부파일, 사용자 정의 양식, Agent 계정 관리 화면, AI 제공자 연결, 실제 이메일 알림, 확장 관리 화면, 앱 내 업데이트, 상용 공유 호스팅 설치 검증은 아직 지원 기능이 아닙니다. 전달·확장 계약이 있다고 해서 외부 제공자 연동이 준비된 것은 아닙니다.

## 요구 사항

설치 호스팅에는 PHP 8.2 이상과 `intl`, `mbstring`, `fileinfo`, `openssl`, `curl`, `mysqli` 확장이 필요합니다. 현재 설치 검증에 사용한 DB는 MariaDB 10.11입니다. 다른 MySQL/MariaDB 버전은 별도 확인이 필요합니다.

설치와 운영에는 HTTPS가 필요합니다. 공개 HTTP 설치는 거부됩니다. `SUPPORT_K_ALLOW_HTTP=1`은 로컬 개발 서버에서만 사용하는 예외입니다. `zip` PHP 확장은 릴리스 ZIP을 만드는 환경에만 필요하며, 완성된 ZIP을 설치하는 호스팅에는 Composer와 빌드용 `zip` 확장이 필요하지 않습니다.

## 설치 ZIP 사용

[설치 안내](docs/installation.md)에서 시작하세요. 호스팅의 문서 루트를 `public/`으로 지정할 수 있다면 분리형 ZIP을, Apache에서 포함된 `.htaccess`를 적용하는 고정 웹 루트라면 기존 ZIP을 선택합니다. FTP 또는 파일 관리자로 업로드하고 빈 MariaDB DB와 사용자를 만든 뒤 HTTPS의 `/setup`을 엽니다. 설치 마법사는 일회용 업로드 확인, DB 정보, 사이트 URL, 첫 Owner 계정을 요청하고 설치 후 일회용 복구 코드를 보여 줍니다.

분리형 ZIP은 `_supportk/`를 `public/` 옆의 웹 루트 바깥에 둡니다. 기존 ZIP은 압축 해제 위치를 문서 루트로 사용하고 Apache 규칙으로 `_supportk/` 접근을 차단합니다. 모든 파일을 유지하고 디렉터리 목록을 끄세요. 두 방식 모두 `index.php`로의 URL 재작성 설정이 필요합니다.

## 개발

소스의 웹 문서 루트는 `public/`이며, 저장소 루트에는 애플리케이션과 개발 도구가 있습니다. 개발 Docker 이미지는 `SUPPORT_K_DOCUMENT_ROOT=/var/www/html/public`을 사용합니다. 기존 ZIP의 문서 루트는 압축 해제 위치이고, 분리형 ZIP의 문서 루트는 `public/`입니다.

```sh
docker build -f packaging/dev/Dockerfile -t support-k-dev .
docker run --rm -v support-k-writable:/var/www/html/writable --entrypoint sh support-k-dev -c 'for directory in cache logs session uploads extensions; do mkdir -p /var/www/html/writable/$directory; done; chown -R www-data:www-data /var/www/html/writable'
docker run --rm -p 127.0.0.1:8080:8080 -e SUPPORT_K_HTTP_PORT=8080 -e SUPPORT_K_ALLOW_HTTP=1 -v "$PWD":/var/www/html -v support-k-writable:/var/www/html/writable support-k-dev
```

이름 있는 `writable` 볼륨은 런타임 파일을 소스 트리와 분리합니다. 위 명령처럼 컨테이너의 `www-data` 사용자에게 쓰기 권한을 부여하세요. 설치 마법사를 열기 전 로컬 MariaDB/MySQL DB도 준비해야 합니다.

로컬 소스 체크아웃에서 개발 의존성을 설치하고 테스트를 실행합니다.

```sh
composer install
vendor/bin/phpunit --no-coverage
```

Composer와 CLI는 개발 도구입니다. 완성된 설치 ZIP을 받는 호스팅에는 필요하지 않습니다.

Git 메타데이터가 없는 디렉터리도 소스 감사로 검사할 수 있습니다.

```sh
php packaging/audit-source.php .
```

감사는 `vendor/`, `writable/`, `.git/`, `dist/`, `build/`를 제외하고, 확실도가 높은 비밀값·개인키·배포 자격 증명·로컬 경로·민감 파일·실제 값이 채워진 런타임 설정을 찾습니다. 문서와 합성 테스트의 예시 값은 허용합니다. 감사 통과만으로 릴리스 검토를 대신할 수 없습니다.

## 라이선스와 서드파티 소프트웨어

Support K와 원본 프레임워크의 저작권 표기는 [MIT 라이선스 원문](LICENSE)에 따릅니다. [한국어 참고 번역](LICENSE.ko.md)도 제공하지만 법적 기준은 원문입니다. 런타임·개발 의존성의 버전, 라이선스, 출처는 [서드파티 고지](THIRD_PARTY_NOTICES.md)에 정리했습니다.

보안 문제를 제보할 때는 고객 데이터나 자격 증명을 포함하지 말고 영향받는 버전과 재현 방법을 적어 주세요. [보안 안내](docs/security.md)를 참고하세요.
