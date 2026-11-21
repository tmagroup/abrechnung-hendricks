<?php
if (!defined('APP_FOLDER')) exit('No direct script access allowed');

require_once __DIR__ . '/../../lib/CoffeejiVat.php';
require_once __DIR__ . '/../../lib/CoffeejiSettlement.php';
require_once __DIR__ . '/../../lib/CoffeejiSettlementPdf.php';
require_once __DIR__ . '/../../lib/CoffeejiSettlementStore.php';
require_once __DIR__ . '/../../lib/AbrechnungListQuery.php';
require_once __DIR__ . '/../../lib/AbrechnungId.php';

$hcMerchantEnv = getenv('ABRECHNUNG_HAENDLER');
$hcMerchantConfig = isset($abrechnung_haendler) ? (string) $abrechnung_haendler : null;
if (!defined('ABRECHNUNG_HAENDLER')) {
	define('ABRECHNUNG_HAENDLER', CoffeejiSettlement::merchant(
		$hcMerchantEnv === false ? null : (string) $hcMerchantEnv,
		$hcMerchantConfig
	));
}

function abrechnung_haendler(): string
{
	return defined('ABRECHNUNG_HAENDLER') ? (string) ABRECHNUNG_HAENDLER : CoffeejiSettlement::MERCHANT_DEFAULT;
}
