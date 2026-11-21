<?php

declare(strict_types=1);

/**
 * Gesamtabrechnung aus Bestellungen.
 *
 * Welche Bestellungen Umsatz sind, entscheidet CoffeejiOrder::countsForPortalRevenue
 * (dieselben Status wie /bestellungen). Gratis und Einlösung fallen danach weg.
 * Beträge sind brutto. Die enthaltene Steuer wird je Satz auf die Summe in Cent
 * gerundet: brutto × Satz / (100 + Satz).
 */
class CoffeejiSettlement
{
	public const MERCHANT_DEFAULT = 'The Hendricks Coffee';

	/**
	 * @return array{start: string, end: string}
	 */
	public static function previousMonth(DateTimeImmutable $now): array
	{
		$now = $now->setTimezone(CoffeejiTime::zone());
		$firstThis = $now->modify('first day of this month')->setTime(0, 0);
		$firstPrev = $firstThis->modify('-1 month');
		$lastPrev = $firstThis->modify('-1 day');

		return array(
			'start' => $firstPrev->format('Y-m-d'),
			'end' => $lastPrev->format('Y-m-d'),
		);
	}

	/**
	 * Beginn und Ende sind inklusive. Intern ist das Ende der folgende Tag 00:00, exklusiv.
	 *
	 * @return array{start: string, end: string, from: DateTimeImmutable, to: DateTimeImmutable}
	 */
	public static function period(string $start, string $end): array
	{
		$from = self::day($start);
		$last = self::day($end);
		if ($from === null || $last === null) {
			throw new InvalidArgumentException('Zeitraum ist ungültig.');
		}
		if ($last < $from) {
			throw new InvalidArgumentException('Das Ende liegt vor dem Beginn.');
		}

		return array(
			'start' => $from->format('Y-m-d'),
			'end' => $last->format('Y-m-d'),
			'from' => $from,
			'to' => $last->modify('+1 day'),
		);
	}

	public static function merchant(?string $env, ?string $config): string
	{
		foreach (array($env, $config) as $candidate) {
			if ($candidate === null) {
				continue;
			}
			$trim = trim($candidate);
			if ($trim !== '') {
				return $trim;
			}
		}

		return self::MERCHANT_DEFAULT;
	}

	public static function toCents(mixed $amount): int
	{
		if (is_string($amount)) {
			$amount = trim(str_replace(array(' ', '€'), '', $amount));
			if (preg_match('/^-?\d+,\d{1,2}$/', $amount) === 1 && !str_contains($amount, '.')) {
				$amount = str_replace(',', '.', $amount);
			}
		}
		if (!is_string($amount) || preg_match('/^-?\d+\.\d{2}$/', $amount) !== 1) {
			$amount = number_format((float) $amount, 2, '.', '');
		}
		$negative = str_starts_with($amount, '-');
		$amount = ltrim($amount, '-');
		$parts = explode('.', $amount);
		$cents = ((int) $parts[0]) * 100 + (int) $parts[1];

		return $negative ? -$cents : $cents;
	}

	public static function money(int $cents): string
	{
		$sign = $cents < 0 ? '-' : '';
		$cents = abs($cents);

		return $sign . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
	}

	/**
	 * Enthaltene Steuer auf eine Brutto-Summe, kaufmännisch auf Cent.
	 */
	public static function includedTaxCents(int $grossCents, int $rate): int
	{
		$sign = $grossCents < 0 ? -1 : 1;
		$grossCents = abs($grossCents);
		if ($grossCents === 0 || $rate <= 0) {
			return 0;
		}
		$numer = $grossCents * $rate;
		$denom = 100 + $rate;
		$tax = intdiv($numer, $denom);
		if (($numer % $denom) * 2 >= $denom) {
			$tax++;
		}

		return $sign * $tax;
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @param list<array<string, mixed>> $rules
	 * @return array<string, mixed>
	 */
	public static function build(array $orders, DateTimeImmutable $from, DateTimeImmutable $to, array $rules, string $merchant): array
	{
		$zone = CoffeejiTime::zone();
		$from = $from->setTimezone($zone);
		$to = $to->setTimezone($zone);
		$merchant = trim($merchant);
		if ($merchant === '') {
			$merchant = self::MERCHANT_DEFAULT;
		}

		$lines = array();
		$portalCents = 0;
		$portalCount = 0;
		$excluded = array(
			'gratis' => array('count' => 0, 'cents' => 0),
			'einloesung' => array('count' => 0, 'cents' => 0),
		);
		$openInPeriod = 0;
		foreach ($orders as $order) {
			if (!is_array($order)) {
				continue;
			}
			$created = CoffeejiTime::parseLocal($order['create_time'] ?? null);
			if (!$created instanceof DateTimeImmutable || $created < $from || $created >= $to) {
				continue;
			}
			$status = (int) ($order['status'] ?? 0);
			$out = CoffeejiOrder::nullableInt($order['out_stock_status'] ?? null);
			if (CoffeejiOrder::isOpen($status, $out)) {
				$openInPeriod++;
			}
			if (!CoffeejiOrder::countsForPortalRevenue($status)) {
				continue;
			}
			$cents = self::toCents($order['pay_amt'] ?? 0);
			$portalCents += $cents;
			$portalCount++;
			$category = CoffeejiPortal::payCategory((string) ($order['pay_way_name'] ?? ''));
			if ($category === 'gratis' || $category === 'einloesung') {
				$excluded[$category]['count']++;
				$excluded[$category]['cents'] += $cents;
				continue;
			}
			$vat = CoffeejiVat::resolve($order, $rules);
			$goods = trim((string) ($order['goods_name'] ?? ''));
			if ($goods === '') {
				$goods = 'Ohne Bezeichnung';
			}
			$lines[] = array(
				'order_no' => trim((string) ($order['order_no'] ?? '')),
				'machine_key' => CoffeejiPortal::machineKeyOf($order),
				'machine_no' => CoffeejiPortal::machineNumber($order),
				'machine_name' => trim((string) ($order['device_name'] ?? '')),
				'dealer' => $merchant,
				'goods_name' => $goods,
				'amount_cents' => $cents,
				'amount' => self::money($cents),
				'pay_way_name' => trim((string) ($order['pay_way_name'] ?? '')),
				'pay_category' => $category,
				'vat_rate' => (int) $vat['rate'],
				'vat_configured' => (bool) $vat['configured'],
				'order_status' => (string) $status,
				'ship_status' => $out === null ? '' : (string) $out,
				'refund_status' => self::text($order['refund_status'] ?? null),
				'order_time' => $created->format('Y-m-d H:i:s'),
				'ship_time' => self::clock($order['out_stock_time'] ?? null),
				'refund_time' => self::clock($order['refund_time'] ?? null),
				'open' => CoffeejiOrder::isOpen($status, $out),
			);
		}
		usort($lines, static function (array $left, array $right): int {
			$time = strcmp((string) $left['order_time'], (string) $right['order_time']);
			if ($time !== 0) {
				return $time;
			}

			return strcmp((string) $left['order_no'], (string) $right['order_no']);
		});

		$machines = array();
		$vatCents = array();
		$unconfigured = array();
		$cash = 0;
		$card = 0;
		$other = 0;
		$open = 0;
		$gross = 0;
		foreach ($lines as $line) {
			$key = (string) $line['machine_key'];
			if (!isset($machines[$key])) {
				$machines[$key] = array(
					'machine_key' => $key,
					'machine_no' => (string) $line['machine_no'],
					'machine_name' => (string) $line['machine_name'],
					'dealer' => $merchant,
					'orders' => 0,
					'amount_cents' => 0,
					'open' => 0,
				);
			}
			if ($machines[$key]['machine_name'] === '' && $line['machine_name'] !== '') {
				$machines[$key]['machine_name'] = (string) $line['machine_name'];
			}
			$machines[$key]['orders']++;
			$machines[$key]['amount_cents'] += (int) $line['amount_cents'];
			if ($line['open']) {
				$machines[$key]['open']++;
				$open++;
			}
			$gross += (int) $line['amount_cents'];
			if ($line['pay_category'] === 'bar') {
				$cash += (int) $line['amount_cents'];
			} elseif ($line['pay_category'] === 'karte') {
				$card += (int) $line['amount_cents'];
			} else {
				$other += (int) $line['amount_cents'];
			}
			$rate = (int) $line['vat_rate'];
			if (!isset($vatCents[$rate])) {
				$vatCents[$rate] = 0;
			}
			$vatCents[$rate] += (int) $line['amount_cents'];
			if (!$line['vat_configured']) {
				$norm = CoffeejiVat::normalizeName((string) $line['goods_name']);
				$unconfigured[$norm] = (string) $line['goods_name'];
			}
		}

		$machineRows = array_values($machines);
		usort($machineRows, static function (array $left, array $right): int {
			$no = strcmp((string) $left['machine_no'], (string) $right['machine_no']);
			if ($no !== 0) {
				return $no;
			}

			return strcmp((string) $left['machine_key'], (string) $right['machine_key']);
		});
		foreach ($machineRows as $index => $row) {
			$machineRows[$index]['amount'] = self::money((int) $row['amount_cents']);
		}

		ksort($vatCents);
		$vat = array();
		foreach ($vatCents as $rate => $cents) {
			$tax = self::includedTaxCents((int) $cents, (int) $rate);
			$net = (int) $cents - $tax;
			$vat[] = array(
				'rate' => (int) $rate,
				'gross_cents' => (int) $cents,
				'tax_cents' => $tax,
				'net_cents' => $net,
				'gross' => self::money((int) $cents),
				'tax' => self::money($tax),
				'net' => self::money($net),
			);
		}
		$names = array_values($unconfigured);
		sort($names, SORT_STRING);

		return array(
			'from' => $from,
			'to' => $to,
			'merchant' => $merchant,
			'lines' => $lines,
			'machines' => $machineRows,
			'vat' => $vat,
			'unconfigured' => $names,
			'totals' => array(
				'amount_cents' => $gross,
				'amount' => self::money($gross),
				'count' => count($lines),
				'cash_cents' => $cash,
				'cash' => self::money($cash),
				'card_cents' => $card,
				'card' => self::money($card),
				'other_cents' => $other,
				'other' => self::money($other),
				'open' => $open,
				'open_in_period' => $openInPeriod,
				'portal_revenue_cents' => $portalCents,
				'portal_revenue' => self::money($portalCents),
				'portal_count' => $portalCount,
				'excluded_gratis_cents' => $excluded['gratis']['cents'],
				'excluded_gratis_count' => $excluded['gratis']['count'],
				'excluded_gratis' => self::money($excluded['gratis']['cents']),
				'excluded_einloesung_cents' => $excluded['einloesung']['cents'],
				'excluded_einloesung_count' => $excluded['einloesung']['count'],
				'excluded_einloesung' => self::money($excluded['einloesung']['cents']),
			),
		);
	}

	/**
	 * PDF zeigt 7 % und 19 % immer getrennt, auch wenn ein Satz 0,00 € ist.
	 *
	 * @param list<array<string, mixed>> $vat
	 * @return list<array<string, mixed>>
	 */
	public static function vatForPdf(array $vat): array
	{
		$byRate = array();
		foreach ($vat as $row) {
			$byRate[(int) $row['rate']] = $row;
		}
		foreach (array(CoffeejiVat::REDUCED_RATE, CoffeejiVat::DEFAULT_RATE) as $rate) {
			if (!isset($byRate[$rate])) {
				$byRate[$rate] = array(
					'rate' => $rate,
					'gross_cents' => 0,
					'tax_cents' => 0,
					'net_cents' => 0,
					'gross' => '0.00',
					'tax' => '0.00',
					'net' => '0.00',
				);
			}
		}
		ksort($byRate);

		return array_values($byRate);
	}

	private static function day(string $value): ?DateTimeImmutable
	{
		$value = trim($value);
		$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, CoffeejiTime::zone());
		if (!$parsed || $parsed->format('Y-m-d') !== $value) {
			return null;
		}

		return $parsed->setTime(0, 0);
	}

	private static function text(mixed $value): string
	{
		if ($value === null) {
			return '';
		}
		$text = trim((string) $value);

		return $text;
	}

	private static function clock(mixed $value): string
	{
		if ($value === null) {
			return '';
		}
		$text = trim((string) $value);
		if ($text === '') {
			return '';
		}
		$parsed = CoffeejiTime::parseLocal($text);

		return $parsed instanceof DateTimeImmutable ? $parsed->format('Y-m-d H:i:s') : $text;
	}
}
