<?php

namespace App\Controllers;

class Contracts extends MYTController
{
    protected $contractModel;
    protected $contractRouteModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key      = $_SERVER['HTTP_API_KEY']  ?? '';
        $this->user_key     = $_SERVER['HTTP_USER_KEY'] ?? '';
        $this->requested_by = $this->user_key;
        $this->_load_essentials();
    }

    public function index()
    {
        if (($response = $this->_api_verification('contracts', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$contracts = $this->contractModel->get_all()) {
            $response = $this->failNotFound('No contracts found.');
        } else {
            $response = $this->respond(['data' => $contracts, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function search()
    {
        if (($response = $this->_api_verification('contracts', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id = $this->request->getVar('customer_id') ?: null;
        $status      = $this->request->getVar('status')      ?: null;

        if (!$contracts = $this->contractModel->search($customer_id, $status)) {
            $response = $this->failNotFound('No contracts found.');
        } else {
            $response = $this->respond(['data' => $contracts, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function details()
    {
        if (($response = $this->_api_verification('contracts', 'details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id = $this->request->getVar('contract_id');

        if (!$contract = $this->contractModel->get_details_by_id($contract_id)) {
            $response = $this->failNotFound('Contract not found.');
        } else {
            // Also get the routes for this contract
            $routes = $this->contractRouteModel->get_by_contract_id($contract_id) ?: [];
            $contract['routes'] = $routes;
            $response = $this->respond(['data' => $contract, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create contract + its routes in one call
     * Expects: customer_id, monthly_rate, included_trips, excess_trip_charge,
     *          fuel_price_per_liter, date_start, date_end
     *          routes[] = array of { origin, destination }
     */
    public function create()
    {
        if (($response = $this->_api_verification('contracts', 'create')) !== true)
            return $response;

        $token = $this->request->getJsonVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_data = [
            'customer_id'               => $this->request->getVar('customer_id'),
            'date_signed'               => $this->request->getVar('date_signed')               ?: null,
            'authorized_representative' => $this->request->getVar('authorized_representative') ?: null,
            'payment_terms'             => $this->request->getVar('payment_terms')             ?: null,
            'monthly_rate'              => $this->request->getVar('monthly_rate'),
            'included_trips'            => $this->request->getVar('included_trips'),
            'excess_trip_charge'        => $this->request->getVar('excess_trip_charge'),
            'fuel_price_per_liter'      => $this->request->getVar('fuel_price_per_liter'),
            'start_date'                => $this->request->getVar('date_start'),
            'end_date'                  => $this->request->getVar('date_end')                  ?: null,
            'status'                    => 'active',
            'remarks'                   => $this->request->getVar('remarks')                   ?: null,
            'added_by'                  => $this->requested_by,
            'added_on'                  => date('Y-m-d H:i:s')
        ];

        $routes = $this->request->getVar('routes') ?: [];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$contract_id = $this->contractModel->insert($contract_data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add contract. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Auto-generate contract number CON-YYYY-XXXX
        $contract_number = 'CON-' . date('Y') . '-' . str_pad($contract_id, 4, '0', STR_PAD_LEFT);
        $this->contractModel->custom_update(
            ['id' => $contract_id],
            ['contract_number' => $contract_number]
        );

        // Insert routes
        foreach ($routes as $route) {
            $route = (array) $route;
            if (empty($route['origin']) || empty($route['destination'])) continue;
            $route_data = [
                'contract_id' => $contract_id,
                'origin'      => $route['origin'],
                'destination' => $route['destination'],
                'distance_km' => $route['distance_km'] ?: null,
                'remarks'     => $route['remarks']     ?: null,
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];

            if (!$this->contractRouteModel->insert($route_data)) {
                $this->db->transRollback();
                $response = $this->fail('Unable to add contract routes. Please try again.');
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            }
        }

        $this->db->transCommit();
        $response = $this->respond(['response' => 'Contract added successfully.']);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update($id = null)
    {
        if (($response = $this->_api_verification('contracts', 'update')) !== true)
            return $response;

        $token = $this->request->getJsonVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id = $this->request->getVar('contract_id');
        $condition   = ['id' => $contract_id, 'is_deleted' => 0];

        $data = [
            'customer_id'               => $this->request->getVar('customer_id'),
            'date_signed'               => $this->request->getVar('date_signed')               ?: null,
            'authorized_representative' => $this->request->getVar('authorized_representative') ?: null,
            'payment_terms'             => $this->request->getVar('payment_terms')             ?: null,
            'monthly_rate'              => $this->request->getVar('monthly_rate'),
            'included_trips'            => $this->request->getVar('included_trips'),
            'excess_trip_charge'        => $this->request->getVar('excess_trip_charge'),
            'fuel_price_per_liter'      => $this->request->getVar('fuel_price_per_liter'),
            'start_date'                => $this->request->getVar('date_start'),
            'end_date'                  => $this->request->getVar('date_end')                  ?: null,
            'status'                    => $this->request->getVar('status'),
            'remarks'                   => $this->request->getVar('remarks')                   ?: null,
            'updated_by'                => $this->requested_by,
            'updated_on'                => date('Y-m-d H:i:s')
        ];

        $routes = $this->request->getVar('routes') ?: [];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->contractModel->custom_update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update contract. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Soft delete existing routes then re-insert
        $this->contractRouteModel->custom_update(
            ['contract_id' => $contract_id, 'is_deleted' => 0],
            ['is_deleted' => 1, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
        );

        foreach ($routes as $route) {
            $route = (array) $route;
            $route_data = [
                'contract_id' => $contract_id,
                'origin'      => $route['origin'],
                'destination' => $route['destination'],
                'distance_km' => $route['distance_km'] ?: null,
                'remarks'     => $route['remarks'] ?: null,
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];

            if (!$this->contractRouteModel->insert($route_data)) {
                $this->db->transRollback();
                $response = $this->fail('Unable to update contract routes. Please try again.');
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            }
        }

        $this->db->transCommit();
        $response = $this->respond(['response' => 'Contract updated successfully.']);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function delete($id = null)
    {
        if (($response = $this->_api_verification('contracts', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id = $this->request->getVar('contract_id');
        $condition   = ['id' => $contract_id, 'is_deleted' => 0];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->contractModel->custom_update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete contract. Please try again.');
        } else {
            // Also soft delete all routes under this contract
            $this->contractRouteModel->custom_update(
                ['contract_id' => $contract_id, 'is_deleted' => 0],
                ['is_deleted' => 1, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
            );
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Contract deleted successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function trip_summary()
    {
        if (($response = $this->_api_verification('contracts', 'trip_summary')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id = $this->request->getVar('contract_id');
        $database    = \Config\Database::connect();

        $contract = $this->contractModel->get_details_by_id($contract_id);
        if (!$contract) {
            $response = $this->failNotFound('Contract not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $trips = $database->query("
            SELECT t.id,
                   t.trip_date,
                   cr.origin,
                   cr.destination,
                   t.is_excess,
                   t.excess_charge,
                   t.fuel_additional_charge
            FROM trip t
            LEFT JOIN contract_route cr
                   ON cr.id = t.contract_route_id AND cr.is_deleted = 0
            WHERE t.contract_id = ?
              AND t.is_deleted  = 0
            ORDER BY t.trip_date DESC
        ", [$contract_id])->getResultArray();

        $total_trips_used = count($trips);
        $included_trips   = (int) ($contract['included_trips'] ?? 0);
        $excess_trips     = max(0, $total_trips_used - $included_trips);

        $response = $this->respond([
            'data' => [
                'included_trips'   => $included_trips,
                'total_trips_used' => $total_trips_used,
                'excess_trips'     => $excess_trips,
                'trips'            => $trips,
            ],
            'status' => 'success',
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function get_suggestions()
{
    if (($response = $this->_api_verification('contracts', 'get_suggestions')) !== true)
        return $response;

    $token = $this->request->getVar('token');
    if (($response = $this->_verify_requester($token)) !== true)
        return $response;

    $keyword = $this->request->getVar('keyword') ?: '';

    $database = \Config\Database::connect();

    $customers = $database->query("
        SELECT DISTINCT c.customer_id AS id,
               cu.first_name AS label
        FROM contract c
        JOIN customer cu ON cu.id = c.customer_id
        WHERE c.is_deleted = 0
          AND (cu.first_name LIKE ? OR cu.last_name LIKE ? OR cu.trade_name LIKE ?)
        LIMIT 10
    ", ["%$keyword%", "%$keyword%", "%$keyword%"])->getResultArray();

    $contracts = $database->query("
        SELECT id, contract_number AS label
        FROM contract
        WHERE is_deleted = 0
          AND contract_number LIKE ?
        LIMIT 10
    ", ["%$keyword%"])->getResultArray();

    $response = $this->respond([
        'data'   => [
            'customers' => $customers,
            'contracts' => $contracts,
        ],
        'status' => 'success'
    ]);

    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
    return $response;
}

    protected function _load_essentials()
    {
        $this->contractModel       = model('App\Models\Contract');
        $this->contractRouteModel  = model('App\Models\Contract_route');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}