<?php

namespace App\Controllers;

class Customers extends MYTController
{
    protected $customerModel;
    protected $customerAttachmentModel; 
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key      = $_SERVER['HTTP_API_KEY']  ?? '';
        $this->user_key     = $_SERVER['HTTP_USER_KEY'] ?? '';
        $this->requested_by = $this->user_key;
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
            'first_name'     => $this->request->getVar('first_name'),
            'last_name'      => $this->request->getVar('last_name'),
            'middle_name'    => $this->request->getVar('middle_name')    ?: null,
            'suffix'         => $this->request->getVar('suffix')         ?: null,
            'trade_name'     => $this->request->getVar('trade_name')     ?: null,
            'bir_name'       => $this->request->getVar('bir_name')       ?: null,
            'trade_address'  => $this->request->getVar('trade_address')  ?: null,
            'bir_address'    => $this->request->getVar('bir_address')    ?: null,
            'tin'            => $this->request->getVar('tin')            ?: null,
            'term'           => $this->request->getVar('term')           ?: 0,
            'credit_limit'   => $this->request->getVar('credit_limit')   ?: 0,
            'payee'          => $this->request->getVar('payee')          ?: null,
            'vat_type'       => $this->request->getVar('vat_type')       ?: null,
            'bir_2307'       => $this->request->getVar('bir_2307')       ?: null,
            'contact_person' => $this->request->getVar('contact_person') ?: null,
            'contact_number' => $this->request->getVar('contact_number') ?: null,
            'email'          => $this->request->getVar('email')          ?: null,
            'address'        => $this->request->getVar('address')        ?: null,
            'added_by'       => $this->requested_by,
            'added_on'       => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$customer_id = $this->customerModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add customer. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Handle attachments if any
        $files = $this->request->getFiles();
        if (!empty($files['attachments'])) {
            $upload_path = FCPATH . 'uploads/customers/' . $customer_id . '/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }

            foreach ($files['attachments'] as $file) {
                if (!$file->isValid() || $file->hasMoved()) continue;

                $new_name = $file->getRandomName();
                $file->move($upload_path, $new_name);

                $attachment_data = [
                    'customer_id' => $customer_id,
                    'file_name'   => $file->getClientName(),
                    'file_path'   => 'uploads/customers/' . $customer_id . '/' . $new_name,
                    'added_by'    => $this->requested_by,
                    'added_on'    => date('Y-m-d H:i:s')
                ];

                if (!$this->customerAttachmentModel->insert($attachment_data)) {
                    $this->db->transRollback();
                    $response = $this->fail('Customer created but failed to save attachment.');
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
            }
        }

        $this->db->transCommit();
        $response = $this->respond(['response' => 'Customer added successfully.']);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update($id = null)
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
            'first_name'    => $this->request->getVar('first_name'),
            'last_name'     => $this->request->getVar('last_name'),
            'middle_name'   => $this->request->getVar('middle_name')   ?: null,
            'suffix'        => $this->request->getVar('suffix')        ?: null,
            'trade_name'    => $this->request->getVar('trade_name')    ?: null,
            'bir_name'      => $this->request->getVar('bir_name')      ?: null,
            'trade_address' => $this->request->getVar('trade_address') ?: null,
            'bir_address'   => $this->request->getVar('bir_address')   ?: null,
            'tin'           => $this->request->getVar('tin')           ?: null,
            'term'          => $this->request->getVar('term')          ?: 0,
            'credit_limit'  => $this->request->getVar('credit_limit')  ?: 0,
            'payee'         => $this->request->getVar('payee')         ?: null,
            'vat_type'      => $this->request->getVar('vat_type')      ?: null,
            'bir_2307'      => $this->request->getVar('bir_2307')      ?: null,
            'contact_person'=> $this->request->getVar('contact_person') ?: null,
            'contact_number'=> $this->request->getVar('contact_number') ?: null,
            'email'         => $this->request->getVar('email')          ?: null,
            'address'       => $this->request->getVar('address')        ?: null,
            'updated_by'    => $this->requested_by,
            'updated_on'    => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->customerModel->custom_update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail($this->db->error()['message'] ?? 'Unable to update customer.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Customer updated successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function delete($id = null)
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
        } elseif (!$this->customerModel->custom_update($condition, $data)) {
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
        $this->customerAttachmentModel  = model('App\Models\Customer_attachment');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}