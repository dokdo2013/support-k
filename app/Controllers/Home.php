<?php
namespace App\Controllers;
use App\Services\Installation\Runtime;
class Home extends BaseController
{
    public function index() { return view('home', ['title' => '고객센터']); }
    public function health()
    {
        $state = 'setup_required';
        if (Runtime::installed()) {
            try {
                $state = (new \App\Services\Installation\InstallationStatus(db_connect()))->check(Runtime::read());
            } catch (\Throwable) {
                $state = 'recovery_required';
            }
        }
        return $this->response->setStatusCode($state === 'recovery_required' ? 503 : 200)->setJSON(['status' => $state]);
    }
}
