<?php
namespace Config;

class Filters extends \CodeIgniter\Config\Filters
{
    public array $aliases = [
        'csrf' => \CodeIgniter\Filters\CSRF::class,
        'invalidchars' => \CodeIgniter\Filters\InvalidChars::class,
        'installed' => \App\Filters\InstalledFilter::class,
        'staff' => \App\Filters\StaffFilter::class,
        'security' => \App\Filters\ResponseSecurityFilter::class,
    ];
    public array $required = ['before' => [], 'after' => ['security']];
    public array $globals = ['before' => ['installed', 'invalidchars', 'csrf'], 'after' => []];
    public array $methods = [];
    public array $filters = [];
}
