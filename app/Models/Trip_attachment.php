<?php

namespace App\Models;

use App\Models\MYTModel;

class Trip_attachment extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'trip_id',
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
        $this->table = 'trip_attachment';
    }

    /**
     * Fetch active attachments for a specific trip
     */
    public function get_by_trip_id($trip_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM trip_attachment
WHERE trip_id = ?
  AND is_deleted = 0
ORDER BY added_on DESC
EOT;
        $query = $database->query($sql, [$trip_id]);
        return $query ? $query->getResultArray() : false;
    }
}