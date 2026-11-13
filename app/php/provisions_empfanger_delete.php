<?php 
	if(!defined("APP_FOLDER")) exit("No direct script access allowed");
	
	if(isset($_POST['action']) && $_POST['action'] == "delete")
	{
		$id = base64_decode($_POST['id']);		
		updateById('commission_recipient',['deleted_at'=>date("Y-m-d H:i:s")],$id);
		echo json_encode(["status"=> true, "msg"=>"Der Provisionsempfänger wurde gelöscht."]);
		die;
	}
?>
