<?php 
	if(!defined("APP_FOLDER")) exit("No direct script access allowed");
	
	if(isset($_POST['action']) && $_POST['action'] == "delete")
	{
		$invoice_id = base64_decode($_POST['id']);
		updateById('invoice',['deleted_at'=>date("Y-m-d H:i:s")],$invoice_id);
		echo json_encode(["status"=> true, "msg"=>"Delete record successfully."]);
		die;
	}
?>
