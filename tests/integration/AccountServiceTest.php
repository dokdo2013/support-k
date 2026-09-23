<?php

declare(strict_types=1);

use App\Services\Accounts\AccountService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class AccountServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;

    public function testCreateOwnerHashesPasswordAndRecoveryCodeAndNormalizesEmail(): void
    {
        $created = $this->service()->createOwner('  OWNER@Example.Test  ', 'owner-password-123', ' 운영자 ');
        $user = $this->db->table('users')->where('id', $created['id'])->get()->getRowArray();
        $code = $this->db->table('recovery_codes')->where('user_id', $created['id'])->get()->getRowArray();

        $this->assertSame('owner@example.test', $user['email']);
        $this->assertSame('운영자', $user['display_name']);
        $this->assertSame('owner', $user['role']);
        $this->assertNotSame('owner-password-123', $user['password_hash']);
        $this->assertTrue(password_verify('owner-password-123', $user['password_hash']));
        $this->assertNotSame($created['recovery_code'], $code['code_hash']);
        $this->assertTrue(password_verify($created['recovery_code'], $code['code_hash']));
        $this->assertNull($code['used_at']);
    }

    public function testAuthenticationNormalizesEmailAndRejectsWrongPassword(): void
    {
        $created = $this->service()->createOwner('owner@example.test', 'owner-password-123', '운영자');

        $authenticated = $this->service()->authenticate(' OWNER@EXAMPLE.TEST ', 'owner-password-123');

        $this->assertNotNull($authenticated);
        $this->assertSame($created['id'], (int) $authenticated['id']);
        $this->assertNull($this->service()->authenticate('owner@example.test', 'wrong-password'));
        $this->assertNull($this->service()->authenticate('unknown@example.test', 'owner-password-123'));
    }

    public function testInvalidInputAndFailedRecoveryHaveNoDatabaseSideEffects(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->service()->createOwner('not-an-email', 'too-short', '');
        } finally {
            $this->assertSame(0, $this->db->table('users')->countAllResults());
            $this->assertSame(0, $this->db->table('recovery_codes')->countAllResults());
        }
    }

    public function testCreateOwnerRejectsPasswordOverSeventyTwoBytesWithoutDatabaseSideEffects(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->service()->createOwner('owner@example.test', str_repeat('a', 73), '운영자');
        } finally {
            $this->assertSame(0, $this->db->table('users')->countAllResults());
            $this->assertSame(0, $this->db->table('recovery_codes')->countAllResults());
        }
    }

    public function testFailedRecoveryDoesNotConsumeCodeOrChangeCredentials(): void
    {
        $created = $this->service()->createOwner('owner@example.test', 'owner-password-123', '운영자');
        $before = $this->db->table('users')->where('id', $created['id'])->get()->getRowArray();

        $this->assertFalse($this->service()->recover('owner@example.test', 'wrong-code', 'replacement-password-123'));

        $after = $this->db->table('users')->where('id', $created['id'])->get()->getRowArray();
        $code = $this->db->table('recovery_codes')->where('user_id', $created['id'])->get()->getRowArray();
        $this->assertSame($before['password_hash'], $after['password_hash']);
        $this->assertSame((int) $before['auth_version'], (int) $after['auth_version']);
        $this->assertNull($code['used_at']);
    }

    public function testInvalidReplacementPasswordDoesNotConsumeValidRecoveryCode(): void
    {
        $created = $this->service()->createOwner('owner@example.test', 'owner-password-123', '운영자');

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->service()->recover('owner@example.test', $created['recovery_code'], 'too-short');
        } finally {
            $user = $this->db->table('users')->where('id', $created['id'])->get()->getRowArray();
            $code = $this->db->table('recovery_codes')->where('user_id', $created['id'])->get()->getRowArray();
            $this->assertSame(1, (int) $user['auth_version']);
            $this->assertTrue(password_verify('owner-password-123', $user['password_hash']));
            $this->assertNull($code['used_at']);
        }
    }

    public function testRecoveryRejectsPasswordOverSeventyTwoBytesWithoutDatabaseSideEffects(): void
    {
        $created = $this->service()->createOwner('owner@example.test', 'owner-password-123', '운영자');
        $before = $this->db->table('users')->where('id', $created['id'])->get()->getRowArray();

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->service()->recover('owner@example.test', $created['recovery_code'], str_repeat('a', 73));
        } finally {
            $after = $this->db->table('users')->where('id', $created['id'])->get()->getRowArray();
            $code = $this->db->table('recovery_codes')->where('user_id', $created['id'])->get()->getRowArray();
            $this->assertSame($before['password_hash'], $after['password_hash']);
            $this->assertSame((int) $before['auth_version'], (int) $after['auth_version']);
            $this->assertNull($code['used_at']);
        }
    }

    public function testSuccessfulRecoveryRotatesPasswordAndAuthVersionAndConsumesCode(): void
    {
        $created = $this->service()->createOwner('owner@example.test', 'owner-password-123', '운영자');
        $this->assertTrue($this->service()->recover(' OWNER@EXAMPLE.TEST ', $created['recovery_code'], 'replacement-password-123'));

        $user = $this->db->table('users')->where('id', $created['id'])->get()->getRowArray();
        $code = $this->db->table('recovery_codes')->where('user_id', $created['id'])->get()->getRowArray();
        $this->assertSame(2, (int) $user['auth_version']);
        $this->assertNotNull($code['used_at']);
        $this->assertNull($this->service()->authenticate('owner@example.test', 'owner-password-123'));
        $this->assertNotNull($this->service()->authenticate('owner@example.test', 'replacement-password-123'));
        $this->assertFalse($this->service()->recover('owner@example.test', $created['recovery_code'], 'another-password-123'));
    }

    private function service(): AccountService
    {
        return new AccountService($this->db);
    }
}
