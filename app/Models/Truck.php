<?php

namespace App\Models;

class Truck extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'unit_code',
        'plate_number',
        'color',
        'capacity',
        'km_per_liter',
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
        $this->table = 'truck';
    }

    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM truck
WHERE truck.is_deleted = 0
ORDER BY truck.unit_code ASC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_id($truck_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM truck
WHERE truck.id = ?
  AND truck.is_deleted = 0
EOT;
        $query = $database->query($sql, [$truck_id]);
        return $query ? $query->getRowArray() : false;
    }

    public function search($unit_code = null, $plate_number = null, $status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM truck
WHERE truck.is_deleted = 0
EOT;
        $binds = [];

        if ($unit_code) {
            $sql    .= " AND truck.unit_code LIKE ?";
            $binds[] = '%' . $unit_code . '%';
        }

        if ($plate_number) {
            $sql    .= " AND truck.plate_number LIKE ?";
            $binds[] = '%' . $plate_number . '%';
        }

        if ($status) {
            $sql    .= " AND truck.status = ?";
            $binds[] = $status;
        }

        $sql .= " ORDER BY truck.unit_code ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}