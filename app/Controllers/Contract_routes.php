<?php

namespace App\Controllers;

class Contract_routes extends MYTController
{
    protected $contractRouteModel;
    protected $contractModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];
        $this->_load_essentials();
    }

    public function index()
    {
        if (($response = $this->_api_verification('contract_routes', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id = $this->request->getVar('contract_id');

        if (!$routes = $this->contractRouteModel->get_by_contract_id($contract_id)) {
            $response = $this->failNotFound('No routes found for this contract.');
        } else {
            $response = $this->respond(['data' => $routes, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function create()
    {
        if (($response = $this->_api_verification('contract_routes', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contract_id = $this->request->getVar('contract_id');

        if (!$this->contractModel->select('', ['id' => $contract_id, 'is_deleted' => 0], 1)) {
            $response = $this->failNotFound('Contract not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'contract_id' => $contract_id,
            'origin'      => $this->request->getVar('origin'),
            'destination' => $this->request->getVar('destination'),
            'added_by'    => $this->requested_by,
            'added_on'    => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->contractRouteModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add route. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Route added successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update($id = null)
    {
        if (($response = $this->_api_verification('contract_routes', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $route_id  = $this->request->getVar('route_id');
        $condition = ['id' => $route_id, 'is_deleted' => 0];

        if (!$this->contractRouteModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Route not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'origin'      => $this->request->getVar('origin'),
            'destination' => $this->request->getVar('destination'),
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->contractRouteModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update route. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Route updated successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function delete($id = null)
    {
        if (($response = $this->_api_verification('contract_routes', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $route_id  = $this->request->getVar('route_id');
        $condition = ['id' => $route_id, 'is_deleted' => 0];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->contractRouteModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Route not found.');
        } elseif (!$this->contractRouteModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete route. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Route deleted successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->contractRouteModel  = model('App\Models\Contract_route');
        $this->contractModel       = model('App\Models\Contract');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}