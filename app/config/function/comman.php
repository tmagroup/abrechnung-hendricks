<?php 
if ( ! defined('APP_FOLDER')) exit('No direct script access allowed');

	if(!function_exists('fontFaceEncode'))
	{
		function fontFaceEncode($str){
			$str = str_replace("<font face=","###F@F###","$str");
			$str = str_replace("</font>","###F@E###","$str");
			$str = str_replace("'","###SQT###","$str");
			$str = str_replace("\\","###SLF###","$str");
			$str = str_replace("/","###SLB###","$str");
			$str = str_replace('"',"###DQT###","$str");
			$str = str_replace('<',"###LT###","$str");
			$str = str_replace('>',"###GT###","$str");
			return $str;
		}
	}
	
	if(!function_exists('fontFaceDecode'))
	{
		function fontFaceDecode($str){
			$str = str_replace("###F@F###","<font face=","$str");
			$str = str_replace("###F@E###","</font>","$str");
			$str = str_ireplace("###SLF###","\\","$str");
			$str = str_replace("###SLB###","/","$str");
			$str = str_replace("###SQT###","'","$str");
			$str = str_replace("###DQT###",'"',"$str");
			$str = str_replace('###LT###',"<","$str");
			$str = str_replace('###GT###',">","$str");
			$str = str_replace("background-color: rgba(0, 0, 0, 0.05);",'',"$str");
			return $str;
		}
	}
	
	
	if(!function_exists('fontFaceDecodeLG'))
	{
		function fontFaceDecodeLG($str){
			$str = str_replace('<',"&lt;","$str");
			$str = str_replace('>',"&gt;","$str");
			$str = htmlentities($str);			
			return $str;
		}
	}
	
	if(!function_exists('appUrl'))
	{
		function appUrl($path)
		{
			return APP_URL."$path";
		}
	}
	
	
	function getStringForValidation($word,$string)
	{
		$return 		= "";
		$new_string 	= strstr($string,"$word");
		$array_str 		= explode("[",$new_string);
		if(isset($array_str[1]))
		{
			$array_str2 = explode("]",$array_str[1]);
			if(isset($array_str2[0]))
			{
				$return = $array_str2[0];
			}
		}
		
		return $return;
		
	}
	
	function getStringBetween($string, $start, $end)
	{
		$string = ' ' . $string;
		$ini = strpos($string, $start);
		if ($ini == 0) return '';
		$ini += strlen($start);
		$len = strpos($string, $end, $ini) - $ini;
		return substr($string, $ini, $len);
	}
	
	if(!function_exists('url'))
	{
		function url($arg1,$arg2)
		{
			global $appurls;
			global $appfiles;
			
			if(empty($appurls) && empty($appfiles))
			{
				$appurls 				= array();
				$appfiles 				= array();
			}
			
			if(in_array($arg1,$appurls))
			{
				$index 					= array_search($arg1,$appurls);
				$appurls[$index]		= $arg1;
				$appfiles[$index]		= $arg2;
			}
			else if(in_array($arg2,$appfiles))
			{
				$index 					= array_search($arg2,$appfiles);
				$appurls[$index]		= $arg1;
				$appfiles[$index]		= $arg2;
			}
			else
			{
				$appurls[] 				= $arg1;
				$appfiles[]				= $arg2;
			}
		}
	}
	
	if(!function_exists('isDuplicate'))
	{
		function isDuplicate($array)
		{
			if(count($array) == count(array_unique($array)))
			{
				return false;
			}
			else
			{
				return true;
			}
		}
	}
	
	if(!function_exists('redirect'))
	{
		function redirect($page="")
		{
			if(trim($page) == "" || trim($page) == "/")
			{
				header("location: ".$_SERVER['HTTP_REFERER']);
			}
			else
			{				
				header("location: ".APP_URL."$page");
			}
			exit();
		}
	}
	
	if(!function_exists('addDBFile'))
	{
		function addDBFile($file_name)
		{	
			if(file_exists(APP_FOLDER."/".DB_FOLDER."/$file_name.php"))
			{
				require_once(APP_FOLDER."/".DB_FOLDER."/$file_name.php");
			}
		}
	}
	
	if(!function_exists('sendEmail'))
	{
		function sendEmail($to_email,$subject,$message,$from_email="noreply@indiangidc.com")
		{
			mail($to_email,$subject,$message);
		}
	}
	
	if(!function_exists('passwordRand'))
	{
		function passwordRand($length=8)
		{
			$keyspace 	= '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$str 		= '';
			$max 		= mb_strlen($keyspace, '8bit') - 1;
			
			for($i=0; $i<$length; $i++)
			{
				$str .= $keyspace[rand(0, $max)];
			}
			
			return $str;
		}
	}
	
	if(!function_exists('paging'))
	{
		function paging($total_row, $per_page,$url="",$tabs="",$float="right")
		{
			$config['base_url']          = base_url($url."/$tabs");
			$config['total_rows']        = $total_row;
			$config['per_page']          = $per_page;
			$config['full_tag_open']     = "<nav aria-label='navigation example' class='float-$float'><ul class='pagination p-b-10'>";
			$config['full_tag_close']    = '</ul></nav>';
			$config['num_tag_open']      = '<li class="page-item">';
			$config['num_tag_close']     = '</li>';
			$config['cur_tag_open']      = '<li class="active page-item"><a class="page-link" >';
			$config['cur_tag_close']     = '</a></li>';
			$config['prev_tag_open']     = '<li class="page-item">';
			$config['prev_tag_close']    = '</li>';
			$config['first_tag_open']    = '<li class="page-item">';
			$config['first_tag_close']   = '</li>';
			$config['last_tag_open']     = '<li class="page-item">';
			$config['last_tag_close']    = '</li>';
			$config['prev_link']         = '<i class="fa fa-angle-double-left"></i>';
			$config['prev_tag_open']     = '<li class="page-item">';
			$config['prev_tag_close']    = '</li>';
			$config['next_link']         = '<i class="fa fa-angle-double-right"></i>';
			$config['next_tag_open']     = '<li class="page-item">';
			$config['next_tag_close']    = '</li>';
			$config['attributes']        = array('class' => 'page-link');
			
			$ci 	= & get_instance();
			
			$ci->pagination->initialize($config);
			
			return $ci->pagination->create_links();
		}
	}
	
	if(!function_exists('fileUpload'))
	{
		function fileUpload($file_name,$folder_upload="",$type="")
		{
		    $delete                 = (isset($_POST["$file_name"]) && trim($_POST["$file_name"]) != '') ? $_POST["$file_name"] : false;
			if(trim($_FILES["$file_name"]['name']) != "")
			{
				$type  				= (trim($type) == "") ? "gif|jpg|jpeg|jpg" : "$type";
				$folder_upload      = (trim($folder_upload) == "") ? "images" : $folder_upload;
				$allowTypes 		= explode("|", $type);
				$fileName           = basename($_FILES["$file_name"]["name"]); 
                $fileType           = pathinfo($fileName, PATHINFO_EXTENSION);
                $new_file_name 		= time().".".$fileType;
                
                if($delete)
				{
				    if(file_exists("./assets/upload/$folder_upload/$delete"))
				    {
				        unlink("./assets/upload/$folder_upload/$delete");
				    }
				}
				
                //if(in_array($fileType, $allowTypes)){
                    move_uploaded_file($_FILES["$file_name"]['tmp_name'], "./assets/upload/$folder_upload/$new_file_name");
                    return $new_file_name;
                //}
                
			    return false;
			}
			else
			{
			    return (trim($delete) == "" || $delete == false) ? "" : $delete;   
			}
			
		}
	}
	
	if(!function_exists('getValue'))
	{
		function getValue($name,$data="")
		{
			$value = "";
			if(is_array($data))
			{
				if(isset($data[0]["$name"]))
				{
					$value = $data[0]["$name"];
				}
				else if(isset($data["$name"]))
				{
					$value = $data["$name"];
				}
			}			
			return $value;
		}
	}
	
	if(!function_exists('printOptionArray'))
	{
		function printOptionArray($array,$name_ary=0)
		{
			$name_ary 		= explode(", ",$name_ary);
			$data    		= "";
			$a        		= 1;
			if(empty($name_ary) || (isset($name_ary[0]) && ($name_ary[0] == "" || $name_ary[0] == 0)))
			{
				return "";
			} 
			foreach($array as $v)
			{
				if(in_array($a ,$name_ary))
				{
					$data .= $v.", ";
				}
				$a++;
			}
			return rtrim($data,", ");
		}
	}
	
	if(!function_exists('printOptionArrayMulti'))
	{
		function printOptionArrayMulti($array,$name_ary=0)
		{
			$name_ary 		= explode(", ",$name_ary);
			$data   	  	= "";
			$a    	    	= 1;
			
			if(empty($name_ary) || (isset($name_ary[0]) && ($name_ary[0] == "" || $name_ary[0] == 0)))
			{
				return "";
			}
			
			foreach($array as $v)
			{
				foreach($v as $v1)
				{
					if(in_array($a ,$name_ary))
					{
						$data .= $v1.", ";
					}
					$a++;
				}
			}
			return rtrim($data,", ");
		}
	}
	
	if(!function_exists('printOptionTable'))
	{
		
		function printOptionTable($tbnm,$ids=0,$field="name")
		{
			return $ids;
			$data 	= "";
			
			if($ids != "" && $ids != 0)
			{
				$ids = explode(", ",$ids);				
				foreach($ids as $id)
				{
					$tbdata 	 = selectFieldById("$tbnm","$field","$id");
					$data 		.= $tbdata["$field"].", ";					
				}
			}
			else
			{
				return "";
			}
			return rtrim($data, ", ");
		}
	}
	
	if(!function_exists('getOptionTable'))
	{
		function getOptionTable($table_name,$values="",$condition="",$field1="id",$field2="name")
		{
			$data   = "";		
			$values = explode(", ",$values);
			$array 	= select("$table_name",$condition);
			print_r($array);
			foreach($array as $v)
			{
				$s = "";
				if(in_array($v["$field1"],$values))
				{
					$s = "selected";
				}
				$data .= "<option $s value='".$v["$field1"]."'>".$v["$field2"]."</option>";
			}
			return $data;
		}
	}
	
	if(!function_exists('getOptionTableSearch'))
	{
		function getOptionTableSearch($table_name,$values=0,$condition="",$field="name")
		{
			$data   = "";		
			$values = explode(", ",$values);
			$array = select("$table_name",$condition);
			foreach($array as $v)
			{
				$s = "";
				if(in_array($v["id"],$values))
				{
					$s = "selected";
				}
				$data .= "<option $s value='".$table_name."-".$v["id"]."'>".$v["$field"]."</option>";
			}
			return $data;
		}
	}
	
	if(!function_exists("getOptionArrayMulti"))
	{
		function getOptionArrayMulti($array,$values=0)
		{
			$data   = "";
			$values = explode(", ",$values);
			$a      = 1;
	
			foreach($array as $k=>$v1)
			{
				$data .= "<optgroup class='mediumtxt' label='$k'>";
				foreach($v1 as $v)
				{
					$s = "";
					if(in_array($a,$values))
					{
						$s = "selected";
					}
					$data .= "<option $s value='$a'>$v</option>";
					$a++;
				}
				$data .= "</optgroup>";
			}
			return $data;
		}
	}
	
	if(!function_exists('getOptionArray'))
	{
	
		function getOptionArray($array,$values=0)
		{
			$data   = "";
			$values = explode(", ",$values);
			$a      = 1;
			
			foreach($array as $v)
			{
				$s = "";
				if(in_array($a,$values))
				{
					$s = "selected";
				}
				$data .= "<option $s value='$a'>$v</option>";
				$a++;
			}
			return $data;
		}
	}
	
	if(!function_exists('getOptionArrayName'))
	{
	
		function getOptionArrayName($array,$values=0)
		{
			$data   = "";
			$values = explode(", ",$values);
			
			foreach($array as $v)
			{
				$s = "";
				if(in_array($v,$values))
				{
					$s = "selected";
				}
				$data .= "<option $s value='$v'>$v</option>";
				
			}
			return $data;
		}
	}
	
	if(!function_exists('getChecked'))
	{
		function getChecked($v1,$v2)
		{
			if($v1 == $v2)
			{
				echo "checked";
			}
		}
	}
	
	if(!function_exists('getSelected'))
	{
		function getSelected($v1,$v2)
		{
			if($v1 == $v2)
			{
				echo "selected";
			}
		}
	}
	
	if(!function_exists('dateConvert'))
	{
		function dateConvert($date,$sparator="-",$rc="-")
		{
			if($date != "")
			{
				$dob = explode("$sparator",$date);
				return $dob[2]."$rc".$dob[1]."$rc".$dob[0];				
			}			
		}
	}
	
	if(!function_exists('dateTimeConvert'))
	{
		function dateTimeConvert($date,$sparator="-",$rc="-")
		{
			if($date != "")
			{
				$dob 	= explode("$sparator",$date);
				$time 	= explode(" ",$dob[2]);
				return $time[0]."$rc".$dob[1]."$rc".$dob[0]." ".$time[1];				
			}			
		}
	}
	
	if(!function_exists('arrayToString'))
	{
		function arrayToString($data,$sparator=", ")
		{
			$rdata = array();
			foreach($data as $k=>$v)
			{
				if(is_array($v))
				{
					$rdata["$k"] = implode(", ",$v);
				}
				else
				{
					$rdata["$k"] = $v;
				}
			}
			
			return $rdata;
		}
	}
	
	
	if(!function_exists('salaryConvert'))
	{
		function salaryConvert($lac,$thosand,$format=true)
		{
			$lac = str_replace(" Lcas","",$lac);
			$lac =  (int)str_replace(" Lac","",$lac)."00000";
			$thosand = str_replace(" Thousands","",$thosand);
			$thosand =  (int)str_replace(" Thousand","",$thosand)."000";
			
			$value = ($lac + $thosand);
			if($format)
			{
				$value = number_format($value);			
			}
			return $value;
		}
	}
	
	if(!function_exists('stringRang'))
	{
		function stringRang($str,$no)
		{
			
			if(strlen($str) >= $no)
			{
				$str = substr($str,0,$no)."...";
			}
			
			return $str;
		}
	}
	
	if(!function_exists('singleComa'))
	{
		function singleComa($str,$sptr=", ")
		{
			$data = "";
			$array = explode($sptr,$str);
			foreach($array as $val){
				if($val != "")
				{
					$data .= "'$val', ";					
				}
			}
			return rtrim($data, ", ");
		}
	}
	
	
	if(!function_exists('likeCondition'))
	{
		function likeCondition($str,$field_name,$qry_sptr="||",$sptr=", ")
		{
			$data 	= "";
			$array = explode($sptr,$str);
			foreach($array as $val){
				if($val != "")
				{
					$data .= " ($field_name like '%$val%') $qry_sptr ";					
				}
			}
			return rtrim($data, " $qry_sptr ");
		}
	}
	
	
	if(!function_exists('yearDiffrance'))
	{
		function yearDiffrance($endDate, $beginDate)
		{
		   $date_parts1 = explode("-", $beginDate);
		   $date_parts2 = explode("-", $endDate);
		   $start_date = gregoriantojd($date_parts1[1], $date_parts1[2], $date_parts1[0]);
		   $end_date = gregoriantojd($date_parts2[1], $date_parts2[2], $date_parts2[0]);
		   $diff = abs($end_date - $start_date);
		   //$years = floor($diff / 365.25);
		   $years = floor($diff / 364);
		   return $years;
		}
	}
	
	if(!function_exists('seoUrl'))
	{
		function seoUrl($value)
		{
			$value = strtolower($value);
			$value = str_replace(" ","-",$value);
			$removechar = array("?","/","{","}","|","(",")","!",",",":",".","&");
			
			foreach($removechar as $v){
				$value = str_replace($v,"",$value);	
			}
			$value = preg_replace('/-+/', '-', $value);
			
			return $value;
		}
	}
	
	if(!function_exists('isBlankMultiArray'))
	{
		function isBlankMultiArray($data)
		{			
			foreach($data as $key=>$val)
			{
				if(!is_array($val[0]))
				{
					return true;
				}
				
				foreach($val[0] as $k=>$v)
				{
					$v = trim($v," ");
					if($v == "")
					{
						return true;
					}
				}
			}
			return false;
		}
	}
	
	if(!function_exists('getBlankValue'))
	{
		function getBlankValue($data)
		{			
			foreach($data as $key=>$val)
			{
				if(!is_array($val[0]))
				{
					return ucfirst(str_replace("_"," ",$key));
				}
				foreach($val[0] as $k=>$v)
				{
					
					$v = trim($v," ");
					if($v == "")
					{
						return ucfirst(str_replace("_"," ",$k));
					}
				}
			}
			return false;
		}
	}	
	
	if(!function_exists('xssClean'))
	{	
		function xssClean($all_data)
		{
			$return_array = array();
			
			if(is_array($all_data))
			{
				foreach($all_data as $k=>$data)
				{
					// Fix &entity\n;
					$data = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $data);
					$data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
					$data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
					$data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

					// Remove any attribute starting with "on" or xmlns
					$data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

					// Remove javascript: and vbscript: protocols
					$data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
					$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
					$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

					// Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
					$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
					$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
					$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

					// Remove namespaced elements (we do not need them)
					$data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

					do
					{
						// Remove really unwanted tags
						$old_data = $data;
						$data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
					}
					while($old_data !== $data);
					
					$return_array["$k"] = addslashes($data);
				}
				
				return $return_array;
				
			}
			else
			{
				$data = $all_data;
				
				// Fix &entity\n;
				$data = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $data);
				$data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
				$data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
				$data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

				// Remove any attribute starting with "on" or xmlns
				$data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

				// Remove javascript: and vbscript: protocols
				$data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
				$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
				$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

				// Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
				$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
				$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
				$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

				// Remove namespaced elements (we do not need them)
				$data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

				do
				{
					// Remove really unwanted tags
					$old_data = $data;
					$data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
				}
				while($old_data !== $data);
				
				return addslashes($data);
			}
		}
	}
	
	if(!function_exists('generateOTP'))
	{

		function generateOTP($length=6)
		{
			$keyspace 	= '0123456789';
			$str 		= '';
			$max 		= mb_strlen($keyspace, '8bit') - 1;
			
			for($i=0; $i<$length; $i++)
			{
				$str .= $keyspace[rand(0, $max)];
			}
			
			return $str;
		}
	}
	