<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of invoice
 *
 * @author Sapta
 */
class Invoice extends MY_Controller {

    //put your code here

    function __construct() {
        parent::__construct("authorize", "invoice", true);
        $this->load->model('invoice_model');
        $this->load->model('so_model');
        $this->load->model('bank_model');
        $this->load->model('work_order_model');
        $this->load->model('payroll_model');
        $this->load->model('payroll_periode_model');
        $this->load->model('appsetting_model');
        $this->load->model('cost_element_model');

    }

    public function get_invoice_list() {
        //var_dump($this->invoice_model->get_invoice_all());
        //die();
        echo "{\"data\" : " . json_encode($this->invoice_model->get_invoice_all()) . "}";
    }

    public function init_create_invoice() {
        $data['payroll'] = $this->payroll_model->get_wo_list();
        return $data;
    }

    public function generate_invoice_number() {
        $this->db->select('*');
        $this->db->from('invoice');
        $this->db->where('YEAR(invoice_date)', date('Y'));

        $result = $this->db->get()->result_array();
        $countResult = count($result) + 1;
        $zeroCount = '';

        for ($i = 0; $i < 4 - strlen($countResult); $i++) {
            $zeroCount .= '0';
        }

        return ("INV" . date('y') . $zeroCount . $countResult);
    }

    public function save_invoice() {
        $id_invoice = null;
        if ($this->input->post('is_edit') == 'false')
        {
            $invoice_number = $this->generate_invoice_number();
            $id_invoice = $this->invoice_model->save_invoice($this->input->post(), $invoice_number);
            $this->create_report('400485678', $invoice_number, 'invoice');
        }
        else
        {
            $this->invoice_model->edit_invoice($this->input->post());
            $id_invoice = $this->input->post('id_invoice');
        }

        $interfunction_param[0] = array("paramKey" => "id", "paramValue" => $id_invoice);
        return array("interfunction_param" => $interfunction_param);
    }

    public function delete_invoice() {
        $this->invoice_model->delete_invoice($this->input->post('id_invoice'));
        //$this->payment_receipt_product_model->delete_payment_receipt_product->('payment_receipt')

        return null;
    }

    public function init_edit_invoice($id) {
        $data = array(
            "data_edit" => $this->invoice_model->get_invoice_by_id($id),
            "is_edit" => 'true'
        );
        return $data;
    }

    public function get_wo_list_invoice()
    {
        echo "{\"data\" : " . json_encode($this->payroll_periode_model->get_wo_list_invoice()) . "}";
    }

    public function get_calculate_invoice($ce)
    {
        echo json_encode($this->cost_element_model->calculate_invoice('per_month', $ce));
    }

    function kirim_invoice_email() {
        $email = $this->input->post('email');
        $id = $this->input->post('id');
        $invNo = $this->input->post('invNo');
        
        $invPdf = "images/upload/" . $invNo . ".pdf";
        $dtpesan = "Invoice";
        $subjek = "Invoice For Security";

        $result = $this->send_email($email, $subjek, $dtpesan, $invPdf);
        if ($result == true) {
            echo json_encode(array("success" => true));
        } else {
            echo json_encode(array("success" => false));
        }
    }

    function send_email($email, $subject, $psn, $attach) {

        $this->load->library('email');

        $this->email->from('alfath.helmy@gmail.com', 'Finance Bazcorp');
        $this->email->to($email);

        $this->email->subject($subject);
        $this->email->attach($attach);
        $this->email->message($psn);

        if ($this->email->send()) {
            return true;
        } else {
            return false;
        }
        //echo $this->email->print_debugger();
    }

    public function invoice_report_template() {
        $this->load->model('invoice_model');
        $this->load->model('so_model');
        $po = $this->invoice_model->get_invoice_by_id($this->input->get('id'));
        $po_product = $this->so_model->get_so_product_by_id($po[0]['so']);
        $data = array();
        $data['company'] = $this->appsetting_model->get_app_config_by_name('company_name');
        $data['company_address'] = $this->appsetting_model->get_app_config_by_name('company_address');
        $data['customer_address'] = $po[0]['address'];
        $data['customer_name'] = $po[0]['customer_name'];
        $data['document_name'] = 'INVOICE';
        $data['document_number'] = $po[0]['invoice_receipt_number'];
        $data['document_date'] = date('d/m/Y', strtotime($po[0]['invoice_date']));
        $data['items'] = $po_product;
        $data['sub_total'] = $po[0]['sub_total'];
        $data['tax'] = $po[0]['tax'];
        $data['total_price'] = $po[0]['total_price'];
        $this->load->view('templates/report/invoice_template', $data);
    }

    public function create_report($id, $doc_no, $doc) {
        //create_report?id={}&doc={}&doc_no=
        $url = '?id=' . $id;
        switch ($doc) {
            case 'po':
                $url = base_url() . 'report/po_report_template' . $url;
                break;
            case 'invoice':
                $url = base_url() . 'report/invoice_report_template' . $url;
                break;
        }
        $filename = $doc_no . '.pdf';
        $filepath = $this->appsetting_model->get_app_config_by_name('report_temp_directory_path') . $filename;
        $cmd = $this->appsetting_model->get_app_config_by_name('report_temp_directory_path') . ' ' . $url . ' ' . $filepath . ' 2>&1';

        //var_dump($cmd);
        //die();       
        exec($cmd);
    }
	
	public function validate_invoice($id)
	{
		$param = null;
        $param = $this->invoice_model->validate_invoice($id);
        $interfunction_param = array();
        $interfunction_param[0] = array("paramKey" => "id", "paramValue" => $id);
        return array('log_param' => $param, "interfunction_param" => $interfunction_param);
	}
	
    function detail_invoice()
    {
        $id=$this->uri->segment(3);
		$payroll_periode = $this->uri->segment(4);
        echo "{\"data\" : " . json_encode($this->payroll_periode_model->get_invoice_all_employee($id, $payroll_periode)) . "}";
    }
	
	public function get_detail_invoice()
	{
		echo "{\"data\" : " . json_encode($this->invoice_model->get_detail_invoice($this->input->get('id'))) . "}";
	}
	
	public function close_invoice()
	{
		$id = $this->input->post('id_invoice');
		$param = null;
        $param = $this->invoice_model->close_invoice($id);
        $interfunction_param = array();
        $interfunction_param[0] = array("paramKey" => "id", "paramValue" => $id);
        return array('log_param' => $param, "interfunction_param" => $interfunction_param);		
	}
	
	public function get_invoice_open_list()
	{
		echo "{\"data\" : " . json_encode($this->invoice_model->get_invoice_open()) . "}";
	}

}
