<?php

namespace App\Models;

class Driver extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'first_name',
        'last_name',
        'contact_number',
        'license_number',
        'address',
        'status',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'driver';
    }

    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM driver
WHERE driver.is_deleted = 0
ORDER BY driver.last_name ASC, driver.first_name ASC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_id($driver_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM driver
WHERE driver.id = ?
  AND driver.is_deleted = 0
EOT;
        $query = $database->query($sql, [$driver_id]);
        return $query ? $query->getRowArray() : false;
    }

    public function search($name = null, $license_number = null, $status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM driver
WHERE driver.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql    .= " AND CONCAT(driver.first_name, ' ', driver.last_name) LIKE ?";
            $binds[] = '%' . $name . '%';
        }

        if ($license_number) {
            $sql    .= " AND driver.license_number LIKE ?";
            $binds[] = '%' . $license_number . '%';
        }

        if ($status) {
            $sql    .= " AND driver.status = ?";
            $binds[] = $status;
        }

        $sql .= " ORDER BY driver.last_name ASC, driver.first_name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}