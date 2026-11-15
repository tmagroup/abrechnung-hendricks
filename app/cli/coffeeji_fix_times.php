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
require APP_FOLDER . '/lib/CoffeejiTimeFix.php';

$args = array_slice($argv, 1);
foreach ($args as $arg) {
	if ($arg !== '--dry-run') {
		fwrite(STDOUT, "Aufruf: php app/cli/coffeeji_fix_times.php [--dry-run]\n");
		exit(1);
	}
}

if (!defined('DB_HOSTNAME') || !defined('DB_NAME') || DB_NAME === '') {
	fwrite(STDERR, "Die Datenbank ist in der Konfiguration nicht gesetzt.\n");
	exit(1);
}

$db = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$db) {
	fwrite(STDERR, "Die Datenbank ist nicht erreichbar.\n");
	exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

$repository = new CoffeejiRepository();
if (!$repository->tablesReady()) {
	fwrite(STDERR, "Coffeeji-Tabellen fehlen. Migration app/db/migrations/20261113_coffeeji.sql einspielen.\n");
	exit(1);
}

$dryRun = CoffeejiTimeFix::isDryRun($args);

try {
	$fix = new CoffeejiTimeFix($db);
	$result = $fix->run($dryRun);
	fwrite(STDOUT, $fix->summary($result) . "\n");
	mysqli_close($db);
	exit(0);
} catch (Throwable $e) {
	fwrite(STDERR, "Zeitkorrektur fehlgeschlagen: " . $e->getMessage() . "\n");
	mysqli_close($db);
	exit(1);
}
