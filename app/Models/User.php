<?php

namespace App\Models;

class User extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'employee_id',
        'name',
        'email',
        'password',
        'role_id',
        'token',
        'token_expiry',
        'added_on',
        'added_by',
        'updated_on',
        'updated_by',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'user';
    }

    /**
     * Get user details by email
     */
    public function get_details_by_email($email)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT user.*, role.name AS role
FROM user
LEFT JOIN role ON user.role_id = role.id
WHERE user.is_deleted = 0
    AND user.email = ?
EOT;
        $binds = [$email];
        $query = $database->query($sql, $binds);
        return ($query AND $query->getResult()) ? $query->getResultArray()[0] : false;
    }

     /**
     * Get recent avtivities by requester
     */
    public function get_recent_activity($requester, $date_from, $date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT webapp_response.id, webapp_log.method, webapp_log.controller, webapp_response.responded_on, webapp_log.requested_by
FROM `webapp_response`
LEFT JOIN `webapp_log` ON webapp_response.webapp_log_id = webapp_log.id
WHERE NOT SUBSTRING(webapp_response.response, 11, 3) >= 400
AND webapp_log.requested_by = ?
AND webapp_log.method IN ('add', 'update','delete', 'login')
ORDER BY webapp_response.responded_on DESC
LIMIT 15
EOT;
        $binds = [$requester];
        $query = $database->query($sql, $binds);
        return ($query AND $query->getResult()) ? $query->getResultArray() : false;
    }


    /**
     * Get user details by id
     */
    public function get_by_id($id = null, $employee_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT user.*, role.name AS role_name
FROM user
LEFT JOIN role ON user.role_id = role.id
WHERE user.is_deleted = 0
EOT;
        $binds = [];

        if(!empty($id)){
            $sql .= <<<EOT

AND user.id = ?
EOT;
            $binds[] = $id;
        }

        if(!empty($employee_id)){
            $sql .= <<<EOT

AND user.employee_id = ?
EOT;
            $binds[] = $employee_id;
        }

        $query = $database->query($sql, $binds);
        return ($query AND $query->getResult()) ? $query->getResultArray() : false;
    }
}

?>