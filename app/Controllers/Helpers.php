<?php

namespace App\Controllers;

class Helpers extends MYTController
{
    protected $helperModel;
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
        if (($response = $this->_api_verification('helpers', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$helpers = $this->helperModel->get_all()) {
            $response = $this->failNotFound('No helpers found.');
        } else {
            $response = $this->respond(['data' => $helpers, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function search()
    {
        if (($response = $this->_api_verification('helpers', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $name   = $this->request->getVar('name')   ?: null;
        $status = $this->request->getVar('status') ?: null;

        if (!$helpers = $this->helperModel->search($name, $status)) {
            $response = $this->failNotFound('No helpers found.');
        } else {
            $response = $this->respond(['data' => $helpers, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function details()
    {
        if (($response = $this->_api_verification('helpers', 'details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $helper_id = $this->request->getVar('helper_id');

        if (!$helper = $this->helperModel->get_details_by_id($helper_id)) {
            $response = $this->failNotFound('Helper not found.');
        } else {
            $response = $this->respond(['data' => $helper, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function create()
    {
        if (($response = $this->_api_verification('helpers', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $data = [
            'first_name'     => $this->request->getVar('first_name'),
            'last_name'      => $this->request->getVar('last_name'),
            'contact_number' => $this->request->getVar('contact_number') ?: null,
            'address'        => $this->request->getVar('address')        ?: null,
            'status'         => 'active',
            'added_by'       => $this->requested_by,
            'added_on'       => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->helperModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add helper. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['status' => 'success', 'response' => 'Helper added successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update($id = null)
    {
        if (($response = $this->_api_verification('helpers', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $helper_id = $this->request->getVar('helper_id');
        $condition = ['id' => $helper_id, 'is_deleted' => 0];

        if (!$this->helperModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Helper not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'first_name'     => $this->request->getVar('first_name'),
            'last_name'      => $this->request->getVar('last_name'),
            'contact_number' => $this->request->getVar('contact_number') ?: null,
            'address'        => $this->request->getVar('address')        ?: null,
            'status'         => $this->request->getVar('status'),
            'updated_by'     => $this->requested_by,
            'updated_on'     => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->helperModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update helper. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['status' => 'success', 'response' => 'Helper updated successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function delete($id = null)
    {
        if (($response = $this->_api_verification('helpers', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $helper_id = $this->request->getVar('helper_id');
        $condition = ['id' => $helper_id, 'is_deleted' => 0];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->helperModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Helper not found.');
        } elseif (!$this->helperModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete helper. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['status' => 'success', 'response' => 'Helper deleted successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->helperModel         = model('App\Models\Helper');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}