<?php

namespace App\Models;

use App\Models\MYTModel;

class Contract extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'contract_number',
        'date_signed',
        'authorized_representative',
        'payment_terms',
        'customer_id',
        'monthly_rate',
        'included_trips',
        'excess_trip_charge',
        'fuel_price_per_liter',
        'start_date',
        'end_date',
        'status',
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
        $this->table = 'contract';
    }

    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
    SELECT contract.*,
        IFNULL(customer.trade_name, CONCAT(customer.first_name, ' ', customer.last_name)) AS customer_name,
        CONCAT(cc.first_name, ' ', cc.last_name) AS authorized_signatory,
        CASE
            WHEN contract.status = 'terminated' THEN 'terminated'
            WHEN contract.end_date IS NOT NULL AND contract.end_date < CURDATE() THEN 'expired'
            ELSE contract.status
        END AS status
    FROM contract
    LEFT JOIN customer ON customer.id = contract.customer_id
    LEFT JOIN (
        SELECT customer_id, first_name, last_name
        FROM customer_contact
        WHERE role = 'Authorized Signatory'
        AND is_deleted = 0
        ORDER BY added_on DESC
    ) cc ON cc.customer_id = customer.id
    WHERE contract.is_deleted = 0
    ORDER BY contract.added_on DESC
    EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_id($contract_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT contract.*,
    IFNULL(customer.trade_name, CONCAT(customer.first_name, ' ', customer.last_name)) AS customer_name
FROM contract
LEFT JOIN customer ON customer.id = contract.customer_id
WHERE contract.id = ?
  AND contract.is_deleted = 0
EOT;
        $query = $database->query($sql, [$contract_id]);
        return $query ? $query->getRowArray() : false;
    }

    public function search($customer_id = null, $status = null, $date_from = null, $date_to = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT contract.*,
    IFNULL(customer.trade_name, CONCAT(customer.first_name, ' ', customer.last_name)) AS customer_name
FROM contract
LEFT JOIN customer ON customer.id = contract.customer_id
WHERE contract.is_deleted = 0
EOT;
        $binds = [];

        if ($customer_id) {
            $sql    .= " AND contract.customer_id = ?";
            $binds[] = $customer_id;
        }

        if ($status === 'expired') {
            $sql .= " AND contract.end_date IS NOT NULL AND contract.end_date < CURDATE()";
        } elseif ($status === 'terminated') {
            $sql .= " AND contract.status = 'terminated'";
        } elseif ($status === 'active') {
            $sql .= " AND contract.status = 'active' AND (contract.end_date IS NULL OR contract.end_date >= CURDATE())";
        } elseif ($status) {
            $sql    .= " AND contract.status = ?";
            $binds[] = $status;
        }

        if ($date_from) {
            $sql    .= " AND contract.start_date >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql    .= " AND contract.start_date <= ?";
            $binds[] = $date_to;
        }

        $sql .= " ORDER BY contract.added_on DESC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get active contract for a customer on a given date
     * Used when recording a trip to find the applicable contract
     */
    public function get_active_contract_by_customer($customer_id, $date)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT contract.*
FROM contract
WHERE contract.customer_id = ?
  AND contract.status = 'active'
  AND contract.date_start <= ?
  AND (contract.date_end IS NULL OR contract.date_end >= ?)
  AND contract.is_deleted = 0
LIMIT 1
EOT;
        $query = $database->query($sql, [$customer_id, $date, $date]);
        return $query ? $query->getRowArray() : false;
    }
}