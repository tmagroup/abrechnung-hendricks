<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiTimeTest extends TestCase
{
	public function testUtc8WallClockBecomesBerlinInWinter(): void
	{
		$this->assertSame('2026-11-12 17:59:28', CoffeejiTime::storeSource('2026-11-13 00:59:28', 'UTC+8'));
		$this->assertSame('2026-11-14 21:52:56', CoffeejiTime::storeSource('2026-11-15 04:52:56', 'UTC+08:00'));
		$this->assertSame('2026-11-12 17:59:28', CoffeejiTime::storeSource('2026-11-13 00:59:28', 'UTC+8:00'));
		$this->assertSame('2026-11-12 17:59:28', CoffeejiTime::storeSource('2026-11-13 00:59:28', 'utc+8'));
		$this->assertSame('2026-11-12 17:59:28', CoffeejiTime::storeSource('2026-11-13 00:59:28', 'GMT+8'));
	}

	public function testUtcPlusOneStaysInWinterAndShiftsInSummer(): void
	{
		$this->assertSame('2026-11-14 21:52:56', CoffeejiTime::storeSource('2026-11-14 21:52:56', 'UTC+01:00'));
		$this->assertSame('2026-07-15 13:00:00', CoffeejiTime::storeSource('2026-07-15 12:00:00', 'UTC+01:00'));
	}

	public function testMissingZoneUsesDocumentedUtc8(): void
	{
		$this->assertSame('2026-11-12 17:59:28', CoffeejiTime::storeSource('2026-11-13 00:59:28', null));
		$this->assertSame('2026-11-12 17:59:28', CoffeejiTime::storeSource('2026-11-13 00:59:28', ''));
		$this->assertSame('2026-11-12 17:59:28', CoffeejiTime::storeSource('2026-11-13 00:59:28', '   '));
	}

	public function testSummerAndWinterOffsetsDiffer(): void
	{
		$this->assertSame('2026-07-15 06:00:00', CoffeejiTime::storeSource('2026-07-15 12:00:00', 'UTC+8'));
		$this->assertSame('2026-01-15 05:00:00', CoffeejiTime::storeSource('2026-01-15 12:00:00', 'UTC+8'));
	}

	public function testMonthBoundaryOctoberNovember(): void
	{
		$this->assertSame('2026-10-31 16:00:00', CoffeejiTime::storeSource('2026-10-31 23:00:00', 'UTC+8'));
		$this->assertSame('2026-10-31 17:30:00', CoffeejiTime::storeSource('2026-11-01 00:30:00', 'UTC+8'));
		$this->assertSame('2026-11-01 00:00:00', CoffeejiTime::storeSource('2026-11-01 07:00:00', 'UTC+8'));
	}

	public function testExplicitOffsetAndEpochStayAbsolute(): void
	{
		$this->assertSame(
			'2026-11-12 17:59:28',
			CoffeejiTime::storeSource('2026-11-13 00:59:28+08:00', 'UTC+01:00')
		);
		$instant = new DateTimeImmutable('2026-11-12 17:59:28', new DateTimeZone('Europe/Berlin'));
		$this->assertSame('2026-11-12 17:59:28', CoffeejiTime::storeSource($instant->getTimestamp(), 'UTC+8'));
		$this->assertSame('2026-11-12 17:59:28', CoffeejiTime::storeSource((string) ($instant->getTimestamp() * 1000), 'UTC+8'));
	}

	public function testStoredBerlinClockIsNotShiftedAgain(): void
	{
		$this->assertSame('2026-11-12 17:59:28', CoffeejiTime::store('2026-11-12 17:59:28'));
		$parsed = CoffeejiTime::parseLocal('2026-11-12 17:59:28');
		$this->assertInstanceOf(DateTimeImmutable::class, $parsed);
		$this->assertSame('2026-11-12 17:59:28', $parsed->format('Y-m-d H:i:s'));
		$this->assertSame('Europe/Berlin', $parsed->getTimezone()->getName());
	}

	public function testOrderNormalizeUsesZoneAndIsIdempotent(): void
	{
		$api = array(
			'orderNo' => 'DEMO-TZ-1',
			'deviceId' => '9000000000000000001',
			'createTime' => '2026-11-13 00:59:28',
			'outStockTime' => '2026-11-15 04:52:56',
			'refundTime' => '2026-11-15 05:00:00',
			'timeZoneId' => 'UTC+8',
			'status' => 2,
			'payAmt' => '2.40',
		);
		$once = CoffeejiOrder::normalize($api);
		$twice = CoffeejiOrder::normalize($once);

		$this->assertSame('2026-11-12 17:59:28', $once['create_time']);
		$this->assertSame('2026-11-13 00:59:28', $once['source_create_time']);
		$this->assertSame('2026-11-14 21:52:56', $once['out_stock_time']);
		$this->assertSame('2026-11-14 22:00:00', $once['refund_time']);
		$this->assertSame('UTC+8', $once['time_zone_id']);
		$this->assertSame($once['create_time'], $twice['create_time']);
		$this->assertSame($once['source_create_time'], $twice['source_create_time']);
		$this->assertSame($once['out_stock_time'], $twice['out_stock_time']);
		$this->assertSame($once['refund_time'], $twice['refund_time']);

		$local = CoffeejiOrder::normalize(array(
			'orderNo' => 'DEMO-TZ-2',
			'createTime' => '2026-11-14 21:52:56',
			'timeZoneId' => 'UTC+01:00',
		));
		$this->assertSame('2026-11-14 21:52:56', $local['create_time']);

		$from = new DateTimeImmutable('2026-11-12 00:00:00', new DateTimeZone('Europe/Berlin'));
		$to = new DateTimeImmutable('2026-11-13 00:00:00', new DateTimeZone('Europe/Berlin'));
		$this->assertCount(1, CoffeejiOrder::filterWindow(array($once), $from, $to));
		$next = new DateTimeImmutable('2026-11-14 00:00:00', new DateTimeZone('Europe/Berlin'));
		$this->assertCount(0, CoffeejiOrder::filterWindow(array($once), $to, $next));
	}

	public function testDeviceHeartTimeUsesDocumentedZoneUnlessOverridden(): void
	{
		$device = CoffeejiDevice::normalize(array(
			'id' => '9000000000000000771',
			'name' => 'Musterautomat',
			'heartTime' => '2026-11-15 08:15:00',
		));
		$this->assertSame('2026-11-15 01:15:00', $device['heart_time']);

		$local = CoffeejiDevice::normalize(array(
			'id' => '9000000000000000772',
			'name' => 'Musterautomat',
			'heartTime' => '2026-11-15 08:15:00',
			'timeZoneId' => 'UTC+01:00',
		));
		$this->assertSame('2026-11-15 08:15:00', $local['heart_time']);
	}
}
