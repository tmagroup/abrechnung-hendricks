<?php 
	if(!defined("APP_FOLDER")) exit("No direct script access allowed");
	afterLogin();
	
	if(isset($_POST['action']) && $_POST['action'] == "delete")
	{
		$id = AbrechnungId::fromBase64($_POST['id'] ?? '');
		if ($id === null) {
			echo json_encode(["status"=> false, "msg"=>"Der Provisionsempfänger wurde nicht gefunden."]);
			die;
		}
		updateById('commission_recipient',['deleted_at'=>date("Y-m-d H:i:s")],$id);
		echo json_encode(["status"=> true, "msg"=>"Der Provisionsempfänger wurde gelöscht."]);
		die;
	}
?>