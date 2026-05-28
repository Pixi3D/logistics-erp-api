<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->options('(:any)', static function () {
    return response()->setStatusCode(200);
});

// LOGIN
$routes->post('login', 'Login::index');

// TRUCKS
$routes->get('trucks/index',   'Trucks::index');
$routes->get('trucks/search',  'Trucks::search');
$routes->get('trucks/details', 'Trucks::details');
$routes->post('trucks/create', 'Trucks::create');
$routes->post('trucks/update', 'Trucks::update');
$routes->post('trucks/delete', 'Trucks::delete');
$routes->get('trucks/get_attachments', 'Trucks::get_attachments');
$routes->get('trucks/download_attachment', 'Trucks::download_attachment');
$routes->post('trucks/delete_attachment', 'Trucks::delete_attachment');
$routes->get('trucks/get_suggestions', 'Trucks::get_suggestions');

// DRIVERS
$routes->get('drivers/index',   'Drivers::index');
$routes->get('drivers/search',  'Drivers::search');
$routes->get('drivers/details', 'Drivers::details');
$routes->post('drivers/create', 'Drivers::create');
$routes->post('drivers/update', 'Drivers::update');
$routes->post('drivers/delete', 'Drivers::delete');
$routes->get('drivers/get_attachments',     'Drivers::get_attachments');
$routes->get('drivers/download_attachment', 'Drivers::download_attachment');
$routes->post('drivers/delete_attachment',  'Drivers::delete_attachment');
$routes->get('drivers/get_suggestions', 'Drivers::get_suggestions');

// HELPERS
$routes->get('helpers/index',   'Helpers::index');
$routes->get('helpers/search',  'Helpers::search');
$routes->get('helpers/details', 'Helpers::details');
$routes->post('helpers/create', 'Helpers::create');
$routes->post('helpers/update', 'Helpers::update');
$routes->post('helpers/delete', 'Helpers::delete');
$routes->get('helpers/get_attachments',     'Helpers::get_attachments');
$routes->get('helpers/download_attachment', 'Helpers::download_attachment');
$routes->post('helpers/delete_attachment',  'Helpers::delete_attachment');
$routes->get('helpers/get_suggestions', 'Helpers::get_suggestions');

// CUSTOMERS
$routes->get('customers/index',   'Customers::index');
$routes->get('customers/search',  'Customers::search');
$routes->get('customers/details', 'Customers::details');
$routes->post('customers/create', 'Customers::create');
$routes->post('customers/update', 'Customers::update');
$routes->post('customers/delete', 'Customers::delete');
$routes->get('customers/get_suggestions', 'Customers::get_suggestions');
$routes->get('customers/get_contacts',    'Customers::get_contacts');

// CONTRACTS
$routes->get('contracts/index',   'Contracts::index');
$routes->get('contracts/search',  'Contracts::search');
$routes->get('contracts/details', 'Contracts::details');
$routes->post('contracts/create', 'Contracts::create');
$routes->post('contracts/update', 'Contracts::update');
$routes->post('contracts/delete', 'Contracts::delete');
$routes->get('contracts/get_suggestions', 'Contracts::get_suggestions');
$routes->get('contracts/trip_summary',    'Contracts::trip_summary');

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
$routes->get('trips/get_contract_trip_info', 'Trips::get_contract_trip_info');
$routes->get('trips/get_suggestions', 'Trips::get_suggestions');
$routes->post('trucks/update_status', 'Trucks::update_status');
$routes->post('trips/complete', 'Trips::complete');

// USERS
$routes->get('users/index',   'Users::index');
$routes->post('users/create', 'Users::create');
$routes->post('users/update', 'Users::update');
$routes->post('users/delete', 'Users::delete');

// TRAIL
$routes->get('trail/index', 'Trail::index');