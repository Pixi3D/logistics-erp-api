<?php

namespace App\Controllers;

class Drivers extends MYTController
{
    protected $driverModel;
protected $driverAttachmentModel;
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
        if (($response = $this->_api_verification('drivers', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$drivers = $this->driverModel->get_all()) {
            $response = $this->failNotFound('No drivers found.');
        } else {
            $response = $this->respond(['data' => $drivers, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function search()
    {
        if (($response = $this->_api_verification('drivers', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $name           = $this->request->getVar('name')            ?: null;
        $license_number = $this->request->getVar('license_number')  ?: null;
        $status         = $this->request->getVar('status')          ?: null;

        if (!$drivers = $this->driverModel->search($name, $license_number, $status)) {
            $response = $this->failNotFound('No drivers found.');
        } else {
            $response = $this->respond(['data' => $drivers, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function details()
    {
        if (($response = $this->_api_verification('drivers', 'details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $driver_id = $this->request->getVar('driver_id');

        if (!$driver = $this->driverModel->get_details_by_id($driver_id)) {
            $response = $this->failNotFound('Driver not found.');
        } else {
            $response = $this->respond(['data' => $driver, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function create()
    {
        if (($response = $this->_api_verification('drivers', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $data = [
            'first_name'                      => $this->request->getVar('first_name'),
            'middle_name'                     => $this->request->getVar('middle_name')                     ?: null,
            'last_name'                       => $this->request->getVar('last_name'),
            'suffix'                          => $this->request->getVar('suffix')                          ?: null,
            'birthdate'                       => $this->request->getVar('birthdate')                       ?: null,
            'gender'                          => $this->request->getVar('gender')                          ?: null,
            'civil_status'                    => $this->request->getVar('civil_status')                    ?: null,
            'nationality'                     => $this->request->getVar('nationality')                     ?: null,
            'religion'                        => $this->request->getVar('religion')                        ?: null,
            'email'                           => $this->request->getVar('email')                           ?: null,
            'contact_number'                  => $this->request->getVar('contact_number')                  ?: null,
            'address'                         => $this->request->getVar('address')                         ?: null,
            'emergency_contact_name'          => $this->request->getVar('emergency_contact_name')          ?: null,
            'emergency_contact_number'        => $this->request->getVar('emergency_contact_number')        ?: null,
            'emergency_contact_relationship'  => $this->request->getVar('emergency_contact_relationship')  ?: null,
            'emergency_contact_address'       => $this->request->getVar('emergency_contact_address')       ?: null,
            'license_number'                  => $this->request->getVar('license_number')                  ?: null,
            'license_expiry'                  => $this->request->getVar('license_expiry')                  ?: null,
            'sss_number'                      => $this->request->getVar('sss_number')                      ?: null,
            'pagibig_number'                  => $this->request->getVar('pagibig_number')                  ?: null,
            'philhealth_number'               => $this->request->getVar('philhealth_number')               ?: null,
            'tin_number'                      => $this->request->getVar('tin_number')                      ?: null,
            'status'                          => $this->request->getVar('status'),
            'added_by'                        => $this->requested_by,
            'added_on'                        => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$driver_id = $this->driverModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add driver. Please try again.');
        } else {
            $files = $this->request->getFiles();
        if (!empty($files['attachments'])) {
            $upload_path = FCPATH . 'uploads/drivers/' . $driver_id . '/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }
            foreach ($files['attachments'] as $file) {
                if (!$file->isValid() || $file->hasMoved()) continue;
                $new_name    = $file->getRandomName();
                $file->move($upload_path, $new_name);
                $client_name = $file->getClientName();
                $driver_name = preg_replace('/\s+/', '', $this->request->getPost('last_name'));
                $ext         = pathinfo($client_name, PATHINFO_EXTENSION);
                $display_name = $driver_name . '_License.' . $ext;
                $attachment_data = [
                    'driver_id' => $driver_id,
                    'file_name' => $display_name,
                    'file_path' => 'uploads/drivers/' . $driver_id . '/' . $new_name,
                    'added_by'  => $this->requested_by,
                    'added_on'  => date('Y-m-d H:i:s'),
                ];
                if (!$this->driverAttachmentModel->insert($attachment_data)) {
                    $this->db->transRollback();
                    $response = $this->fail('Driver created but failed to save attachment.');
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
            }
        }

        $this->db->transCommit();
        $response = $this->respond(['response' => 'Driver added successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update($id = null)
    {
        if (($response = $this->_api_verification('drivers', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $driver_id = $this->request->getVar('driver_id');
        $condition = ['id' => $driver_id, 'is_deleted' => 0];

        if (!$this->driverModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Driver not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $data = [
            'first_name'                      => $this->request->getVar('first_name'),
            'middle_name'                     => $this->request->getVar('middle_name')                     ?: null,
            'last_name'                       => $this->request->getVar('last_name'),
            'suffix'                          => $this->request->getVar('suffix')                          ?: null,
            'birthdate'                       => $this->request->getVar('birthdate')                       ?: null,
            'gender'                          => $this->request->getVar('gender')                          ?: null,
            'civil_status'                    => $this->request->getVar('civil_status')                    ?: null,
            'nationality'                     => $this->request->getVar('nationality')                     ?: null,
            'religion'                        => $this->request->getVar('religion')                        ?: null,
            'email'                           => $this->request->getVar('email')                           ?: null,
            'contact_number'                  => $this->request->getVar('contact_number')                  ?: null,
            'address'                         => $this->request->getVar('address')                         ?: null,
            'emergency_contact_name'          => $this->request->getVar('emergency_contact_name')          ?: null,
            'emergency_contact_number'        => $this->request->getVar('emergency_contact_number')        ?: null,
            'emergency_contact_relationship'  => $this->request->getVar('emergency_contact_relationship')  ?: null,
            'emergency_contact_address'       => $this->request->getVar('emergency_contact_address')       ?: null,
            'license_number'                  => $this->request->getVar('license_number')                  ?: null,
           'license_expiry'                  => $this->request->getVar('license_expiry')                  ?: null,
            'sss_number'                      => $this->request->getVar('sss_number')                      ?: null,
            'pagibig_number'                  => $this->request->getVar('pagibig_number')                  ?: null,
            'philhealth_number'               => $this->request->getVar('philhealth_number')               ?: null,
            'tin_number'                      => $this->request->getVar('tin_number')                      ?: null,
            'status'                          => $this->request->getVar('status'),
            'updated_by'                      => $this->requested_by,
            'updated_on'                      => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->driverModel->custom_update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update driver. Please try again.');
        } else {
            $files = $this->request->getFiles();
        if (!empty($files['attachments'])) {
            $upload_path = FCPATH . 'uploads/drivers/' . $driver_id . '/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }
            foreach ($files['attachments'] as $file) {
                if (!$file->isValid() || $file->hasMoved()) continue;
                $new_name    = $file->getRandomName();
                $file->move($upload_path, $new_name);
                $client_name = $file->getClientName();
                $driver_name = preg_replace('/\s+/', '', $this->request->getPost('last_name'));
                $ext         = pathinfo($client_name, PATHINFO_EXTENSION);
                $display_name = $driver_name . '_License.' . $ext;
                $attachment_data = [
                    'driver_id' => $driver_id,
                    'file_name' => $display_name,
                    'file_path' => 'uploads/drivers/' . $driver_id . '/' . $new_name,
                    'added_by'  => $this->requested_by,
                    'added_on'  => date('Y-m-d H:i:s'),
                ];
                if (!$this->driverAttachmentModel->insert($attachment_data)) {
                    $this->db->transRollback();
                    $response = $this->fail('Driver updated but failed to save attachment.');
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
            }
        }

        $this->db->transCommit();
        $response = $this->respond(['response' => 'Driver updated successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function delete($id = null)
    {
        if (($response = $this->_api_verification('drivers', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $driver_id = $this->request->getVar('driver_id');
        $condition = ['id' => $driver_id, 'is_deleted' => 0];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->driverModel->custom_update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete driver. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Driver deleted successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function get_attachments()
    {
        if (($response = $this->_api_verification('drivers', 'get_attachments')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $driver_id   = $this->request->getVar('driver_id');
        $attachments = $this->driverAttachmentModel->get_by_driver_id($driver_id);

        $response = $this->respond(['data' => $attachments ?: [], 'status' => 'success']);
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

    public function delete_attachment()
    {
        if (($response = $this->_api_verification('drivers', 'delete_attachment')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $attachment_id = $this->request->getVar('attachment_id');
        $condition     = ['id' => $attachment_id, 'is_deleted' => 0];
        $data          = ['is_deleted' => 1, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')];

        $this->db = db_connect();
        $this->driverAttachmentModel->custom_update($condition, $data);

        if ($this->db->error()['code']) {
            $response = $this->fail('Failed to remove attachment.');
        } else {
            $response = $this->respond(['response' => 'Attachment removed.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->driverModel          = model('App\Models\Driver');
        $this->driverAttachmentModel = model('App\Models\Driver_attachment');   
        $this->webappResponseModel  = model('App\Models\Webapp_response');
    }
}