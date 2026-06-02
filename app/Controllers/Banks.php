<?php

namespace App\Controllers;

class Banks extends MYTController
{
    protected $bankModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key      = $_SERVER['HTTP_API_KEY']  ?? '';
        $this->user_key     = $_SERVER['HTTP_USER_KEY'] ?? '';
        $this->requested_by = $this->user_key;
        $this->_load_essentials();
    }

    // GET banks/get_all
    public function get_all()
    {
        if (($response = $this->_api_verification('banks', 'get_all')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$banks = $this->bankModel->get_all()) {
            $response = $this->failNotFound('No banks found.');
        } else {
            $response = $this->respond(['data' => $banks, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // GET banks/search
    public function search()
    {
        if (($response = $this->_api_verification('banks', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $filters = [
            'bank_name' => $this->request->getVar('bank_name') ?: null,
        ];

        if (!$banks = $this->bankModel->search($filters)) {
            $response = $this->failNotFound('No banks found.');
        } else {
            $response = $this->respond(['data' => $banks, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // POST banks/create
    public function create()
    {
        if (($response = $this->_api_verification('banks', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $bank_name     = $this->request->getVar('bank_name');
        $account_name  = $this->request->getVar('account_name');
        $account_number = $this->request->getVar('account_number');
        $beginning_bal = $this->request->getVar('beginning_bal') ?: 0;

        if (!$bank_name) {
            $response = $this->fail('bank_name is required.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'bank_name'      => $bank_name,
            'account_name'   => $account_name  ?: null,
            'account_number' => $account_number ?: null,
            'beginning_bal'  => $beginning_bal,
            'current_bal'    => $beginning_bal,
            'added_by'       => $this->requested_by,
            'added_on'       => date('Y-m-d H:i:s'),
        ];

        if (!$this->bankModel->insert($data)) {
            $response = $this->fail('Unable to create bank.');
        } else {
            $response = $this->respond(['status' => 'success', 'response' => 'Bank created successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // POST banks/update
    public function update()
    {
        if (($response = $this->_api_verification('banks', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $bank_id = $this->request->getVar('bank_id');

        if (!$bank = $this->bankModel->get_details_by_id($bank_id)) {
            $response = $this->failNotFound('Bank not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'bank_name'      => $this->request->getVar('bank_name')      ?: $bank['bank_name'],
            'account_name'   => $this->request->getVar('account_name')   ?: $bank['account_name'],
            'account_number' => $this->request->getVar('account_number') ?: $bank['account_number'],
            'beginning_bal'  => $this->request->getVar('beginning_bal')  ?? $bank['beginning_bal'],
            'updated_by'     => $this->requested_by,
            'updated_on'     => date('Y-m-d H:i:s'),
        ];

        $this->bankModel->custom_update(['id' => $bank_id, 'is_deleted' => 0], $data);

        if ($this->db->error()['code']) {
            $response = $this->fail('Unable to update bank.');
        } else {
            $response = $this->respond(['status' => 'success', 'response' => 'Bank updated successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // POST banks/delete
    public function delete()
    {
        if (($response = $this->_api_verification('banks', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $bank_id = $this->request->getVar('bank_id');

        if (!$this->bankModel->get_details_by_id($bank_id)) {
            $response = $this->failNotFound('Bank not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $this->bankModel->custom_update(
            ['id' => $bank_id, 'is_deleted' => 0],
            ['is_deleted' => 1, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
        );

        if ($this->db->error()['code']) {
            $response = $this->fail('Unable to delete bank.');
        } else {
            $response = $this->respond(['status' => 'success', 'response' => 'Bank deleted successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->bankModel           = model('App\Models\Bank');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}