<?php
 if ( ! defined('APP_FOLDER')) exit('No direct script access allowed');

	global $validation;
	$validation = 'no_error';
	
	if(!function_exists('setValue'))
	{
		function setValue($name,$value="")
		{
			$return = "";
			if($value != "" || $value != 0)
			{
				$return = $value;
			}
			else if(isset($_REQUEST["$name"]))
			{
				$return = $_REQUEST["$name"];				
			}
			return $return;
		}
	}
	
	if(!function_exists('setChecked'))
	{
		function setChecked($name,$value,$flag="")
		{
			$return = "";
			
			if($flag === true)
			{
				$return = 'checked';
			}
			else if(isset($_REQUEST["$name"]))
			{
				if(is_string($_REQUEST["$name"]) && $_REQUEST["$name"] == $value)
				{
					$return = 'checked';
				}
				else if(is_array($_REQUEST["$name"]) && in_array($value,$_REQUEST["$name"]))
				{
					$return = 'checked';
				}
			}
			else if(is_string($flag) && $flag != "" && $flag == $value)
			{
				$return = 'checked';
			}
			else if(is_array($flag) && (!empty($flag)) && in_array($value,$flag))
			{
				$return = 'checked';
			}
			return $return;
		}
	}
	
	if(!function_exists('setSelected'))
	{
		function setSelected($name,$value,$flag="")
		{
			$return = "";
			
			if($flag === true)
			{
				$return = 'selected';
			}
			else if(isset($_REQUEST["$name"]))
			{
				if(is_string($_REQUEST["$name"]) && $_REQUEST["$name"] == $value)
				{
					$return = 'selected';
				}
				else if(is_array($_REQUEST["$name"]) && in_array($value,$_REQUEST["$name"]))
				{
					$return = 'selected';
				}
			}
			else if(is_string($flag) && $flag != "" && $flag == $value)
			{
				$return = 'selected';
			}
			else if(is_array($flag) && (!empty($flag)) && in_array($value,$flag))
			{
				$return = 'selected';
			}
			return $return;
		}
	}
	
	
	if(!function_exists('errorMsg'))
	{
		function errorMsg($name)
		{
			$msg = getSession("$name",'validation');
			deleteSession("$name",'validation');
			return $msg;
		}
	}
	
	if(!function_exists('setRules'))
	{
		function setRules($name,$value,$rules="",$msg=array(),$is_file=false)
		{
			global $validation;
			$rules = "$rules|";			
			//required, pattern, match, diff, unique, min_length, max_length, exact_length, <, >=, <=, >=, in_list, char, char_no, char_no_space, char_dash, no, decimal, url, email, emails, ip, indian_mobile, xss_clean, available
			//if(isset($_REQUEST["$name"]))
			//{
				if($is_file)
				{
					if(!isset($_FILES["$name"]["name"]))
					{
						setSession("$name","$value is required.",'validation');
						$validation = "error";
					}
					else if($_FILES["$name"]["name"] == "" && $_FILES["$name"]["name"] == 0)
					{
						setSession("$name","$value is required.",'validation');
						$validation = "error";
					}
				}
				else
				{					
					if(strpos($rules, 'required|') !== false)
					{
						if(!isset($_REQUEST["$name"]))
						{
							setSession("$name","$value is required.",'validation');
							$validation = "error";
						}
						else if($_REQUEST["$name"] == "" && $_REQUEST["$name"] == 0)
						{
							setSession("$name","$value is required.",'validation');
							$validation = "error";
						}
					}
				}
			//}
			//else
			//{
				//setMsg('Field is required')
				//$validation = false;
			//}
		}
	}
	
	
	if(!function_exists('validation'))
	{
		function validation()
		{
			global $validation;
			if($validation == "error")
			{
				return false;
			}
			else
			{
				return true;
			}
		}
	}
	
	


?>