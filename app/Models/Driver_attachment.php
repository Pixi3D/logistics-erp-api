<?php

namespace App\Models;

use App\Models\MYTModel;

class Driver_attachment extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'driver_id',
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
        $this->table = 'driver_attachment';
    }

    public function get_by_driver_id($driver_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM driver_attachment
WHERE driver_id = ?
  AND is_deleted = 0
ORDER BY added_on DESC
EOT;
        $query = $database->query($sql, [$driver_id]);
        return $query ? $query->getResultArray() : false;
    }
}