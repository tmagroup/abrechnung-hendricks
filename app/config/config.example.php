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

	// Webhook-Empfang. Leer oder kürzer als 16 Zeichen deaktiviert den Endpunkt (HTTP 403).
	// Erzeugen: openssl rand -hex 32
	// Dieselbe URL in alle drei Felder im Portal (Benutzerinfo, Tab "open api").
	// Umgebungsvariablen haben Vorrang: COFFEEJI_WEBHOOK_TOKEN, COFFEEJI_WEBHOOK_IPS, COFFEEJI_WEBHOOK_MAX_BYTES
	$coffeeji_webhook_token = '';
	// Optional, kommagetrennt. Leer = keine IP-Prüfung. Beispiel: 203.0.113.10,203.0.113.11
	$coffeeji_webhook_ips = '';
	// Body-Obergrenze in Bytes (1024 bis 1048576).
	$coffeeji_webhook_max_bytes = 65536;

	// heartTime von Geräteliste und Gerätedetails, wenn der Payload keine timeZoneId hat.
	// Die Schnittstelle liefert eine naive Uhr, im Winter genau 3 Stunden hinter MEZ
	// (Portal 15.11.2026 17:15:16, Rohwert 14:15:16). Standard ist der feste Offset UTC−2.
	// storeSource rechnet danach nach Europe/Berlin. Im Sommer (MESZ, UTC+2) wird der
	// Abstand 4 Stunden, nicht 2: die Quelle springt nicht mit. Das ist bei UTC-02:00 so.
	// Leer oder 'none' lässt die Roh-Wanduhr stehen. Eine timeZoneId im Payload hat Vorrang.
	// Bestellungen und fix_times lesen diesen Wert nicht. Umgebungsvariable hat Vorrang:
	// COFFEEJI_DEVICE_TIME_ZONE
	$coffeeji_device_time_zone = 'UTC-02:00';
?>
