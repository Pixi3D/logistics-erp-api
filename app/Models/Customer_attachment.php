<?php

namespace App\Models;

use App\Models\MYTModel;

class Customer_attachment extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'customer_id',
        'file_name',
        'file_path',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = 'customer_attachment';
    }

    public function get_by_customer_id($customer_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM customer_attachment
WHERE customer_id = ?
  AND is_deleted = 0
ORDER BY added_on DESC
EOT;
        $query = $database->query($sql, [$customer_id]);
        return $query ? $query->getResultArray() : false;
    }
}