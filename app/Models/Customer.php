<?php

namespace App\Models;

use App\Models\MYTModel;

class Customer extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'trade_name',
        'bir_name',
        'trade_address',
        'bir_address',
        'tin',
        'term',
        'credit_limit',
        'payee',
        'vat_type',
        'bir_2307',
        'contact_person',
        'contact_number',
        'email',
        'address',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = 'customer';
    }

    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM customer
WHERE customer.is_deleted = 0
ORDER BY customer.last_name ASC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_id($customer_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM customer
WHERE customer.id = ?
  AND customer.is_deleted = 0
EOT;
        $query = $database->query($sql, [$customer_id]);
        return $query ? $query->getRowArray() : false;
    }

    public function search($name = null, $status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM customer
WHERE customer.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND (customer.first_name LIKE ? OR customer.last_name LIKE ?)";
            $binds[] = '%' . $name . '%';
            $binds[] = '%' . $name . '%';
        }

        if ($status) {
            $sql    .= " AND customer.status = ?";
            $binds[] = $status;
        }

        $sql .= " ORDER BY customer.last_name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}