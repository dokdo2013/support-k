<?php
/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('/', 'Home::index');
$routes->get('health', 'Home::health');
$routes->get('setup', 'Setup::index');
$routes->get('setup/proof', 'Setup::proof');
$routes->post('setup/verify', 'Setup::verify');
$routes->post('setup/install', 'Setup::install');
$routes->get('knowledge', 'Customer\Knowledge::index');
$routes->get('knowledge/(:num)', 'Customer\Knowledge::show/$1');
$routes->get('tickets/new', 'Customer\Tickets::newTicket');
$routes->post('tickets', 'Customer\Tickets::create');
$routes->get('tickets/lookup', 'Customer\Tickets::lookupForm');
$routes->post('tickets/lookup', 'Customer\Tickets::lookup');
$routes->get('tickets/(:segment)', 'Customer\Tickets::show/$1');
$routes->post('tickets/(:segment)/replies', 'Customer\Tickets::reply/$1');
$routes->get('admin/login', 'Admin\Auth::login');
$routes->post('admin/login', 'Admin\Auth::authenticate');
$routes->get('admin/recovery', 'Admin\Auth::recovery');
$routes->post('admin/recovery', 'Admin\Auth::recover');
$routes->post('admin/logout', 'Admin\Auth::logout', ['filter' => 'staff']);
$routes->group('admin', ['filter' => 'staff'], static function ($routes) {
    $routes->get('/', 'Admin\Tickets::index');
    $routes->get('tickets', 'Admin\Tickets::index');
    $routes->get('tickets/(:num)', 'Admin\Tickets::show/$1');
    $routes->post('tickets/(:num)/replies', 'Admin\Tickets::reply/$1');
    $routes->post('tickets/(:num)/notes', 'Admin\Tickets::note/$1');
    $routes->post('tickets/(:num)/status', 'Admin\Tickets::status/$1');
    $routes->get('knowledge', 'Admin\Knowledge::index');
    $routes->get('knowledge/new', 'Admin\Knowledge::newForm');
    $routes->get('knowledge/(:num)', 'Admin\Knowledge::edit/$1');
    $routes->post('knowledge', 'Admin\Knowledge::create');
    $routes->post('knowledge/(:num)', 'Admin\Knowledge::update/$1');
    $routes->post('knowledge/(:num)/delete', 'Admin\Knowledge::delete/$1');
});
