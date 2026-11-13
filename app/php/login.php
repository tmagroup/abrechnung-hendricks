<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	beforeLogin();
?>
<!DOCTYPE html>
<html lang="de">
	<head>
		<meta charset="utf-8">
		<title>Anmelden · Hendricks Coffee</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/hendricks.css">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/toastr.min.css">
		<script src="<?php echo $app_url; ?>assets/js/jquery.min.js"></script>
		<script src="<?php echo $app_url; ?>assets/js/toastr.min.js"></script>
	</head>
	<body class="hc-login">
		<?php echo getMsg(); ?>
		<form class="login-card" method="post">
			<img src="<?php echo $app_url; ?>assets/images/logo-black.png" alt="The Hendricks Coffee">
			<h1>CRM</h1>
			<p class="lead">Anmeldung für Abrechnung und Live-Übersicht</p>
			<div class="field">
				<input type="text" name="user_name" placeholder="E-Mail oder Mobilnummer" value="<?php echo htmlspecialchars((string) ($_POST['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username">
				<div class="err"><?php echo errorMsg('user_name'); ?></div>
			</div>
			<div class="field">
				<input type="password" name="password" placeholder="Passwort" autocomplete="current-password">
				<div class="err"><?php echo errorMsg('password'); ?></div>
			</div>
			<div class="rowish">
				<label><input type="checkbox" name="remeber" id="remember"> Angemeldet bleiben</label>
				<button type="submit" name="login">Anmelden</button>
			</div>
		</form>
	</body>
</html>
