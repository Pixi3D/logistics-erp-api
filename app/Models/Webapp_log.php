<?php

namespace App\Models;

class Webapp_log extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'controller',
        'method',
        'ip_address',
        'data_received',
        'requested_by',
        'requested_on',
    ];

    public function __construct()
    {
        $this->table = 'webapp_log';
    }

    public function get_all($controller = null, $search = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM webapp_log
WHERE 1=1
EOT;
        $binds = [];

        if ($controller) {
            $sql    .= " AND webapp_log.controller = ?";
            $binds[] = $controller;
        }

        if ($search) {
            $sql    .= " AND (webapp_log.controller LIKE ? OR webapp_log.method LIKE ? OR webapp_log.ip_address LIKE ?)";
            $binds[] = '%' . $search . '%';
            $binds[] = '%' . $search . '%';
            $binds[] = '%' . $search . '%';
        }

        $sql .= " ORDER BY webapp_log.id DESC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}