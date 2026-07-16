<?php 
	if(!defined("APP_FOLDER")) exit("No direct script access allowed");	
	
	if(isset($_POST["company"]))
	{
		setRules("company","Firma","required");
		setRules("first_name","Vorname","required");
		setRules("name","Name","required");
		setRules("commission","Provision","required");

		if(validation())
		{
			$data_invoice = [];
			$data_invoice['company'] = $_POST['company'];
			$data_invoice['first_name'] = $_POST['first_name'];
			$data_invoice['name'] = $_POST['name'];
			$data_invoice['commission'] = $_POST['commission'];			
			$data_invoice['street'] = $_POST['street'];
			$data_invoice['zip_code'] = $_POST['zip_code'];
			$data_invoice['location'] = $_POST['location'];
			$data_invoice['email'] = $_POST['email'];
			$data_invoice['phone'] = $_POST['phone'];
			$data_invoice['bank_recipient'] = $_POST['bank_recipient'];
			$data_invoice['iban_no'] = $_POST['iban_no'];
			$data_invoice['bic'] = $_POST['bic'];			
			$data_invoice['created_at'] = date("Y-m-d H:i:s");
			$data_invoice['created_by'] = getLoginId();
			if(isset($_POST['hidden_id'])){
				$id = base64_decode($_POST['hidden_id']);
				updateById("commission_recipient",$data_invoice,$id);
				echo json_encode([ 'status' => true, 'msg' => 'Provisions Empfänger updated successfully']);
			}else{
				$invoice_id = insert("commission_recipient",$data_invoice);
				echo json_encode([ 'status' => true, 'msg' => 'Provisions Empfänger added successfully']);
			}
		}
	}
?>
