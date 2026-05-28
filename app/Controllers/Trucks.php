<?php

namespace App\Controllers;

class Trucks extends MYTController
{
    protected $truckModel;
    protected $truckAttachmentModel;
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
            'unit_code'    => $this->request->getPost('unit_code'),
            'plate_number' => $this->request->getPost('plate_number'),
            'truck_type'   => $this->request->getPost('truck_type')   ?: null,
            'or_expiry'    => $this->request->getPost('or_expiry')    ?: null,
            'color'        => $this->request->getPost('color')        ?: null,
            'capacity'     => $this->request->getPost('capacity')     ?: null,
            'km_per_liter' => $this->request->getPost('km_per_liter') ?: null,
            'status'       => $this->request->getPost('status')       ?: 'active',
            'remarks'      => $this->request->getPost('remarks')      ?: null,
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$truck_id = $this->truckModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add truck. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $files = $this->request->getFiles();
        if (!empty($files['attachments'])) {
            $upload_path = FCPATH . 'uploads/trucks/' . $truck_id . '/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }

            foreach ($files['attachments'] as $file) {
                if (!$file->isValid() || $file->hasMoved()) continue;

                $new_name = $file->getRandomName();
                $file->move($upload_path, $new_name);

                $client_name = $file->getClientName();
                $file_type   = (strpos($client_name, 'cr_') === 0) ? 'CR' : 'OR';
                $unit_code   = preg_replace('/\s+/', '', $this->request->getPost('unit_code'));
                $ext         = pathinfo($client_name, PATHINFO_EXTENSION);
                $display_name = $file_type === 'CR'
                    ? 'cr_' . $unit_code . '_CR.' . $ext
                    : $unit_code . '_OR.' . $ext;

                $attachment_data = [
                    'truck_id'  => $truck_id,
                    'file_name' => $display_name,
                    'file_type' => $file_type,
                    'file_path' => 'uploads/trucks/' . $truck_id . '/' . $new_name,
                    'added_by'  => $this->requested_by,
                    'added_on'  => date('Y-m-d H:i:s')
                ];
                if (!$this->truckAttachmentModel->insert($attachment_data)) {
                    $this->db->transRollback();
                    $response = $this->fail('Truck created but failed to save attachment.');
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
            }
        }

        $this->db->transCommit();
        $response = $this->respond(['response' => 'Truck added successfully.', 'status' => 'success']);

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

        $truck_id  = $this->request->getPost('truck_id') ?: $this->request->getVar('truck_id');
        $condition = ['id' => $truck_id, 'is_deleted' => 0];

        if (!$this->truckModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Truck not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

       $data = [
            'unit_code'    => $this->request->getPost('unit_code'),
            'plate_number' => $this->request->getPost('plate_number'),
            'truck_type'   => $this->request->getPost('truck_type')   ?: null,
            'or_expiry'    => $this->request->getPost('or_expiry')    ?: null,
            'color'        => $this->request->getPost('color')        ?: null,
            'capacity'     => $this->request->getPost('capacity')     ?: null,
            'km_per_liter' => $this->request->getPost('km_per_liter') ?: null,
            'status'       => $this->request->getPost('status')       ?: 'active',
            'remarks'      => $this->request->getPost('remarks')      ?: null,
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        $this->truckModel->custom_update($condition, $data);

        if ($this->db->error()['code']) {
            $this->db->transRollback();
            $response = $this->fail($this->db->error()['message'] ?? 'Unable to update truck. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $files = $this->request->getFiles();
        if (!empty($files['attachments'])) {
            $upload_path = FCPATH . 'uploads/trucks/' . $truck_id . '/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }

            foreach ($files['attachments'] as $file) {
                if (!$file->isValid() || $file->hasMoved()) continue;

                $new_name = $file->getRandomName();
                $file->move($upload_path, $new_name);

               $client_name = $file->getClientName();
                $file_type   = (strpos($client_name, 'cr_') === 0) ? 'CR' : 'OR';
                $unit_code   = preg_replace('/\s+/', '', $this->request->getPost('unit_code'));
                $ext         = pathinfo($client_name, PATHINFO_EXTENSION);
                $display_name = $file_type === 'CR'
                    ? 'cr_' . $unit_code . '_CR.' . $ext
                    : $unit_code . '_OR.' . $ext;

                $attachment_data = [
                    'truck_id'  => $truck_id,
                    'file_name' => $display_name,
                    'file_type' => $file_type,
                    'file_path' => 'uploads/trucks/' . $truck_id . '/' . $new_name,
                    'added_by'  => $this->requested_by,
                    'added_on'  => date('Y-m-d H:i:s')
                ];
                if (!$this->truckAttachmentModel->insert($attachment_data)) {
                    $this->db->transRollback();
                    $response = $this->fail('Truck updated but failed to save attachment.');
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
            }
        }

        $this->db->transCommit();
        $response = $this->respond(['response' => 'Truck updated successfully.', 'status' => 'success']);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function download_attachment()
    {
        $file_path = $this->request->getVar('file_path');
        $file_name = $this->request->getVar('file_name');

        $full_path = FCPATH . $file_path;

        if (!file_exists($full_path)) {
            return $this->failNotFound('File not found.');
        }

        $mime = mime_content_type($full_path);

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $file_name . '"')
            ->setHeader('Content-Length', filesize($full_path))
            ->setBody(file_get_contents($full_path));
    }

    public function get_attachments()
{
    if (($response = $this->_api_verification('trucks', 'get_attachments')) !== true)
        return $response;

    $token = $this->request->getVar('token');
    if (($response = $this->_verify_requester($token)) !== true)
        return $response;

    $truck_id = $this->request->getVar('truck_id');

    $attachments = $this->truckAttachmentModel->get_by_truck_id($truck_id);

    if (!$attachments) {
        $response = $this->respond(['data' => [], 'status' => 'success']);
    } else {
        $response = $this->respond(['data' => $attachments, 'status' => 'success']);
    }

    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
    return $response;
}
public function delete_attachment()
{
    if (($response = $this->_api_verification('trucks', 'delete_attachment')) !== true)
        return $response;

    $token = $this->request->getVar('token');
    if (($response = $this->_verify_requester($token)) !== true)
        return $response;

    $attachment_id = $this->request->getVar('attachment_id');
    $condition     = ['id' => $attachment_id, 'is_deleted' => 0];
    $data          = ['is_deleted' => 1, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')];

    $this->db = db_connect();
    $this->truckAttachmentModel->custom_update($condition, $data);

    if ($this->db->error()['code']) {
        $response = $this->fail('Failed to remove attachment.');
    } else {
        $response = $this->respond(['response' => 'Attachment removed.', 'status' => 'success']);
    }

    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
    return $response;
}

public function update_status()
{
    if (($response = $this->_api_verification('trucks', 'update_status')) !== true)
        return $response;

    $token = $this->request->getVar('token');
    if (($response = $this->_verify_requester($token)) !== true)
        return $response;

    $truck_id = $this->request->getPost('truck_id');
    $status   = $this->request->getPost('status');

    $condition = ['id' => $truck_id, 'is_deleted' => 0];
    $data      = [
        'status'     => $status,
        'updated_by' => $this->requested_by,
        'updated_on' => date('Y-m-d H:i:s'),
    ];

    $this->db = db_connect();
    $this->truckModel->custom_update($condition, $data);

    if ($this->db->error()['code']) {
        $response = $this->fail('Failed to update truck status.');
    } else {
        $response = $this->respond(['response' => 'Truck status updated.', 'status' => 'success']);
    }

    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
    return $response;
}

public function get_suggestions()
{
    if (($response = $this->_api_verification('trucks', 'get_suggestions')) !== true)
        return $response;

    $token = $this->request->getVar('token');
    if (($response = $this->_verify_requester($token)) !== true)
        return $response;

    $keyword = $this->request->getVar('keyword') ?: '';

    $database = \Config\Database::connect();

    $trucks = $database->query("
        SELECT id, unit_code AS label, 'unit_code' AS type
        FROM truck
        WHERE is_deleted = 0
          AND (unit_code LIKE ? OR plate_number LIKE ? OR truck_type LIKE ?)
        LIMIT 10
    ", ["%$keyword%", "%$keyword%", "%$keyword%"])->getResultArray();

    $response = $this->respond([
        'data'   => ['trucks' => $trucks],
        'status' => 'success'
    ]);

    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
    return $response;
}
    protected function _load_essentials()
    {
        $this->truckModel           = model('App\Models\Truck');
        $this->truckAttachmentModel = model('App\Models\Truck_attachment');
        $this->webappResponseModel  = model('App\Models\Webapp_response');
    }
}