<?php

namespace App\Models;

use App\Models\MYTModel;

class Bank extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'bank_name',
        'account_name',
        'account_number',
        'beginning_bal',
        'current_bal',
        'is_deleted',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = 'bank';
    }

    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM bank
WHERE is_deleted = 0
ORDER BY bank_name ASC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_id($bank_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM bank
WHERE id = ?
  AND is_deleted = 0
EOT;
        $query = $database->query($sql, [$bank_id]);
        return $query ? $query->getRowArray() : false;
    }

    public function search($filters = [])
    {
        $database = \Config\Database::connect();
        $sql      = "SELECT * FROM bank WHERE is_deleted = 0";
        $binds    = [];

        if (!empty($filters['bank_name'])) {
            $sql    .= " AND bank_name LIKE ?";
            $binds[] = '%' . $filters['bank_name'] . '%';
        }

        $sql .= " ORDER BY bank_name ASC";
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}