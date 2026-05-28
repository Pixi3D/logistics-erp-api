<?php

namespace App\Controllers;

class Contract_billing_payments extends MYTController
{
    protected $contractBillingPaymentModel;
    protected $contractBillingPaymentAttachmentModel;
    protected $contractBillingModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key      = $_SERVER['HTTP_API_KEY']  ?? '';
        $this->user_key     = $_SERVER['HTTP_USER_KEY'] ?? '';
        $this->requested_by = $this->user_key;
        $this->_load_essentials();
    }

    // GET contract_billing_payments/index
    public function index()
    {
        if (($response = $this->_api_verification('contract_billing_payments', 'index')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$payments = $this->contractBillingPaymentModel->get_all()) {
            $response = $this->failNotFound('No payments found.');
        } else {
            $response = $this->respond(['data' => $payments, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // GET contract_billing_payments/search
    public function search()
    {
        if (($response = $this->_api_verification('contract_billing_payments', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $filters = [
            'billing_id'     => $this->request->getVar('billing_id')     ?: null,
            'payment_method' => $this->request->getVar('payment_method') ?: null,
            'date_from'      => $this->request->getVar('date_from')      ?: null,
            'date_to'        => $this->request->getVar('date_to')        ?: null,
            'customer_id'    => $this->request->getVar('customer_id')    ?: null,
        ];

        if (!$payments = $this->contractBillingPaymentModel->search($filters)) {
            $response = $this->failNotFound('No payments found.');
        } else {
            $response = $this->respond(['data' => $payments, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // GET contract_billing_payments/details
    public function details()
    {
        if (($response = $this->_api_verification('contract_billing_payments', 'details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $payment_id = $this->request->getVar('payment_id');

        if (!$payment = $this->contractBillingPaymentModel->get_details_by_id($payment_id)) {
            $response = $this->failNotFound('Payment not found.');
        } else {
            $response = $this->respond(['data' => $payment, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // POST contract_billing_payments/create
    public function create()
    {
        if (($response = $this->_api_verification('contract_billing_payments', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $billing_id     = $this->request->getVar('billing_id');
        $payment_method = $this->request->getVar('payment_method');
        $amount         = $this->request->getVar('amount');
        $payment_date   = $this->request->getVar('payment_date');

        if (!$billing_id || !$payment_method || !$amount || !$payment_date) {
            $response = $this->fail('billing_id, payment_method, amount, and payment_date are required.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        if (!$billing = $this->contractBillingModel->get_details_by_id($billing_id)) {
            $response = $this->failNotFound('Billing not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $check_date    = $this->request->getVar('check_date');
        $deposit_date  = $this->request->getVar('deposit_date');
        $transfer_date = $this->request->getVar('transfer_date');

        $data = [
            'billing_id'       => $billing_id,
            'payment_date'     => $payment_date,
            'payment_method'   => $payment_method,
            'amount'           => $amount,
            'reference_number' => $this->request->getVar('reference_number') ?: null,
            'check_number'     => $this->request->getVar('check_number')     ?: null,
            'check_date'       => $this->request->getVar('check_date')       ?: null,
            'bank_name'        => $this->request->getVar('bank_name')        ?: null,
            'deposit_date'     => $this->request->getVar('deposit_date')     ?: null,
            'deposited_to'     => $this->request->getVar('deposited_to')     ?: null,
            'transfer_date'    => $this->request->getVar('transfer_date')    ?: null,
            'remarks'          => $this->request->getVar('remarks')          ?: null,
            'added_by'         => $this->requested_by,
            'added_on'         => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->contractBillingPaymentModel->insert($data)) {
            $this->db->transRollback();
            $db_error = $this->db->error();
            $response = $this->fail('Unable to record payment: ' . $db_error['message']);
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Recompute billing balance and status
        if (!$this->_recompute_billing($billing_id)) {
            $this->db->transRollback();
            $response = $this->fail('Payment saved but failed to update billing balance.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $payment_id = $this->contractBillingPaymentModel->getInsertID();
        $this->db->transCommit();
        $response = $this->respond(['status' => 'success', 'response' => 'Payment recorded successfully.', 'payment_id' => $payment_id]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // POST contract_billing_payments/delete
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('contract_billing_payments', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $payment_id = $this->request->getVar('payment_id');

        if (!$payment = $this->contractBillingPaymentModel->get_details_by_id($payment_id)) {
            $response = $this->failNotFound('Payment not found.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $billing_id = $payment['billing_id'];

        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        $this->contractBillingPaymentModel->custom_update(['id' => $payment_id, 'is_deleted' => 0], $data);

        if ($this->db->error()['code']) {
            $this->db->transRollback();
            $response = $this->fail('Unable to delete payment. Please try again.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        // Recompute billing balance and status after deletion
        if (!$this->_recompute_billing($billing_id)) {
            $this->db->transRollback();
            $response = $this->fail('Payment deleted but failed to update billing balance.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $this->db->transCommit();
        $response = $this->respond(['status' => 'success', 'response' => 'Payment deleted successfully.']);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Recompute amount_paid, balance, and status for a billing.
     * Called after every payment create or delete.
     * Returns true on success, false on DB error.
     */
    private function _recompute_billing($billing_id)
    {
        $billing = $this->contractBillingModel->get_details_by_id($billing_id);
        if (!$billing) return false;

        $grand_total = (float) $billing['grand_total'];
        $amount_paid = $this->contractBillingPaymentModel->get_total_paid($billing_id);
        $balance     = $grand_total - $amount_paid;

        if ($amount_paid <= 0) {
            $status = 'unpaid';
        } elseif ($balance <= 0) {
            $status = 'paid';
        } else {
            $status = 'partial';
        }

        $this->contractBillingModel->custom_update(
            ['id' => $billing_id, 'is_deleted' => 0],
            [
                'amount_paid' => $amount_paid,
                'balance'     => max(0, $balance),
                'status'      => $status,
                'updated_by'  => $this->requested_by,
                'updated_on'  => date('Y-m-d H:i:s'),
            ]
        );

        return !$this->db->error()['code'];
    }

    protected function _load_essentials()
    {
        $this->contractBillingPaymentModel           = model('App\Models\Contract_billing_payment');
        $this->contractBillingPaymentAttachmentModel = model('App\Models\Contract_billing_payment_attachment');
        $this->contractBillingModel                  = model('App\Models\Contract_billing');
        $this->webappResponseModel                   = model('App\Models\Webapp_response');
    }


    // GET contract_billing_payments/get_attachments
    public function get_attachments()
    {
        if (($response = $this->_api_verification('contract_billing_payments', 'get_attachments')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $payment_id  = $this->request->getVar('payment_id');
        $attachments = $this->contractBillingPaymentAttachmentModel->get_by_payment_id($payment_id) ?: [];

        $response = $this->respond(['data' => $attachments, 'status' => 'success']);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // POST contract_billing_payments/upload_attachment
    public function upload_attachment()
    {
        if (($response = $this->_api_verification('contract_billing_payments', 'upload_attachment')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $payment_id = $this->request->getVar('payment_id');
        $files      = $this->request->getFiles('attachments') ?? [];

        if (empty($files)) {
            $response = $this->fail('No files uploaded.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $upload_path = WRITEPATH . 'uploads/payment_attachments/';
        if (!is_dir($upload_path)) mkdir($upload_path, 0755, true);

        foreach ($files as $file) {
            if (!$file->isValid()) continue;
            $new_name  = $file->getRandomName();
            $file->move($upload_path, $new_name);
            $this->contractBillingPaymentAttachmentModel->insert([
                'payment_id' => $payment_id,
                'file_name'  => $file->getClientName(),
                'file_path'  => 'payment_attachments/' . $new_name,
                'added_by'   => $this->requested_by,
                'added_on'   => date('Y-m-d H:i:s'),
            ]);
        }

        $response = $this->respond(['status' => 'success', 'response' => 'Attachments uploaded.']);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // POST contract_billing_payments/delete_attachment
    public function delete_attachment()
    {
        if (($response = $this->_api_verification('contract_billing_payments', 'delete_attachment')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $attachment_id = $this->request->getVar('attachment_id');
        $this->contractBillingPaymentAttachmentModel->custom_update(
            ['id' => $attachment_id, 'is_deleted' => 0],
            ['is_deleted' => 1, 'updated_by' => $this->requested_by, 'updated_on' => date('Y-m-d H:i:s')]
        );

        $response = $this->respond(['status' => 'success', 'response' => 'Attachment deleted.']);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // GET contract_billing_payments/download_attachment
    public function download_attachment()
    {
        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $file_path = WRITEPATH . 'uploads/' . $this->request->getVar('file_path');
        $file_name = $this->request->getVar('file_name');

        if (!file_exists($file_path)) {
            return $this->failNotFound('File not found.');
        }

        return $this->response->download($file_path, null)->setFileName($file_name);
    }
}