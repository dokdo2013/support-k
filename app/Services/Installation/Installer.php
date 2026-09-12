<?php
declare(strict_types=1);

namespace App\Services\Installation;

use App\Services\Accounts\AccountService;
use CodeIgniter\Database\Config as Database;
use Config\Migrations;
use CodeIgniter\Database\MigrationRunner;
use InvalidArgumentException;
use RuntimeException;

final class Installer
{
    public function install(array $input): ?string
    {
        if (Runtime::installed()) {
            throw new RuntimeException('이미 설치되었습니다. 다시 설치할 수 없습니다.');
        }
        $base = trim((string) ($input['base_url'] ?? ''));
        $url = parse_url($base);
        if (!filter_var($base, FILTER_VALIDATE_URL) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !in_array($url['scheme'] ?? '', ['http', 'https'], true)) {
            throw new InvalidArgumentException('올바른 설치 주소를 입력해 주세요.');
        }
        if (($url['scheme'] ?? '') !== 'https' && !in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true)) {
            throw new InvalidArgumentException('공개 고객센터는 HTTPS 주소를 사용해 주세요.');
        }
        $base = rtrim($base, '/') . '/';
        $prefix = (string) ($input['db_prefix'] ?? 'sk_');
        if (!preg_match('/^[a-z][a-z0-9_]{0,19}_$/D', $prefix)) {
            throw new InvalidArgumentException('테이블 접두사는 영문 소문자로 시작하고 밑줄로 끝나는 2~21자여야 합니다.');
        }
        $site = trim((string) ($input['site_name'] ?? ''));
        if ($site === '' || mb_strlen($site) > 100) {
            throw new InvalidArgumentException('고객센터 이름을 100자 이내로 입력해 주세요.');
        }
        $password = (string) ($input['owner_password'] ?? '');
        if (!filter_var($input['owner_email'] ?? '', FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12 || strlen($password) > 72) {
            throw new InvalidArgumentException('관리자 이메일과 12자 이상·72바이트 이하의 비밀번호를 입력해 주세요.');
        }
        if (!extension_loaded('mysqli')) {
            throw new RuntimeException('MySQL 연결에 필요한 mysqli 확장이 없습니다.');
        }
        $database = array_replace(config('Database')->default, [
            'hostname' => trim((string) ($input['db_host'] ?? 'localhost')),
            'port' => (int) ($input['db_port'] ?? 3306),
            'database' => trim((string) ($input['db_name'] ?? '')),
            'username' => trim((string) ($input['db_user'] ?? '')),
            'password' => (string) ($input['db_password'] ?? ''),
            'DBDriver' => 'MySQLi', 'DBPrefix' => $prefix, 'DBDebug' => false,
        ]);
        if ($database['database'] === '' || $database['username'] === '' || $database['port'] < 1 || $database['port'] > 65535) {
            throw new InvalidArgumentException('DB 이름, 사용자와 포트를 확인해 주세요.');
        }
        $lock = fopen(Runtime::directory() . 'install.mutex', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('설치가 진행 중입니다. 잠시 뒤 다시 시도해 주세요.');
        }
        try {
            if (Runtime::installed()) {
                throw new RuntimeException('이미 설치되었습니다.');
            }
            (new ProtectionCheck())->verify($base);
            $db = Database::connect($database, false);
            try {
                $db->initialize();
            } catch (\Throwable) {
                throw new RuntimeException('DB에 연결하지 못했습니다. 호스트·사용자·비밀번호·권한을 확인해 주세요.');
            }
            $engine = $db->query('SELECT @@default_storage_engine AS engine')->getRowArray();
            if (strtolower((string) ($engine['engine'] ?? '')) !== 'innodb') {
                throw new RuntimeException('안정적인 저장을 위해 InnoDB 기본 저장 엔진이 필요합니다. DB 설정을 확인해 주세요.');
            }
            $existing = Runtime::read();
            if ($existing && ($existing['database'] ?? []) !== $database) {
                throw new RuntimeException('이전 설치가 중단되었습니다. 같은 DB 설정으로 재개해 주세요.');
            }
            if (!$existing) {
                foreach ($db->listTables() as $table) {
                    if (str_starts_with($table, $prefix)) {
                        throw new RuntimeException('같은 접두사의 테이블이 있습니다. 덮어쓰지 않도록 다른 접두사를 선택해 주세요.');
                    }
                }
                $existing = ['format' => 1, 'installed' => false, 'schema_version' => Runtime::SCHEMA_VERSION, 'installation_id' => bin2hex(random_bytes(16)), 'base_url' => $base, 'site_name' => $site, 'database' => $database, 'key' => bin2hex(random_bytes(32))];
                Runtime::write($existing);
            }
            // DDL progress is recorded by the framework; it is not a rollbackable transaction.
            $migration = new MigrationRunner(new Migrations(), $db);
            $migration->setNamespace('App');
            if (!$migration->latest()) {
                throw new RuntimeException('DB 준비가 중단되었습니다. 설정을 유지한 채 다시 시도해 주세요.');
            }
            $recovery = null;
            $state = $db->table('installation_state')->where('id', $existing['installation_id'])->get()->getRowArray();
            if (!$state) {
                if ($db->table('users')->countAllResults() > 0) {
                    throw new RuntimeException('기존 계정이 발견되어 설치를 중단했습니다. 백업과 설치 상태를 확인해 주세요.');
                }
                if (!$db->transBegin()) throw new RuntimeException('DB 저장 작업을 시작할 수 없습니다. 다시 시도해 주세요.');
                try {
                    $owner = (new AccountService($db))->createOwner((string) $input['owner_email'], $password, '소유자');
                    $db->table('settings')->insert(['name' => 'site_name', 'value' => $site]);
                    $db->table('installation_state')->insert(['id' => $existing['installation_id'], 'status' => 'complete', 'schema_version' => Runtime::SCHEMA_VERSION, 'created_at' => gmdate('Y-m-d H:i:s')]);
                    if (!$db->transStatus()) throw new RuntimeException('설치 정보 저장에 실패했습니다.');
                    if (!$db->transCommit()) throw new RuntimeException('DB 저장에 실패했습니다. 같은 설정으로 다시 시도해 주세요.');
                    $recovery = $owner['recovery_code'];
                } catch (\Throwable $error) {
                    $db->transRollback();
                    throw $error;
                }
            } elseif ($state['status'] !== 'complete' || (int) $state['schema_version'] !== Runtime::SCHEMA_VERSION) {
                throw new RuntimeException('설치 상태가 완료되지 않았습니다. DB와 설치 설정을 확인해 주세요.');
            }
            $existing['installed'] = true;
            Runtime::write($existing);
            file_put_contents(Runtime::directory() . 'installed.lock', $existing['installation_id'], LOCK_EX);
            return $recovery;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
