<?php 
	if(!defined("APP_FOLDER")) exit("No direct script access allowed");
	afterLogin();
	
	if(isset($_POST['action']) && $_POST['action'] == "delete")
	{
		$invoice_id = AbrechnungId::fromBase64($_POST['id'] ?? '');
		if ($invoice_id === null) {
			echo json_encode(["status"=> false, "msg"=>"Die Abrechnung wurde nicht gefunden."]);
			die;
		}
		updateById('invoice',['deleted_at'=>date("Y-m-d H:i:s")],$invoice_id);
		echo json_encode(["status"=> true, "msg"=>"Die Abrechnung wurde gelöscht."]);
		die;
	}
?>
