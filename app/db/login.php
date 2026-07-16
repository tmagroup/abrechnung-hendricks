<?php 
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	if(isset($_POST['login']))
	{
		setRules('user_name','User Name','required');
		setRules('password','Password','required');
		if(validation())
		{
			extract(xssClean($_POST));
			$mu = select('manage_user',"(email = '$user_name' || mobile = '$user_name') && password = '$password'");
			
			if(count($mu) == 1)
			{
				setLoginId($mu[0]['id']);
				setMsg('login successfully.');
				redirect('detailabrechnung');
			}
			else
			{
				setMsg('Your user name or password is wrong.',1);
				redirect();
			}
			
		}
	}
?>