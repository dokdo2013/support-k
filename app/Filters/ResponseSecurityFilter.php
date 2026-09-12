<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ResponseSecurityFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null) {}
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'no-store');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('Referrer-Policy', 'same-origin');
        $response->setHeader('Content-Security-Policy', "default-src 'self'; style-src 'self'; img-src 'self' data:; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    }
}
