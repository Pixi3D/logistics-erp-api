<?php

namespace App\Models;

use App\Models\MYTModel;

class Contract_billing_payment_attachment extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'payment_id',
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
        $this->table = 'contract_billing_payment_attachment';
    }

    public function get_by_payment_id($payment_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM contract_billing_payment_attachment
WHERE payment_id = ?
  AND is_deleted = 0
ORDER BY added_on DESC
EOT;
        $query = $database->query($sql, [$payment_id]);
        return $query ? $query->getResultArray() : false;
    }
}