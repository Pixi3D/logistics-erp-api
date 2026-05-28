<?php

namespace App\Controllers;

class Contract_billings extends MYTController
{
    protected $contractBillingModel;
    protected $contractModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key      = $_SERVER['HTTP_API_KEY']  ?? '';
        $this->user_key     = $_SERVER['HTTP_USER_KEY'] ?? '';
        $this->requested_by = $this->user_key;
        $this->_load_essentials();
    }

    // GET contract_billings/index
    public function index()
    {
        if (($response = $this->_api_verification('contract_billings', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$billings = $this->contractBillingModel->get_all()) {
            $response = $this->failNotFound('No billings found.');
        } else {
            $response = $this->respond(['data' => $billings, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // GET contract_billings/search
    public function search()
    {
        if (($response = $this->_api_verification('contract_billings', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $filters = [
            'customer_id' => $this->request->getVar('customer_id') ?: null,
            'contract_id' => $this->request->getVar('contract_id') ?: null,
            'status'      => $this->request->getVar('status')      ?: null,
            'month_from'  => $this->request->getVar('month_from')  ?: null,
            'month_to'    => $this->request->getVar('month_to')    ?: null,
        ];

        if (!$billings = $this->contractBillingModel->search($filters)) {
            $response = $this->failNotFound('No billings found.');
        } else {
            $response = $this->respond(['data' => $billings, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // GET contract_billings/details
    public function details()
    {
        if (($response = $this->_api_verification('contract_billings', 'details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $billing_id = $this->request->getVar('billing_id');

        if (!$billing = $this->contractBillingModel->get_details_by_id($billing_id)) {
            $response = $this->failNotFound('Billing not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Attach trip breakdown
        $trips = $this->contractBillingModel->get_trips_for_billing(
            $billing['contract_id'],
            $billing['billing_period_start'],
            $billing['billing_period_end']
        );
        $billing['trips'] = $trips ?: [];

        // Attach payments
        $billing['payments'] = $this->contractBillingPaymentModel->get_by_billing_id($billing_id) ?: [];

        $response = $this->respond(['data' => $billing, 'status' => 'success']);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // POST contract_billings/create
    public function create($id = null)
    {
        if (($response = $this->_api_verification('contract_billings', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id   = $this->request->getVar('contract_id');
        $period_start  = $this->request->getVar('billing_period_start');
        $period_end    = $this->request->getVar('billing_period_end');
        $remarks       = $this->request->getVar('remarks') ?: null;

        // Validate required fields
        if (!$contract_id || !$period_start || !$period_end) {
            $response = $this->fail('contract_id, billing_period_start, and billing_period_end are required.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Prevent duplicate billing for same contract + period
        if ($this->contractBillingModel->is_duplicate($contract_id, $period_start, $period_end)) {
            $response = $this->fail('A billing for this contract and period already exists.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Load contract to get rate info
        if (!$contract = $this->contractModel->get_details_by_id($contract_id)) {
            $response = $this->failNotFound('Contract not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Compute totals from trips in DB — never trust request values
        $trips = $this->contractBillingModel->get_trips_for_billing($contract_id, $period_start, $period_end);
        $trips = $trips ?: [];

        $included_trips     = (int)   $contract['included_trips'];
        $monthly_rate       = (float) $contract['monthly_rate'];
        $excess_trip_charge = (float) $contract['excess_trip_charge'];

        $total_trips         = count($trips);
        $excess_trips        = max(0, $total_trips - $included_trips);
        $excess_trip_total   = 0;
        $fuel_surcharge_total = 0;

        foreach ($trips as $trip) {
            $excess_trip_total    += (float) $trip['excess_charge'];
            $fuel_surcharge_total += (float) $trip['fuel_additional_charge'];
        }

        $grand_total = $monthly_rate + $excess_trip_total + $fuel_surcharge_total;

        $this->db = db_connect();
        $this->db->transBegin();

        // Insert with a temporary billing_number; update after we have the ID
        $data = [
            'billing_number'      => 'TEMP',
            'contract_id'         => $contract_id,
            'customer_id'         => $contract['customer_id'],
            'billing_period_start'=> $period_start,
            'billing_period_end'  => $period_end,
            'total_trips'         => $total_trips,
            'included_trips'      => $included_trips,
            'excess_trips'        => $excess_trips,
            'monthly_rate'        => $monthly_rate,
            'excess_trip_charge'  => $excess_trip_charge,
            'excess_trip_total'   => $excess_trip_total,
            'fuel_surcharge_total'=> $fuel_surcharge_total,
            'grand_total'         => $grand_total,
            'amount_paid'         => 0,
            'balance'             => $grand_total,
            'status'              => 'unpaid',
            'remarks'             => $remarks,
            'added_by'            => $this->requested_by,
            'added_on'            => date('Y-m-d H:i:s'),
        ];

        if (!$this->contractBillingModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to create billing. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $billing_id = $this->contractBillingModel->getInsertID();

        // Build billing number: BL-YYYYMM-XXXX
        $billing_number = 'BL-' . date('Ym', strtotime($period_start)) . '-' . str_pad($billing_id, 4, '0', STR_PAD_LEFT);

        $this->contractBillingModel->custom_update(
            ['id' => $billing_id],
            ['billing_number' => $billing_number, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
        );

        if ($this->db->error()['code']) {
            $this->db->transRollback();
            $response = $this->fail('Failed to assign billing number.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Link trips via contract_billing_trip
        if (!empty($trips)) {
            foreach ($trips as $trip) {
                $trip_data = [
                    'billing_id'             => $billing_id,
                    'trip_id'                => $trip['id'],
                    'contract_route_id'      => $trip['contract_route_id'] ?? 0,
                    'truck_id'               => $trip['truck_id']          ?? 0,
                    'trip_date'              => $trip['trip_date'],
                    'is_excess'              => $trip['is_excess']         ?? 0,
                    'excess_charge'          => $trip['excess_charge']     ?? 0,
                    'actual_fuel_price'      => $trip['actual_fuel_price'] ?? 0,
                    'fuel_additional_charge' => $trip['fuel_additional_charge'] ?? 0,
                    'added_on'               => date('Y-m-d H:i:s'),
                ];
                $this->db->table('contract_billing_trip')->insert($trip_data);
                $db_error = $this->db->error();
                if ($db_error['code']) {
                    $this->db->transRollback();
                    $response = $this->fail('Failed to link trips: ' . $db_error['message']);
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
            }
        }

        $this->db->transCommit();
        $response = $this->respond([
            'status'         => 'success',
            'response'       => 'Billing created successfully.',
            'billing_id'     => $billing_id,
            'billing_number' => $billing_number,
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // POST contract_billings/update
    public function update($id = null)
    {
        if (($response = $this->_api_verification('contract_billings', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $billing_id = $this->request->getVar('billing_id');

        if (!$this->contractBillingModel->get_details_by_id($billing_id)) {
            $response = $this->failNotFound('Billing not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Only remarks and status are editable
        $data = [
            'remarks'    => $this->request->getVar('remarks') ?: null,
            'status'     => $this->request->getVar('status'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->contractBillingModel->custom_update(['id' => $billing_id, 'is_deleted' => 0], $data);

        if ($this->db->error()['code']) {
            $response = $this->fail('Unable to update billing. Please try again.');
        } else {
            $response = $this->respond(['status' => 'success', 'response' => 'Billing updated successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // POST contract_billings/delete
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('contract_billings', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $billing_id = $this->request->getVar('billing_id');

        if (!$this->contractBillingModel->get_details_by_id($billing_id)) {
            $response = $this->failNotFound('Billing not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->contractBillingModel->custom_update(['id' => $billing_id, 'is_deleted' => 0], $data);

        if ($this->db->error()['code']) {
            $response = $this->fail('Unable to delete billing. Please try again.');
        } else {
            $response = $this->respond(['status' => 'success', 'response' => 'Billing deleted successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // GET contract_billings/get_unbilled_cycles
    public function get_unbilled_cycles()
    {
        if (($response = $this->_api_verification('contract_billings', 'get_unbilled_cycles')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id = $this->request->getVar('contract_id');

        if (!$contract = $this->contractModel->get_details_by_id($contract_id)) {
            $response = $this->failNotFound('Contract not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Get already-billed periods
        $billed = $this->contractBillingModel->get_by_contract_id($contract_id) ?: [];
        $billed_keys = [];
        foreach ($billed as $b) {
            $billed_keys[] = $b['billing_period_start'] . '|' . $b['billing_period_end'];
        }

        // Generate monthly cycles from contract start_date to today
        $cycles  = [];
        $cursor  = new \DateTime(date('Y-m-01', strtotime($contract['start_date'])));
        $today   = new \DateTime();

        while ($cursor <= $today) {
            $period_start = $cursor->format('Y-m-d');
            $period_end   = $cursor->format('Y-m-t');
            $key          = $period_start . '|' . $period_end;

            if (!in_array($key, $billed_keys)) {
                $cycles[] = ['period_start' => $period_start, 'period_end' => $period_end];
            }

            $cursor->modify('+1 month');
        }

        if (empty($cycles)) {
            $response = $this->respond(['data' => [], 'status' => 'success']);
        } else {
            $response = $this->respond(['data' => $cycles, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // GET contract_billings/preview
    public function preview()
    {
        if (($response = $this->_api_verification('contract_billings', 'preview')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id  = $this->request->getVar('contract_id');
        $period_start = $this->request->getVar('period_start');
        $period_end   = $this->request->getVar('period_end');

        if (!$contract = $this->contractModel->get_details_by_id($contract_id)) {
            $response = $this->failNotFound('Contract not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $trips            = $this->contractBillingModel->get_trips_for_billing($contract_id, $period_start, $period_end) ?: [];
        $included_trips   = (int)   $contract['included_trips'];
        $monthly_rate     = (float) $contract['monthly_rate'];
        $excess_trip_charge = (float) $contract['excess_trip_charge'];

        $excess_trip_total    = 0;
        $fuel_surcharge_total = 0;

        $mapped = array_map(function ($trip, $i) use ($included_trips, $excess_trip_charge, &$excess_trip_total, &$fuel_surcharge_total) {
            $is_excess              = $i >= $included_trips;
            $excess_charge          = $is_excess ? (float) $trip['excess_charge'] : 0;
            $fuel_additional_charge = (float) $trip['fuel_additional_charge'];
            $excess_trip_total    += $excess_charge;
            $fuel_surcharge_total += $fuel_additional_charge;
            return array_merge($trip, [
                'is_excess'              => $is_excess,
                'excess_charge'          => $excess_charge,
                'fuel_additional_charge' => $fuel_additional_charge,
            ]);
        }, $trips, array_keys($trips));

        $grand_total = $monthly_rate + $excess_trip_total + $fuel_surcharge_total;

        $response = $this->respond([
            'data' => [
                'trips'   => $mapped,
                'summary' => [
                    'monthly_rate'         => $monthly_rate,
                    'total_trips'          => count($trips),
                    'included_trips'       => $included_trips,
                    'excess_trips'         => max(0, count($trips) - $included_trips),
                    'excess_trip_charge'   => $excess_trip_charge,
                    'excess_trip_total'    => $excess_trip_total,
                    'fuel_surcharge_total' => $fuel_surcharge_total,
                    'grand_total'          => $grand_total,
                ],
            ],
            'status' => 'success',
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->contractBillingModel        = model('App\Models\Contract_billing');
        $this->contractBillingPaymentModel = model('App\Models\Contract_billing_payment');
        $this->contractModel               = model('App\Models\Contract');
        $this->webappResponseModel         = model('App\Models\Webapp_response');
    }
}