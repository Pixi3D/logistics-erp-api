<?php

namespace App\Models;

class Trip_helper extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'trip_id',
        'helper_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = 'trip_helper';
    }

    public function get_by_trip_id($trip_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT trip_helper.*,
    CONCAT(helper.first_name, ' ', helper.last_name) AS helper_name,
    helper.contact_number
FROM trip_helper
LEFT JOIN helper ON helper.id = trip_helper.helper_id
WHERE trip_helper.trip_id = ?
  AND trip_helper.is_deleted = 0
EOT;
        $query = $database->query($sql, [$trip_id]);
        return $query ? $query->getResultArray() : false;
    }
}