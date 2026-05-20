<?php

namespace App\Models;

class Helper extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'first_name',
        'last_name',
        'contact_number',
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
        $this->table = 'helper';
    }

    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM helper
WHERE helper.is_deleted = 0
ORDER BY helper.last_name ASC, helper.first_name ASC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_id($helper_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM helper
WHERE helper.id = ?
  AND helper.is_deleted = 0
EOT;
        $query = $database->query($sql, [$helper_id]);
        return $query ? $query->getRowArray() : false;
    }

    public function search($name = null, $status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM helper
WHERE helper.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql    .= " AND CONCAT(helper.first_name, ' ', helper.last_name) LIKE ?";
            $binds[] = '%' . $name . '%';
        }

        if ($status) {
            $sql    .= " AND helper.status = ?";
            $binds[] = $status;
        }

        $sql .= " ORDER BY helper.last_name ASC, helper.first_name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}