<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Webapp_response;

class Login extends MYTController
{
    protected $userModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->userModel = new User();
        $this->webappResponseModel = new Webapp_response();
    }
    
    /**
     * login to website
     */
    public function index()
    {
        // var_dump($this->request->getVar('api_key'));
        // die();
        $this->requested_by = 0;
        if (($response = $this->_api_verification('login', 'index')) !== true) {
            return $response;
        }

        return $this->_attempt_login();
    }

    protected function _attempt_login()
    {
        $email = $this->request->getVar('email');
        $password = $this->request->getVar('password');
        //var_dump($email,$password);die;
        // if user doesn't exist
        if (!$user = $this->userModel->get_details_by_email($email)) {
            $response = $this->failUnauthorized('Unregistered user');
        } elseif (!password_verify($password, $user['password'])) { // if password incorrect
            $response = $this->failUnauthorized('Incorrect Password');
        } else {
            if ($token_details = $this->_register_token($user['id'])) {
                $user['token'] = $token_details['token'];
                $user['token_expiry'] = $token_details['token_expiry'];
            }
            
            unset($user['password']);
            $response = $this->respond($user);
        }
        
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }
}
