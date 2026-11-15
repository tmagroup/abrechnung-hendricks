<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
		$this->assertSame('2026-10-12 08:52:03', CoffeejiTime::storeSource('2026-10-12 07:52:03', 'UTC+01:00'));
		$this->assertSame('2026-10-25 03:30:00', CoffeejiTime::storeSource('2026-10-25 03:30:00', 'UTC+01:00'));
		$this->assertSame('2026-10-24 23:00:00', CoffeejiTime::storeSource('2026-10-24 22:00:00', 'UTC+01:00'));
	}

	public function testMissingZoneKeepsTheWallClock(): void
	{
		$this->assertSame('2026-10-12 07:52:03', CoffeejiTime::storeSource('2026-10-12 07:52:03', null));
		$this->assertSame('2026-10-12 07:52:03', CoffeejiTime::storeSource('2026-10-12 07:52:03', ''));
		$this->assertSame('2026-10-12 07:52:03', CoffeejiTime::storeSource('2026-10-12 07:52:03', '   '));
		$this->assertSame('2026-10-12 07:52:03', CoffeejiTime::storeSource('2026-10-12 07:52:03', 'keine-zone'));
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
		$stored = CoffeejiOrder::storedJson($once);
		$this->assertStringContainsString('createTime', $stored);
		$this->assertStringContainsString('outStockTime', $stored);
		$this->assertStringNotContainsString('devicePwd', $stored);
		$withSecret = CoffeejiOrder::normalize(array(
			'orderNo' => 'DEMO-TZ-SECRET',
			'createTime' => '2026-10-12 07:52:03',
			'timeZoneId' => 'UTC+01:00',
			'devicePwd' => 'darf-nicht-in-raw',
		));
		$this->assertSame('2026-10-12 08:52:03', $withSecret['create_time']);
		$this->assertStringNotContainsString('darf-nicht-in-raw', CoffeejiOrder::storedJson($withSecret));
		$this->assertStringNotContainsString('devicePwd', CoffeejiOrder::storedJson($withSecret));

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

	public function testDeviceHeartTimeWithoutZoneMatchesMain(): void
	{
		$this->withDeviceZone(false, function (): void {
			if (defined('COFFEEJI_DEVICE_TIME_ZONE') && trim((string) COFFEEJI_DEVICE_TIME_ZONE) !== CoffeejiTime::DEFAULT_DEVICE_ZONE) {
				$this->fail('COFFEEJI_DEVICE_TIME_ZONE überdeckt den Standard im Testprozess.');
			}
			$this->assertSame(CoffeejiTime::DEFAULT_DEVICE_ZONE, CoffeejiTime::deviceSourceZone());

			$portal = CoffeejiDevice::normalize(array(
				'id' => '9000000000000000771',
				'name' => 'Musterautomat',
				'heartTime' => '2026-11-15 14:15:16',
			));
			$this->assertSame('2026-11-15 17:15:16', $portal['heart_time']);
			$this->assertSame('2026-11-15 14:15:16', $portal['raw']['heartTime']);

			$winter = array(
				'2026-11-14 09:12:06' => '2026-11-14 12:12:06',
				'2026-11-14 16:12:06' => '2026-11-14 19:12:06',
				'2026-11-14 16:27:46' => '2026-11-14 19:27:46',
				'2026-11-15 04:54:00' => '2026-11-15 07:54:00',
				'2026-11-15 22:30:00' => '2026-11-16 01:30:00',
			);
			foreach ($winter as $raw => $expected) {
				$this->assertSame($expected, $this->heart($raw), $raw);
				$this->assertSame(CoffeejiTime::storeSource($raw, 'UTC-02:00'), $this->heart($raw), $raw);
			}

			// Fester Offset UTC−2: im Sommer vier Stunden hinter MESZ, nicht zwei.
			$this->assertSame('2026-07-15 20:12:06', $this->heart('2026-07-15 16:12:06'));
			$this->assertSame('2026-07-16 02:30:00', $this->heart('2026-07-15 22:30:00'));
			$this->assertSame(
				CoffeejiTime::storeSource('2026-07-15 14:15:16', 'UTC-02:00'),
				$this->heart('2026-07-15 14:15:16')
			);
			$this->assertSame('2026-07-15 18:15:16', $this->heart('2026-07-15 14:15:16'));

			$order = CoffeejiOrder::normalize(array(
				'orderNo' => 'DEMO-ORDER-KEINE-GERAETEZONE',
				'createTime' => '2026-11-15 14:15:16',
			));
			$this->assertSame('2026-11-15 14:15:16', $order['create_time']);
			$summerOrder = CoffeejiOrder::normalize(array(
				'orderNo' => 'DEMO-ORDER-SOMMER',
				'createTime' => '2026-07-15 12:00:00',
				'timeZoneId' => 'UTC+01:00',
			));
			$this->assertSame('2026-07-15 13:00:00', $summerOrder['create_time']);
			$this->assertNull($this->heartMissing());
		});

		foreach (array('none', 'NONE', ' none ', '') as $setting) {
			$this->withDeviceZone($setting, function () use ($setting): void {
				$this->assertNull(CoffeejiTime::deviceSourceZone(), $setting);
				$this->assertSame('2026-11-15 14:15:16', $this->heart('2026-11-15 14:15:16'), $setting);
				$this->assertSame(
					CoffeejiTime::store('2026-07-15 16:12:06'),
					$this->heart('2026-07-15 16:12:06'),
					$setting
				);
				$this->assertSame('2026-11-15 01:15:00', $this->heart('2026-11-15 08:15:00', array(
					'timeZoneId' => 'UTC+8',
				)), $setting);
			});
		}

		$this->withDeviceZone('UTC-03:00', function (): void {
			$this->assertSame('UTC-03:00', CoffeejiTime::deviceSourceZone());
			$this->assertSame('2026-11-15 18:15:16', $this->heart('2026-11-15 14:15:16'));
			$this->assertSame('2026-11-15 01:15:00', $this->heart('2026-11-15 08:15:00', array(
				'time_zone_id' => 'UTC+8',
			)));
		});
	}

	public function testDeviceHeartTimeUsesZoneOnlyWhenPresent(): void
	{
		$this->withDeviceZone(false, function (): void {
			$this->assertSame('2026-11-15 11:15:00', $this->heart('2026-11-15 08:15:00'));
			$this->assertSame('2026-11-15 01:15:00', $this->heart('2026-11-15 08:15:00', array(
				'timeZoneId' => 'UTC+8',
			)));
			$this->assertSame('2026-11-15 08:15:00', $this->heart('2026-11-15 08:15:00', array(
				'timeZoneId' => 'UTC+01:00',
			)));
		});
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testDeviceZoneFromConfigWhenEnvironmentIsUnset(): void
	{
		$this->loadCoffeejiConfig('UTC-03:00', false);
		$this->assertSame('UTC-03:00', COFFEEJI_DEVICE_TIME_ZONE);
		$this->assertSame('2026-11-15 18:15:16', $this->heart('2026-11-15 14:15:16'));
		$this->assertSame('2026-07-15 12:00:00', CoffeejiOrder::normalize(array(
			'orderNo' => 'DEMO-ORDER-CONFIG',
			'createTime' => '2026-07-15 12:00:00',
		))['create_time']);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testDeviceZoneEnvironmentOverridesConfig(): void
	{
		$this->loadCoffeejiConfig('UTC-03:00', 'none');
		$this->assertSame('none', COFFEEJI_DEVICE_TIME_ZONE);
		$this->assertNull(CoffeejiTime::deviceSourceZone());
		$this->assertSame('2026-11-15 14:15:16', $this->heart('2026-11-15 14:15:16'));
		$this->assertSame('2026-11-15 08:15:00', $this->heart('2026-11-15 08:15:00', array(
			'timeZoneId' => 'UTC+01:00',
		)));
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testDeviceZoneDefaultsWhenConfigAndEnvironmentAreMissing(): void
	{
		$this->loadCoffeejiConfig(null, false);
		$this->assertSame(CoffeejiTime::DEFAULT_DEVICE_ZONE, COFFEEJI_DEVICE_TIME_ZONE);
		$this->assertSame('2026-11-15 17:15:16', $this->heart('2026-11-15 14:15:16'));
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testEmptyDeviceZoneConfigKeepsTheWallClock(): void
	{
		$this->loadCoffeejiConfig('', false);
		$this->assertSame('', COFFEEJI_DEVICE_TIME_ZONE);
		$this->assertNull(CoffeejiTime::deviceSourceZone());
		$this->assertSame('2026-11-15 14:15:16', $this->heart('2026-11-15 14:15:16'));
	}

	/**
	 * @param callable(): void $assert
	 */
	private function withDeviceZone(string|false $zone, callable $assert): void
	{
		$previous = getenv('COFFEEJI_DEVICE_TIME_ZONE');
		if ($zone === false) {
			putenv('COFFEEJI_DEVICE_TIME_ZONE');
		} else {
			putenv('COFFEEJI_DEVICE_TIME_ZONE=' . $zone);
		}
		try {
			$assert();
		} finally {
			if ($previous === false) {
				putenv('COFFEEJI_DEVICE_TIME_ZONE');
			} else {
				putenv('COFFEEJI_DEVICE_TIME_ZONE=' . $previous);
			}
		}
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	private function heart(string $raw, array $extra = array()): ?string
	{
		$device = CoffeejiDevice::normalize(array_merge(array(
			'id' => '9000000000000000771',
			'name' => 'Musterautomat',
			'heartTime' => $raw,
		), $extra));

		return $device['heart_time'];
	}

	private function heartMissing(): ?string
	{
		$device = CoffeejiDevice::normalize(array(
			'id' => '9000000000000000771',
			'name' => 'Musterautomat',
		));

		return $device['heart_time'];
	}

	private function loadCoffeejiConfig(?string $configured, string|false $env): void
	{
		if (!defined('APP_FOLDER')) {
			define('APP_FOLDER', 'app');
		}
		if ($env === false) {
			putenv('COFFEEJI_DEVICE_TIME_ZONE');
		} else {
			putenv('COFFEEJI_DEVICE_TIME_ZONE=' . $env);
		}
		if ($configured !== null) {
			$coffeeji_device_time_zone = $configured;
		}
		require dirname(__DIR__) . '/app/config/function/coffeeji.php';
	}
}
