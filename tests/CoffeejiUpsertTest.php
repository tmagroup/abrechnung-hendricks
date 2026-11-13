<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiUpsertTest extends TestCase
{
	public function testPlanInsertsUpdatesAndSkipsIdenticalOrders(): void
	{
		$existing = array(
			'A-1' => $this->order('A-1', '1.50', 2),
			'B-2' => $this->order('B-2', '2.00', 1),
		);
		$existing['A-1']['pay_amt'] = '1.5';

		$incoming = array(
			$this->order('A-1', '1.50', 2),
			$this->order('B-2', '2.00', 2),
			$this->order('C-3', '3.25', 2),
			$this->order('', '9.00', 2),
			$this->order('C-3', '3.50', 2),
		);

		$plan = CoffeejiUpsert::plan($existing, $incoming);

		$this->assertSame(array('C-3'), array_column($plan['insert'], 'order_no'));
		$this->assertSame('3.50', $plan['insert'][0]['pay_amt']);
		$this->assertSame(array('B-2'), array_column($plan['update'], 'order_no'));
		$this->assertSame(2, $plan['update'][0]['status']);
		$this->assertSame(1, $plan['unchanged']);
	}

	public function testMoneyAndEmptyRefundShareOneFingerprint(): void
	{
		$left = $this->order('A-1', '1.50', 2);
		$right = $this->order('A-1', '1.5', 2);
		$right['refund_status'] = '';

		$this->assertSame(CoffeejiUpsert::fingerprint($left), CoffeejiUpsert::fingerprint($right));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function order(string $no, string $amount, int $status): array
	{
		return array(
			'order_no' => $no,
			'device_id' => '9000000000000000001',
			'device_ext_no' => 'DEMO-101',
			'device_name' => 'Musterkiosk',
			'goods_name' => 'Hausmischung',
			'price' => $amount,
			'pay_amt' => $amount,
			'pay_way_name' => 'Bargeld',
			'status' => $status,
			'refund_status' => null,
			'create_time' => '2026-11-13 10:00:00',
			'time_zone_id' => 'UTC+01:00',
		);
	}
}
