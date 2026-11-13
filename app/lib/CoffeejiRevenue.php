<?php

declare(strict_types=1);

class CoffeejiRevenue
{
	/**
	 * @param list<array<string, mixed>> $orders
	 * @return array{amount: float, count: int}
	 */
	public static function sum(array $orders, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		$amount = 0.0;
		$count = 0;
		foreach ($orders as $order) {
			if (!CoffeejiOrder::isSale($order)) {
				continue;
			}
			$created = CoffeejiTime::parseLocal($order['create_time'] ?? null);
			if ($created === null || $created < $from || $created >= $to) {
				continue;
			}
			$amount += (float) ($order['pay_amt'] ?? 0);
			$count++;
		}

		return array(
			'amount' => round($amount, 2),
			'count' => $count,
		);
	}

	public static function changePercent(float $current, float $previous): ?float
	{
		if (abs($previous) < 0.00001) {
			return $current > 0 ? null : 0.0;
		}

		return (($current - $previous) / $previous) * 100;
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @return array<string, mixed>
	 */
	public static function summarize(array $orders, DateTimeImmutable $now): array
	{
		$zone = CoffeejiTime::zone();
		$now = $now->setTimezone($zone);
		$todayStart = $now->setTime(0, 0);
		$tomorrow = $todayStart->modify('+1 day');
		$yesterdayStart = $todayStart->modify('-1 day');
		$yesterdaySame = $yesterdayStart->setTime(
			(int) $now->format('H'),
			(int) $now->format('i'),
			(int) $now->format('s')
		);
		$monthStart = $now->modify('first day of this month')->setTime(0, 0);
		$prevMonthStart = $monthStart->modify('-1 month');
		$prevDay = min((int) $now->format('j'), (int) $prevMonthStart->format('t'));
		$prevSame = $prevMonthStart->setDate(
			(int) $prevMonthStart->format('Y'),
			(int) $prevMonthStart->format('n'),
			$prevDay
		)->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));

		$today = self::sum($orders, $todayStart, $now->modify('+1 second'));
		$yesterdaySameSum = self::sum($orders, $yesterdayStart, $yesterdaySame->modify('+1 second'));
		$month = self::sum($orders, $monthStart, $now->modify('+1 second'));
		$monthCompare = self::sum($orders, $prevMonthStart, $prevSame->modify('+1 second'));
		$prevMonth = self::sum($orders, $prevMonthStart, $monthStart);
		$todayCount = self::sum($orders, $todayStart, $tomorrow);

		$chartStart = $todayStart->modify('-29 days');
		$chart = array();
		$chartTotal = 0.0;
		for ($i = 0; $i < 30; $i++) {
			$day = $chartStart->modify('+' . $i . ' days');
			$next = $day->modify('+1 day');
			$bucket = self::sum($orders, $day, $next);
			$chartTotal += $bucket['amount'];
			$chart[] = array(
				'date' => $day->format('Y-m-d'),
				'label' => $day->format('d.m.'),
				'amount' => $bucket['amount'],
				'today' => $day->format('Y-m-d') === $todayStart->format('Y-m-d'),
			);
		}

		$drinks = array();
		foreach ($orders as $order) {
			if (!CoffeejiOrder::isSale($order)) {
				continue;
			}
			$created = CoffeejiTime::parseLocal($order['create_time'] ?? null);
			if ($created === null || $created < $monthStart || $created >= $tomorrow) {
				continue;
			}
			$name = trim((string) ($order['goods_name'] ?? ''));
			if ($name === '') {
				$name = 'Ohne Bezeichnung';
			}
			if (!isset($drinks[$name])) {
				$drinks[$name] = 0;
			}
			$drinks[$name]++;
		}
		arsort($drinks);
		$top = array();
		foreach ($drinks as $name => $count) {
			$top[] = array('name' => $name, 'count' => $count);
			if (count($top) >= 6) {
				break;
			}
		}

		return array(
			'today' => $today,
			'yesterday_same' => $yesterdaySameSum,
			'today_delta' => self::changePercent($today['amount'], $yesterdaySameSum['amount']),
			'month' => $month,
			'month_compare' => $monthCompare,
			'month_delta' => self::changePercent($month['amount'], $monthCompare['amount']),
			'prev_month' => $prevMonth,
			'today_count' => $todayCount['count'],
			'sales_delta' => self::changePercent((float) $month['count'], (float) $monthCompare['count']),
			'chart' => $chart,
			'avg_per_day' => round($chartTotal / 30, 2),
			'drinks' => $top,
			'month_start' => $monthStart,
			'prev_month_start' => $prevMonthStart,
		);
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @return array<string, array{amount: float, count: int, last: ?string}>
	 */
	public static function perDevice(array $orders, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		$stats = array();
		foreach ($orders as $order) {
			if (!CoffeejiOrder::isSale($order)) {
				continue;
			}
			$created = CoffeejiTime::parseLocal($order['create_time'] ?? null);
			if ($created === null || $created < $from || $created >= $to) {
				continue;
			}
			$key = trim((string) ($order['device_id'] ?? ''));
			if ($key === '') {
				$key = trim((string) ($order['device_ext_no'] ?? ''));
			}
			if ($key === '') {
				continue;
			}
			if (!isset($stats[$key])) {
				$stats[$key] = array('amount' => 0.0, 'count' => 0, 'last' => null);
			}
			$stats[$key]['amount'] = round($stats[$key]['amount'] + (float) ($order['pay_amt'] ?? 0), 2);
			$stats[$key]['count']++;
			$stamp = $created->format('Y-m-d H:i:s');
			if ($stats[$key]['last'] === null || $stamp > $stats[$key]['last']) {
				$stats[$key]['last'] = $stamp;
			}
		}

		return $stats;
	}
}
