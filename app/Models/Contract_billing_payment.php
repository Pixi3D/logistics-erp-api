<?php

namespace App\Models;

use App\Models\MYTModel;

class Contract_billing_payment extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'billing_id',
        'payment_date',
        'payment_method',
        'amount',
        'reference_number',
        'check_number',
        'check_date',
        'bank_name',
        'deposit_date',
        'deposited_to',
        'transfer_date',
        'remarks',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = 'contract_billing_payment';
    }

    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT contract_billing_payment.*,
    contract_billing.billing_number,
    contract_billing.grand_total,
    contract.contract_number,
    COALESCE(NULLIF(customer.trade_name, ''), CONCAT(customer.first_name, ' ', customer.last_name)) AS customer_name
FROM contract_billing_payment
LEFT JOIN contract_billing ON contract_billing.id       = contract_billing_payment.billing_id
LEFT JOIN contract         ON contract.id               = contract_billing.contract_id
LEFT JOIN customer         ON customer.id               = contract_billing.customer_id
WHERE contract_billing_payment.is_deleted = 0
ORDER BY contract_billing_payment.payment_date DESC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_id($payment_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT contract_billing_payment.*,
    contract_billing.billing_number,
    contract_billing.grand_total,
    contract.contract_number,
    COALESCE(NULLIF(customer.trade_name, ''), CONCAT(customer.first_name, ' ', customer.last_name)) AS customer_name
FROM contract_billing_payment
LEFT JOIN contract_billing ON contract_billing.id = contract_billing_payment.billing_id
LEFT JOIN contract         ON contract.id         = contract_billing.contract_id
LEFT JOIN customer         ON customer.id         = contract_billing.customer_id
WHERE contract_billing_payment.id = ?
  AND contract_billing_payment.is_deleted = 0
EOT;
        $query = $database->query($sql, [$payment_id]);
        return $query ? $query->getRowArray() : false;
    }

    public function get_by_billing_id($billing_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM contract_billing_payment
WHERE billing_id = ?
  AND is_deleted = 0
ORDER BY payment_date DESC
EOT;
        $query = $database->query($sql, [$billing_id]);
        return $query ? $query->getResultArray() : false;
    }

    public function get_total_paid($billing_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT COALESCE(SUM(amount), 0) AS total_paid
FROM contract_billing_payment
WHERE billing_id = ?
  AND is_deleted = 0
EOT;
        $query = $database->query($sql, [$billing_id]);
        if (!$query) return 0;
        $row = $query->getRowArray();
        return (float) $row['total_paid'];
    }

    public function search($filters = [])
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT contract_billing_payment.*,
    contract_billing.billing_number,
    contract_billing.grand_total,
    contract.contract_number,
    COALESCE(NULLIF(customer.trade_name, ''), CONCAT(customer.first_name, ' ', customer.last_name)) AS customer_name
FROM contract_billing_payment
LEFT JOIN contract_billing ON contract_billing.id = contract_billing_payment.billing_id
LEFT JOIN contract         ON contract.id         = contract_billing.contract_id
LEFT JOIN customer         ON customer.id         = contract_billing.customer_id
WHERE contract_billing_payment.is_deleted = 0
EOT;
        $binds = [];

        if (!empty($filters['billing_id'])) {
            $sql    .= " AND contract_billing_payment.billing_id = ?";
            $binds[] = $filters['billing_id'];
        }

        if (!empty($filters['payment_method'])) {
            $sql    .= " AND contract_billing_payment.payment_method = ?";
            $binds[] = $filters['payment_method'];
        }

        if (!empty($filters['date_from'])) {
            $sql    .= " AND contract_billing_payment.payment_date >= ?";
            $binds[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql    .= " AND contract_billing_payment.payment_date <= ?";
            $binds[] = $filters['date_to'];
        }

        if (!empty($filters['customer_id'])) {
            $sql    .= " AND customer.id = ?";
            $binds[] = $filters['customer_id'];
        }

        $sql .= " ORDER BY contract_billing_payment.payment_date DESC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}