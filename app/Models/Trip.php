<?php

namespace App\Models;

class Trip extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'contract_id',
        'contract_route_id',
        'truck_id',
        'trip_date',
        'is_excess',
        'excess_charge',
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
        $this->table = 'trip';
    }

    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT trip.*,
    CONCAT(customer.first_name, ' ', customer.last_name) AS customer_name,
    contract_route.origin                               AS route_origin,
    contract_route.destination                          AS route_destination,
    truck.unit_code                                     AS truck_unit_code,
    truck.plate_number                                  AS truck_plate_number,
    (SELECT GROUP_CONCAT(CONCAT(driver.first_name, ' ', driver.last_name) SEPARATOR ', ')
     FROM trip_driver
     JOIN driver ON driver.id = trip_driver.driver_id
     WHERE trip_driver.trip_id = trip.id AND trip_driver.is_deleted = 0) AS drivers_label,
    (SELECT GROUP_CONCAT(CONCAT(helper.first_name, ' ', helper.last_name) SEPARATOR ', ')
     FROM trip_helper
     JOIN helper ON helper.id = trip_helper.helper_id
     WHERE trip_helper.trip_id = trip.id AND trip_helper.is_deleted = 0) AS helpers_label
FROM trip
LEFT JOIN contract        ON contract.id        = trip.contract_id
LEFT JOIN customer        ON customer.id        = contract.customer_id
LEFT JOIN contract_route  ON contract_route.id  = trip.contract_route_id
LEFT JOIN truck           ON truck.id           = trip.truck_id
WHERE trip.is_deleted = 0
ORDER BY trip.trip_date DESC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_id($trip_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT trip.*,
    CONCAT(customer.first_name, ' ', customer.last_name) AS customer_name,
    contract_route.origin                               AS route_origin,
    contract_route.destination                          AS route_destination,
    truck.unit_code                                     AS truck_unit_code,
    truck.plate_number                                  AS truck_plate_number
FROM trip
LEFT JOIN contract        ON contract.id        = trip.contract_id
LEFT JOIN customer        ON customer.id        = contract.customer_id
LEFT JOIN contract_route  ON contract_route.id  = trip.contract_route_id
LEFT JOIN truck           ON truck.id           = trip.truck_id
WHERE trip.id = ?
  AND trip.is_deleted = 0
EOT;
        $query = $database->query($sql, [$trip_id]);
        return $query ? $query->getRowArray() : false;
    }

    public function search($customer_id = null, $contract_id = null, $truck_id = null, $date_from = null, $date_to = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT trip.*,
    CONCAT(customer.first_name, ' ', customer.last_name) AS customer_name,
    contract_route.origin                               AS route_origin,
    contract_route.destination                          AS route_destination,
    truck.unit_code                                     AS truck_unit_code,
    truck.plate_number                                  AS truck_plate_number
FROM trip
LEFT JOIN contract        ON contract.id        = trip.contract_id
LEFT JOIN customer        ON customer.id        = contract.customer_id
LEFT JOIN contract_route  ON contract_route.id  = trip.contract_route_id
LEFT JOIN truck           ON truck.id           = trip.truck_id
WHERE trip.is_deleted = 0
EOT;
        $binds = [];

        if ($customer_id) {
            $sql    .= " AND customer.id = ?";
            $binds[] = $customer_id;
        }

        if ($contract_id) {
            $sql    .= " AND trip.contract_id = ?";
            $binds[] = $contract_id;
        }

        if ($truck_id) {
            $sql    .= " AND trip.truck_id = ?";
            $binds[] = $truck_id;
        }

        if ($date_from) {
            $sql    .= " AND trip.trip_date >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql    .= " AND trip.trip_date <= ?";
            $binds[] = $date_to;
        }

        $sql .= " ORDER BY trip.trip_date DESC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Count trips for a contract within a month
     * Used by billing computation
     */
    public function count_trips_by_contract_and_month($contract_id, $month_start, $month_end)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT COUNT(*) AS trip_count
FROM trip
WHERE trip.contract_id = ?
  AND trip.trip_date >= ?
  AND trip.trip_date <= ?
  AND trip.is_deleted = 0
EOT;
        $query = $database->query($sql, [$contract_id, $month_start, $month_end]);
        if (!$query) return 0;
        $row = $query->getRowArray();
        return (int) $row['trip_count'];
    }
}