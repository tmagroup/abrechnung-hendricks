<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();

	function migrateLegacyPasswords()
	{
		$users = selectPrepared("SELECT id, password FROM manage_user", "", array());
		$migrated = 0;

		foreach($users as $user)
		{
			$stored_password = (string)($user['password'] ?? '');
			if($stored_password === '' || isPasswordHash($stored_password))
			{
				continue;
			}

			$new_hash = password_hash($stored_password, PASSWORD_DEFAULT);
			if($new_hash && executePrepared("UPDATE manage_user SET password = ? WHERE id = ?", "si", array($new_hash, (int)$user['id'])))
			{
				$migrated++;
			}
		}

		return $migrated;
	}

	if(isset($_POST['migrate_passwords']))
	{
		$migrated = migrateLegacyPasswords();
		setMsg($migrated > 0 ? "$migrated password(s) migrated successfully." : 'No legacy passwords found.');
		redirect('account');
	}

	if(isset($_POST['change_password']))
	{
		$current_password = (string)($_POST['current_password'] ?? '');
		$new_password = (string)($_POST['new_password'] ?? '');
		$confirm_password = (string)($_POST['confirm_password'] ?? '');
		$user_id = (int)getLoginId();

		if($current_password === '' || $new_password === '' || $confirm_password === '')
		{
			setMsg('Please fill in all password fields.', 1);
			redirect('account');
		}

		if(strlen($new_password) < 10)
		{
			setMsg('The new password must be at least 10 characters long.', 1);
			redirect('account');
		}

		if($new_password !== $confirm_password)
		{
			setMsg('The new password confirmation does not match.', 1);
			redirect('account');
		}

		$users = selectPrepared("SELECT id, password FROM manage_user WHERE id = ? LIMIT 1", "i", array($user_id));
		if(count($users) !== 1)
		{
			setMsg('User account not found.', 1);
			redirect('account');
		}

		$user = $users[0];
		$stored_password = (string)$user['password'];
		if(!isPasswordHash($stored_password))
		{
			setMsg('Please migrate legacy passwords first.', 1);
			redirect('account');
		}

		if(!password_verify($current_password, $stored_password))
		{
			setMsg('The current password is incorrect.', 1);
			redirect('account');
		}

		$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
		if(!$new_hash)
		{
			setMsg('The new password could not be saved.', 1);
			redirect('account');
		}

		if(executePrepared("UPDATE manage_user SET password = ? WHERE id = ?", "si", array($new_hash, $user_id)))
		{
			setMsg('Password updated successfully.');
		}
		else
		{
			setMsg('The new password could not be saved.', 1);
		}
		redirect('account');
	}
?>
