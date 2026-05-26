<?php

namespace App\Controllers;

class Trucks extends MYTController
{
    protected $truckModel;
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
        if (($response = $this->_api_verification('trucks', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$trucks = $this->truckModel->get_all()) {
            $response = $this->failNotFound('No trucks found.');
        } else {
            $response = $this->respond(['data' => $trucks, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function search()
    {
        if (($response = $this->_api_verification('trucks', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $unit_code    = $this->request->getVar('unit_code')    ?: null;
        $plate_number = $this->request->getVar('plate_number') ?: null;
        $status       = $this->request->getVar('status')       ?: null;

        if (!$trucks = $this->truckModel->search($unit_code, $plate_number, $status)) {
            $response = $this->failNotFound('No trucks found.');
        } else {
            $response = $this->respond(['data' => $trucks, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function details()
    {
        if (($response = $this->_api_verification('trucks', 'details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $truck_id = $this->request->getVar('truck_id');

        if (!$truck = $this->truckModel->get_details_by_id($truck_id)) {
            $response = $this->failNotFound('Truck not found.');
        } else {
            $response = $this->respond(['data' => $truck, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function create()
    {
        if (($response = $this->_api_verification('trucks', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $data = [
            'unit_code'    => $this->request->getVar('unit_code'),
            'plate_number' => $this->request->getVar('plate_number'),
            'truck_type'  => $this->request->getVar('truck_type')  ?: null,  // ADD
            'color'        => $this->request->getVar('color')        ?: null,
            'capacity'     => $this->request->getVar('capacity')     ?: null,
            'or_expiry'    => $this->request->getVar('or_expiry')    ?: null,  // ADD
            'km_per_liter' => $this->request->getVar('km_per_liter') ?: null,
            'status'       => $this->request->getVar('status')       ?: 'active',
            'remarks'      => $this->request->getVar('remarks')      ?: null,
            'added_by'     => $this->requested_by,
            'added_on'     => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->truckModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add truck. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Truck added successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update($id = null)
    {
        if (($response = $this->_api_verification('trucks', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $truck_id  = $this->request->getVar('truck_id');
        $condition = ['id' => $truck_id, 'is_deleted' => 0];

        if (!$this->truckModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Truck not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'unit_code'    => $this->request->getVar('unit_code'),
            'plate_number' => $this->request->getVar('plate_number'),
            'truck_type'  => $this->request->getVar('truck_type')  ?: null,  // ADD
            'color'        => $this->request->getVar('color')        ?: null,
            'capacity'     => $this->request->getVar('capacity')     ?: null,
            'or_expiry'    => $this->request->getVar('or_expiry')    ?: null,  // ADD
            'km_per_liter' => $this->request->getVar('km_per_liter') ?: null,
            'status'       => $this->request->getVar('status')       ?: 'active',
            'remarks'      => $this->request->getVar('remarks')      ?: null,
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->truckModel->custom_update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update truck. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Truck updated successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function delete($id = null)
    {
        if (($response = $this->_api_verification('trucks', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $truck_id  = $this->request->getVar('truck_id');
        $condition = ['id' => $truck_id, 'is_deleted' => 0];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->truckModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Truck not found.');
        } elseif (!$this->truckModel->custom_update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete truck. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Truck deleted successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->truckModel          = model('App\Models\Truck');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}