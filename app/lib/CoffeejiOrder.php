<?php

declare(strict_types=1);

class CoffeejiOrder
{
	public const SALE_STATUS = 2;

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function normalize(array $row): array
	{
		$refund = $row['refundStatus'] ?? null;
		$refundStatus = ($refund === null || $refund === '') ? null : (int) $refund;
		$zone = trim((string) ($row['timeZoneId'] ?? $row['time_zone_id'] ?? ''));
		$source = self::sourceClock($row);

		return array(
			'order_no' => trim((string) ($row['orderNo'] ?? '')),
			'device_id' => trim((string) ($row['deviceId'] ?? '')),
			'device_ext_no' => trim((string) ($row['deviceExtNo'] ?? '')),
			'device_name' => trim((string) ($row['deviceName'] ?? '')),
			'goods_name' => trim((string) ($row['goodsName'] ?? '')),
			'price' => self::money($row['price'] ?? 0),
			'pay_amt' => self::money($row['payAmt'] ?? $row['pay_amt'] ?? 0),
			'pay_way_name' => trim((string) ($row['payWayName'] ?? $row['pay_way_name'] ?? '')),
			'status' => (int) ($row['status'] ?? 0),
			'refund_status' => $refundStatus,
			'create_time' => CoffeejiTime::storeSource($source, $zone),
			'time_zone_id' => $zone,
			'source_create_time' => self::sourceLiteral($source),
		);
	}

	/**
	 * Ursprüngliche Wanduhr. source_create_time gewinnt vor createTime und create_time,
	 * damit ein zweites Normalisieren die schon gedrehte Berlin-Zeit nicht noch einmal schiebt.
	 *
	 * @param array<string, mixed> $row
	 */
	public static function sourceClock(array $row): mixed
	{
		foreach (array('source_create_time', 'createTime', 'create_time') as $key) {
			if (!array_key_exists($key, $row)) {
				continue;
			}
			$value = $row[$key];
			if ($value === null || (is_string($value) && trim($value) === '')) {
				continue;
			}

			return is_string($value) ? trim($value) : $value;
		}

		return null;
	}

	public static function sourceLiteral(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}
		if (is_int($value)) {
			return (string) $value;
		}
		if (is_string($value)) {
			$value = trim($value);

			return $value === '' ? null : $value;
		}
		$text = trim((string) $value);

		return $text === '' ? null : $text;
	}

	public static function money(mixed $value): string
	{
		if (is_string($value)) {
			$value = str_replace(',', '.', $value);
		}

		return number_format((float) $value, 2, '.', '');
	}

	public static function isSale(array $order): bool
	{
		return (int) ($order['status'] ?? 0) === self::SALE_STATUS;
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @return list<array<string, mixed>>
	 */
	public static function filterWindow(array $orders, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		$kept = array();
		foreach ($orders as $order) {
			$created = CoffeejiTime::parseLocal($order['create_time'] ?? null);
			if ($created === null) {
				continue;
			}
			if ($created >= $from && $created < $to) {
				$kept[] = $order;
			}
		}

		return $kept;
	}
}
