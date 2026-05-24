<?php

namespace App\Models;

class Trip_driver extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'trip_id',
        'driver_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = 'trip_driver';
    }

    public function get_by_trip_id($trip_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT trip_driver.*,
    CONCAT(driver.first_name, ' ', driver.last_name) AS driver_name,
    driver.license_number,
    driver.contact_number
FROM trip_driver
LEFT JOIN driver ON driver.id = trip_driver.driver_id
WHERE trip_driver.trip_id = ?
  AND trip_driver.is_deleted = 0
EOT;
        $query = $database->query($sql, [$trip_id]);
        return $query ? $query->getResultArray() : false;
    }
}