<?php

declare(strict_types=1);

class CoffeejiUpsert
{
	/**
	 * @param array<string, array<string, mixed>> $existingByOrderNo
	 * @param list<array<string, mixed>> $incoming
	 * @return array{insert: list<array<string, mixed>>, update: list<array<string, mixed>>, unchanged: int}
	 */
	public static function plan(array $existingByOrderNo, array $incoming): array
	{
		$seen = array();
		foreach ($incoming as $order) {
			$orderNo = trim((string) ($order['order_no'] ?? ''));
			if ($orderNo === '') {
				continue;
			}
			$order['order_no'] = $orderNo;
			$seen[$orderNo] = $order;
		}

		$insert = array();
		$update = array();
		$unchanged = 0;
		foreach ($seen as $orderNo => $order) {
			if (!isset($existingByOrderNo[$orderNo])) {
				$insert[] = $order;
				continue;
			}
			if (self::fingerprint($order) === self::fingerprint($existingByOrderNo[$orderNo])) {
				$unchanged++;
				continue;
			}
			$update[] = $order;
		}

		return array(
			'insert' => $insert,
			'update' => $update,
			'unchanged' => $unchanged,
		);
	}

	/**
	 * @param array<string, mixed> $order
	 */
	public static function fingerprint(array $order): string
	{
		$keys = array(
			'device_id',
			'device_ext_no',
			'device_name',
			'goods_name',
			'price',
			'pay_amt',
			'pay_way_name',
			'status',
			'refund_status',
			'create_time',
			'time_zone_id',
		);
		$parts = array();
		foreach ($keys as $key) {
			$value = $order[$key] ?? '';
			if ($key === 'price' || $key === 'pay_amt') {
				$value = CoffeejiOrder::money($value);
			} elseif ($key === 'status') {
				$value = (string) (int) $value;
			} elseif ($key === 'refund_status') {
				$value = ($value === null || $value === '') ? '' : (string) (int) $value;
			} else {
				$value = trim((string) $value);
			}
			$parts[] = $key . '=' . $value;
		}

		return implode('|', $parts);
	}
}
