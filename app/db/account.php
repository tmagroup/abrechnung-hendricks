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
		setMsg($migrated === 1 ? '1 Passwort wurde gehasht.' : ($migrated > 1 ? $migrated . ' Passwörter wurden gehasht.' : 'Keine Klartext-Passwörter gefunden.'));
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
			setMsg('Bitte alle Passwortfelder ausfüllen.', 1);
			redirect('account');
		}

		if(strlen($new_password) < 10)
		{
			setMsg('Das neue Passwort muss mindestens 10 Zeichen lang sein.', 1);
			redirect('account');
		}

		if($new_password !== $confirm_password)
		{
			setMsg('Die Passwort-Wiederholung stimmt nicht überein.', 1);
			redirect('account');
		}

		$users = selectPrepared("SELECT id, password FROM manage_user WHERE id = ? LIMIT 1", "i", array($user_id));
		if(count($users) !== 1)
		{
			setMsg('Das Benutzerkonto wurde nicht gefunden.', 1);
			redirect('account');
		}

		$user = $users[0];
		$stored_password = (string)$user['password'];
		if(!isPasswordHash($stored_password))
		{
			setMsg('Bitte zuerst die alten Klartext-Passwörter umstellen.', 1);
			redirect('account');
		}

		if(!password_verify($current_password, $stored_password))
		{
			setMsg('Das aktuelle Passwort ist falsch.', 1);
			redirect('account');
		}

		$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
		if(!$new_hash)
		{
			setMsg('Das neue Passwort konnte nicht gespeichert werden.', 1);
			redirect('account');
		}

		if(executePrepared("UPDATE manage_user SET password = ? WHERE id = ?", "si", array($new_hash, $user_id)))
		{
			setMsg('Das Passwort wurde gespeichert.');
		}
		else
		{
			setMsg('Das neue Passwort konnte nicht gespeichert werden.', 1);
		}
		redirect('account');
	}
?>
