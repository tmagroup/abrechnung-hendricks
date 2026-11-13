<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	$app_url = 'https://abrechnung.thehendricks-coffee.de/';
	$default_file = 'dashboard.php';

	$db_hostname = 'localhost';
	$db_username = 'abrechnung';
	$db_password = 'CHANGE_ME';
	$db_name = 'abrechnung';

	$app_session = 'execel_admin';

	// Coffeeji Merchant Open API (nur lesend). Leer lassen, solange nicht angebunden.
	// Dieselben Schlüssel können als Umgebungsvariablen gesetzt werden und haben Vorrang:
	// COFFEEJI_BASE_URL, COFFEEJI_API_KEY, COFFEEJI_API_SECRET
	$coffeeji_base_url = 'https://vdopen.coffeeji.com';
	$coffeeji_api_key = 'CHANGE_ME';
	$coffeeji_api_secret = 'CHANGE_ME';
?>
