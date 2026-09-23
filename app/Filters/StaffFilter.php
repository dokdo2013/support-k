<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class StaffFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $id = $session->get('staff_id');
        $user = $id ? db_connect()->table('users')->where('id', $id)->get()->getRowArray() : null;
        if (!$user || !in_array($user['role'], ['owner', 'agent'], true) || (int) $user['auth_version'] !== (int) $session->get('staff_version')) {
            $session->remove(['staff_id', 'staff_role', 'staff_name', 'staff_version']);
            return redirect()->to(site_url('admin/login'));
        }
        $session->set(['staff_role' => $user['role'], 'staff_name' => $user['display_name']]);
        if (in_array('owner', $arguments ?? [], true) && $user['role'] !== 'owner') return service('response')->setStatusCode(403)->setBody('소유자만 사용할 수 있습니다.');
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
