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
        $this->api_key             = $_SERVER['HTTP_API_KEY'];
        $this->userModel           = new User();
        $this->webappResponseModel = new Webapp_response();
    }

    /**
     * Get Users
     */
    public function get()
    {
        $this->requested_by = $this->request->getVar('requester');
        if (($response = $this->_api_verification('users', 'get')) !== true) {
            return $response;
        }

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $user_id     = $this->request->getVar('user_id') ?: null;
        $employee_id = $this->request->getVar('employee_id') ?: null;

        // $where = ['is_deleted' => 0];
        // if (isset($user_id)) {
        //     $where['id'] = $user_id;
        // }

        // if (isset($employee_id)) {
        //     $where['employee_id'] = $employee_id;
        // }

        $users = $this->userModel->get_by_id($user_id, $employee_id);

        if (!$users) {
            $response = $this->failNotFound('No user found');
        } else {
            $response         = [];
            $response['data'] = $users;
            $response         = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get Recent Activities
     */
    public function get_recent_activities()
    {
        $this->requested_by = $this->request->getVar('requester');
        if (($response = $this->_api_verification('users', 'get')) !== true) {
            return $response;
        }

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $date_from         = date('Y-m-d', strtotime($this->request->getVar('date_from')));
        $date_to           = date('Y-m-d', strtotime($this->request->getVar('date_to')));
        $recent_activities = $this->userModel->get_recent_activity($this->requested_by, $date_from, $date_to);

        if (!$recent_activities) {
            $response = $this->failNotFound('No recent activities');
        } else {
            $response         = [];
            $response['data'] = $recent_activities;
            $response         = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Add user
     */
    public function add()
    {
        try {
            $this->requested_by = $this->request->getVar('requester');

            if (($response = $this->_api_verification('users', 'add')) !== true) {
                return $response;
            }

            $token = $this->request->getVar('token');
            if (($response = $this->_verify_requester($token)) !== true) {
                return $response;
            }


            $id = 116;
            $validation = \Config\Services::validation();

            // Load the validation rules from the config file
            $validationRules = config('Validation')->user;

            // Modify the email rule to is_unique with the current $id
            $validationRules['email']['rules'] .= '|is_unique[user.email]';
            $validationRules['email']['errors'] = [
                'is_unique' => 'Duplicate entry: The email or employee ID already exists',
            ];


            if (($response = $this->_validation_check(['user'], $validationRules)) !== true) {
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            }

            $values = [
                'employee_id'  => $this->request->getVar('employee_id'),
                'name'         => $this->request->getVar('name'),
                'email'        => $this->request->getVar('email'),
                'password'     => password_hash($this->request->getVar('password'), PASSWORD_BCRYPT),
                'role_id'      => $this->request->getVar('role_id'),
                'token'        => null,
                'token_expiry' => null,
                'added_by'     => $this->requested_by,
                'added_on'     => date('Y-m-d H:i:s'),
            ];

            // Use insert() method and catch database exceptions
            try {
                $this->userModel->insert($values);
                $response = $this->respond(['response' => 'User created successfully']);
            } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
                // Check if the exception is a duplicate entry error
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $response = $this->fail(['response' => 'Duplicate entry: The email or employee ID already exists']);
                } else {
                    // Handle other database-related errors
                    $response = $this->fail(['response' => 'Database error: ' . $e->getMessage()]);
                }
            }

            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        } catch (Exception $e) {
            // Handle other exceptions
            $response = $this->fail(['response' => 'Caught exception: ' . $e->getMessage()]);

            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }
    }

    /**
     * Update user
     */
    public function update($id = null)
    {
        $this->requested_by = $this->request->getVar('requester');
        if (($response = $this->_api_verification('users', 'update')) !== true) {
            return $response;
        }

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $validation = \Config\Services::validation();

        // Load the validation rules from the config file
        $validationRules = config('Validation')->user;

        // Modify the email rule to is_unique with the current $id
        $validationRules['email']['rules'] .= '|is_unique[user.email,id,' . $id . ']';
        $validationRules['email']['errors'] = [
            'is_unique' => 'Duplicate entry: The email or employee ID already exists',
        ];


        if (($response = $this->_validation_check(['user'], $validationRules)) !== true) {
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $employee_id = $this->request->getVar('employee_id');
        $where       = ['employee_id' => $employee_id, 'is_deleted' => 0];

        if (!$user = $this->userModel->select('', $where, 1)) {
            $response = $this->failNotFound('User not found');
        } elseif (!$this->_attempt_update($user)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'User updated successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete user
     */
    public function delete($id = null)
    {
        $this->requested_by = $this->request->getVar('requester');
        if (($response = $this->_api_verification('users', 'delete')) !== true) {
            return $response;
        }

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $user_id = $this->request->getVar('user_id');

        $where = ['id' => $user_id, 'is_deleted' => 0];

        if (!$user = $this->userModel->select('', $where, 1)) {
            $response = $this->failNotFound('User not found');
        } elseif (!$this->_attempt_delete($user)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'User deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($user)
    {
        $values = [
            'name'        => $this->request->getVar('name'),
            'employee_id' => $user['employee_id'],
            'email'       => $this->request->getVar('email'),
            'password'    => password_hash($this->request->getVar('password'), PASSWORD_BCRYPT),
            'role_id'     => $this->request->getVar('role_id'),
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if (!$this->userModel->update($user['id'], $values)) {
            return false;
        }
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($user)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ];

        if (!$this->userModel->update($user['id'], $values)) {
            return false;
        }

        return true;
    }
}
