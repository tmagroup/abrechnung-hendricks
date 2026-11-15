<?php

declare(strict_types=1);

class CoffeejiOrder
{
	/**
	 * Dashboard, Live-Verkäufe und Maschinenkarten zählen weiterhin nur diesen Status.
	 * Die Bestellverwaltung und die Statistikseiten nutzen PORTAL_REVENUE_STATUSES.
	 */
	public const SALE_STATUS = 2;

	/**
	 * Wie das Händlerportal: In Herstellung (1) und fertig (2) zählen zum Umsatz.
	 * Hängende Bestellungen bleiben in der Liste sichtbar und werden als „offen“ markiert.
	 *
	 * @var list<int>
	 */
	public const PORTAL_REVENUE_STATUSES = array(1, 2);

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
			'pay_way' => self::nullableInt($row['payWay'] ?? $row['pay_way'] ?? null),
			'status' => (int) ($row['status'] ?? 0),
			'refund_status' => $refundStatus,
			'out_stock_status' => self::nullableInt($row['outStockStatus'] ?? $row['out_stock_status'] ?? null),
			'out_stock_time' => self::clock($row['outStockTime'] ?? $row['out_stock_time'] ?? null),
			'refund_time' => self::clock($row['refundTime'] ?? $row['refund_time'] ?? null),
			'fail_remark' => self::clip(trim((string) ($row['failRemark'] ?? $row['fail_remark'] ?? '')), 2000),
			'create_time' => CoffeejiTime::store($row['createTime'] ?? $row['create_time'] ?? null),
			'time_zone_id' => trim((string) ($row['timeZoneId'] ?? $row['time_zone_id'] ?? '')),
		);
	}

	public static function countsForPortalRevenue(int $status): bool
	{
		return in_array($status, self::PORTAL_REVENUE_STATUSES, true);
	}

	/**
	 * Hängt in Herstellung oder die Ausgabe ist noch offen.
	 */
	public static function isOpen(int $status, ?int $outStockStatus): bool
	{
		return $status === 1 || $outStockStatus === 1;
	}

	public static function nullableInt(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}
		if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) !== 1) {
			return null;
		}

		return (int) $value;
	}

	private static function clock(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}
		if (is_string($value) && trim($value) === '') {
			return null;
		}

		return CoffeejiTime::store($value);
	}

	private static function clip(string $value, int $max): string
	{
		if (mb_strlen($value) <= $max) {
			return $value;
		}

		return mb_substr($value, 0, $max);
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
