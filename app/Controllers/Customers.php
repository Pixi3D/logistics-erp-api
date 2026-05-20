<?php

namespace App\Controllers;

class Customers extends MYTController
{
    protected $customerModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];
        $this->_load_essentials();
    }

    public function index()
    {
        if (($response = $this->_api_verification('customers', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$customers = $this->customerModel->get_all()) {
            $response = $this->failNotFound('No customers found.');
        } else {
            $response = $this->respond(['data' => $customers, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function search()
    {
        if (($response = $this->_api_verification('customers', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $name   = $this->request->getVar('name')   ?: null;
        $status = $this->request->getVar('status') ?: null;

        if (!$customers = $this->customerModel->search($name, $status)) {
            $response = $this->failNotFound('No customers found.');
        } else {
            $response = $this->respond(['data' => $customers, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function details()
    {
        if (($response = $this->_api_verification('customers', 'details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id = $this->request->getVar('customer_id');

        if (!$customer = $this->customerModel->get_details_by_id($customer_id)) {
            $response = $this->failNotFound('Customer not found.');
        } else {
            $response = $this->respond(['data' => $customer, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function create()
    {
        if (($response = $this->_api_verification('customers', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $data = [
            'name'           => $this->request->getVar('name'),
            'contact_person' => $this->request->getVar('contact_person') ?: null,
            'contact_number' => $this->request->getVar('contact_number') ?: null,
            'address'        => $this->request->getVar('address')        ?: null,
            'status'         => 'active',
            'added_by'       => $this->requested_by,
            'added_on'       => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->customerModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add customer. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Customer added successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update()
    {
        if (($response = $this->_api_verification('customers', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id = $this->request->getVar('customer_id');
        $condition   = ['id' => $customer_id, 'is_deleted' => 0];

        if (!$this->customerModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Customer not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'name'           => $this->request->getVar('name'),
            'contact_person' => $this->request->getVar('contact_person') ?: null,
            'contact_number' => $this->request->getVar('contact_number') ?: null,
            'address'        => $this->request->getVar('address')        ?: null,
            'status'         => $this->request->getVar('status'),
            'updated_by'     => $this->requested_by,
            'updated_on'     => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->customerModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update customer. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Customer updated successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function delete()
    {
        if (($response = $this->_api_verification('customers', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id = $this->request->getVar('customer_id');
        $condition   = ['id' => $customer_id, 'is_deleted' => 0];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->customerModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Customer not found.');
        } elseif (!$this->customerModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete customer. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Customer deleted successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->customerModel       = model('App\Models\Customer');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}