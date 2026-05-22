<?php

namespace App\Controllers;

use App\Models\Webapp_log;
use App\Models\Webapp_response;

class Trail extends MYTController
{
    protected $webappLogModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key      = $_SERVER['HTTP_API_KEY']  ?? '';
        $this->user_key     = $_SERVER['HTTP_USER_KEY'] ?? '';
        $this->requested_by = $this->user_key;
        $this->webappLogModel      = new Webapp_log();
        $this->webappResponseModel = new Webapp_response();
    }

    public function index()
    {
        if (($response = $this->_api_verification('trail', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $controller = $this->request->getVar('table_name') ?: null;
        $search     = $this->request->getVar('search')     ?: null;

        $trails = $this->webappLogModel->get_all($controller, $search);
        if ($trails === false) {
            $response = $this->failNotFound('No trail records found.');
        } else {
            $response = $this->respond(['data' => $trails, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }
}