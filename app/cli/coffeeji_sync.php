<?php

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "Dieses Skript läuft nur in der Kommandozeile.\n");
	exit(1);
}

$root = dirname(__DIR__, 2);
chdir($root);

define('APP_FOLDER', 'app');
define('CONFIG_FOLDER', 'config');
define('FUNCTION_FOLDER', 'function');
define('PHP_FOLDER', 'php');
define('DB_FOLDER', 'db');

$configFile = APP_FOLDER . '/' . CONFIG_FOLDER . '/config.php';
if (!is_file($configFile)) {
	fwrite(STDERR, "Konfiguration fehlt: app/config/config.php\n");
	exit(1);
}

require $configFile;
require APP_FOLDER . '/' . CONFIG_FOLDER . '/function/coffeeji.php';

$job = $argv[1] ?? '';
$allowed = array('devices', 'materials', 'orders', 'orders-backfill');
if (!in_array($job, $allowed, true)) {
	fwrite(STDOUT, "Aufruf: php app/cli/coffeeji_sync.php <" . implode('|', $allowed) . ">\n");
	exit(1);
}

if (!coffeeji_configured()) {
	fwrite(STDERR, "Coffeeji ist nicht konfiguriert. COFFEEJI_API_KEY und COFFEEJI_API_SECRET setzen.\n");
	exit(1);
}

$repository = new CoffeejiRepository();
if (!$repository->tablesReady()) {
	fwrite(STDERR, "Coffeeji-Tabellen fehlen. Migration app/db/migrations/20261113_coffeeji.sql einspielen.\n");
	exit(1);
}

$client = new CoffeejiClient(
	COFFEEJI_BASE_URL,
	COFFEEJI_API_KEY,
	COFFEEJI_API_SECRET,
	new CoffeejiLogger(COFFEEJI_LOG)
);
$sync = new CoffeejiSync($client, $repository);
$runId = $repository->startRun($job);

try {
	if ($job === 'devices') {
		$result = $sync->devices();
	} elseif ($job === 'materials') {
		$result = $sync->materials();
	} else {
		$mode = $job === 'orders-backfill' ? 'backfill' : 'recent';
		$window = CoffeejiTime::window($mode);
		$result = $sync->orders($window[0], $window[1]);
	}
	$repository->finishRun($runId, 'ok', $result['message'], $result['records']);
	fwrite(STDOUT, $job . ': ' . $result['message'] . "\n");
	exit(0);
} catch (Throwable $e) {
	$message = $e->getMessage();
	try {
		$repository->finishRun($runId, 'error', $message, 0);
	} catch (Throwable $ignored) {
	}
	fwrite(STDERR, $job . ' fehlgeschlagen: ' . $message . "\n");
	exit(1);
}
