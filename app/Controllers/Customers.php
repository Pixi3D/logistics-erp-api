<?php

namespace App\Controllers;

class Customers extends MYTController
{
    protected $customerModel;
    protected $customerContactModel;
    protected $customerAttachmentModel; 
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
        if (($response = $this->_api_verification('customers', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$customers = $this->customerModel->get_all()) {
            $response = $this->failNotFound('No customers found.');
        } else {
            $response = $this->respond(['data' => $customers, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function search()
    {
        if (($response = $this->_api_verification('customers', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $name   = $this->request->getVar('name')   ?: null;
        $status = $this->request->getVar('status') ?: null;

        if (!$customers = $this->customerModel->search($name, $status)) {
            $response = $this->failNotFound('No customers found.');
        } else {
            $response = $this->respond(['data' => $customers, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function details()
    {
        if (($response = $this->_api_verification('customers', 'details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id = $this->request->getVar('customer_id');

        if (!$customer = $this->customerModel->get_details_by_id($customer_id)) {
            $response = $this->failNotFound('Customer not found.');
        } else {
            $customer['contacts'] = $this->customerContactModel->get_by_customer($customer_id);
            $response = $this->respond(['data' => $customer, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function create()
    {
        if (($response = $this->_api_verification('customers', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $contacts_json = $this->request->getVar('contacts');
        $contacts      = json_decode($contacts_json, true) ?? [];
        $primary       = $contacts[0] ?? [];

        $data = [
            'first_name'     => $primary['first_name']    ?? '',
            'last_name'      => $primary['last_name']     ?? '',
            'trade_name'     => $this->request->getVar('trade_name')    ?: null,
            'bir_name'       => $this->request->getVar('bir_name')      ?: null,
            'business_type'  => $this->request->getVar('business_type')  ?: null, // Added this too
            
            // --- BIR Address Fields Fixed ---
            'bir_address'    => $this->request->getVar('bir_address')   ?: null,
            'bir_region'     => $this->request->getVar('bir_region')    ?: null,
            'bir_province'   => $this->request->getVar('bir_province')  ?: null,
            'bir_city'       => $this->request->getVar('bir_city')      ?: null,
            'bir_barangay'   => $this->request->getVar('bir_barangay')  ?: null,
            'bir_street'     => $this->request->getVar('bir_street')    ?: null,

            // --- Trade Address Fields Fixed ---
            'trade_address'  => $this->request->getVar('trade_address') ?: null,
            'trade_region'   => $this->request->getVar('trade_region')  ?: null,
            'trade_province' => $this->request->getVar('trade_province')?: null,
            'trade_city'     => $this->request->getVar('trade_city')    ?: null,
            'trade_barangay' => $this->request->getVar('trade_barangay')?: null,
            'trade_street'   => $this->request->getVar('trade_street')  ?: null,

            'tin'            => $this->request->getVar('tin')           ?: null,
            'term'           => $this->request->getVar('term')          ?: 0,
            'credit_limit'   => $this->request->getVar('credit_limit')  ?: 0,
            'vat_type'       => $this->request->getVar('vat_type')      ?: null,
            'bir_2307'       => $this->request->getVar('bir_2307')      ?: null,
            'contact_person' => $primary['role']          ?? null,
            'contact_number' => $primary['number']        ?? null,
            'email'          => $this->request->getVar('email')         ?: null,
            'address'        => $this->request->getVar('address')       ?: null,
            'added_by'       => $this->requested_by ?: ($_SERVER['HTTP_USER_KEY'] ?? 1),
            'added_on'       => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$customer_id = $this->customerModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to add customer. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Save all contacts into customer_contact table
        foreach ($contacts as $contact) {
            if (empty($contact['first_name']) && empty($contact['last_name'])) continue;
            $contact_data = [
                'customer_id'    => $customer_id,
                'first_name'     => $contact['first_name']   ?? '',
                'middle_name'    => $contact['middle_name']  ?? null,
                'last_name'      => $contact['last_name']    ?? '',
                'suffix'         => $contact['suffix']       ?? null,
                'contact_number' => $contact['number']       ?? null,
                'email'          => $contact['email']        ?? null,
                'position'       => $contact['position']     ?? null,
                'role'           => ($contact['role'] ?? '') === 'Others'
                                        ? ($contact['other_role'] ?? 'Others')
                                        : ($contact['role'] ?? null),
                                        
                'added_by'       => $this->requested_by,
                'added_on'       => date('Y-m-d H:i:s'),
            ];
            if (!$this->customerContactModel->insert($contact_data)) {
                $this->db->transRollback();
                $response = $this->fail('Customer created but failed to save contact.');
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            }
        }

        // Handle attachments
        $uploaded_files = $this->request->getFileMultiple('attachments');
        if (!empty($uploaded_files)) {
            $upload_path = FCPATH . 'uploads/customers/' . $customer_id . '/';
            if (!is_dir($upload_path)) mkdir($upload_path, 0755, true);

            foreach ($uploaded_files as $file) {
                if (!$file->isValid() || $file->hasMoved()) continue;
                $new_name = $file->getRandomName();
                $client_name = $file->getClientName(); // Dynamic name formatted as tradename_filename.ext from frontend
                $file->move($upload_path, $new_name);
                $attachment_data = [
                    'customer_id' => $customer_id,
                    'file_name'   => $client_name,
                    'file_path'   => 'uploads/customers/' . $customer_id . '/' . $new_name,
                    'added_by'    => $this->requested_by,
                    'added_on'    => date('Y-m-d H:i:s'),
                ];
                if (!$this->customerAttachmentModel->insert($attachment_data)) {
                    $this->db->transRollback();
                    $response = $this->fail('Customer created but failed to save attachment.');
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
            }
        }

        $this->db->transCommit();
        $response = $this->respond(['response' => 'Customer added successfully.']);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function update($id = null)
    {
        if (($response = $this->_api_verification('customers', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id = $this->request->getPost('customer_id') 
                       ?: ($this->request->getPost('id') 
                       ?: $this->request->getVar('customer_id'));
                       
        $condition   = ['id' => $customer_id, 'is_deleted' => 0];

        if (!$this->customerModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Customer not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $contacts_json = $this->request->getVar('contacts');
        $contacts      = json_decode($contacts_json, true) ?? [];
        $primary       = $contacts[0] ?? [];

        $data = [
            'first_name'     => $primary['first_name']    ?? '',
            'last_name'      => $primary['last_name']     ?? '',
            'trade_name'     => $this->request->getVar('trade_name')    ?: null,
            'bir_name'       => $this->request->getVar('bir_name')      ?: null,
            'business_type'  => $this->request->getVar('business_type')  ?: null,
            'bir_address'    => $this->request->getVar('bir_address')    ?: null,
            'bir_region'     => $this->request->getVar('bir_region')     ?: null,
            'bir_province'   => $this->request->getVar('bir_province')   ?: null,
            'bir_city'       => $this->request->getVar('bir_city')       ?: null,
            'bir_barangay'   => $this->request->getVar('bir_barangay')   ?: null,
            'bir_street'     => $this->request->getVar('bir_street')     ?: null,
            'trade_address'  => $this->request->getVar('trade_address')  ?: null,
            'trade_region'   => $this->request->getVar('trade_region')   ?: null,
            'trade_province' => $this->request->getVar('trade_province') ?: null,
            'trade_city'     => $this->request->getVar('trade_city')     ?: null,
            'trade_barangay' => $this->request->getVar('trade_barangay') ?: null,
            'trade_street'   => $this->request->getVar('trade_street')   ?: null,
            'tin'            => $this->request->getVar('tin')           ?: null,
            'term'           => $this->request->getVar('term')          ?: 0,
            'credit_limit'   => $this->request->getVar('credit_limit')  ?: 0,
            'vat_type'       => $this->request->getVar('vat_type')      ?: null,
            'bir_2307'       => $this->request->getVar('bir_2307')      ?: null,
            'contact_person' => $primary['role']          ?? null,
            'contact_number' => $primary['number']        ?? null,
            'email'          => $this->request->getVar('email')         ?: null,
            'address'        => $this->request->getVar('address')       ?: null,
            'updated_by'     => $this->requested_by ?: ($_SERVER['HTTP_USER_KEY'] ?? 1),
            'updated_on'     => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->customerModel->custom_update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to update customer.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Soft-delete old contacts then re-insert
        $this->db->query(
            "UPDATE customer_contact SET is_deleted = 1 WHERE customer_id = ?",
            [$customer_id]
        );

        foreach ($contacts as $contact) {
            if (empty($contact['first_name']) && empty($contact['last_name'])) continue;
            $contact_data = [
                'customer_id'    => $customer_id,
                'first_name'     => $contact['first_name']   ?? '',
                'middle_name'    => $contact['middle_name']  ?? null,
                'last_name'      => $contact['last_name']    ?? '',
                'suffix'         => $contact['suffix']       ?? null,
                'contact_number' => $contact['number']       ?? null,
                'email'          => $contact['email']        ?? null,
                'role'           => ($contact['role'] ?? '') === 'Others'
                                        ? ($contact['other_role'] ?? 'Others')
                                        : ($contact['role'] ?? null),
                                        
                'added_by'       => $this->requested_by,
                'added_on'       => date('Y-m-d H:i:s'),
            ];
            if (!$this->customerContactModel->insert($contact_data)) {
                $this->db->transRollback();
                $response = $this->fail('Customer updated but failed to save contact.');
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            }
        }
        
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as $file) {
                log_message('error', 'File name: ' . $file->getName());
                log_message('error', 'File valid: ' . ($file->isValid() ? 'yes' : 'no'));
                log_message('error', 'File error: ' . $file->getError());
                log_message('error', 'File moved: ' . ($file->hasMoved() ? 'yes' : 'no'));
            }
        }

        $uploaded_files = $this->request->getFileMultiple('attachments');
        if (!empty($uploaded_files)) {
            $upload_path = FCPATH . 'uploads/customers/' . $customer_id . '/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }
            foreach ($uploaded_files as $file) {
                if (!$file->isValid() || $file->hasMoved()) continue;
                $new_name    = $file->getRandomName();
                $client_name = $file->getClientName();
                $file->move($upload_path, $new_name);
                $attachment_data = [
                    'customer_id' => $customer_id,
                    'file_name'   => $client_name,
                    'file_path'   => 'uploads/customers/' . $customer_id . '/' . $new_name,
                    'added_by'    => $this->requested_by ?: ($_SERVER['HTTP_USER_KEY'] ?? 1),
                    'added_on'    => date('Y-m-d H:i:s'),
                ];
                if (!$this->customerAttachmentModel->insert($attachment_data)) {
                    $this->db->transRollback();
                    $response = $this->fail('Customer updated but failed to save attachment.');
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
            }
        }

        $this->db->transCommit();
        $response = $this->respond(['response' => 'Customer updated successfully.', 'status' => 'success']);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
}

    public function delete($id = null)
    {
        if (($response = $this->_api_verification('customers', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id = $this->request->getVar('customer_id');
        $condition   = ['id' => $customer_id, 'is_deleted' => 0];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->customerModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Customer not found.');
        } elseif (!$this->customerModel->custom_update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete customer. Please try again.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Customer deleted successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function get_suggestions()
{
    if (($response = $this->_api_verification('customers', 'get_suggestions')) !== true)
        return $response;

    $token = $this->request->getVar('token');
    if (($response = $this->_verify_requester($token)) !== true)
        return $response;

    $keyword = $this->request->getVar('keyword') ?: '';

    $database = \Config\Database::connect();

    $customers = $database->query("
        SELECT id, CONCAT(first_name, ' ', last_name, IFNULL(CONCAT(' — ', trade_name), '')) AS label
        FROM customer
        WHERE is_deleted = 0
          AND (first_name LIKE ? OR last_name LIKE ? OR trade_name LIKE ? OR tin LIKE ?)
        LIMIT 10
    ", ["%$keyword%", "%$keyword%", "%$keyword%", "%$keyword%"])->getResultArray();

    $response = $this->respond([
        'data'   => ['customers' => $customers],
        'status' => 'success'
    ]);
    
    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
    return $response;
}

    public function get_attachments()
    {
        if (($response = $this->_api_verification('customers', 'get_attachments')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $customer_id = $this->request->getVar('customer_id');
        $attachments = $this->customerAttachmentModel->get_by_customer_id($customer_id);

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
        if (($response = $this->_api_verification('customers', 'delete_attachment')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $attachment_id = $this->request->getVar('attachment_id');
        $condition     = ['id' => $attachment_id, 'is_deleted' => 0];
        $data          = ['is_deleted' => 1, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')];

        $this->db = db_connect();
        $this->customerAttachmentModel->custom_update($condition, $data);

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
        $this->customerModel           = model('App\Models\Customer');
        $this->customerContactModel    = model('App\Models\Customer_contact');  // add this
        $this->customerAttachmentModel = model('App\Models\Customer_attachment');
        $this->webappResponseModel     = model('App\Models\Webapp_response');
    }
}