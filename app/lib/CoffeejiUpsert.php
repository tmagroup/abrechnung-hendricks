<?php

declare(strict_types=1);

class CoffeejiUpsert
{
	/**
	 * @param array<string, array<string, mixed>> $existingByOrderNo
	 * @param list<array<string, mixed>> $incoming
	 * @return array{insert: list<array<string, mixed>>, update: list<array<string, mixed>>, unchanged: int}
	 */
	public static function plan(array $existingByOrderNo, array $incoming, bool $details = true): array
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
			if (self::fingerprint($order, $details) === self::fingerprint($existingByOrderNo[$orderNo], $details)) {
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
	public static function fingerprint(array $order, bool $details = true): string
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
		if ($details) {
			$keys = array_merge($keys, array(
				'pay_way',
				'out_stock_status',
				'out_stock_time',
				'refund_time',
				'fail_remark',
			));
		}
		$parts = array();
		foreach ($keys as $key) {
			$value = $order[$key] ?? '';
			if ($key === 'price' || $key === 'pay_amt') {
				$value = CoffeejiOrder::money($value);
			} elseif ($key === 'status') {
				$value = (string) (int) $value;
			} elseif ($key === 'refund_status' || $key === 'pay_way' || $key === 'out_stock_status') {
				$value = ($value === null || $value === '') ? '' : (string) (int) $value;
			} else {
				$value = trim((string) $value);
			}
			$parts[] = $key . '=' . $value;
		}

		return implode('|', $parts);
	}
}
