<?php

namespace App\Models;

use CodeIgniter\Model;

class Webapp_log extends Model
{
    protected $table            = 'webapp_log';
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
}