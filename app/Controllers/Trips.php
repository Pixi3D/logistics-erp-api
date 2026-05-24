<?php

namespace App\Controllers;

class Trips extends MYTController
{
    protected $tripModel;
    protected $tripDriverModel;
    protected $tripHelperModel;
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

        $customer_id  = $this->request->getVar('customer_id')  ?: null;
        $contract_id  = $this->request->getVar('contract_id')  ?: null;
        $truck_id     = $this->request->getVar('truck_id')      ?: null;
        $date_from    = $this->request->getVar('date_from')     ?: null;
        $date_to      = $this->request->getVar('date_to')       ?: null;

        if (!$trips = $this->tripModel->search($customer_id, $contract_id, $truck_id, $date_from, $date_to)) {
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
            $trip['drivers'] = $this->tripDriverModel->get_by_trip_id($trip_id) ?: [];
            $trip['helpers'] = $this->tripHelperModel->get_by_trip_id($trip_id) ?: [];
            $response = $this->respond(['data' => $trip, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create a trip
     * Expects: contract_id, contract_route_id, truck_id, trip_date, remarks (optional)
     *          driver_ids[] = array of driver IDs
     *          helper_ids[] = array of helper IDs
     */
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
        $driver_ids        = $this->request->getVar('driver_ids') ?: [];
        $helper_ids        = $this->request->getVar('helper_ids') ?: [];

        // Verify contract exists
        if (!$contract = $this->contractModel->select('', ['id' => $contract_id, 'is_deleted' => 0], 1)) {
            $response = $this->failNotFound('Contract not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Verify route belongs to this contract
        if (!$this->contractRouteModel->select('', ['id' => $contract_route_id, 'contract_id' => $contract_id, 'is_deleted' => 0], 1)) {
            $response = $this->fail('Route does not belong to this contract.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Count trips already done this month for billing computation
        $month_start = date('Y-m-01', strtotime($trip_date));
        $month_end   = date('Y-m-t', strtotime($trip_date));
        $trips_this_month = $this->tripModel->count_trips_by_contract_and_month($contract_id, $month_start, $month_end);
        $included_trips   = (int) $contract['included_trips'];

        // Determine if this trip is excess
        $is_excess        = ($trips_this_month >= $included_trips) ? 1 : 0;
        $excess_charge    = $is_excess ? $contract['excess_trip_charge'] : 0.00;

        $trip_data = [
            'contract_id'       => $contract_id,
            'contract_route_id' => $contract_route_id,
            'truck_id'          => $truck_id,
            'trip_date'         => $trip_date,
            'is_excess'         => $is_excess,
            'excess_charge'     => $excess_charge,
            'remarks'           => $this->request->getVar('remarks') ?: null,
            'added_by'          => $this->requested_by,
            'added_on'          => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$trip_id = $this->tripModel->insert($trip_data)) {
            $this->db->transRollback();
            $errors = $this->db->error();
            $response = $this->fail(json_encode($errors) ?: 'Unable to record trip. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Insert drivers
        foreach ($driver_ids as $driver_id) {
            $driver_data = [
                'trip_id'   => $trip_id,
                'driver_id' => $driver_id,
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s')
            ];

            if (!$this->tripDriverModel->insert($driver_data)) {
                $this->db->transRollback();
                $response = $this->fail('Unable to assign driver to trip. Please try again.');
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            }
        }

        // Insert helpers
        foreach ($helper_ids as $helper_id) {
            $helper_data = [
                'trip_id'   => $trip_id,
                'helper_id' => $helper_id,
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s')
            ];

            if (!$this->tripHelperModel->insert($helper_data)) {
                $this->db->transRollback();
                $response = $this->fail('Unable to assign helper to trip. Please try again.');
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            }
        }

        $this->db->transCommit();
        $response = $this->respond([
            'response'     => 'Trip recorded successfully.',
            'status'        => 'success',
            'is_excess'    => (bool) $is_excess,
            'excess_charge' => $excess_charge
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

        $trip_id   = $this->request->getVar('trip_id');
        $condition = ['id' => $trip_id, 'is_deleted' => 0];

        if (!$this->tripModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Trip not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'contract_route_id' => $this->request->getVar('contract_route_id'),
            'truck_id'          => $this->request->getVar('truck_id'),
            'trip_date'         => $this->request->getVar('trip_date'),
            'remarks'           => $this->request->getVar('remarks') ?: null,
            'updated_by'        => $this->requested_by,
            'updated_on'        => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->tripModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update trip. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Trip updated successfully.', 'status' => 'success']);
        }

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
        } elseif (!$this->tripModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete trip. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Trip updated successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Compute billing summary for a customer contract within a month
     * Expects: contract_id, month (Y-m format e.g. 2025-01)
     */
    public function compute_billing()
    {
        if (($response = $this->_api_verification('trips', 'compute_billing')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id = $this->request->getVar('contract_id');
        $month       = $this->request->getVar('month'); // format: Y-m

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
                'contract_id'      => $contract_id,
                'customer_name'    => $contract['customer_name'],
                'month'            => $month,
                'monthly_rate'     => $contract['monthly_rate'],
                'included_trips'   => $included_trips,
                'total_trips'      => $total_trips,
                'excess_trips'     => $excess_trips,
                'excess_charge'    => $excess_charge,
                'total_amount'     => $total_amount
            ],
            'status' => 'success'
        ]);

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
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}