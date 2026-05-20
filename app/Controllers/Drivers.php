<?php

namespace App\Controllers;

class Drivers extends MYTController
{
    protected $driverModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];
        $this->_load_essentials();
    }

    public function index()
    {
        if (($response = $this->_api_verification('drivers', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$drivers = $this->driverModel->get_all()) {
            $response = $this->failNotFound('No drivers found.');
        } else {
            $response = $this->respond(['data' => $drivers, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function search()
    {
        if (($response = $this->_api_verification('drivers', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $name           = $this->request->getVar('name')            ?: null;
        $license_number = $this->request->getVar('license_number')  ?: null;
        $status         = $this->request->getVar('status')          ?: null;

        if (!$drivers = $this->driverModel->search($name, $license_number, $status)) {
            $response = $this->failNotFound('No drivers found.');
        } else {
            $response = $this->respond(['data' => $drivers, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function details()
    {
        if (($response = $this->_api_verification('drivers', 'details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $driver_id = $this->request->getVar('driver_id');

        if (!$driver = $this->driverModel->get_details_by_id($driver_id)) {
            $response = $this->failNotFound('Driver not found.');
        } else {
            $response = $this->respond(['data' => $driver, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function create()
    {
        if (($response = $this->_api_verification('drivers', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $data = [
            'first_name'     => $this->request->getVar('first_name'),
            'last_name'      => $this->request->getVar('last_name'),
            'contact_number' => $this->request->getVar('contact_number') ?: null,
            'license_number' => $this->request->getVar('license_number') ?: null,
            'address'        => $this->request->getVar('address')        ?: null,
            'status'         => 'active',
            'added_by'       => $this->requested_by,
            'added_on'       => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->driverModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add driver. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Driver added successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update()
    {
        if (($response = $this->_api_verification('drivers', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $driver_id = $this->request->getVar('driver_id');
        $condition = ['id' => $driver_id, 'is_deleted' => 0];

        if (!$this->driverModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Driver not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'first_name'     => $this->request->getVar('first_name'),
            'last_name'      => $this->request->getVar('last_name'),
            'contact_number' => $this->request->getVar('contact_number') ?: null,
            'license_number' => $this->request->getVar('license_number') ?: null,
            'address'        => $this->request->getVar('address')        ?: null,
            'status'         => $this->request->getVar('status'),
            'updated_by'     => $this->requested_by,
            'updated_on'     => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->driverModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update driver. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Driver updated successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function delete()
    {
        if (($response = $this->_api_verification('drivers', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $driver_id = $this->request->getVar('driver_id');
        $condition = ['id' => $driver_id, 'is_deleted' => 0];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->driverModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Driver not found.');
        } elseif (!$this->driverModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete driver. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Driver deleted successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->driverModel          = model('App\Models\Driver');
        $this->webappResponseModel  = model('App\Models\Webapp_response');
    }
}