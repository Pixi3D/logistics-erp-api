<?php

namespace App\Models;

use App\Models\MYTModel;

class Contract_route extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'contract_id',
        'origin',
        'destination',
        'distance_km',
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
        $this->table = 'contract_route';
    }

    public function get_by_contract_id($contract_id)
{
    $database = \Config\Database::connect();
    $sql = <<<EOT
SELECT contract_route.*, CONCAT(customer.first_name, ' ', customer.last_name) AS contract_name
FROM contract_route
LEFT JOIN contract ON contract.id = contract_route.contract_id
LEFT JOIN customer ON customer.id = contract.customer_id
WHERE contract_route.contract_id = ?
  AND contract_route.is_deleted = 0
ORDER BY contract_route.id ASC
EOT;
    $query = $database->query($sql, [$contract_id]);
    return $query ? $query->getResultArray() : false;
}

public function get_details_by_id($route_id)
{
    $database = \Config\Database::connect();
    $sql = <<<EOT
SELECT contract_route.*, CONCAT(customer.first_name, ' ', customer.last_name) AS contract_name
FROM contract_route
LEFT JOIN contract ON contract.id = contract_route.contract_id
LEFT JOIN customer ON customer.id = contract.customer_id
WHERE contract_route.id = ?
  AND contract_route.is_deleted = 0
EOT;
    $query = $database->query($sql, [$route_id]);
    return $query ? $query->getRowArray() : false;
}

public function get_all()
{
    $database = \Config\Database::connect();
    $sql = <<<EOT
SELECT contract_route.*, CONCAT(customer.first_name, ' ', customer.last_name) AS contract_name
FROM contract_route
LEFT JOIN contract ON contract.id = contract_route.contract_id
LEFT JOIN customer ON customer.id = contract.customer_id
WHERE contract_route.is_deleted = 0
ORDER BY contract_route.id ASC
EOT;
    $query = $database->query($sql);
    return $query ? $query->getResultArray() : false;
}

}