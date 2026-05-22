<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Webapp_response;

class Users extends MYTController
{
    protected $userModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key      = $_SERVER['HTTP_API_KEY']  ?? '';
        $this->user_key     = $_SERVER['HTTP_USER_KEY'] ?? '';
        $this->requested_by = $this->user_key;
        $this->userModel           = new User();
        $this->webappResponseModel = new Webapp_response();
    }

    public function index()
    {
        if (($response = $this->_api_verification('users', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $users = $this->userModel->get_all();
        if (!$users) {
            $response = $this->failNotFound('No users found.');
        } else {
            $response = $this->respond(['data' => $users, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function create()
    {
        if (($response = $this->_api_verification('users', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $data = [
            'first_name' => $this->request->getVar('first_name'),
            'last_name'  => $this->request->getVar('last_name'),
            'email'      => $this->request->getVar('email'),
            'password'   => password_hash($this->request->getVar('password'), PASSWORD_BCRYPT),
            'role'       => $this->request->getVar('role') ?: 'viewer',
            'added_by'   => $this->requested_by,
            'added_on'   => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->userModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add user. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'User added successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update($id = null)
    {
        if (($response = $this->_api_verification('users', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $user_id   = $this->request->getVar('user_id');
        $condition = ['id' => $user_id, 'is_deleted' => 0];

        if (!$this->userModel->select('', $condition, 1)) {
            $response = $this->failNotFound('User not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'first_name' => $this->request->getVar('first_name'),
            'last_name'  => $this->request->getVar('last_name'),
            'email'      => $this->request->getVar('email'),
            'role'       => $this->request->getVar('role') ?: 'viewer',
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ];

        $password = $this->request->getVar('password');
        if ($password) {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->userModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update user. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'User updated successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function delete($id = null)
    {
        if (($response = $this->_api_verification('users', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $user_id   = $this->request->getVar('user_id');
        $condition = ['id' => $user_id, 'is_deleted' => 0];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->userModel->select('', $condition, 1)) {
            $response = $this->failNotFound('User not found.');
        } elseif (!$this->userModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete user. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'User deleted successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }
}