<?php

declare(strict_types=1);

/**
 * Filter, Umsatz und Gruppierung für Bestellverwaltung und Statistik.
 * Die Regeln liegen hier, damit Liste, Export und Diagramm dieselben Status verwenden.
 */
class CoffeejiPortal
{
	/** @var list<int> */
	public const PAGE_SIZES = array(10, 20, 30, 50);

	/** @var list<string> */
	public const GRAINS = array('tag', 'monat', 'jahr');

	/** @var array<int, string> */
	public const ORDER_STATUSES = array(
		7 => 'Nicht hergestellt',
		1 => 'In Herstellung',
		2 => 'Fertig',
		6 => 'Zahlungs-Timeout',
		0 => 'Storniert',
	);

	/** @var array<int, string> */
	public const DELIVERY_STATUSES = array(
		0 => 'Storniert',
		1 => 'Offen',
		2 => 'Ausgegeben',
	);

	/** @var array<int, string> */
	public const REFUND_STATUSES = array(
		1 => 'Erstattet',
		2 => 'Erstattung fehlgeschlagen',
	);

	/** @var array<string, string> */
	public const PAY_CATEGORIES = array(
		'bar' => 'Bar',
		'karte' => 'Karte',
		'gratis' => 'Gratis',
		'einloesung' => 'Einlösung',
		'sonstige' => 'Sonstige',
	);

	/**
	 * Letzte 30 Kalendertage inklusive heute. Ende ist exklusiv.
	 *
	 * @return array{from: DateTimeImmutable, to: DateTimeImmutable}
	 */
	public static function defaultOrderRange(DateTimeImmutable $now): array
	{
		$today = $now->setTimezone(CoffeejiTime::zone())->setTime(0, 0);

		return array(
			'from' => $today->modify('-29 days'),
			'to' => $today->modify('+1 day'),
		);
	}

	/**
	 * Laufender Monat bis morgen 00:00. Ende ist exklusiv.
	 *
	 * @return array{from: DateTimeImmutable, to: DateTimeImmutable}
	 */
	public static function defaultStatsRange(DateTimeImmutable $now): array
	{
		$today = $now->setTimezone(CoffeejiTime::zone())->setTime(0, 0);

		return array(
			'from' => $today->modify('first day of this month')->setTime(0, 0),
			'to' => $today->modify('+1 day'),
		);
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array<string, mixed>
	 */
	public static function orderFilter(array $query, DateTimeImmutable $now): array
	{
		return self::filter($query, $now, false);
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array<string, mixed>
	 */
	public static function statsFilter(array $query, DateTimeImmutable $now): array
	{
		return self::filter($query, $now, true);
	}

	/**
	 * @param array<string, mixed> $order
	 * @param array<string, mixed> $filter
	 */
	public static function matches(array $order, array $filter): bool
	{
		$created = CoffeejiTime::parseLocal($order['create_time'] ?? null);
		if (!$created instanceof DateTimeImmutable) {
			return false;
		}
		if ($created < $filter['from'] || $created >= $filter['to']) {
			return false;
		}
		if (!self::contains((string) ($order['order_no'] ?? ''), (string) $filter['order_no'])) {
			return false;
		}
		if (!self::contains((string) ($order['device_name'] ?? ''), (string) $filter['device_name'])) {
			return false;
		}
		if (!self::contains((string) ($order['goods_name'] ?? ''), (string) $filter['goods_name'])) {
			return false;
		}
		$needle = (string) $filter['device_no'];
		if ($needle !== '') {
			$hay = array(
				(string) ($order['device_ext_no'] ?? ''),
				(string) ($order['device_no'] ?? ''),
				(string) ($order['device_id'] ?? ''),
			);
			$hit = false;
			foreach ($hay as $value) {
				if (self::contains($value, $needle)) {
					$hit = true;
					break;
				}
			}
			if (!$hit) {
				return false;
			}
		}
		$pay = (string) $filter['pay_way_name'];
		if ($pay !== '' && (string) ($order['pay_way_name'] ?? '') !== $pay) {
			return false;
		}
		if ($filter['status'] !== null && (int) ($order['status'] ?? 0) !== $filter['status']) {
			return false;
		}
		if ($filter['out_stock_status'] !== null) {
			$out = CoffeejiOrder::nullableInt($order['out_stock_status'] ?? null);
			if ($out !== $filter['out_stock_status']) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @param array<string, mixed> $filter
	 * @return list<array<string, mixed>>
	 */
	public static function filterOrders(array $orders, array $filter): array
	{
		$kept = array();
		foreach ($orders as $order) {
			if (self::matches($order, $filter)) {
				$kept[] = $order;
			}
		}

		return $kept;
	}

	/**
	 * @return array{page: int, pages: int, per_page: int, total: int, offset: int}
	 */
	public static function pageMeta(int $total, int $page, int $perPage): array
	{
		if (!in_array($perPage, self::PAGE_SIZES, true)) {
			$perPage = 10;
		}
		if ($total < 0) {
			$total = 0;
		}
		$pages = $total === 0 ? 1 : (int) ceil($total / $perPage);
		if ($page < 1) {
			$page = 1;
		}
		if ($page > $pages) {
			$page = $pages;
		}

		return array(
			'page' => $page,
			'pages' => $pages,
			'per_page' => $perPage,
			'total' => $total,
			'offset' => ($page - 1) * $perPage,
		);
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return array{rows: list<array<string, mixed>>, meta: array{page: int, pages: int, per_page: int, total: int, offset: int}}
	 */
	public static function paginate(array $rows, int $page, int $perPage): array
	{
		$meta = self::pageMeta(count($rows), $page, $perPage);

		return array(
			'rows' => array_slice($rows, $meta['offset'], $meta['per_page']),
			'meta' => $meta,
		);
	}

	/**
	 * Summe über die bereits gefilterte Liste, unabhängig vom Umsatzstatus.
	 *
	 * @param list<array<string, mixed>> $orders
	 * @return array{count: int, amount: float, revenue: float, revenue_count: int, open: int}
	 */
	public static function totals(array $orders): array
	{
		$amount = 0.0;
		$revenue = 0.0;
		$revenueCount = 0;
		$open = 0;
		foreach ($orders as $order) {
			$pay = (float) ($order['pay_amt'] ?? 0);
			$amount += $pay;
			$status = (int) ($order['status'] ?? 0);
			$out = CoffeejiOrder::nullableInt($order['out_stock_status'] ?? null);
			if (CoffeejiOrder::countsForPortalRevenue($status)) {
				$revenue += $pay;
				$revenueCount++;
			}
			if (CoffeejiOrder::isOpen($status, $out)) {
				$open++;
			}
		}

		return array(
			'count' => count($orders),
			'amount' => round($amount, 2),
			'revenue' => round($revenue, 2),
			'revenue_count' => $revenueCount,
			'open' => $open,
		);
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @return list<array{key: string, label: string, amount: float, count: int, today: bool}>
	 */
	public static function groupByPeriod(array $orders, string $grain, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		if (!in_array($grain, self::GRAINS, true)) {
			$grain = 'tag';
		}
		$zone = CoffeejiTime::zone();
		$from = $from->setTimezone($zone);
		$to = $to->setTimezone($zone);
		$todayKey = CoffeejiTime::now()->setTimezone($zone)->format('Y-m-d');
		$buckets = array();
		$cursor = $from;
		if ($grain === 'monat') {
			$cursor = $from->modify('first day of this month')->setTime(0, 0);
		} elseif ($grain === 'jahr') {
			$cursor = $from->setDate((int) $from->format('Y'), 1, 1)->setTime(0, 0);
		} else {
			$cursor = $from->setTime(0, 0);
		}
		$guard = 0;
		while ($cursor < $to && $guard < 400) {
			$key = self::periodKey($cursor, $grain);
			if (!isset($buckets[$key])) {
				$buckets[$key] = array(
					'key' => $key,
					'label' => self::periodLabel($cursor, $grain),
					'amount' => 0.0,
					'count' => 0,
					'today' => $grain === 'tag' && $key === $todayKey,
					'force_label' => $grain !== 'tag',
				);
			}
			if ($grain === 'monat') {
				$cursor = $cursor->modify('first day of next month');
			} elseif ($grain === 'jahr') {
				$cursor = $cursor->setDate((int) $cursor->format('Y') + 1, 1, 1);
			} else {
				$cursor = $cursor->modify('+1 day');
			}
			$guard++;
		}

		foreach ($orders as $order) {
			if (!CoffeejiOrder::countsForPortalRevenue((int) ($order['status'] ?? 0))) {
				continue;
			}
			$created = CoffeejiTime::parseLocal($order['create_time'] ?? null);
			if (!$created instanceof DateTimeImmutable || $created < $from || $created >= $to) {
				continue;
			}
			$key = self::periodKey($created, $grain);
			if (!isset($buckets[$key])) {
				$buckets[$key] = array(
					'key' => $key,
					'label' => self::periodLabel($created, $grain),
					'amount' => 0.0,
					'count' => 0,
					'today' => false,
					'force_label' => $grain !== 'tag',
				);
			}
			$buckets[$key]['amount'] = round($buckets[$key]['amount'] + (float) ($order['pay_amt'] ?? 0), 2);
			$buckets[$key]['count']++;
		}

		return array_values($buckets);
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @return list<array<string, mixed>>
	 */
	public static function byMachine(array $orders, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		$groups = array();
		foreach (self::revenueRows($orders, $from, $to) as $order) {
			$key = self::machineKey($order);
			if (!isset($groups[$key])) {
				$groups[$key] = self::blankGroup($order);
			}
			self::addRevenue($groups[$key], $order);
		}

		return self::sortGroups($groups);
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @return list<array<string, mixed>>
	 */
	public static function byProduct(array $orders, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		$groups = array();
		foreach (self::revenueRows($orders, $from, $to) as $order) {
			$goods = trim((string) ($order['goods_name'] ?? ''));
			if ($goods === '') {
				$goods = 'Ohne Bezeichnung';
			}
			$key = mb_strtolower($goods) . '|' . self::machineKey($order);
			if (!isset($groups[$key])) {
				$groups[$key] = self::blankGroup($order);
				$groups[$key]['goods_name'] = $goods;
			}
			self::addRevenue($groups[$key], $order);
		}

		return self::sortGroups($groups);
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @return list<array{key: string, label: string, amount: float, count: int}>
	 */
	public static function byPayWay(array $orders, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		$groups = array();
		foreach (self::PAY_CATEGORIES as $key => $label) {
			$groups[$key] = array('key' => $key, 'label' => $label, 'amount' => 0.0, 'count' => 0);
		}
		foreach (self::revenueRows($orders, $from, $to) as $order) {
			$key = self::payCategory((string) ($order['pay_way_name'] ?? ''));
			$groups[$key]['amount'] = round($groups[$key]['amount'] + (float) ($order['pay_amt'] ?? 0), 2);
			$groups[$key]['count']++;
		}

		return array_values($groups);
	}

	public static function payLabel(string $name): string
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
			'兑换订单' => 'Einlösung',
			'兑换' => 'Einlösung',
		);
		if (isset($map[$trim])) {
			return $map[$trim];
		}
		if ($trim === '') {
			return '—';
		}
		$lower = mb_strtolower($trim);
		if (str_contains($lower, 'cash') || str_contains($lower, 'bar')) {
			return 'Bargeld';
		}
		if (str_contains($lower, 'card') || str_contains($lower, 'kart')) {
			return 'Karte';
		}
		if (str_contains($lower, 'free') || str_contains($lower, 'gratis') || str_contains($trim, '免费')) {
			return 'Gratis';
		}
		if (str_contains($lower, 'exchange') || str_contains($lower, 'tausch') || str_contains($lower, 'einlös') || str_contains($trim, '兑换')) {
			return 'Einlösung';
		}

		return $trim;
	}

	public static function payCategory(string $name): string
	{
		return match (self::payLabel($name)) {
			'Bargeld' => 'bar',
			'Karte' => 'karte',
			'Gratis' => 'gratis',
			'Einlösung' => 'einloesung',
			default => 'sonstige',
		};
	}

	public static function csvCell(string $value): string
	{
		if ($value !== '' && preg_match('/^[=+\-@]/', $value) === 1) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $filter
	 * @param array<string, scalar|null> $override
	 */
	public static function queryString(array $filter, array $override = array()): string
	{
		$inclusiveEnd = $filter['to']->setTimezone(CoffeejiTime::zone())->modify('-1 second');
		$params = array(
			'von' => $filter['from']->setTimezone(CoffeejiTime::zone())->format('Y-m-d'),
			'bis' => $inclusiveEnd->format('Y-m-d'),
			'korn' => (string) $filter['grain'],
			'pro' => (string) $filter['per_page'],
			'bestellnummer' => (string) $filter['order_no'],
			'maschinenname' => (string) $filter['device_name'],
			'maschinennummer' => (string) $filter['device_no'],
			'produkt' => (string) $filter['goods_name'],
			'zahlart' => (string) $filter['pay_way_name'],
		);
		if ($filter['status'] !== null) {
			$params['bestellstatus'] = (string) $filter['status'];
		}
		if ($filter['out_stock_status'] !== null) {
			$params['ausgabestatus'] = (string) $filter['out_stock_status'];
		}
		foreach ($override as $key => $value) {
			if ($value === null || $value === '') {
				unset($params[$key]);
				continue;
			}
			$params[$key] = (string) $value;
		}
		$clean = array();
		foreach ($params as $key => $value) {
			if ($value === '' || ($key === 'korn' && $value === 'tag' && !isset($override['korn']))) {
				continue;
			}
			$clean[$key] = $value;
		}

		return http_build_query($clean);
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array<string, mixed>
	 */
	private static function filter(array $query, DateTimeImmutable $now, bool $stats): array
	{
		$range = $stats ? self::defaultStatsRange($now) : self::defaultOrderRange($now);
		$from = self::day($query['von'] ?? null, $range['from']);
		$toDay = self::day($query['bis'] ?? null, $range['to']->modify('-1 second'));
		$to = $toDay->setTime(0, 0)->modify('+1 day');
		if ($from >= $to) {
			$swap = $from;
			$from = $to->modify('-1 day');
			$to = $swap->modify('+1 day');
		}
		$status = self::allowedInt($query['bestellstatus'] ?? null, array_keys(self::ORDER_STATUSES));
		$delivery = self::allowedInt($query['ausgabestatus'] ?? null, array_keys(self::DELIVERY_STATUSES));
		$grain = (string) ($query['korn'] ?? 'tag');
		if (!in_array($grain, self::GRAINS, true)) {
			$grain = 'tag';
		}
		$perPage = (int) ($query['pro'] ?? 10);
		if (!in_array($perPage, self::PAGE_SIZES, true)) {
			$perPage = 10;
		}

		return array(
			'order_no' => self::text($query['bestellnummer'] ?? '', 80),
			'device_name' => self::text($query['maschinenname'] ?? '', 120),
			'device_no' => self::text($query['maschinennummer'] ?? '', 80),
			'goods_name' => self::text($query['produkt'] ?? '', 120),
			'pay_way_name' => self::text($query['zahlart'] ?? '', 128),
			'status' => $status,
			'out_stock_status' => $delivery,
			'from' => $from,
			'to' => $to,
			'page' => max(1, (int) ($query['seite'] ?? 1)),
			'per_page' => $perPage,
			'grain' => $grain,
		);
	}

	private static function day(mixed $value, DateTimeImmutable $fallback): DateTimeImmutable
	{
		$raw = trim((string) $value);
		$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, CoffeejiTime::zone());
		if (!$parsed || $parsed->format('Y-m-d') !== $raw) {
			return $fallback->setTimezone(CoffeejiTime::zone())->setTime(0, 0);
		}

		return $parsed->setTime(0, 0);
	}

	private static function text(mixed $value, int $max): string
	{
		$text = trim((string) $value);
		if (mb_strlen($text) > $max) {
			$text = mb_substr($text, 0, $max);
		}

		return $text;
	}

	/**
	 * @param list<int> $allowed
	 */
	private static function allowedInt(mixed $value, array $allowed): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}
		if (is_string($value) && preg_match('/^-?\d+$/', $value) !== 1) {
			return null;
		}
		$int = (int) $value;
		if (!in_array($int, $allowed, true)) {
			return null;
		}

		return $int;
	}

	private static function contains(string $haystack, string $needle): bool
	{
		if ($needle === '') {
			return true;
		}

		return mb_stripos($haystack, $needle) !== false;
	}

	private static function periodKey(DateTimeImmutable $moment, string $grain): string
	{
		if ($grain === 'monat') {
			return $moment->format('Y-m');
		}
		if ($grain === 'jahr') {
			return $moment->format('Y');
		}

		return $moment->format('Y-m-d');
	}

	private static function periodLabel(DateTimeImmutable $moment, string $grain): string
	{
		if ($grain === 'jahr') {
			return $moment->format('Y');
		}
		if ($grain === 'monat') {
			$names = array(1 => 'Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.');

			return ($names[(int) $moment->format('n')] ?? $moment->format('m')) . ' ' . $moment->format('Y');
		}

		return $moment->format('d.m.');
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @return list<array<string, mixed>>
	 */
	private static function revenueRows(array $orders, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		$rows = array();
		foreach ($orders as $order) {
			if (!CoffeejiOrder::countsForPortalRevenue((int) ($order['status'] ?? 0))) {
				continue;
			}
			$created = CoffeejiTime::parseLocal($order['create_time'] ?? null);
			if (!$created instanceof DateTimeImmutable || $created < $from || $created >= $to) {
				continue;
			}
			$rows[] = $order;
		}

		return $rows;
	}

	/**
	 * @param array<string, mixed> $order
	 */
	private static function machineKey(array $order): string
	{
		foreach (array('device_id', 'device_ext_no', 'device_no') as $field) {
			$value = trim((string) ($order[$field] ?? ''));
			if ($value !== '') {
				return $value;
			}
		}

		return 'unbekannt';
	}

	/**
	 * @param array<string, mixed> $order
	 * @return array<string, mixed>
	 */
	private static function blankGroup(array $order): array
	{
		$ext = trim((string) ($order['device_ext_no'] ?? ''));
		$deviceNo = trim((string) ($order['device_no'] ?? ''));
		$id = trim((string) ($order['device_id'] ?? ''));

		return array(
			'device_ext_no' => $ext !== '' ? $ext : ($deviceNo !== '' ? $deviceNo : $id),
			'device_name' => trim((string) ($order['device_name'] ?? '')),
			'device_no' => $deviceNo !== '' ? $deviceNo : $id,
			'goods_name' => trim((string) ($order['goods_name'] ?? '')),
			'count' => 0,
			'amount' => 0.0,
			'open' => 0,
		);
	}

	/**
	 * @param array<string, mixed> $group
	 * @param array<string, mixed> $order
	 */
	private static function addRevenue(array &$group, array $order): void
	{
		$group['amount'] = round($group['amount'] + (float) ($order['pay_amt'] ?? 0), 2);
		$group['count']++;
		$status = (int) ($order['status'] ?? 0);
		$out = CoffeejiOrder::nullableInt($order['out_stock_status'] ?? null);
		if (CoffeejiOrder::isOpen($status, $out)) {
			$group['open']++;
		}
	}

	/**
	 * @param array<string, array<string, mixed>> $groups
	 * @return list<array<string, mixed>>
	 */
	private static function sortGroups(array $groups): array
	{
		$rows = array_values($groups);
		usort($rows, function (array $left, array $right): int {
			$amount = $right['amount'] <=> $left['amount'];
			if ($amount !== 0) {
				return $amount;
			}

			return strcmp((string) $left['device_name'], (string) $right['device_name']);
		});

		return $rows;
	}
}
