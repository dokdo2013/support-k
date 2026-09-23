<?php
namespace App\Controllers;

use App\Services\Installation\Installer;
use App\Services\Installation\Runtime;

class Setup extends BaseController
{
    public function index()
    {
        if (Runtime::installed()) return redirect()->to(site_url('admin/login'));
        if (!session()->has('install_proof') || (int) session('install_proof_until') < time()) {
            session()->set(['install_proof' => bin2hex(random_bytes(32)), 'install_proof_until' => time() + 1800]);
            session()->remove('install_verified');
        }
        return view('setup/index', ['title' => '고객센터 설치', 'verified' => session('install_verified') === true, 'baseUrl' => config('App')->baseURL, 'hasMysql' => extension_loaded('mysqli'), 'writable' => is_writable(Runtime::directory())]);
    }
    public function proof()
    {
        if (Runtime::installed() || !session()->has('install_proof') || (int) session('install_proof_until') < time()) return $this->response->setStatusCode(403);
        return $this->response->download('support-k-install-proof.txt', session('install_proof'));
    }
    public function verify()
    {
        if (Runtime::installed()) return redirect()->to(site_url('admin/login'));
        $path = FCPATH . 'support-k-install-proof.txt';
        $expected = session('install_proof');
        if (!is_string($expected) || (int) session('install_proof_until') < time() || !is_file($path) || filesize($path) > 200 || !hash_equals($expected, trim((string) file_get_contents($path)))) return redirect()->to(site_url('setup'))->with('error', '이 화면에서 받은 확인 파일을 index.php와 같은 폴더에 업로드해 주세요.');
        if (!unlink($path)) return redirect()->to(site_url('setup'))->with('error', '확인 파일을 삭제하지 못했습니다. 폴더 권한을 확인해 주세요.');
        session()->regenerate(true);
        session()->set('install_verified', true);
        return redirect()->to(site_url('setup'));
    }
    public function install()
    {
        if (Runtime::installed()) return redirect()->to(site_url('admin/login'));
        if (session('install_verified') !== true || (int) session('install_proof_until') < time()) return redirect()->to(site_url('setup'))->with('error', '먼저 업로드 권한을 확인해 주세요.');
        try {
            $recovery = (new Installer())->install($this->request->getPost());
        } catch (\InvalidArgumentException | \RuntimeException $error) {
            return redirect()->to(site_url('setup'))->with('error', $error->getMessage());
        } catch (\Throwable) {
            return redirect()->to(site_url('setup'))->with('error', '설치가 중단되었습니다. DB 권한과 파일 업로드 상태를 확인한 뒤 같은 설정으로 다시 시도해 주세요.');
        }
        session()->remove(['install_proof', 'install_proof_until', 'install_verified']);
        session()->regenerate(true);
        return view('setup/complete', ['title' => '설치 완료', 'recoveryCode' => $recovery]);
    }
}
