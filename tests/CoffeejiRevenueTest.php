<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiRevenueTest extends TestCase
{
	public function testOnlyCompletedOrdersInsideTheLocalWindowCount(): void
	{
		$now = new DateTimeImmutable('2026-11-13 12:35:00', new DateTimeZone('Europe/Berlin'));
		$orders = array(
			$this->order('A', 2, '2.50', '2026-11-13 10:00:00'),
			$this->order('B', 1, '9.00', '2026-11-13 11:00:00'),
			$this->order('C', 2, '1.50', '2026-11-12 11:00:00'),
			$this->order('D', 2, '4.00', '2026-11-11 22:30:00'),
			$this->order('E', 2, '3.00', '2026-11-13 09:00:00', 1),
			$this->order('F', 0, '5.00', '2026-11-13 08:00:00'),
		);

		$window = CoffeejiTime::window('recent', $now);
		$filtered = CoffeejiOrder::filterWindow($orders, $window[0], $window[1]);
		$numbers = array_column($filtered, 'order_no');
		sort($numbers);
		$this->assertSame(array('A', 'B', 'C', 'E', 'F'), $numbers);

		$summary = CoffeejiRevenue::summarize($filtered, $now);

		$this->assertSame(5.5, $summary['today']['amount']);
		$this->assertSame(2, $summary['today']['count']);
		$this->assertSame(1.5, $summary['yesterday_same']['amount']);
		$this->assertSame(30, count($summary['chart']));
		$this->assertSame(0.0, $summary['chart'][0]['amount']);
	}

	public function testBackfillWindowIsThirtyFiveLocalDays(): void
	{
		$now = new DateTimeImmutable('2026-11-13 03:15:00', new DateTimeZone('Europe/Berlin'));
		$window = CoffeejiTime::window('backfill', $now);

		$this->assertSame('2026-10-10 00:00:00', $window[0]->format('Y-m-d H:i:s'));
		$this->assertSame('2026-11-14 00:00:00', $window[1]->format('Y-m-d H:i:s'));
		$this->assertSame(35, (int) $window[0]->diff($window[1])->days);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function order(string $no, int $status, string $amount, string $time, ?int $refund = null): array
	{
		return array(
			'order_no' => $no,
			'device_id' => '9000000000000000001',
			'device_ext_no' => 'DEMO-101',
			'device_name' => 'Musterkiosk',
			'goods_name' => 'Hausmischung',
			'price' => $amount,
			'pay_amt' => $amount,
			'pay_way_name' => '现金支付',
			'status' => $status,
			'refund_status' => $refund,
			'create_time' => $time,
			'time_zone_id' => 'UTC+01:00',
		);
	}
}
