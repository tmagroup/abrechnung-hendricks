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
			'create_time' => CoffeejiTime::store($row['createTime'] ?? $row['create_time'] ?? null),
			'time_zone_id' => trim((string) ($row['timeZoneId'] ?? $row['time_zone_id'] ?? '')),
		);
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
