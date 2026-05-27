<?php
namespace App\Models;
use App\Models\MYTModel;

class Customer_contact extends MYTModel
{
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'customer_id', 'first_name', 'middle_name', 'last_name', 'suffix',
        'contact_number', 'email', 'role',
        'added_by', 'added_on', 'updated_by', 'updated_on', 'is_deleted'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = 'customer_contact';
    }

    public function get_by_customer($customer_id)
    {
        $db = \Config\Database::connect();
        $query = $db->query(
            "SELECT * FROM customer_contact
             WHERE customer_id = ? AND is_deleted = 0
             ORDER BY id ASC",
            [$customer_id]
        );
        return $query ? $query->getResultArray() : [];
    }
}