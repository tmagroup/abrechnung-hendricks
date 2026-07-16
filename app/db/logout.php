<?php 
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	deleteAllSession();
	redirect('login');
?>