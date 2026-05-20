<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;

// Model Declaration
use App\Models\User;
use App\Models\Webapp_log;
use App\Models\Webapp_response;

header("Access-Control-Allow-Origin: *");

class MYTController extends ResourceController
{
    use ResponseTrait;
    protected $helpers = ['form', 'url', 'text'];

    protected $api_key;

    protected $validation;

    protected $webapp_log_id;

    protected $requested_by;

    protected function _validation_check($rule_group, $custom_rules = null)
    {
        $this->validation = \Config\Services::validation();
        $rules = [];
        foreach($rule_group as $rule) {
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
        if ($this->api_key == API_KEY) {
            return true;
        } else {
            return false;
        }
    }

    protected function _api_verification($controller, $method)
    {
        $webappLogModel = new Webapp_log();
        $webappResponseModel = new Webapp_response();

        $data_received = $this->request->getPost();
        if (array_key_exists('password', $data_received)) {
            $data_received['password'] = password_hash($data_received['password'], PASSWORD_BCRYPT);
        }

        $values = [
            'controller' => $controller,
            'method' => $method,
            'ip_address' => $this->request->getServer('REMOTE_ADDR'),
            'data_received' => json_encode($data_received),
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

    protected function _store_request($data, $method, $user_id)
    {
        $requestModel = new Request();

        $values = [
            'data_sent' => json_encode($data),
            'method' => $method,
            'user_id' => $user_id,
            'requested_on' => date('Y-m-d H:i:s')
        ];

        return $requestModel->insert($values);
    }

    protected function _verify_requester($token)
    {
        $userModel = new User();

        $date_today = new \DateTime();
        $webappResponseModel = new Webapp_response();

        if (empty($token) && empty($this->requested_by)) {
            $response = $this->failUnauthorized('Invalid Auth token');
            $webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        } else {
            //Check requester token and token expiry validity
            $where = [
                'id' => $this->requested_by,
                'token' => $token,
                'is_deleted' => 0
            ];

            if (!$requester = $userModel->select('', $where, 1)) {
                $response = $this->failUnauthorized('Token Expired');
                $webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            } else {
                $token_expiry = new \DateTime($requester['token_expiry']);

                if (empty($requester)) {
                    $response = $this->failUnauthorized('Invalid requester');
                    $webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                } else if ($requester['token'] !== $token) {
                    $response = $this->failUnauthorized('Invalid Auth token');
                    $webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                } else if ($token_expiry < $date_today) {
                    $response = $this->failUnauthorized('Token Expired');

                    $values = [
                        'token' => null,
                        'token_expiry' => null,
                        'updated_on' => date('Y-m-d H:i:s'),
                        'updated_by' => $this->requested_by
                    ];
                    $userModel->update($this->requested_by, $values);

                    $webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }

                return true;
            }
        }
    }

    protected function _register_token($requested_by)
    {
        $userModel = new User();

        $token = $this->generate_token(40);

        $current_datetime = date('Y-m-d H:i:s');
        $token_expiry = date("Y-m-d H:i:s", strtotime("$current_datetime +9 hours"));

        $values = [
            'token' => $token,
            'token_expiry' => $token_expiry,
            'updated_by' => $requested_by,
            'updated_on' => $current_datetime
        ];

        if (!$userModel->update($requested_by, $values))
            return false;

        // Update requester for webapp log
        $webappLogModel = new Webapp_log();
        $value = ['requested_by' => $requested_by];

        if (!$webappLogModel->update($this->webapp_log_id, $value))
            return false;

        return ['token' => $token, 'token_expiry' => $token_expiry];
    }

    protected function _clear_token()
    {
        $userModel = new User();
        $values = [
            'token' => null,
            'token_expiry' => null,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$userModel->update($this->requested_by, $values))
            return false;
        return true;
    }

    /**
     * Used for token randomizer
     */
    protected function generate_token($length)
    {
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $size = strlen($chars);
        $token = '';
        for($i = 0; $i < $length; $i++) {
            $str = $chars[rand(0, $size - 1)];
            $token .= $str;
        }
        return $token;
    }
}
