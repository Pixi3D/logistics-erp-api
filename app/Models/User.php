<?php

namespace App\Models;

use CodeIgniter\Model;

class User extends Model
{
    protected $table         = 'user';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'token',    
        'token_expiry', 
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted',
    ];

    public function get_details_by_email($email)
    {
        $database = \Config\Database::connect();
        $query = $database->query(
            'SELECT * FROM user WHERE is_deleted = 0 AND email = ? LIMIT 1',
            [$email]
        );
        $result = $query ? $query->getResultArray() : [];
        return $result ? $result[0] : false;
    }
}