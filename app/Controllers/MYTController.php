<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;
use App\Models\User;
use App\Models\Webapp_log;
use App\Models\Webapp_response;

class MYTController extends ResourceController
{
    use ResponseTrait;

    protected $helpers      = ['form', 'url', 'text'];
    protected $api_key;
    protected $user_key;
    protected $validation;
    protected $webapp_log_id;
    protected $requested_by;

    protected function _validation_check($rule_group, $custom_rules = null)
    {
        $this->validation = \Config\Services::validation();
        $rules = [];
        foreach ($rule_group as $rule) {
            $current_rule = $this->validation->getRuleGroup($rule);
            $rules = array_merge($rules, $current_rule);
        }
        if (isset($custom_rules)) {
            $rules = array_merge($rules, $custom_rules);
        }
        if (!$this->validate($rules)) {
            $errors = $this->validator->getErrors();
            return $this->fail($errors, 400);
        }
        return true;
    }

    protected function _verify_client()
    {
        return $this->api_key === API_KEY;
    }

    protected function _api_verification($controller, $method)
    {
        $webappLogModel      = new Webapp_log();
        $webappResponseModel = new Webapp_response();

        $data_received = $this->request->getPost();
        if (array_key_exists('password', $data_received)) {
            $data_received['password'] = password_hash($data_received['password'], PASSWORD_BCRYPT);
        }

        $values = [
            'controller'   => $controller,
            'method'       => $method,
            'ip_address'   => $this->request->getServer('REMOTE_ADDR'),
            'data_received'=> json_encode($data_received),
            'requested_by' => $this->requested_by,
            'requested_on' => date('Y-m-d H:i:s'),
        ];

        if (!$insertID = $webappLogModel->insert($values))
            return false;

        $this->webapp_log_id = $insertID;

        if (!$this->_verify_client()) {
            $response = $this->failUnauthorized('Invalid API key.');
            $webappResponseModel->record_response($insertID, $response);
            return $response;
        }

        return true;
    }

    protected function _verify_requester($token)
    {
        $userModel           = new User();
        $webappResponseModel = new Webapp_response();
        $date_today          = new \DateTime();

        if (empty($token) && empty($this->requested_by)) {
            $response = $this->failUnauthorized('Invalid Auth token');
            $webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $requester = $userModel->where([
            'id'         => $this->requested_by,
            'token'      => $token,
            'is_deleted' => 0,
        ])->first();

        if (!$requester) {
            $response = $this->failUnauthorized('Token Expired');
            $webappResponseModel->record_response($this->webapp_log_id, $response); 
            return $response;
        }

        $token_expiry = new \DateTime($requester['token_expiry']);
        if ($token_expiry < $date_today) {
            $response = $this->failUnauthorized('Token Expired');
            $userModel->update($this->requested_by, [
                'token'      => null,
                'token_expiry'=> null,
                'updated_on' => date('Y-m-d H:i:s'),
                'updated_by' => $this->requested_by,
            ]);
            $webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        return true;
    }

    protected function _register_token($requested_by)
    {
        $userModel      = new User();
        $webappLogModel = new Webapp_log();

        $token            = $this->generate_token(40);
        $current_datetime = date('Y-m-d H:i:s');
        $token_expiry     = date('Y-m-d H:i:s', strtotime("$current_datetime +9 hours"));

        if (!$userModel->update($requested_by, [
            'token'        => $token,
            'token_expiry' => $token_expiry,
            'updated_by'   => $requested_by,
            'updated_on'   => $current_datetime,
        ])) return false;

        if (!$webappLogModel->update($this->webapp_log_id, [
            'requested_by' => $requested_by,
        ])) return false;

        return ['token' => $token, 'token_expiry' => $token_expiry];
    }

    protected function _clear_token()
    {
        $userModel = new User();
        return $userModel->update($this->requested_by, [
            'token'        => null,
            'token_expiry' => null,
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s'),
        ]);
    }

    protected function generate_token($length)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $size  = strlen($chars);
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[rand(0, $size - 1)];
        }
        return $token;
    }
}