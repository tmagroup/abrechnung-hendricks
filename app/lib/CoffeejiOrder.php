<?php

declare(strict_types=1);

class CoffeejiOrder
{
	/**
	 * Fertig ausgeliefert. Der Umsatz aller Anzeigen nutzt PORTAL_REVENUE_STATUSES.
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
		$zone = trim((string) ($row['timeZoneId'] ?? $row['time_zone_id'] ?? ''));
		$source = self::sourceClock($row);
		$outSource = self::sourceField($row, array('source_out_stock_time', 'outStockTime', 'out_stock_time'));
		$refundSource = self::sourceField($row, array('source_refund_time', 'refundTime', 'refund_time'));

		$normalized = array(
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
			'out_stock_time' => self::clock($outSource, $zone),
			'refund_time' => self::clock($refundSource, $zone),
			'fail_remark' => self::clip(trim((string) ($row['failRemark'] ?? $row['fail_remark'] ?? '')), 2000),
			'create_time' => CoffeejiTime::storeSource($source, $zone),
			'time_zone_id' => $zone,
			'source_create_time' => self::sourceLiteral($source),
			'source_out_stock_time' => self::sourceLiteral($outSource),
			'source_refund_time' => self::sourceLiteral($refundSource),
		);
		$apiRaw = self::apiRaw($row);
		if ($apiRaw !== null) {
			$normalized['api_raw'] = $apiRaw;
		}

		return $normalized;
	}

	/**
	 * JSON für raw_json. Liegt die API-Zeile bei, wird nur sie gespeichert,
	 * damit createTime erhalten bleibt. devicePwd fällt weg.
	 *
	 * @param array<string, mixed> $order
	 */
	public static function storedJson(array $order): string
	{
		$payload = $order['api_raw'] ?? null;
		if (!is_array($payload)) {
			$payload = $order;
			unset($payload['api_raw']);
		}
		$clean = CoffeejiResponse::stripSecrets($payload);
		if (!is_array($clean)) {
			$clean = array();
		}
		$json = json_encode($clean, JSON_UNESCAPED_UNICODE);

		return $json === false ? '{}' : $json;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>|null
	 */
	private static function apiRaw(array $row): ?array
	{
		if (isset($row['api_raw']) && is_array($row['api_raw'])) {
			$clean = CoffeejiResponse::stripSecrets($row['api_raw']);

			return is_array($clean) ? $clean : null;
		}
		if (!array_key_exists('createTime', $row) && !array_key_exists('timeZoneId', $row) && !array_key_exists('orderNo', $row)) {
			return null;
		}
		$clean = CoffeejiResponse::stripSecrets($row);

		return is_array($clean) ? $clean : null;
	}

	/**
	 * Ursprüngliche Wanduhr. source_create_time gewinnt vor createTime und create_time,
	 * damit ein zweites Normalisieren die schon gedrehte Berlin-Zeit nicht noch einmal schiebt.
	 *
	 * @param array<string, mixed> $row
	 */
	public static function sourceClock(array $row): mixed
	{
		return self::sourceField($row, array('source_create_time', 'createTime', 'create_time'));
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

	/**
	 * Dieselbe Zone wie create_time. source_* bleibt die Wanduhr, damit der nächste Lauf nicht noch einmal schiebt.
	 */
	private static function clock(mixed $value, string $zone): ?string
	{
		if ($value === null) {
			return null;
		}
		if (is_string($value) && trim($value) === '') {
			return null;
		}

		return CoffeejiTime::storeSource($value, $zone);
	}

	/**
	 * @param array<string, mixed> $row
	 * @param list<string> $keys
	 */
	private static function sourceField(array $row, array $keys): mixed
	{
		foreach ($keys as $key) {
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
		return self::countsForPortalRevenue((int) ($order['status'] ?? 0));
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
