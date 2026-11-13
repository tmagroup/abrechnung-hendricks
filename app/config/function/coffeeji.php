<?php
if (!defined('APP_FOLDER')) exit('No direct script access allowed');

require_once __DIR__ . '/../../lib/CoffeejiException.php';
require_once __DIR__ . '/../../lib/CoffeejiSignature.php';
require_once __DIR__ . '/../../lib/CoffeejiResponse.php';
require_once __DIR__ . '/../../lib/CoffeejiLogger.php';
require_once __DIR__ . '/../../lib/CoffeejiTime.php';
require_once __DIR__ . '/../../lib/CoffeejiOrder.php';
require_once __DIR__ . '/../../lib/CoffeejiDevice.php';
require_once __DIR__ . '/../../lib/CoffeejiMaterial.php';
require_once __DIR__ . '/../../lib/CoffeejiUpsert.php';
require_once __DIR__ . '/../../lib/CoffeejiRevenue.php';
require_once __DIR__ . '/../../lib/CoffeejiClient.php';
require_once __DIR__ . '/../../lib/CoffeejiRepository.php';
require_once __DIR__ . '/../../lib/CoffeejiSync.php';
require_once __DIR__ . '/../../lib/CoffeejiReadModel.php';

if (!defined('DB_HOSTNAME')) {
	define('DB_HOSTNAME', (string) ($db_hostname ?? 'localhost'));
	define('DB_USERNAME', (string) ($db_username ?? ''));
	define('DB_PASSWORD', (string) ($db_password ?? ''));
	define('DB_NAME', (string) ($db_name ?? ''));
}

$hcKeyEnv = getenv('COFFEEJI_API_KEY');
$hcSecretEnv = getenv('COFFEEJI_API_SECRET');
$hcBaseEnv = getenv('COFFEEJI_BASE_URL');
$hcKey = ($hcKeyEnv !== false && $hcKeyEnv !== '') ? $hcKeyEnv : (isset($coffeeji_api_key) ? (string) $coffeeji_api_key : '');
$hcSecret = ($hcSecretEnv !== false && $hcSecretEnv !== '') ? $hcSecretEnv : (isset($coffeeji_api_secret) ? (string) $coffeeji_api_secret : '');
$hcBase = ($hcBaseEnv !== false && $hcBaseEnv !== '') ? $hcBaseEnv : (isset($coffeeji_base_url) && $coffeeji_base_url !== '' ? (string) $coffeeji_base_url : 'https://vdopen.coffeeji.com');

if (!defined('COFFEEJI_API_KEY')) {
	define('COFFEEJI_API_KEY', $hcKey);
}
if (!defined('COFFEEJI_API_SECRET')) {
	define('COFFEEJI_API_SECRET', $hcSecret);
}
if (!defined('COFFEEJI_BASE_URL')) {
	define('COFFEEJI_BASE_URL', $hcBase);
}
if (!defined('COFFEEJI_LOG')) {
	define('COFFEEJI_LOG', dirname(__DIR__, 3) . '/storage/logs/coffeeji.log');
}

function coffeeji_configured(): bool
{
	if (!defined('COFFEEJI_API_KEY') || !defined('COFFEEJI_API_SECRET')) {
		return false;
	}
	$key = trim(COFFEEJI_API_KEY);
	$secret = trim(COFFEEJI_API_SECRET);
	if ($key === '' || $secret === '' || $key === 'CHANGE_ME' || $secret === 'CHANGE_ME') {
		return false;
	}

	return true;
}

function hc_h(mixed $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function hc_money(float $amount): string
{
	return number_format($amount, 2, ',', '.') . ' €';
}

function hc_qty(float $qty): string
{
	if (abs($qty - round($qty)) < 0.001) {
		return number_format($qty, 0, ',', '.');
	}

	return number_format($qty, 1, ',', '.');
}

function hc_unit_label(string $unit): string
{
	$normalized = strtolower(trim($unit));
	if (in_array($normalized, array('pcs', 'pc', 'stk', 'stk.'), true)) {
		return 'Stk.';
	}
	if ($normalized === 'g') {
		return 'g';
	}

	return $unit;
}

function hc_month_long(int $month): string
{
	$names = array(1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember');

	return $names[$month] ?? '';
}

function hc_month_short(int $month): string
{
	$names = array(1 => 'Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.');

	return $names[$month] ?? '';
}

function hc_clock(?string $stored, bool $withDate = false): string
{
	if ($stored === null || trim($stored) === '') {
		return '—';
	}
	$dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $stored, CoffeejiTime::zone());
	if (!$dt) {
		$parsed = CoffeejiTime::parseLocal($stored);
		if ($parsed === null) {
			return hc_h($stored);
		}
		$dt = $parsed;
	}
	if ($withDate) {
		return $dt->format('d.m.Y, H:i') . ' Uhr MEZ';
	}

	return $dt->format('H:i') . ' Uhr';
}

function hc_payway(string $name): string
{
	$trim = trim($name);
	$map = array(
		'现金支付' => 'Bargeld',
		'现金' => 'Bargeld',
		'微信支付' => 'WeChat',
		'支付宝支付' => 'Alipay',
		'支付宝' => 'Alipay',
		'刷卡支付' => 'Karte',
		'银行卡' => 'Karte',
		'免费' => 'Gratis',
	);
	if (isset($map[$trim])) {
		return $map[$trim];
	}
	if ($trim === '') {
		return '—';
	}
	if (stripos($trim, 'bar') !== false || stripos($trim, 'cash') !== false) {
		return 'Bargeld';
	}
	if (stripos($trim, 'kart') !== false || stripos($trim, 'card') !== false) {
		return 'Karte';
	}

	return $trim;
}

function hc_order_status(int $status): string
{
	return match ($status) {
		0 => 'Storniert',
		1 => 'In Herstellung',
		2 => 'Ausgegeben',
		6 => 'Zeitüberschreitung',
		7 => 'Nicht hergestellt',
		default => 'Status ' . $status,
	};
}

function hc_order_tone(int $status): string
{
	return match ($status) {
		2 => 'gruen',
		1 => 'gelb',
		default => 'rot',
	};
}

/**
 * @return list<array{code: string, text: string}>
 */
function hc_remark_lines(string $remark): array
{
	$parts = preg_split('/\s*;\s*/', trim($remark)) ?: array();
	$lines = array();
	foreach ($parts as $part) {
		if ($part === '') {
			continue;
		}
		if (preg_match('/^([A-Za-z0-9]+)\s*[:：]\s*(.*)$/u', $part, $matches) === 1) {
			$lines[] = array('code' => $matches[1], 'text' => $matches[2]);
		} else {
			$lines[] = array('code' => '', 'text' => $part);
		}
	}

	return $lines;
}

/**
 * @param array<string, mixed> $device
 * @return array{0: string, 1: string}
 */
function hc_machine_tone(array $device): array
{
	$online = (int) ($device['status'] ?? 0) === 1;
	$run = (int) ($device['device_run_status'] ?? 0);
	$scarce = (int) ($device['scarce_status'] ?? 0);
	if (!$online) {
		return array('rot', 'Offline');
	}
	if ($run === 2) {
		return array('rot', 'Störung');
	}
	if ($scarce === 3) {
		return array('gelb', 'Mangel');
	}
	if ($scarce === 2) {
		return array('gelb', 'Eingeschränkt');
	}

	return array('gruen', 'Betriebsbereit');
}

function hc_delta(?float $percent): string
{
	if ($percent === null) {
		return '<span class="pl n">neu</span>';
	}
	$class = $percent >= 0 ? 'up' : 'down';
	$sign = $percent > 0 ? '+' : '';

	return '<span class="pl ' . $class . '">' . $sign . number_format($percent, 1, ',', '.') . ' %</span>';
}

/**
 * @param list<array{label: string, amount: float, today?: bool}> $days
 */
function hc_bar_chart(array $days): string
{
	$max = 0.0;
	foreach ($days as $day) {
		$max = max($max, (float) $day['amount']);
	}
	$scale = $max <= 0 ? 10 : (ceil($max / 10) * 10);
	if ($scale < 10) {
		$scale = 10;
	}
	$width = 670;
	$height = 290;
	$left = 52;
	$plotWidth = 602;
	$base = 250;
	$top = 16;
	$plotHeight = $base - $top;
	$count = max(1, count($days));
	$slot = $plotWidth / $count;
	$bar = max(4, min(14, $slot * 0.62));
	$svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" role="img" aria-label="Umsatzverlauf">';
	for ($step = 0; $step <= 3; $step++) {
		$value = $scale * $step / 3;
		$y = $base - ($value / $scale) * $plotHeight;
		$svg .= '<line x1="' . $left . '" x2="654" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" stroke="#eadfcd" stroke-width="1"' . ($step === 0 ? '' : ' stroke-dasharray="3 5"') . '/>';
		$svg .= '<text x="42" y="' . round($y + 4, 1) . '" text-anchor="end" font-size="11" fill="#8a7867">' . (int) $value . ' €</text>';
	}
	foreach ($days as $index => $day) {
		$x = $left + ($index + 0.5) * $slot - $bar / 2;
		$h = $scale > 0 ? ((float) $day['amount'] / $scale) * $plotHeight : 0;
		$y = $base - $h;
		$fill = !empty($day['today']) ? '#6f4e37' : '#b98a5c';
		$opacity = !empty($day['today']) ? ' opacity="0.7"' : '';
		$svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bar, 1) . '" height="' . round(max($h, 0), 1) . '" rx="3" fill="' . $fill . '"' . $opacity . '/>';
		$nearToday = (count($days) - 1 - $index) <= 2;
		if (!empty($day['today']) || ($index % 5 === 0 && !$nearToday)) {
			$svg .= '<text x="' . round($x + $bar / 2, 1) . '" y="272" text-anchor="middle" font-size="11" fill="#8a7867">' . hc_h($day['label']) . '</text>';
		}
	}
	$svg .= '</svg>';

	return $svg;
}

function hc_icon(string $name, int $size = 19): string
{
	$paths = array(
		'grid' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
		'machine' => '<path d="M10 2v2"/><path d="M14 2v2"/><path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/><path d="M6 2v2"/>',
		'pulse' => '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/>',
		'gauge' => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
		'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
		'doc' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
		'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
		'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
		'percent' => '<line x1="19" x2="5" y1="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
		'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
		'euro' => '<path d="M4 10h12"/><path d="M4 14h9"/><path d="M19 6a7.7 7.7 0 0 0-5.2-2A7.9 7.9 0 0 0 6 12c0 4.4 3.5 8 7.8 8 2 0 3.8-.8 5.2-2"/>',
		'cart' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
		'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
		'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
		'menu' => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
	);
	$body = $paths[$name] ?? '';

	return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}

function hc_current_user(): array
{
	static $loaded = false;
	static $user = array();
	if ($loaded) {
		return $user;
	}
	$loaded = true;
	if (!function_exists('getLoginId') || !function_exists('selectPrepared')) {
		return $user;
	}
	$id = getLoginId();
	if (!$id) {
		return $user;
	}
	$rows = selectPrepared('SELECT id, name, email, mobile FROM manage_user WHERE id = ? LIMIT 1', 'i', array((int) $id));
	$user = $rows[0] ?? array();

	return $user;
}

function hc_first_name(string $name): string
{
	$name = trim($name);
	if ($name === '') {
		return '';
	}
	$parts = preg_split('/\s+/', $name) ?: array();

	return (string) ($parts[0] ?? '');
}

function hc_initials(string $name): string
{
	$parts = preg_split('/\s+/', trim($name)) ?: array();
	$initials = '';
	foreach ($parts as $part) {
		if ($part === '') {
			continue;
		}
		$initials .= mb_strtoupper(mb_substr($part, 0, 1));
		if (mb_strlen($initials) >= 2) {
			break;
		}
	}

	return $initials !== '' ? $initials : 'HC';
}

function hc_nav_on(array $names): string
{
	global $page_name;

	return in_array($page_name ?? '', $names, true) ? 'on' : '';
}

/**
 * @param array<string, mixed> $material
 */
function hc_fill_bar(array $material): string
{
	$capacity = (float) ($material['capacity'] ?? 0);
	$residue = (float) ($material['residue_qty'] ?? 0);
	$warn = (float) ($material['warn_capacity'] ?? 0);
	$percent = $capacity > 0 ? min(100, max(0, $residue / $capacity * 100)) : 0;
	$warnPercent = $capacity > 0 ? min(100, max(0, $warn / $capacity * 100)) : 0;
	$low = $capacity > 0 && $residue <= $warn;
	$unit = hc_unit_label((string) ($material['unit'] ?? ''));

	$html = '<div class="fl"><div class="t"><span>' . hc_h($material['name'] ?? '') . '</span><span>'
		. hc_qty($residue) . ' / ' . hc_qty($capacity) . ' ' . hc_h($unit) . '</span></div>';
	$html .= '<div class="bar"><i class="' . ($low ? 'low' : '') . '" style="width:' . number_format($percent, 1, '.', '') . '%"></i>';
	if ($capacity > 0 && $warn > 0) {
		$html .= '<u style="left:' . number_format($warnPercent, 1, '.', '') . '%"></u>';
	}
	$html .= '</div></div>';

	return $html;
}

/**
 * @param array<string, mixed> $device
 */
function hc_machine_card(array $device): string
{
	$tone = hc_machine_tone($device);
	$online = (int) ($device['status'] ?? 0) === 1 ? 'online' : 'offline';
	$ext = trim((string) ($device['device_ext_no'] ?? ''));
	$meta = trim($ext . ($ext !== '' ? ' · ' : '') . $online);
	$remark = trim((string) ($device['exception_remark'] ?? ''));
	if ($remark === '') {
		$remark = trim((string) ($device['scarce_remark'] ?? ''));
	}
	$lines = hc_remark_lines($remark);
	$html = '<article class="mc ' . hc_h($tone[0]) . '">';
	$html .= '<div class="mh"><div class="mi">' . hc_icon('machine', 30) . '</div><div><b>' . hc_h($device['name'] ?: 'Maschine') . '</b><span>' . hc_h($meta) . '</span></div></div>';
	$html .= '<div class="amp-wrap"><span class="amp ' . hc_h($tone[0]) . '"><i></i>' . hc_h($tone[1]) . '</span></div>';
	if ($lines !== array()) {
		$html .= '<div class="eb ' . hc_h($tone[0]) . '">' . hc_icon('alert', 17) . '<div>';
		foreach ($lines as $line) {
			$html .= '<div>';
			if ($line['code'] !== '') {
				$html .= '<b>' . hc_h($line['code']) . '</b> · ';
			}
			$html .= hc_h($line['text']) . '</div>';
		}
		$html .= '</div></div>';
	} else {
		$quiet = $tone[0] === 'gruen' ? 'Keine Störung gemeldet.' : $tone[1];
		$html .= '<div class="eb ' . hc_h($tone[0]) . '">' . hc_icon('alert', 17) . '<div><div>' . hc_h($quiet) . '</div></div></div>';
	}
	$materials = $device['materials'] ?? array();
	if ($materials === array()) {
		$html .= '<p class="muted">Noch keine Füllstände synchronisiert.</p>';
	}
	foreach ($materials as $material) {
		$html .= hc_fill_bar($material);
	}
	$html .= '<div class="mf"><div><b>' . hc_money((float) ($device['month_amount'] ?? 0)) . '</b>Umsatz Monat</div>';
	$html .= '<div><b>' . (int) ($device['month_count'] ?? 0) . '</b>Verkäufe</div>';
	$html .= '<div><b>' . hc_clock($device['last_sale'] ?? null) . '</b>Letzter Verkauf</div></div>';
	$html .= '</article>';

	return $html;
}

/**
 * @param list<array<string, mixed>> $orders
 */
function hc_sales_html(array $orders): string
{
	if ($orders === array()) {
		return '<p class="muted">Heute liegen noch keine Bestellungen vor.</p>';
	}
	$html = '';
	foreach ($orders as $order) {
		$status = (int) ($order['status'] ?? 0);
		$html .= '<div class="sale"><div class="cup">' . hc_icon('machine', 19) . '</div><div><b>' . hc_h($order['goods_name'] ?: 'Verkauf') . '</b>';
		$html .= '<span>' . hc_clock($order['create_time'] ?? null) . ' · ' . hc_h($order['device_name'] ?: $order['device_ext_no']) . '</span></div>';
		$html .= '<div class="am">' . hc_money((float) ($order['pay_amt'] ?? 0)) . '<span>' . hc_h(hc_payway((string) ($order['pay_way_name'] ?? ''))) . '</span></div></div>';
	}

	return $html;
}

/**
 * @param list<array<string, mixed>> $orders
 */
function hc_render_live_table(array $orders): string
{
	if ($orders === array()) {
		return '<p class="muted">Heute liegen noch keine Bestellungen vor.</p>';
	}
	$html = '<table class="hc-table"><thead><tr><th>Zeit</th><th>Getränk</th><th>Maschine</th><th>Zahlart</th><th class="r">Betrag</th><th>Status</th></tr></thead><tbody>';
	foreach ($orders as $order) {
		$status = (int) ($order['status'] ?? 0);
		$html .= '<tr><td>' . hc_clock($order['create_time'] ?? null) . '</td>';
		$html .= '<td><b>' . hc_h($order['goods_name'] ?: '—') . '</b></td>';
		$html .= '<td>' . hc_h($order['device_name'] ?: $order['device_ext_no']) . '</td>';
		$html .= '<td>' . hc_h(hc_payway((string) ($order['pay_way_name'] ?? ''))) . '</td>';
		$html .= '<td class="r">' . hc_money((float) ($order['pay_amt'] ?? 0)) . '</td>';
		$html .= '<td><span class="amp ' . hc_h(hc_order_tone($status)) . '"><i></i>' . hc_h(hc_order_status($status)) . '</span></td></tr>';
	}
	$html .= '</tbody></table>';

	return $html;
}

function hc_notice(string $tone, string $title, string $text): string
{
	return '<div class="eb ' . hc_h($tone) . ' notice">' . hc_icon('alert', 18) . '<div><b>' . hc_h($title) . '</b><br>' . hc_h($text) . '</div></div>';
}
