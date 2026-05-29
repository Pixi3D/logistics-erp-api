<?php

namespace App\Models;

class Trip extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'contract_id',
        'contract_route_id',
        'truck_id',
        'expected_departure_datetime',
        'expected_arrival_datetime',
        'actual_departure_datetime',
        'actual_arrival_datetime',
        'is_excess',
        'excess_charge',
        'actual_fuel_price',
        'fuel_additional_charge',
        'remarks',
        'status',
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
    contract.contract_number,
    customer.trade_name                                 AS trade_name,
    CONCAT(customer.first_name, ' ', customer.last_name) AS customer_name,
    contract_route.origin                               AS route_origin,
    contract_route.destination                          AS route_destination,
    contract_route.distance_km                          AS route_distance_km,
    truck.unit_code                                     AS truck_unit_code,
    truck.plate_number                                  AS truck_plate_number,
    truck.km_per_liter                                  AS truck_km_per_liter,
    (SELECT CONCAT(driver.first_name, ' ', driver.last_name)
     FROM trip_driver
     JOIN driver ON driver.id = trip_driver.driver_id
     WHERE trip_driver.trip_id = trip.id AND trip_driver.is_deleted = 0
     LIMIT 1) AS driver_label,
    (SELECT CONCAT(helper.first_name, ' ', helper.last_name)
     FROM trip_helper
     JOIN helper ON helper.id = trip_helper.helper_id
     WHERE trip_helper.trip_id = trip.id AND trip_helper.is_deleted = 0
     LIMIT 1) AS helper_label
FROM trip
LEFT JOIN contract        ON contract.id        = trip.contract_id
LEFT JOIN customer        ON customer.id        = contract.customer_id
LEFT JOIN contract_route  ON contract_route.id  = trip.contract_route_id
LEFT JOIN truck           ON truck.id           = trip.truck_id
WHERE trip.is_deleted = 0
ORDER BY trip.expected_departure_datetime DESC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_id($trip_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT trip.*,
    contract.contract_number,
    contract.fuel_price_per_liter                       AS agreed_fuel_price,
    contract.included_trips,
    contract.excess_trip_charge,
    customer.trade_name                                 AS trade_name,
    CONCAT(customer.first_name, ' ', customer.last_name) AS customer_name,
    contract_route.origin                               AS route_origin,
    contract_route.destination                          AS route_destination,
    contract_route.distance_km                          AS route_distance_km,
    truck.unit_code                                     AS truck_unit_code,
    truck.plate_number                                  AS truck_plate_number,
    truck.km_per_liter                                  AS truck_km_per_liter
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

    public function search($filters = [])
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT trip.*,
    contract.contract_number,
    customer.trade_name                                 AS trade_name,
    CONCAT(customer.first_name, ' ', customer.last_name) AS customer_name,
    contract_route.origin                               AS route_origin,
    contract_route.destination                          AS route_destination,
    contract_route.distance_km                          AS route_distance_km,
    truck.unit_code                                     AS truck_unit_code,
    truck.plate_number                                  AS truck_plate_number,
    truck.km_per_liter                                  AS truck_km_per_liter,
    (SELECT CONCAT(driver.first_name, ' ', driver.last_name)
     FROM trip_driver
     JOIN driver ON driver.id = trip_driver.driver_id
     WHERE trip_driver.trip_id = trip.id AND trip_driver.is_deleted = 0
     LIMIT 1) AS driver_label,
    (SELECT CONCAT(helper.first_name, ' ', helper.last_name)
     FROM trip_helper
     JOIN helper ON helper.id = trip_helper.helper_id
     WHERE trip_helper.trip_id = trip.id AND trip_helper.is_deleted = 0
     LIMIT 1) AS helper_label
FROM trip
LEFT JOIN contract        ON contract.id        = trip.contract_id
LEFT JOIN customer        ON customer.id        = contract.customer_id
LEFT JOIN contract_route  ON contract_route.id  = trip.contract_route_id
LEFT JOIN truck           ON truck.id           = trip.truck_id
LEFT JOIN trip_driver     ON trip_driver.trip_id = trip.id AND trip_driver.is_deleted = 0
LEFT JOIN driver          ON driver.id           = trip_driver.driver_id
LEFT JOIN trip_helper     ON trip_helper.trip_id = trip.id AND trip_helper.is_deleted = 0
LEFT JOIN helper          ON helper.id           = trip_helper.helper_id
WHERE trip.is_deleted = 0
EOT;
        $binds = [];

        if (!empty($filters['customer_id'])) {
            $sql    .= " AND customer.id = ?";
            $binds[] = $filters['customer_id'];
        }

        if (!empty($filters['contract_id'])) {
            $sql    .= " AND trip.contract_id = ?";
            $binds[] = $filters['contract_id'];
        }

        if (!empty($filters['truck_id'])) {
            $sql    .= " AND trip.truck_id = ?";
            $binds[] = $filters['truck_id'];
        }

        if (!empty($filters['driver_id'])) {
            $sql    .= " AND driver.id = ?";
            $binds[] = $filters['driver_id'];
        }

        if (!empty($filters['helper_id'])) {
            $sql    .= " AND helper.id = ?";
            $binds[] = $filters['helper_id'];
        }

        if (!empty($filters['route_id'])) {
            $sql    .= " AND trip.contract_route_id = ?";
            $binds[] = $filters['route_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql    .= " AND trip.expected_departure_datetime >= ?";
            $binds[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql    .= " AND trip.expected_departure_datetime <= ?";
            $binds[] = $filters['date_to'];
        }

        $sql .= " GROUP BY trip.id ORDER BY trip.expected_departure_datetime DESC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function count_trips_by_contract_and_month($contract_id, $month_start, $month_end)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT COUNT(*) AS trip_count
FROM trip
WHERE trip.contract_id = ?
  AND trip.expected_departure_datetime >= ?
  AND trip.expected_departure_datetime <= ?
  AND trip.is_deleted = 0
EOT;
        $query = $database->query($sql, [$contract_id, $month_start, $month_end]);
        if (!$query) return 0;
        $row = $query->getRowArray();
        return (int) $row['trip_count'];
    }

public function search_suggestions($keyword)
{
    $database = \Config\Database::connect();
    $like     = '%' . $keyword . '%';

    $customers = $database->query("
        SELECT id, CONCAT(first_name, ' ', last_name) AS label
        FROM customer
        WHERE (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
          AND is_deleted = 0
        LIMIT 5
    ", [$like, $like, $like])->getResultArray();

    $contracts = $database->query("
        SELECT contract.id, 
               CONCAT('#', contract.contract_number, ' — ', CONCAT(customer.first_name, ' ', customer.last_name)) AS label
        FROM contract
        LEFT JOIN customer ON customer.id = contract.customer_id
        WHERE (contract.contract_number LIKE ? OR customer.first_name LIKE ? OR customer.last_name LIKE ?)
          AND contract.is_deleted = 0
        LIMIT 5
    ", [$like, $like, $like])->getResultArray();

    $trucks = $database->query("
        SELECT id, CONCAT(plate_number, ' — ', IFNULL(unit_code, '')) AS label
        FROM truck
        WHERE (plate_number LIKE ? OR unit_code LIKE ?)
          AND is_deleted = 0
        LIMIT 5
    ", [$like, $like])->getResultArray();

    $drivers = $database->query("
        SELECT id, CONCAT(first_name, ' ', last_name) AS label
        FROM driver
        WHERE (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
          AND is_deleted = 0
        LIMIT 5
    ", [$like, $like, $like])->getResultArray();

    $helpers = $database->query("
        SELECT id, CONCAT(first_name, ' ', last_name) AS label
        FROM helper
        WHERE (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
          AND is_deleted = 0
        LIMIT 5
    ", [$like, $like, $like])->getResultArray();

    $routes = $database->query("
        SELECT contract_route.id,
               CONCAT(contract_route.origin, ' → ', contract_route.destination) AS label
        FROM contract_route
        WHERE (contract_route.origin LIKE ? OR contract_route.destination LIKE ?)
          AND contract_route.is_deleted = 0
        LIMIT 5
    ", [$like, $like])->getResultArray();

    return [
        'customers' => $customers,
        'contracts' => $contracts,
        'trucks'    => $trucks,
        'drivers'   => $drivers,
        'helpers'   => $helpers,
        'routes'    => $routes,
    ];
}

public function check_asset_conflict($asset_type, $asset_id, $departure, $arrival, $exclude_trip_id = null)
{
    $database = \Config\Database::connect();

    if ($asset_type === 'truck') {
        $sql = <<<EOT
SELECT COUNT(*) AS conflict_count
FROM trip
WHERE trip.truck_id = ?
  AND trip.status IN ('scheduled', 'in_transit')
  AND trip.is_deleted = 0
  AND (
    ? < trip.expected_arrival_datetime
    AND ? > trip.expected_departure_datetime
  )
EOT;
        $binds = [$asset_id, $departure, $arrival];
    } elseif ($asset_type === 'driver') {
        $sql = <<<EOT
SELECT COUNT(*) AS conflict_count
FROM trip
JOIN trip_driver ON trip_driver.trip_id = trip.id AND trip_driver.is_deleted = 0
WHERE trip_driver.driver_id = ?
  AND trip.status IN ('scheduled', 'in_transit')
  AND trip.is_deleted = 0
  AND (
    ? < trip.expected_arrival_datetime
    AND ? > trip.expected_departure_datetime
  )
EOT;
        $binds = [$asset_id, $departure, $arrival];
    } elseif ($asset_type === 'helper') {
        $sql = <<<EOT
SELECT COUNT(*) AS conflict_count
FROM trip
JOIN trip_helper ON trip_helper.trip_id = trip.id AND trip_helper.is_deleted = 0
WHERE trip_helper.helper_id = ?
  AND trip.status IN ('scheduled', 'in_transit')
  AND trip.is_deleted = 0
  AND (
    ? < trip.expected_arrival_datetime
    AND ? > trip.expected_departure_datetime
  )
EOT;
        $binds = [$asset_id, $departure, $arrival];
    } else {
        return 0;
    }

    if ($exclude_trip_id) {
        $sql    .= " AND trip.id != ?";
        $binds[] = $exclude_trip_id;
    }

    $query = $database->query($sql, $binds);
    if (!$query) return 0;
    $row = $query->getRowArray();
    return (int) $row['conflict_count'];
}
}