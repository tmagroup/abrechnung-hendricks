<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	//set main session
	if($app_session == ""){ $app_session = "user"; } 
	define('APP_SESSION',"$app_session");	
	
	//set database constant
	define('DB_HOSTNAME',"$db_hostname");
	define('DB_USERNAME',"$db_username");	
	define('DB_PASSWORD',"$db_password");	
	define('DB_NAME',"$db_name");
	
	foreach(glob(APP_FOLDER.'/'.CONFIG_FOLDER."/".FUNCTION_FOLDER."/*.php") as $file)
	{
		$file = basename($file);
		if(file_exists(APP_FOLDER.'/'.CONFIG_FOLDER."/".FUNCTION_FOLDER."/$file"))
		{
			require_once(APP_FOLDER.'/'.CONFIG_FOLDER.'/'.FUNCTION_FOLDER."/$file");
		}	
	}
	
	if($default_file == "")
	{
		die("Error: set your default file.");
	}
	
	if($app_url == "")
	{
		$app_url = "http://".$_SERVER['HTTP_HOST'].str_replace("index.php","",$_SERVER['SCRIPT_NAME']);
	}		
	
	define('DEFAULT_FILE',$default_file);
	define('APP_URL',$app_url);
	
	foreach(glob(APP_FOLDER."/".PHP_FOLDER."/*.php") as $file)
	{
		$path_parts 	= pathinfo(APP_FOLDER."/".PHP_FOLDER."/$file");
		$filename 		= $path_parts['filename'];
		$extension 		= $path_parts['extension'];
		url("$filename","$filename.php");
	}
	
	require_once(APP_FOLDER.'/'.CONFIG_FOLDER.'/url.php');	
	
	if(isDuplicate($appurls))
	{
		die("Error: Your url name can not redeclare.");
	}
	
	if(isDuplicate($appfiles))
	{
		die("Error: Your file name can not redeclare.");
	}
	
	$page_filename 	= "";
	$request_url_path		= array();
	if(isset($_SERVER['PATH_INFO']))
	{
		$request_url_path = explode('/',$_SERVER['PATH_INFO']);
		if(!(isset($request_url_path[1]) && in_array($request_url_path[1],$appurls)))
		{
			if(file_exists(APP_FOLDER."/".CONFIG_FOLDER."/404.php"))
			{				
				require_once(APP_FOLDER."/".CONFIG_FOLDER."/404.php");
				die();
			}
			else
			{
				echo "<h3>Error: 404 your page not found.</h3>";
				echo "<a href='<?php echo $app_url;'>Go to home</a>";
				die('Error: 404 page not found.');
			}			
		}
		$index 					= array_search($request_url_path[1],$appurls);
		$page_filename			= $appfiles[$index];
	}
	else
	{
		$page_filename 			= "$default_file";
	}
	
	if(!file_exists(APP_FOLDER."/".PHP_FOLDER."/$page_filename"))
	{
		die("Error: Your $page_filename not found in ".PHP_FOLDER." folder.");
	}
	
	if(count($request_url_path) > 2)
	{
		$a = 1;
		for($i=2;$i<count($request_url_path);$i++)
		{
			${'page_var'."$a"} = $request_url_path[$i];
			$a++;
		}
	}
	
	if(session_id() === "")
	{
		session_start();
	}
		
	$page_name = pathinfo(APP_FOLDER."/".PHP_FOLDER."/$page_filename", PATHINFO_FILENAME);
	if(file_exists(APP_FOLDER."/".DB_FOLDER."/$page_filename"))
	{
		require_once(APP_FOLDER."/".DB_FOLDER."/$page_filename");
	}
	require_once(APP_FOLDER."/".PHP_FOLDER."/$page_filename");
	
?>