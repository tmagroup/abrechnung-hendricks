<?php
	error_reporting(E_ALL);
	
	ini_set('max_execution_time', 600);
	ini_set('memory_limit', '1G');

	ob_start();
	
/* -------------------------------------------- */

	//set your application folder name
	define('APP_FOLDER','app');	
	
	//set your configration folder name 
	define('CONFIG_FOLDER','config');

	//set your function folder name
	define('FUNCTION_FOLDER','function');	
	
	//set your php files folder name
	define('PHP_FOLDER','php');
	
	//set your data base folder name
	define('DB_FOLDER','db');
	
/* -------------------------------------------- */
	
	global $appurls;
	global $appfiles;
	require_once(APP_FOLDER.'/'.CONFIG_FOLDER.'/constant.php');
	require_once(APP_FOLDER.'/'.CONFIG_FOLDER.'/config.php');
	require_once(APP_FOLDER.'/'.CONFIG_FOLDER.'/array.php');
	require_once(APP_FOLDER.'/'.CONFIG_FOLDER.'/settings.php');
	
?>