<?php 
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	if(isset($_POST['login']))
	{
		setRules('user_name','User Name','required');
		setRules('password','Password','required');
		if(validation())
		{
			$user_name = trim($_POST['user_name'] ?? '');
			$password = (string)($_POST['password'] ?? '');

			$users = selectPrepared(
				"SELECT id, password FROM manage_user WHERE email = ? OR mobile = ? LIMIT 1",
				"ss",
				array($user_name, $user_name)
			);
			
			if(count($users) == 1)
			{
				$user = $users[0];
				$stored_password = (string)$user['password'];

				if(password_verify($password, $stored_password))
				{
					session_regenerate_id(true);
					setLoginId($user['id']);
					setMsg('login successfully.');
					redirect('detailabrechnung');
				}
			}

			setMsg('Your user name or password is wrong.',1);
			redirect();
		}
	}
?>
