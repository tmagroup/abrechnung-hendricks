<?php
 if ( ! defined('APP_FOLDER')) exit('No direct script access allowed');
		
	if(!function_exists('setSession'))
	{
		function setSession($name,$value,$group=APP_SESSION)
		{
			if(session_id() == '')
			{
				session_start();
			}
			$_SESSION["$group"][$name] = $value;
		}
	}
	
	if(!function_exists('getSession'))
	{
		function getSession($name,$group=APP_SESSION)
		{
			
			if(session_id() == '')
			{
				session_start();
			}
			
			if(isset($_SESSION["$group"][$name]))
			{
				return $_SESSION["$group"][$name];
			}
			else
			{
				return false;
			}			
		}
	}
	
	if(!function_exists('deleteSession'))
	{
		function deleteSession($name,$group=APP_SESSION)
		{
			if(session_id() == '')
			{
				session_start();
			}
			if(isset($_SESSION["$group"][$name]))
			{
				unset($_SESSION["$group"][$name]);
			}
		}
	}
	
	if(!function_exists('deleteAllSession'))
	{
		function deleteAllSession($group=APP_SESSION)
		{
			if(session_id() == '')
			{
				session_start();
			}
			if(isset($_SESSION["$group"]))
			{
				unset($_SESSION["$group"]);
			}
		}
	}
	
	
	if(!function_exists('setMsg'))
	{
		function setMsg($msg,$error_type=0,$title="Success")
		{
			if(session_id() == '')
			{
				session_start();
			}
			
			if($error_type == 0)
			{
				$_SESSION['msg'] = "<script> toastr.success('$msg') </script>";
			}
			else
			{
				$_SESSION['msg'] = "<script> toastr.error('$msg') </script>";
			}
		}
	}
	
	if(!function_exists('getMsg'))
	{
		function getMsg()
		{
			$msg = "";
			if(session_id() == '')
			{
				session_start();
			}
			
			if(isset($_SESSION["msg"]))
			{
				$msg = $_SESSION["msg"];
				unset($_SESSION["msg"]);				
			}
			return $msg;
		}
	}	
	
	
	if(!function_exists('setLoginId'))
	{
		
		function setLoginId($id,$group=APP_SESSION)
		{
			$name_id = $group."_id";
			$_SESSION["$group"]["$name_id"] = $id;
		}
	}
	
	if(!function_exists('getLoginId'))
	{
		
		function getLoginId($group=APP_SESSION)
		{
			$name_id = $group."_id";
			if(isset($_SESSION["$group"]["$name_id"]))
			{
				return $_SESSION["$group"]["$name_id"];				
			}
			else
			{
				return false;
			}
		}
	}
	
	if(!function_exists('afterLogin'))
	{
		function afterLogin($group=APP_SESSION,$redirect="login",$msg="Bitte zuerst anmelden.")
		{
			$name_id = $group."_id";
			if(!isset($_SESSION["$group"]["$name_id"]))
			{
				setMsg("$msg",1);
				redirect("$redirect");
			}
		}
	}
	
	if(!function_exists('beforeLogin'))
	{
		function beforeLogin($group=APP_SESSION,$redirect='dashboard',$msg="Sie sind bereits angemeldet.")
		{
			$name_id = $group."_id";
			if(isset($_SESSION["$group"]["$name_id"]))
			{	
				setMsg("$msg",1);
				redirect("$redirect");
			}
		}
	}
?>