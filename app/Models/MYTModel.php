<?php

namespace App\Models;
use CodeIgniter\Model;

class MYTModel extends Model
{
    public $table = NULL;

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect();
    }

	/**
	 * Parameter database will be passed if there
	 * is no need to create a database connection
	 */
    public function select($columns = NULL, $conditions = NULL, $limit = NULL, $order = NULL, $offset = NULL, $db = NULL)
	{
		if (!isset($db))
        	$database = \Config\Database::connect();
		else
			$database = $db;

        $builder = $database->table($this->table);

		if (!empty($columns)) {
			$builder->select($columns);
		}

		if (!empty($order)) {
			$builder->orderBy($order);
		}

        if (!empty($conditions)) {
			$query = $builder->getWhere($conditions);
		} else {
            if (isset($limit) and isset($offset))
                $query = $builder->get($limit, $offset);
            else
                $query = $builder->get();
        }

		if (!$query or empty($query->getResultArray())) {
			return FALSE;
		} else {
			if ($limit !== 1) {
				return $query->getResultArray();
			} else {
                $result = $query->getResultArray();
				return $result[0];
			}
		}
	}

	public function custom_update($conditions, $values, $db = null)
	{
		if (!isset($db))
        	$database = \Config\Database::connect();
		else
			$database = $db;
		$builder = $database->table($this->table);

		if (!$builder->update($values, $conditions))
			$return_value = false;
		else
			$return_value = true;

		return $return_value;
	}

	public function insert_batch($data, $db = NULL)
	{
		if (!isset($db))
        	$database = \Config\Database::connect();
		else
			$database = $db;
			
        $builder = $database->table($this->table);

		$result = $builder->insertBatch($data);

		if ( ! $result)
		{
			log_message('error', 'Experiencing query error:');
			log_message('error', $database->error());
			log_message('error', 'SQL Query:');
			log_message('error', $database->getLastQuery());
		}

		return $result;
	}
}