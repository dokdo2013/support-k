<?php
declare(strict_types=1);

namespace App\Services\Accounts;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

final class AccountService
{
    public function __construct(private BaseConnection $db) {}

    public function createOwner(string $email, string $password, string $name): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || mb_strlen($password) < 12 || strlen($password) > 72 || trim($name) === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('올바른 이메일과 이름, 12자 이상·72바이트 이하의 비밀번호를 입력해 주세요.');
        }
        $this->db->table('users')->insert(['email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'display_name' => trim($name), 'role' => 'owner', 'created_at' => gmdate('Y-m-d H:i:s')]);
        $id = (int) $this->db->insertID();
        $code = strtoupper(bin2hex(random_bytes(16)));
        $this->db->table('recovery_codes')->insert(['user_id' => $id, 'code_hash' => password_hash($code, PASSWORD_DEFAULT)]);
        return ['id' => $id, 'recovery_code' => $code];
    }

    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->db->table('users')->where('email', strtolower(trim($email)))->get()->getRowArray();
        // Always hash-check so an unknown email does not skip the expensive operation.
        $hash = $user['password_hash'] ?? '$2y$10$BqysIi8rw1VXoS3ErPVKr.DFkKNQoc9FeXZmnzQdLYhyK0x2JhULq';
        return password_verify($password, $hash) && $user !== null ? $user : null;
    }

    public function recover(string $email, string $code, string $newPassword): bool
    {
        if (mb_strlen($newPassword) < 12 || strlen($newPassword) > 72) {
            throw new InvalidArgumentException('새 비밀번호는 12자 이상·72바이트 이하로 입력해 주세요.');
        }
        $user = $this->db->table('users')->where('email', strtolower(trim($email)))->where('role', 'owner')->get()->getRowArray();
        if (!$user) {
            return false;
        }
        foreach ($this->db->table('recovery_codes')->where('user_id', $user['id'])->where('used_at', null)->get()->getResultArray() as $candidate) {
            if (!password_verify(strtoupper(trim($code)), $candidate['code_hash'])) {
                continue;
            }
            if (!$this->db->transBegin()) return false;
            $this->db->table('recovery_codes')->where('id', $candidate['id'])->where('used_at', null)->update(['used_at' => gmdate('Y-m-d H:i:s')]);
            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();
                return false;
            }
            $this->db->table('users')->where('id', $user['id'])->set('auth_version', 'auth_version + 1', false)->update(['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)]);
            if (!$this->db->transStatus()) {
                $this->db->transRollback();
                return false;
            }
            return $this->db->transCommit();
        }
        return false;
    }
}
