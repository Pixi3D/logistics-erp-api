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

    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT id, first_name, last_name, email, role, added_on, is_deleted
FROM user
WHERE is_deleted = 0
ORDER BY last_name ASC, first_name ASC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function select($columns = null, $conditions = null, $limit = null)
    {
        $database = \Config\Database::connect();
        $builder = $database->table($this->table);
        if (!empty($conditions)) {
            $query = $builder->getWhere($conditions, $limit ?? 0);
        } else {
            $query = $builder->get($limit ?? 0);
        }
        if (!$query || empty($query->getResultArray())) {
            return false;
        }
        $result = $query->getResultArray();
        return $limit === 1 ? $result[0] : $result;
    }
}