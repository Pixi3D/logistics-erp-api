<?php

namespace App\Models;

use App\Models\MYTModel;

class Truck_attachment extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'truck_id',
        'file_name',
        'file_type',
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
        $this->table = 'truck_attachment';
    }

    public function get_by_truck_id($truck_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM truck_attachment
WHERE truck_id = ?
  AND is_deleted = 0
ORDER BY added_on DESC
EOT;
        $query = $database->query($sql, [$truck_id]);
        return $query ? $query->getResultArray() : false;
    }
}