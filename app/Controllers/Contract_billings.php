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
            'customer_id'     => $this->request->getVar('customer_id')     ?: null,
            'contract_id'     => $this->request->getVar('contract_id')     ?: null,
            'billing_number'  => $this->request->getVar('billing_number')  ?: null,
            'status'          => $this->request->getVar('status')          ?: null,
            'month_from'      => $this->request->getVar('month_from')      ?: null,
            'month_to'        => $this->request->getVar('month_to')        ?: null,
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
            'status'              => 'open_bill',
            'remarks'             => $remarks,
            'billing_date'        => $this->request->getVar('billing_date') ?: date('Y-m-d'),
            'due_date'            => $this->request->getVar('due_date') ?: null,
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
                    'trip_date'              => $trip['trip_date'] ?? null,
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

        // Generate monthly cycles from contract start_date to end_date
        $cycles  = [];
        $cursor  = new \DateTime(date('Y-m-01', strtotime($contract['start_date'])));
        $end     = new \DateTime(date('Y-m-01', strtotime($contract['end_date'])));

        while ($cursor <= $end) {
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

        $customer         = $this->customerModel->get_details_by_id($contract['customer_id']);
        $trips            = $this->contractBillingModel->get_trips_for_billing($contract_id, $period_start, $period_end) ?: [];
        $included_trips   = (int)   $contract['included_trips'];
        $monthly_rate     = (float) $contract['monthly_rate'];
        $excess_trip_charge = (float) $contract['excess_trip_charge'];

        $excess_trip_total    = 0;
        $fuel_surcharge_total = 0;

        $mapped = array_map(function ($trip) use (&$excess_trip_total, &$fuel_surcharge_total) {
            $is_excess              = (bool) $trip['is_excess'];
            $excess_charge          = $is_excess ? (float) $trip['excess_charge'] : 0;
            $fuel_additional_charge = (float) $trip['fuel_additional_charge'];
            $excess_trip_total    += $excess_charge;
            $fuel_surcharge_total += $fuel_additional_charge;
            return array_merge($trip, [
                'is_excess'              => $is_excess,
                'excess_charge'          => $excess_charge,
                'fuel_additional_charge' => $fuel_additional_charge,
            ]);
        }, $trips);

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
                'customer' => [
                    'trade_name'    => $customer['trade_name']    ?? null,
                    'trade_address' => $customer['trade_address'] ?? null,
                    'tin'           => $customer['tin']           ?? null,
                ],
            ],
            'status' => 'success',
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }
public function create_batch($id = null)
    {
        if (($response = $this->_api_verification('contract_billings', 'create_batch')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id  = $this->request->getVar('customer_id');
        $period_start = $this->request->getVar('period_start');
        $period_end   = $this->request->getVar('period_end');
        $due_dates_raw = $this->request->getVar('due_dates') ?: [];
        $due_dates = [];
        foreach ($due_dates_raw as $entry) {
            $entry = is_object($entry) ? (array) $entry : $entry;
            $due_dates[$entry['contract_id']] = $entry['due_date'] ?? null;
        }
        $contract_ids = $this->request->getVar('contract_ids') ?: [];
        $billing_date = date('Y-m-d');

        if (!$customer_id || !$period_start || !$period_end || empty($contract_ids)) {
            $response = $this->fail('customer_id, period_start, period_end, and contract_ids are required.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $this->db = db_connect();
        $this->db->transBegin();

        // Generate billing number using a temp insert
        $temp_data = [
            'billing_number'       => 'TEMP',
            'contract_id'          => $contract_ids[0],
            'customer_id'          => $customer_id,
            'billing_period_start' => $period_start,
            'billing_period_end'   => $period_end,
            'billing_date'         => $billing_date,
            'due_date'             => $due_dates[$contract_ids[0]] ?? null,
            'total_trips'          => 0,
            'included_trips'       => 0,
            'excess_trips'         => 0,
            'monthly_rate'         => 0,
            'excess_trip_charge'   => 0,
            'excess_trip_total'    => 0,
            'fuel_surcharge_total' => 0,
            'grand_total'          => 0,
            'amount_paid'          => 0,
            'balance'              => 0,
            'status'               => 'open_bill',
            'added_by'             => $this->requested_by,
            'added_on'             => date('Y-m-d H:i:s'),
        ];

        if (!$this->contractBillingModel->insert($temp_data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to generate billing number.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $first_id       = $this->contractBillingModel->getInsertID();
        $billing_number = 'BL-' . date('Ym', strtotime($period_start)) . '-' . str_pad($first_id, 4, '0', STR_PAD_LEFT);

        // Update the temp row with real billing number — will be overwritten with real data below
        $this->contractBillingModel->custom_update(
            ['id' => $first_id],
            ['billing_number' => $billing_number, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
        );

        $billing_ids = [];

        foreach ($contract_ids as $index => $contract_id) {
            if (!$contract = $this->contractModel->get_details_by_id($contract_id)) continue;

            $trips = $this->contractBillingModel->get_trips_for_billing($contract_id, $period_start, $period_end) ?: [];

            $included_trips     = (int)   $contract['included_trips'];
            $monthly_rate       = (float) $contract['monthly_rate'];
            $excess_trip_charge = (float) $contract['excess_trip_charge'];
            $total_trips          = count($trips);
            $excess_trips         = max(0, $total_trips - $included_trips);
            $excess_trip_total    = 0;
            $fuel_surcharge_total = 0;

            foreach ($trips as $trip) {
                $excess_trip_total    += (float) $trip['excess_charge'];
                $fuel_surcharge_total += (float) $trip['fuel_additional_charge'];
            }

            $grand_total = $monthly_rate + $excess_trip_total + $fuel_surcharge_total;

            $row_data = [
                'billing_number'       => $billing_number,
                'contract_id'          => $contract_id,
                'customer_id'          => $customer_id,
                'billing_period_start' => $period_start,
                'billing_period_end'   => $period_end,
                'billing_date'         => $billing_date,
                'due_date'             => $due_dates[$contract_id] ?? null,
                'total_trips'          => $total_trips,
                'included_trips'       => $included_trips,
                'excess_trips'         => $excess_trips,
                'monthly_rate'         => $monthly_rate,
                'excess_trip_charge'   => $excess_trip_charge,
                'excess_trip_total'    => $excess_trip_total,
                'fuel_surcharge_total' => $fuel_surcharge_total,
                'grand_total'          => $grand_total,
                'amount_paid'          => 0,
                'balance'              => $grand_total,
                'status'               => 'open_bill',
                'added_by'             => $this->requested_by,
                'added_on'             => date('Y-m-d H:i:s'),
            ];

            // First contract reuses the temp row
            if ($index === 0) {
                $this->contractBillingModel->custom_update(['id' => $first_id], $row_data);
                $billing_id = $first_id;
            } else {
                if (!$this->contractBillingModel->insert($row_data)) {
                    $this->db->transRollback();
                    $response = $this->fail('Failed to insert billing for contract ' . $contract_id);
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
                $billing_id = $this->contractBillingModel->getInsertID();
            }

            // Link trips
            foreach ($trips as $trip) {
                $this->db->table('contract_billing_trip')->insert([
                    'billing_id'             => $billing_id,
                    'trip_id'                => $trip['id'],
                    'contract_route_id'      => $trip['contract_route_id'] ?? 0,
                    'truck_id'               => $trip['truck_id']          ?? 0,
                    'trip_date'              => $trip['trip_date']          ?? null,
                    'is_excess'              => $trip['is_excess']          ?? 0,
                    'excess_charge'          => $trip['excess_charge']      ?? 0,
                    'actual_fuel_price'      => $trip['actual_fuel_price']  ?? 0,
                    'fuel_additional_charge' => $trip['fuel_additional_charge'] ?? 0,
                    'added_on'               => date('Y-m-d H:i:s'),
                ]);
                if ($this->db->error()['code']) {
                    $this->db->transRollback();
                    $response = $this->fail('Failed to link trips for contract ' . $contract_id);
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
            }

            $billing_ids[] = $billing_id;
        }

        $this->db->transCommit();
        $response = $this->respond([
            'status'         => 'success',
            'response'       => 'Billing created successfully.',
            'billing_number' => $billing_number,
            'billing_ids'    => $billing_ids,
        ]);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function get_contracts_with_unbilled()
    {
        if (($response = $this->_api_verification('contract_billings', 'get_contracts_with_unbilled')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id  = $this->request->getVar('customer_id');
        $period_start = $this->request->getVar('period_start');
        $period_end   = $this->request->getVar('period_end');

        if (!$customer_id || !$period_start || !$period_end) {
            $response = $this->fail('customer_id, period_start, and period_end are required.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $db = \Config\Database::connect();
        $contracts = $db->query("
            SELECT contract.*, 
                IFNULL(customer.trade_name, CONCAT(customer.first_name, ' ', customer.last_name)) AS customer_name
            FROM contract
            LEFT JOIN customer ON customer.id = contract.customer_id
            WHERE contract.customer_id = ?
              AND contract.is_deleted = 0
              AND contract.status = 'active'
              AND (contract.end_date IS NULL OR contract.end_date >= ?)
        ", [$customer_id, $period_start])->getResultArray() ?: [];
        $result = [];

        foreach ($contracts as $contract) {
            // Skip if already billed for this period
            if ($this->contractBillingModel->is_duplicate($contract['id'], $period_start, $period_end)) {
                continue;
            }

            // Skip if no trips in this period
            $trips = $this->contractBillingModel->get_trips_for_billing($contract['id'], $period_start, $period_end) ?: [];
            if (empty($trips)) continue;
            usort($trips, fn($a, $b) => strtotime($a['trip_date'] ?? $a['expected_departure_datetime']) <=> strtotime($b['trip_date'] ?? $b['expected_departure_datetime']));

            $included_trips     = (int)   $contract['included_trips'];
            $monthly_rate       = (float) $contract['monthly_rate'];
            $excess_trip_charge = (float) $contract['excess_trip_charge'];
            $excess_trip_total    = 0;
            $fuel_surcharge_total = 0;

            $mapped = array_map(function ($trip) use (&$excess_trip_total, &$fuel_surcharge_total) {
                $excess_charge          = (float) $trip['excess_charge'];
                $fuel_additional_charge = (float) $trip['fuel_additional_charge'];
                $excess_trip_total    += $excess_charge;
                $fuel_surcharge_total += $fuel_additional_charge;
                return array_merge($trip, [
                    'is_excess'              => (bool) $trip['is_excess'],
                    'excess_charge'          => $excess_charge,
                    'fuel_additional_charge' => $fuel_additional_charge,
                ]);
            }, $trips);

            $grand_total = $monthly_rate + $excess_trip_total + $fuel_surcharge_total;

            $result[] = [
                'contract_id'       => $contract['id'],
                'contract_number'   => $contract['contract_number'],
                'customer_name'     => $contract['customer_name'],
                'payment_terms'     => $contract['payment_terms'] ?? null,
                'trips'             => $mapped,
                'summary'           => [
                    'monthly_rate'         => $monthly_rate,
                    'total_trips'          => count($trips),
                    'included_trips'       => $included_trips,
                    'excess_trips'         => max(0, count($trips) - $included_trips),
                    'excess_trip_charge'   => $excess_trip_charge,
                    'excess_trip_total'    => $excess_trip_total,
                    'fuel_surcharge_total' => $fuel_surcharge_total,
                    'grand_total'          => $grand_total,
                ],
            ];
        }

        $customer = $this->customerModel->get_details_by_id($customer_id);
        $customer = is_object($customer) ? (array) $customer : ($customer ?: []);

        $response = $this->respond([
            'data'     => $result,
            'customer' => [
                'trade_name'    => $customer['trade_name']    ?? null,
                'trade_address' => $customer['trade_address'] ?? null,
                'tin'           => $customer['tin']           ?? null,
            ],
            'status' => 'success',
        ]);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function get_suggestions()
{
    if (($response = $this->_api_verification('contract_billings', 'get_suggestions')) !== true)
        return $response;

    $token = $this->request->getVar('token');
    if (($response = $this->_verify_requester($token)) !== true)
        return $response;

    $keyword = $this->request->getVar('keyword') ?: '';
    $database = \Config\Database::connect();

    $results = $database->query("
        SELECT 
            cb.id,
            cb.billing_number,
            cb.customer_id,
            COALESCE(c.trade_name, CONCAT(c.first_name, ' ', c.last_name)) AS customer_name,
            'billing' AS match_type
        FROM contract_billing cb
        LEFT JOIN customer c ON c.id = cb.customer_id
        WHERE cb.is_deleted = 0
          AND (cb.billing_number LIKE ? OR c.trade_name LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)
        GROUP BY cb.billing_number, cb.customer_id
        LIMIT 10
    ", ["%$keyword%", "%$keyword%", "%$keyword%", "%$keyword%"])->getResultArray();

    return $this->respond(['data' => ['suggestions' => $results], 'status' => 'success']);
}
    protected function _load_essentials()
    {
        $this->contractBillingModel        = model('App\Models\Contract_billing');
        $this->contractBillingPaymentModel = model('App\Models\Contract_billing_payment');
        $this->contractModel               = model('App\Models\Contract');
        $this->customerModel               = model('App\Models\Customer');
        $this->webappResponseModel         = model('App\Models\Webapp_response');
    }
}