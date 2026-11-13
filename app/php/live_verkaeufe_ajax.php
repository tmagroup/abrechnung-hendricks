<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	header('Content-Type: application/json; charset=utf-8');
	if (!getLoginId()) {
		http_response_code(401);
		echo json_encode(array('ok' => false, 'message' => 'Bitte anmelden.'), JSON_UNESCAPED_UNICODE);
		exit;
	}

	$now = CoffeejiTime::now();
	$deviceFilter = trim((string) ($_GET['geraet'] ?? ''));
	try {
		$bundle = (new CoffeejiReadModel(new CoffeejiRepository()))->live($now, $deviceFilter !== '' ? $deviceFilter : null);
	} catch (Throwable $e) {
		(new CoffeejiLogger(COFFEEJI_LOG))->error('Live-Ajax: ' . $e->getMessage());
		echo json_encode(array('ok' => false, 'message' => 'Die Verkäufe konnten nicht gelesen werden.'), JSON_UNESCAPED_UNICODE);
		exit;
	}

	if (($bundle['state'] ?? '') !== 'ready') {
		echo json_encode(array(
			'ok' => true,
			'html' => hc_notice('gelb', 'Noch nicht verbunden', (string) $bundle['message']),
			'sum' => hc_money(0),
			'count' => '0',
			'updated' => $now->format('d.m.Y, H:i') . ' Uhr',
		), JSON_UNESCAPED_UNICODE);
		exit;
	}

	echo json_encode(array(
		'ok' => true,
		'html' => hc_render_live_table($bundle['orders']),
		'sum' => hc_money((float) $bundle['sales']['amount']),
		'count' => (string) (int) $bundle['sales']['count'],
		'updated' => $now->format('d.m.Y, H:i') . ' Uhr',
	), JSON_UNESCAPED_UNICODE);
	exit;
