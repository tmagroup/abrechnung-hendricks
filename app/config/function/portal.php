<?php
if (!defined('APP_FOLDER')) exit('No direct script access allowed');

function hc_stamp(?string $stored): string
{
	if ($stored === null || trim($stored) === '') {
		return '—';
	}
	$dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($stored), CoffeejiTime::zone());
	if (!$dt) {
		return '—';
	}

	return $dt->format('d.m.Y H:i:s');
}

function hc_portal_status(int $status): string
{
	return CoffeejiPortal::ORDER_STATUSES[$status] ?? ('Status ' . $status);
}

function hc_portal_delivery(mixed $status): string
{
	if ($status === null || $status === '') {
		return '—';
	}
	$int = (int) $status;

	return CoffeejiPortal::DELIVERY_STATUSES[$int] ?? ('Status ' . $int);
}

function hc_portal_refund(mixed $status): string
{
	if ($status === null || $status === '') {
		return '—';
	}
	$int = (int) $status;

	return CoffeejiPortal::REFUND_STATUSES[$int] ?? ('Status ' . $int);
}

function hc_open_mark(int $status, mixed $outStock): string
{
	$out = CoffeejiOrder::nullableInt($outStock);
	if (!CoffeejiOrder::isOpen($status, $out)) {
		return '';
	}

	return '<span class="chip wait"><i></i>offen</span>';
}

/**
 * @param array<string, mixed> $filter
 */
function hc_portal_href(string $path, array $filter, array $override = array()): string
{
	$query = CoffeejiPortal::queryString($filter, $override);
	if ($query === '') {
		return appUrl($path);
	}

	return appUrl($path) . '?' . $query;
}

/**
 * @param array<string, mixed> $filter
 */
function hc_portal_pager(string $path, array $filter, int $page, int $pages, int $perPage, int $total): string
{
	$html = '<div class="pager"><span>Gesamt ' . (int) $total . '</span>';
	$html .= '<label>pro Seite <select onchange="location.href=this.value">';
	foreach (CoffeejiPortal::PAGE_SIZES as $size) {
		$url = hc_portal_href($path, $filter, array('pro' => (string) $size, 'seite' => '1'));
		$html .= '<option value="' . hc_h($url) . '"' . ($size === $perPage ? ' selected' : '') . '>' . $size . '</option>';
	}
	$html .= '</select></label>';
	$window = array(1, $pages, $page - 1, $page, $page + 1);
	$nums = array();
	foreach ($window as $num) {
		if ($num >= 1 && $num <= $pages) {
			$nums[$num] = $num;
		}
	}
	ksort($nums);
	$previous = 0;
	foreach ($nums as $num) {
		if ($previous > 0 && $num > $previous + 1) {
			$html .= '<span class="gap">…</span>';
		}
		if ($num === $page) {
			$html .= '<span class="on">' . $num . '</span>';
		} else {
			$html .= '<a href="' . hc_h(hc_portal_href($path, $filter, array('seite' => (string) $num))) . '">' . $num . '</a>';
		}
		$previous = $num;
	}
	$html .= '</div>';

	return $html;
}

/**
 * @param array<string, mixed> $filter
 */
function hc_portal_grain(string $path, array $filter): string
{
	$labels = array('tag' => 'Täglich', 'monat' => 'Monatlich', 'jahr' => 'Jährlich');
	$html = '<div class="seg" role="group" aria-label="Gruppierung">';
	foreach ($labels as $key => $label) {
		$class = $filter['grain'] === $key ? ' class="on"' : '';
		$html .= '<a' . $class . ' href="' . hc_h(hc_portal_href($path, $filter, array('korn' => $key, 'seite' => '1'))) . '">' . hc_h($label) . '</a>';
	}
	$html .= '</div>';

	return $html;
}

/**
 * @param list<array{label: string, amount: float, count: int}> $rows
 */
function hc_pay_split(array $rows): string
{
	$max = 0.0;
	foreach ($rows as $row) {
		$max = max($max, (float) $row['amount']);
	}
	if ($max <= 0) {
		return '<p class="muted">Im Zeitraum liegt kein Umsatz nach Zahlart vor.</p>';
	}
	$html = '';
	foreach ($rows as $row) {
		if ((int) $row['count'] === 0) {
			continue;
		}
		$width = number_format(((float) $row['amount'] / $max) * 100, 1, '.', '');
		$html .= '<div class="prod"><div class="n">' . hc_h($row['label']) . '</div><div class="b"><i style="width:' . $width . '%"></i></div>';
		$html .= '<div class="c">' . hc_h((string) $row['count']) . '</div><div class="pay">' . hc_money((float) $row['amount']) . '</div></div>';
	}

	return $html;
}

/**
 * @param array<string, mixed> $filter
 * @param list<string> $headers
 * @param list<list<string>> $lines
 */
function hc_send_csv(string $filename, array $headers, array $lines): void
{
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('X-Content-Type-Options: nosniff');
	echo "\xEF\xBB\xBF";
	$out = fopen('php://output', 'w');
	if ($out === false) {
		exit;
	}
	fputcsv($out, $headers, ';');
	foreach ($lines as $line) {
		$safe = array();
		foreach ($line as $cell) {
			$safe[] = CoffeejiPortal::csvCell((string) $cell);
		}
		fputcsv($out, $safe, ';');
	}
	fclose($out);
	exit;
}

function hc_csv_money(float $amount): string
{
	return number_format($amount, 2, ',', '');
}

/**
 * @param array<string, mixed> $order
 * @return list<string>
 */
function hc_order_csv_line(array $order): array
{
	$status = (int) ($order['status'] ?? 0);
	$out = CoffeejiOrder::nullableInt($order['out_stock_status'] ?? null);

	return array(
		(string) ($order['order_no'] ?? ''),
		(string) ($order['machine_no'] ?? $order['device_ext_no'] ?? ''),
		(string) ($order['device_name'] ?? ''),
		(string) ($order['goods_name'] ?? ''),
		hc_csv_money((float) ($order['pay_amt'] ?? 0)),
		hc_payway((string) ($order['pay_way_name'] ?? '')),
		hc_portal_status($status),
		hc_portal_delivery($out),
		hc_portal_refund($order['refund_status'] ?? null),
		hc_stamp($order['create_time'] ?? null),
		hc_stamp(isset($order['out_stock_time']) ? (string) $order['out_stock_time'] : null),
		hc_stamp(isset($order['refund_time']) ? (string) $order['refund_time'] : null),
		(string) ($order['time_zone_id'] ?? ''),
		(string) ($order['fail_remark'] ?? ''),
		CoffeejiOrder::isOpen($status, $out) ? 'ja' : 'nein',
	);
}

/**
 * @return list<string>
 */
function hc_order_csv_headers(): array
{
	return array(
		'Bestellnummer',
		'Maschinennummer',
		'Maschinenname',
		'Produkt',
		'Betrag',
		'Zahlart',
		'Bestellstatus',
		'Ausgabestatus',
		'Erstattungsstatus',
		'Bestellzeit',
		'Ausgabezeit',
		'Erstattungszeit',
		'Zeitzone',
		'Grund für Herstellungsfehler',
		'Offen',
	);
}
