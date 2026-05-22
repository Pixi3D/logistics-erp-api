<?php

namespace App\Models;

use CodeIgniter\Model;

class Webapp_response extends Model
{
    protected $table            = 'webapp_response';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'webapp_log_id',
        'response',
        'responded_on',
    ];

    public function record_response($webapp_log_id, $response)
    {
        $converted_response = (array) $response;
        $prefix = chr(0) . '*' . chr(0);

        $values = [
            'webapp_log_id' => $webapp_log_id,
            'response'      => $converted_response[$prefix . 'body'],
            'responded_on'  => date('Y-m-d H:i:s'),
        ];

        return $this->insert($values) ? true : false;
    }
}