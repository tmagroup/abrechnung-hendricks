<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Seite nicht gefunden · Hendricks Coffee</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/hendricks.css">
</head>
<body class="hc-app">
	<main class="miss">
		<h1>Seite nicht gefunden</h1>
		<p>Die aufgerufene Adresse gibt es in diesem CRM nicht.</p>
		<p><a class="btn d" href="<?php echo $app_url; ?>">Zur Startseite</a></p>
	</main>
</body>
</html>
