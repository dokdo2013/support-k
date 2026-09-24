한국어 | [English](THIRD_PARTY_NOTICES.en.md)

# 서드파티 고지

Support K는 MIT 라이선스로 배포됩니다. 이 문서는 현재 `composer.lock`에 선택된 서드파티 소프트웨어를 기록합니다. 각 패키지에는 자체 저작권과 라이선스 조건이 적용됩니다. 릴리스를 재배포할 때는 해당 패키지 출처의 전체 고지문도 확인하세요.

## 프레임워크 기반

애플리케이션 기본 구조는 공식 [CodeIgniter 4 appstarter 배포본](https://github.com/codeigniter4/appstarter)에서 가져왔습니다. 현재 lock 파일의 `codeigniter4/framework`는 공식 [CodeIgniter4 저장소](https://github.com/codeigniter4/CodeIgniter4)의 **v4.7.4**, 커밋 `67ead895b7491703e5e5bc17436778806192008f`입니다. 업스트림 appstarter와 CodeIgniter Foundation의 저작권 표기는 `LICENSE`에 유지됩니다. Support K의 애플리케이션 코드는 별도로 관리되며 CodeIgniter Foundation의 보증이나 추천을 뜻하지 않습니다.

## 런타임 의존성

다음 패키지는 `composer.lock`의 `packages`에 있으며 애플리케이션 실행에 포함됩니다.

| 패키지 | 고정 버전 | 라이선스 | 출처 |
| --- | --- | --- | --- |
| `codeigniter4/framework` | `v4.7.4` | MIT | [github.com/codeigniter4/CodeIgniter4](https://github.com/codeigniter4/CodeIgniter4) |
| `laminas/laminas-escaper` | `2.18.0` | BSD-3-Clause | [github.com/laminas/laminas-escaper](https://github.com/laminas/laminas-escaper) |
| `psr/log` | `3.0.2` | MIT | [github.com/php-fig/log](https://github.com/php-fig/log) |

위 버전, 배포 참조, 패키지 메타데이터의 기준은 `composer.lock`입니다. 릴리스 빌더는 해당 릴리스에 사용한 정확한 lock 파일로 의존성 고지를 만들고, 패키지 라이선스가 요구하는 라이선스 파일을 포함해야 합니다.

## 개발 의존성

`packages-dev`는 로컬 테스트에 사용하며 호스팅 설치에는 필요하지 않습니다. 현재 Faker, PHPUnit 10.5, VFS Stream 및 전이 의존성이 있습니다. 고정 버전과 라이선스 선언은 `composer.lock`에 있으며 주요 출처는 [Faker](https://github.com/FakerPHP/Faker), [PHPUnit](https://github.com/sebastianbergmann/phpunit), [VFS Stream](https://github.com/bovigo/vfsStream)입니다. 릴리스 패키지에 개발 전용 의존성을 암묵적으로 포함하지 마세요.

현재 개발 의존성의 라이선스 계열은 MIT와 BSD-3-Clause입니다. 이 요약은 패키지에 포함된 라이선스 파일이나 릴리스별 의존성 감사를 대신하지 않습니다.

## 글꼴·JavaScript·CSS와 기타 자산

현재 기반에는 서드파티 프런트엔드 번들, 글꼴, 아이콘 세트, 원격 자산이 선언되어 있지 않습니다. 이후 자산을 추가할 때는 버전이나 변경되지 않는 출처에 고정하고 재배포 가능성을 검토한 뒤 릴리스 ZIP에 넣기 전에 이 문서에 기록하세요. CDN에서 런타임에 내려받는 방식은 이 고지나 재현 가능한 패키징을 대신하지 않습니다.
