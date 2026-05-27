<?php

namespace App\Models;

use App\Models\MYTModel;

class Helper_attachment extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'helper_id',
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
        $this->table = 'helper_attachment';
    }

    public function get_by_helper_id($helper_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM helper_attachment
WHERE helper_id = ?
  AND is_deleted = 0
ORDER BY added_on DESC
EOT;
        $query = $database->query($sql, [$helper_id]);
        return $query ? $query->getResultArray() : false;
    }
}