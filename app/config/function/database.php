<?php
 if (!defined('APP_FOLDER')) exit('No direct script access allowed');

	if(!function_exists('select'))
	{
		function select($table_name,$condition="",$field_name="*")
		{
			if($condition != "")
			{
				$condition = "where $condition";
			}

			$mysqli 	= mysqli_connect(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_NAME);
			$qry 		= "select $field_name from $table_name $condition";

			$res 		= mysqli_query($mysqli,$qry);
			$data 		= array();
			while($row = mysqli_fetch_assoc($res))
			{
			    $rw = array();
			    foreach($row as $k=>$v){
			        $rw["$k"] = $v;
			    }
				$data[] = $rw;
			}
			mysqli_free_result($res);
			mysqli_close($mysqli);

			
			return $data;
		}
	}


	if(!function_exists('selectById'))
	{
		function selectById($table_name,$id,$field_name="*")
		{
			$mysqli 	= mysqli_connect(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_NAME);
			$qry 		= "select $field_name from $table_name where id = $id";
			$res 		= mysqli_query($mysqli,$qry);
			$row 		= mysqli_fetch_assoc($res);

			mysqli_free_result($res);
			mysqli_close($mysqli);
            $rw = array();
			if(is_array($row)){				
				foreach($row as $k=>$v){
					$rw["$k"] = $v;
				}
			}
			return $rw;
		}
	}

	if(!function_exists('insert'))
	{
		function insert($table_name,$data)
		{
			$fields 	= "";
			$values 	= "";

			foreach($data as $k=>$v)
			{
				$fields .= "$k, ";
				$values .= "'".$v."', ";
			}
			
			$flag		= false;
			$fields 	= rtrim($fields,', ');
			$values 	= rtrim($values,', ');
			$mysqli 	= mysqli_connect(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_NAME);
			$qry 		= "insert into $table_name ($fields) values($values)";
			if(mysqli_query($mysqli,$qry))
			{
				$flag = mysqli_insert_id($mysqli);
			}
			mysqli_close($mysqli);
			return $flag;
		}
	}

	if(!function_exists('update'))
	{
		function update($table_name,$data,$condition="")
		{
			$field_value = "";
			foreach($data as $k=>$v)
			{
				$field_value .= "$k = '".$v."', ";
			}
			if($condition != "")
			{
				$condition = "where $condition";
			}

			$flag			= false;
			$field_value 	= rtrim($field_value,', ');
			$mysqli 		= mysqli_connect(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_NAME);
			$qry 			= "update $table_name set $field_value $condition";

			if(mysqli_query($mysqli,$qry))
			{
				$flag = true;
			}
			mysqli_close($mysqli);
			return $flag;
		}
	}

	if(!function_exists('updateById'))
	{
		function updateById($table_name,$data,$id)
		{
			$field_value = "";
			foreach($data as $k=>$v)
			{
				$field_value .= "$k = '".$v."', ";
			}
			$flag			= false;
			$field_value 	= rtrim($field_value,', ');
			$mysqli 		= mysqli_connect(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_NAME);
			$qry 			= "update $table_name set $field_value where id = $id";

			if(mysqli_query($mysqli,$qry))
			{
				$flag = true;
			}
			mysqli_close($mysqli);
			return $flag;
		}
	}

	if(!function_exists('deleteById'))
	{
		function deleteById($table_name,$id)
		{
			$flag		= false;
			$mysqli 	= mysqli_connect(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_NAME);
			$qry 		= "delete from $table_name where id = $id";

			if(mysqli_query($mysqli,$qry))
			{
				$flag 	= true;
			}

			mysqli_close($mysqli);
			return $flag;
		}
	}

	if(!function_exists('delete'))
	{
		function delete($table_name,$condition="")
		{
			if($condition != "")
			{
				$condition = "where $condition";
			}

			$flag		= false;
			$mysqli 	= mysqli_connect(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_NAME);
			$qry 		= "delete from $table_name $condition";

			if(mysqli_query($mysqli,$qry))
			{
				$flag 	= true;
			}

			mysqli_close($mysqli);
			return $flag;
		}
	}

	if(!function_exists('query'))
	{
		function query($qry)
		{
			$mysqli 	= mysqli_connect(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_NAME);
			$qry 		= "$qry";
			$res 		= mysqli_query($mysqli,$qry);
			$data 		= array();
			while($row = mysqli_fetch_assoc($res))
			{
			    $rw = array();
			    foreach($row as $k=>$v){
			        $rw["$k"] = $v;
			    }
				$data[] = $rw;
			}

			mysqli_free_result($res);
			mysqli_close($mysqli);
			return $data;
		}
	}

	if(!function_exists('countRec'))
	{
		function countRec($table_name,$condition="")
		{

			if($condition != "")
			{
				$condition = " where ".$condition;
			}

			$table_name 	= strtolower($table_name);
			if(strpos($table_name, "limit"))
			{
				$table_name 	= substr($table_name, 0, strpos($table_name, "limit"));
			}
			if(strpos($table_name, "from"))
			{
				$table_name 	= strstr($table_name,"from");
				$table_name 	= ltrim($table_name,"from");
			}

			$mysqli 	= mysqli_connect(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_NAME);
			$qry 		= "select count(*) as total_rec from $table_name $condition";
			$res 		= mysqli_query($mysqli,$qry);
			$row 		= mysqli_fetch_assoc($res);

			mysqli_free_result($res);
			mysqli_close($mysqli);
			return $row["total_rec"];
		}
	}

	if(!function_exists('deleteSlashes'))
	{
		function deleteSlashes($value)
		{
			$value = is_array($value) ? array_map('deleteSlashes', $value) : str_replace("\'","'",$value);
			$value = is_array($value) ? array_map('deleteSlashes', $value) : str_replace('\"','"',$value);
			return $value;
		}
	}
