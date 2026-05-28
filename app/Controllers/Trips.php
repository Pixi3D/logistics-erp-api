<?php

namespace App\Controllers;

class Trips extends MYTController
{
    protected $tripModel;
    protected $tripDriverModel;
    protected $tripHelperModel;
    protected $contractModel;
    protected $contractRouteModel;
    protected $truckModel;
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
        if (($response = $this->_api_verification('trips', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$trips = $this->tripModel->get_all()) {
            $response = $this->failNotFound('No trips found.');
        } else {
            $response = $this->respond(['data' => $trips, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function search()
    {
        if (($response = $this->_api_verification('trips', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $filters = [
            'customer_id' => $this->request->getVar('customer_id') ?: null,
            'contract_id' => $this->request->getVar('contract_id') ?: null,
            'truck_id'    => $this->request->getVar('truck_id')    ?: null,
            'driver_id'   => $this->request->getVar('driver_id')   ?: null,
            'helper_id'   => $this->request->getVar('helper_id')   ?: null,
            'date_from'   => $this->request->getVar('date_from')   ?: null,
            'date_to'     => $this->request->getVar('date_to')     ?: null,
        ];

        if (!$trips = $this->tripModel->search($filters)) {
            $response = $this->failNotFound('No trips found.');
        } else {
            $response = $this->respond(['data' => $trips, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function details()
    {
        if (($response = $this->_api_verification('trips', 'details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $trip_id = $this->request->getVar('trip_id');

        if (!$trip = $this->tripModel->get_details_by_id($trip_id)) {
            $response = $this->failNotFound('Trip not found.');
        } else {
            $trip['driver'] = $this->tripDriverModel->get_by_trip_id($trip_id)[0] ?? null;
            $trip['helper'] = $this->tripHelperModel->get_by_trip_id($trip_id)[0] ?? null;
            $response = $this->respond(['data' => $trip, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get contract info needed for the log trip form:
     * - agreed fuel price
     * - trips used this month
     * - included trips
     * Expects: contract_id, trip_date (Y-m-d)
     */
    public function get_contract_trip_info()
    {
        if (($response = $this->_api_verification('trips', 'get_contract_trip_info')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id = $this->request->getVar('contract_id');
        $trip_date   = $this->request->getVar('trip_date') ?: date('Y-m-d');

        if (!$contract = $this->contractModel->get_details_by_id($contract_id)) {
            $response = $this->failNotFound('Contract not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $month_start      = date('Y-m-01', strtotime($trip_date));
        $month_end        = date('Y-m-t',  strtotime($trip_date));
        $trips_this_month = $this->tripModel->count_trips_by_contract_and_month($contract_id, $month_start, $month_end);
        $included_trips   = (int) $contract['included_trips'];
        $next_is_excess   = $trips_this_month >= $included_trips;

        $response = $this->respond([
            'data' => [
                'contract_id'        => $contract_id,
                'included_trips'     => $included_trips,
                'trips_this_month'   => $trips_this_month,
                'remaining_trips'    => max(0, $included_trips - $trips_this_month),
                'next_is_excess'     => $next_is_excess,
                'excess_trip_charge' => $contract['excess_trip_charge'],
                'fuel_price_per_liter' => $contract['fuel_price_per_liter'],
            ],
            'status' => 'success'
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function create()
    {
        if (($response = $this->_api_verification('trips', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id       = $this->request->getVar('contract_id');
        $contract_route_id = $this->request->getVar('contract_route_id');
        $truck_id          = $this->request->getVar('truck_id');
        $trip_date         = $this->request->getVar('trip_date');
        $driver_id         = $this->request->getVar('driver_id');
        $helper_id         = $this->request->getVar('helper_id') ?: null;
        $actual_fuel_price = (float) ($this->request->getVar('actual_fuel_price') ?? 0);

        if (!$driver_id) {
            $response = $this->fail('Driver is required.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        if (!$contract = $this->contractModel->get_details_by_id($contract_id)) {
            $response = $this->failNotFound('Contract not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        if (!$route = $this->contractRouteModel->select('', ['id' => $contract_route_id, 'contract_id' => $contract_id, 'is_deleted' => 0], 1)) {
            $response = $this->fail('Route does not belong to this contract.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        if (!$truck = $this->truckModel->select('', ['id' => $truck_id, 'is_deleted' => 0], 1)) {
            $response = $this->failNotFound('Truck not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Excess trip computation
        $month_start      = date('Y-m-01', strtotime($trip_date));
        $month_end        = date('Y-m-t',  strtotime($trip_date));
        $trips_this_month = $this->tripModel->count_trips_by_contract_and_month($contract_id, $month_start, $month_end);
        $included_trips   = (int) $contract['included_trips'];
        $is_excess        = ($trips_this_month >= $included_trips) ? 1 : 0;
        $excess_charge    = $is_excess ? (float) $contract['excess_trip_charge'] : 0.00;

        // Fuel additional charge computation
        $agreed_fuel_price      = (float) $contract['fuel_price_per_liter'];
        $distance_km            = (float) $route['distance_km'];
        $km_per_liter           = (float) $truck['km_per_liter'];
        $fuel_additional_charge = 0.00;

        if ($km_per_liter > 0 && $distance_km > 0 && $actual_fuel_price > $agreed_fuel_price) {
            $liters_needed          = $distance_km / $km_per_liter;
            $fuel_additional_charge = round(($actual_fuel_price - $agreed_fuel_price) * $liters_needed, 2);
        }

        $trip_data = [
            'contract_id'            => $contract_id,
            'contract_route_id'      => $contract_route_id,
            'truck_id'               => $truck_id,
            'trip_date'              => $trip_date,
            'is_excess'              => $is_excess,
            'excess_charge'          => $excess_charge,
            'actual_fuel_price'      => $actual_fuel_price,
            'fuel_additional_charge' => $fuel_additional_charge,
            'remarks'                => $this->request->getVar('remarks') ?: null,
            'added_by'               => $this->requested_by,
            'added_on'               => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$trip_id = $this->tripModel->insert($trip_data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to record trip. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Insert driver (single)
        if (!$this->tripDriverModel->insert([
            'trip_id'   => $trip_id,
            'driver_id' => $driver_id,
            'added_by'  => $this->requested_by,
            'added_on'  => date('Y-m-d H:i:s')
        ])) {
            $this->db->transRollback();
            $response = $this->fail('Unable to assign driver to trip. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Insert helper (optional)
        if ($helper_id) {
            if (!$this->tripHelperModel->insert([
                'trip_id'   => $trip_id,
                'helper_id' => $helper_id,
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s')
            ])) {
                $this->db->transRollback();
                $response = $this->fail('Unable to assign helper to trip. Please try again.');
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            }
        }

        // Set truck to dispatched
        $this->truckModel->custom_update(
            ['id' => $truck_id, 'is_deleted' => 0],
            ['status' => 'dispatched', 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
        );

        $this->db->transCommit();
        $response = $this->respond([
            'response'               => 'Trip recorded successfully.',
            'status'                 => 'success',
            'is_excess'              => (bool) $is_excess,
            'excess_charge'          => $excess_charge,
            'fuel_additional_charge' => $fuel_additional_charge,
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update($id = null)
    {
        if (($response = $this->_api_verification('trips', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $trip_id           = $this->request->getVar('trip_id');
        $driver_id         = $this->request->getVar('driver_id');
        $helper_id         = $this->request->getVar('helper_id') ?: null;
        $actual_fuel_price = (float) ($this->request->getVar('actual_fuel_price') ?? 0);
        $contract_route_id = $this->request->getVar('contract_route_id');
        $truck_id          = $this->request->getVar('truck_id');
        $trip_date         = $this->request->getVar('trip_date');

        if (!$driver_id) {
            $response = $this->fail('Driver is required.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        if (!$trip = $this->tripModel->get_details_by_id($trip_id)) {
            $response = $this->failNotFound('Trip not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        if (!$route = $this->contractRouteModel->select('', ['id' => $contract_route_id, 'is_deleted' => 0], 1)) {
            $response = $this->fail('Route not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        if (!$truck = $this->truckModel->select('', ['id' => $truck_id, 'is_deleted' => 0], 1)) {
            $response = $this->failNotFound('Truck not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Recompute fuel additional charge
        $agreed_fuel_price      = (float) $trip['agreed_fuel_price'];
        $distance_km            = (float) $route['distance_km'];
        $km_per_liter           = (float) $truck['km_per_liter'];
        $fuel_additional_charge = 0.00;

        if ($km_per_liter > 0 && $distance_km > 0 && $actual_fuel_price > $agreed_fuel_price) {
            $liters_needed          = $distance_km / $km_per_liter;
            $fuel_additional_charge = round(($actual_fuel_price - $agreed_fuel_price) * $liters_needed, 2);
        }

        $data = [
            'contract_route_id'      => $contract_route_id,
            'truck_id'               => $truck_id,
            'trip_date'              => $trip_date,
            'actual_fuel_price'      => $actual_fuel_price,
            'fuel_additional_charge' => $fuel_additional_charge,
            'remarks'                => $this->request->getVar('remarks') ?: null,
            'updated_by'             => $this->requested_by,
            'updated_on'             => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->tripModel->custom_update(['id' => $trip_id, 'is_deleted' => 0], $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update trip. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Sync driver — soft delete old, insert new
        $this->tripDriverModel->custom_update(
            ['trip_id' => $trip_id, 'is_deleted' => 0],
            ['is_deleted' => 1, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
        );
        if (!$this->tripDriverModel->insert([
            'trip_id'   => $trip_id,
            'driver_id' => $driver_id,
            'added_by'  => $this->requested_by,
            'added_on'  => date('Y-m-d H:i:s')
        ])) {
            $this->db->transRollback();
            $response = $this->fail('Unable to assign driver to trip. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Sync helper
        $this->tripHelperModel->custom_update(
            ['trip_id' => $trip_id, 'is_deleted' => 0],
            ['is_deleted' => 1, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
        );
        if ($helper_id) {
            if (!$this->tripHelperModel->insert([
                'trip_id'   => $trip_id,
                'helper_id' => $helper_id,
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s')
            ])) {
                $this->db->transRollback();
                $response = $this->fail('Unable to assign helper to trip. Please try again.');
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            }
        }

        $this->db->transCommit();
        $response = $this->respond([
            'response'               => 'Trip updated successfully.',
            'status'                 => 'success',
            'fuel_additional_charge' => $fuel_additional_charge,
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function delete($id = null)
    {
        if (($response = $this->_api_verification('trips', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $trip_id   = $this->request->getVar('trip_id');
        $condition = ['id' => $trip_id, 'is_deleted' => 0];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->tripModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Trip not found.');
        } elseif (!$this->tripModel->custom_update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete trip. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Trip deleted successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function compute_billing()
    {
        if (($response = $this->_api_verification('trips', 'compute_billing')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id = $this->request->getVar('contract_id');
        $month       = $this->request->getVar('month');

        $month_start = $month . '-01';
        $month_end   = date('Y-m-t', strtotime($month_start));

        if (!$contract = $this->contractModel->get_details_by_id($contract_id)) {
            $response = $this->failNotFound('Contract not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $total_trips    = $this->tripModel->count_trips_by_contract_and_month($contract_id, $month_start, $month_end);
        $included_trips = (int) $contract['included_trips'];
        $excess_trips   = max(0, $total_trips - $included_trips);
        $excess_charge  = $excess_trips * (float) $contract['excess_trip_charge'];
        $total_amount   = (float) $contract['monthly_rate'] + $excess_charge;

        $response = $this->respond([
            'data' => [
                'contract_id'    => $contract_id,
                'customer_name'  => $contract['customer_name'],
                'month'          => $month,
                'monthly_rate'   => $contract['monthly_rate'],
                'included_trips' => $included_trips,
                'total_trips'    => $total_trips,
                'excess_trips'   => $excess_trips,
                'excess_charge'  => $excess_charge,
                'total_amount'   => $total_amount
            ],
            'status' => 'success'
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function complete()
    {
        if (($response = $this->_api_verification('trips', 'complete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $trip_id = $this->request->getVar('trip_id');

        if (!$trip = $this->tripModel->get_details_by_id($trip_id)) {
            $response = $this->failNotFound('Trip not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $this->db = db_connect();
        $this->db->transBegin();

        // Mark trip as completed
        $this->tripModel->custom_update(
            ['id' => $trip_id, 'is_deleted' => 0],
            ['status' => 'completed', 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
        );

        if ($this->db->error()['code']) {
            $this->db->transRollback();
            $response = $this->fail('Failed to complete trip.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Set truck back to active
        if (!empty($trip['truck_id'])) {
            $this->truckModel->custom_update(
                ['id' => $trip['truck_id'], 'is_deleted' => 0],
                ['status' => 'active', 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
            );
        }

        $this->db->transCommit();
        $response = $this->respond(['response' => 'Trip marked as completed.', 'status' => 'success']);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function get_suggestions()
    {
        if (($response = $this->_api_verification('trips', 'get_suggestions')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $keyword = $this->request->getVar('keyword') ?? '';

        if (strlen(trim($keyword)) < 1) {
            $response = $this->respond(['data' => [], 'status' => 'success']);
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $results  = $this->tripModel->search_suggestions($keyword);
        $response = $this->respond(['data' => $results, 'status' => 'success']);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->tripModel           = model('App\Models\Trip');
        $this->tripDriverModel     = model('App\Models\Trip_driver');
        $this->tripHelperModel     = model('App\Models\Trip_helper');
        $this->contractModel       = model('App\Models\Contract');
        $this->contractRouteModel  = model('App\Models\Contract_route');
        $this->truckModel          = model('App\Models\Truck');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}