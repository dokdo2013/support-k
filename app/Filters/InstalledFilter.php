<?php
namespace App\Filters;

use App\Services\Installation\Runtime;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class InstalledFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $path = trim($request->getUri()->getRoutePath(), '/');
        if ($path === 'health' || $path === 'setup' || str_starts_with($path, 'setup/')) return;
        if (!Runtime::installed()) return redirect()->to(site_url('setup'));
        try {
            $state = (new \App\Services\Installation\InstallationStatus(db_connect()))->check(Runtime::read());
        } catch (\Throwable) {
            $state = 'recovery_required';
        }
        if ($state !== 'installed') return service('response')->setStatusCode(503)->setBody(view('setup/repair', ['title' => '설치 상태 확인']));
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
