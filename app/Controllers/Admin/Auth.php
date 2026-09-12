<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Accounts\AccountService;
use App\Services\Accounts\RateLimiter;

class Auth extends BaseController
{
    public function login() { return view('admin/auth/login', ['title' => '운영자 로그인']); }
    private function allowed(): bool
    {
        $limiter = new RateLimiter();
        return $limiter->allow('staff-ip:' . $this->request->getIPAddress(), 30) && $limiter->allow('staff-email:' . strtolower(trim(substr((string) $this->request->getPost('email'), 0, 254))), 8);
    }
    public function authenticate()
    {
        if (!$this->allowed()) return $this->response->setStatusCode(429)->setBody('시도가 많습니다. 5분 뒤 다시 시도해 주세요.');
        $user = (new AccountService(db_connect()))->authenticate((string) $this->request->getPost('email'), (string) $this->request->getPost('password'));
        if (!$user) return redirect()->to(site_url('admin/login'))->with('error', '이메일 또는 비밀번호를 확인해 주세요.');
        session()->regenerate(true);
        session()->set(['staff_id' => (int) $user['id'], 'staff_role' => $user['role'], 'staff_name' => $user['display_name'], 'staff_version' => (int) $user['auth_version']]);
        return redirect()->to(site_url('admin/tickets'));
    }
    public function logout()
    {
        session()->destroy();
        return redirect()->to(site_url('admin/login'));
    }
    public function recovery() { return view('admin/auth/recovery', ['title' => '계정 복구']); }
    public function recover()
    {
        if (!$this->allowed()) return $this->response->setStatusCode(429)->setBody('시도가 많습니다. 5분 뒤 다시 시도해 주세요.');
        try {
            $success = (new AccountService(db_connect()))->recover((string) $this->request->getPost('email'), (string) $this->request->getPost('code'), (string) $this->request->getPost('password'));
        } catch (\InvalidArgumentException $error) {
            return redirect()->to(site_url('admin/recovery'))->with('error', $error->getMessage());
        }
        if (!$success) return redirect()->to(site_url('admin/recovery'))->with('error', '이메일과 사용하지 않은 복구 코드를 확인해 주세요.');
        session()->destroy();
        return redirect()->to(site_url('admin/login'))->with('message', '비밀번호를 변경했습니다. 복구 코드는 사용 처리되었습니다.');
    }
}
