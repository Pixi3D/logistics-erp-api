<?php

namespace App\Controllers;

class Reports extends MYTController
{
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key      = $_SERVER['HTTP_API_KEY']  ?? '';
        $this->user_key     = $_SERVER['HTTP_USER_KEY'] ?? '';
        $this->requested_by = $this->user_key;
        $this->_load_essentials();
    }

    // GET reports/get_accounts_receivable
    public function get_accounts_receivable()
    {
        if (($response = $this->_api_verification('reports', 'get_accounts_receivable')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id  = $this->request->getVar('customer_id')  ?: null;
        $contract_id  = $this->request->getVar('contract_id')  ?: null;

        $db    = \Config\Database::connect();
        $today = date('Y-m-d');

        $sql = "
            SELECT
                cb.id                AS billing_id,
                cb.billing_number,
                cb.billing_date,
                cb.due_date,
                cb.balance,
                cb.contract_id,
                c.contract_number,
                cb.customer_id,
                COALESCE(NULLIF(cu.trade_name, ''), CONCAT(cu.first_name, ' ', cu.last_name)) AS customer_name,
                DATEDIFF(?, cb.due_date) AS days_overdue
            FROM contract_billing cb
            LEFT JOIN contract c  ON c.id  = cb.contract_id
            LEFT JOIN customer cu ON cu.id = cb.customer_id
            WHERE cb.is_deleted = 0
              AND cb.status     = 'open_bill'
              AND cb.balance    > 0
        ";

        $binds = [$today];

        if ($customer_id) {
            $sql    .= " AND cb.customer_id = ?";
            $binds[] = $customer_id;
        }

        if ($contract_id) {
            $sql    .= " AND cb.contract_id = ?";
            $binds[] = $contract_id;
        }

        $sql .= " ORDER BY cu.trade_name ASC, c.contract_number ASC";

        $billings = $db->query($sql, $binds)->getResultArray() ?: [];

        // Group by contract
        $grouped = [];
        $summary = [
            'total_current'            => 0,
            'total_one_to_thirty'      => 0,
            'total_thirty_one_to_sixty'=> 0,
            'total_sixty_one_to_ninety'=> 0,
            'total_above_ninety'       => 0,
            'total_receivables'        => 0,
        ];
        foreach ($billings as $billing) {
            $key = $billing['contract_id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'customer_id'     => $billing['customer_id'],
                    'customer_name'   => $billing['customer_name'],
                    'contract_id'     => $billing['contract_id'],
                    'contract_number' => $billing['contract_number'],
                    'current'         => 0,
                    'one_to_thirty'        => 0,
                    'thirty_one_to_sixty'  => 0,
                    'sixty_one_to_ninety'  => 0,
                    'above_ninety'         => 0,
                    'total'                => 0,
                    'billings'             => [],
                ];
            }

            $days    = (int) $billing['days_overdue'];
            $balance = (float) $billing['balance'];

            // Determine bucket
            if ($days <= 0) {
                $bucket = 'current';
            } elseif ($days <= 30) {
                $bucket = 'one_to_thirty';
            } elseif ($days <= 60) {
                $bucket = 'thirty_one_to_sixty';
            } elseif ($days <= 90) {
                $bucket = 'sixty_one_to_ninety';
            } else {
                $bucket = 'above_ninety';
            }

            $grouped[$key][$bucket]  += $balance;
            $grouped[$key]['total']  += $balance;
            $summary['total_' . $bucket] += $balance;
            $summary['total_receivables'] += $balance;
            $grouped[$key]['billings'][] = [
                'billing_number' => $billing['billing_number'],
                'billing_date'   => $billing['billing_date'],
                'due_date'       => $billing['due_date'],
                'balance'        => $balance,
                'bucket'         => $bucket,
            ];
        }

        $result = array_values($grouped);

        $response = $this->respond([
            'data'    => $result,
            'summary' => $summary,
            'status'  => 'success',
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}