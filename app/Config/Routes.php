<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// LOGIN
$routes->post('login', 'Login::index');

// TRUCKS
$routes->get('trucks/index',   'Trucks::index');
$routes->get('trucks/search',  'Trucks::search');
$routes->get('trucks/details', 'Trucks::details');
$routes->post('trucks/create', 'Trucks::create');
$routes->post('trucks/update', 'Trucks::update');
$routes->post('trucks/delete', 'Trucks::delete');

// DRIVERS
$routes->get('drivers/index',   'Drivers::index');
$routes->get('drivers/search',  'Drivers::search');
$routes->get('drivers/details', 'Drivers::details');
$routes->post('drivers/create', 'Drivers::create');
$routes->post('drivers/update', 'Drivers::update');
$routes->post('drivers/delete', 'Drivers::delete');

// HELPERS
$routes->get('helpers/index',   'Helpers::index');
$routes->get('helpers/search',  'Helpers::search');
$routes->get('helpers/details', 'Helpers::details');
$routes->post('helpers/create', 'Helpers::create');
$routes->post('helpers/update', 'Helpers::update');
$routes->post('helpers/delete', 'Helpers::delete');

// CUSTOMERS
$routes->get('customers/index',   'Customers::index');
$routes->get('customers/search',  'Customers::search');
$routes->get('customers/details', 'Customers::details');
$routes->post('customers/create', 'Customers::create');
$routes->post('customers/update', 'Customers::update');
$routes->post('customers/delete', 'Customers::delete');

// CONTRACTS
$routes->get('contracts/index',   'Contracts::index');
$routes->get('contracts/search',  'Contracts::search');
$routes->get('contracts/details', 'Contracts::details');
$routes->post('contracts/create', 'Contracts::create');
$routes->post('contracts/update', 'Contracts::update');
$routes->post('contracts/delete', 'Contracts::delete');

// CONTRACT ROUTES
$routes->get('contract_routes/index',   'Contract_routes::index');
$routes->post('contract_routes/create', 'Contract_routes::create');
$routes->post('contract_routes/update', 'Contract_routes::update');
$routes->post('contract_routes/delete', 'Contract_routes::delete');

// TRIPS
$routes->get('trips/index',           'Trips::index');
$routes->get('trips/search',          'Trips::search');
$routes->get('trips/details',         'Trips::details');
$routes->post('trips/create',         'Trips::create');
$routes->post('trips/update',         'Trips::update');
$routes->post('trips/delete',         'Trips::delete');
$routes->get('trips/compute_billing', 'Trips::compute_billing');