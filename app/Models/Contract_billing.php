<?php

namespace App\Models;

use App\Models\MYTModel;

class Contract_billing extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'billing_number',
        'contract_id',
        'customer_id',
        'billing_period_start',
        'billing_period_end',
        'total_trips',
        'included_trips',
        'excess_trips',
        'monthly_rate',
        'excess_trip_charge',
        'excess_trip_total',
        'fuel_surcharge_total',
        'grand_total',
        'amount_paid',
        'balance',
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
        $this->table = 'contract_billing';
    }

    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT contract_billing.*,
    contract.contract_number,
    CONCAT(customer.first_name, ' ', customer.last_name) AS customer_name
FROM contract_billing
LEFT JOIN contract ON contract.id = contract_billing.contract_id
LEFT JOIN customer ON customer.id = contract_billing.customer_id
WHERE contract_billing.is_deleted = 0
ORDER BY contract_billing.added_on DESC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_id($billing_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT contract_billing.*,
    contract.contract_number,
    contract.monthly_rate        AS contract_monthly_rate,
    contract.included_trips      AS contract_included_trips,
    contract.excess_trip_charge  AS contract_excess_trip_charge,
    contract.fuel_price_per_liter,
    CONCAT(customer.first_name, ' ', customer.last_name) AS customer_name
FROM contract_billing
LEFT JOIN contract ON contract.id = contract_billing.contract_id
LEFT JOIN customer ON customer.id = contract_billing.customer_id
WHERE contract_billing.id = ?
  AND contract_billing.is_deleted = 0
EOT;
        $query = $database->query($sql, [$billing_id]);
        return $query ? $query->getRowArray() : false;
    }

    public function get_by_contract_id($contract_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT contract_billing.*,
    contract.contract_number,
    CONCAT(customer.first_name, ' ', customer.last_name) AS customer_name
FROM contract_billing
LEFT JOIN contract ON contract.id = contract_billing.contract_id
LEFT JOIN customer ON customer.id = contract_billing.customer_id
WHERE contract_billing.contract_id = ?
  AND contract_billing.is_deleted = 0
ORDER BY contract_billing.billing_period_start DESC
EOT;
        $query = $database->query($sql, [$contract_id]);
        return $query ? $query->getResultArray() : false;
    }

    public function search($filters = [])
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT contract_billing.*,
    contract.contract_number,
    CONCAT(customer.first_name, ' ', customer.last_name) AS customer_name
FROM contract_billing
LEFT JOIN contract ON contract.id = contract_billing.contract_id
LEFT JOIN customer ON customer.id = contract_billing.customer_id
WHERE contract_billing.is_deleted = 0
EOT;
        $binds = [];

        if (!empty($filters['customer_id'])) {
            $sql    .= " AND contract_billing.customer_id = ?";
            $binds[] = $filters['customer_id'];
        }

        if (!empty($filters['contract_id'])) {
            $sql    .= " AND contract_billing.contract_id = ?";
            $binds[] = $filters['contract_id'];
        }

        if (!empty($filters['status'])) {
            $sql    .= " AND contract_billing.status = ?";
            $binds[] = $filters['status'];
        }

        if (!empty($filters['month_from'])) {
            $sql    .= " AND contract_billing.billing_period_start >= ?";
            $binds[] = $filters['month_from'];
        }

        if (!empty($filters['month_to'])) {
            $sql    .= " AND contract_billing.billing_period_end <= ?";
            $binds[] = $filters['month_to'];
        }

        $sql .= " ORDER BY contract_billing.billing_period_start DESC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function get_trips_for_billing($contract_id, $month_start, $month_end)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT
    trip.id,
    trip.contract_route_id,
    trip.truck_id,
    trip.expected_departure_datetime AS trip_date,
    trip.is_excess,
    trip.excess_charge,
    trip.actual_fuel_price,
    trip.fuel_additional_charge,
    contract_route.origin,
    contract_route.destination,
    contract_route.distance_km,
    truck.plate_number,
    truck.unit_code
FROM trip
LEFT JOIN contract_route ON contract_route.id = trip.contract_route_id
LEFT JOIN truck          ON truck.id          = trip.truck_id
WHERE trip.contract_id = ?
  AND trip.expected_departure_datetime >= ?
  AND trip.expected_departure_datetime <= ?
  AND trip.is_deleted  = 0
ORDER BY trip.expected_departure_datetime ASC
EOT;
        $query = $database->query($sql, [$contract_id, $month_start, $month_end]);
        return $query ? $query->getResultArray() : false;
    }

    public function is_duplicate($contract_id, $period_start, $period_end)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT id FROM contract_billing
WHERE contract_id          = ?
  AND billing_period_start = ?
  AND billing_period_end   = ?
  AND is_deleted           = 0
LIMIT 1
EOT;
        $query = $database->query($sql, [$contract_id, $period_start, $period_end]);
        return $query ? $query->getRowArray() : false;
    }
}