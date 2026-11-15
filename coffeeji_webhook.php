<?php

declare(strict_types=1);

ob_start();

define('APP_FOLDER', 'app');

$configFile = __DIR__ . '/app/config/config.php';
if (is_file($configFile)) {
	require $configFile;
}

require __DIR__ . '/app/config/function/coffeeji.php';

coffeeji_webhook_dispatch();
