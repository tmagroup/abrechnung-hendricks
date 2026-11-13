<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiRepositoryTest extends TestCase
{
	private ?CoffeejiRepository $repository = null;

	protected function setUp(): void
	{
		if (!defined('DB_HOSTNAME')) {
			define('DB_HOSTNAME', getenv('DB_HOSTNAME') ?: '127.0.0.1');
			define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
			define('DB_PASSWORD', getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '');
			define('DB_NAME', getenv('DB_NAME') ?: 'hendricks_test');
		}

		try {
			$repository = new CoffeejiRepository();
			if (!$repository->tablesReady()) {
				$this->installSchema();
			}
			if (!$repository->tablesReady()) {
				$this->markTestSkipped('Coffeeji-Tabellen sind nicht vorhanden.');
			}
			$this->repository = $repository;
		} catch (Throwable $e) {
			$this->markTestSkipped('Keine Testdatenbank: ' . $e->getMessage());
		}
	}

	public function testOrderUpsertAndDevicePasswordIsNotStored(): void
	{
		$repository = $this->repository;
		$this->assertInstanceOf(CoffeejiRepository::class, $repository);

		$device = CoffeejiDevice::normalize(array(
			'id' => '9000000000000000099',
			'deviceExtNo' => 'DEMO-UPSERT',
			'name' => 'Upsert-Automat',
			'status' => 1,
			'devicePwd' => 'darf-nicht-in-die-datenbank',
		));
		$repository->upsertDevices(array($device));

		$order = array(
			'order_no' => 'TEST-UPSERT-1',
			'device_id' => '9000000000000000099',
			'device_ext_no' => 'DEMO-UPSERT',
			'device_name' => 'Upsert-Automat',
			'goods_name' => 'Probegetränk',
			'price' => '1.80',
			'pay_amt' => '1.80',
			'pay_way_name' => 'Bargeld',
			'status' => 2,
			'refund_status' => null,
			'create_time' => '2026-11-13 10:15:00',
			'time_zone_id' => 'UTC+01:00',
		);
		$first = $repository->upsertOrders(array($order));
		$this->assertSame(1, $first['insert']);

		$second = $repository->upsertOrders(array($order));
		$this->assertSame(0, $second['insert']);
		$this->assertSame(1, $second['unchanged']);

		$order['pay_amt'] = '2.10';
		$order['status'] = 2;
		$third = $repository->upsertOrders(array($order));
		$this->assertSame(1, $third['update']);

		$from = new DateTimeImmutable('2026-11-13 00:00:00', new DateTimeZone('Europe/Berlin'));
		$to = new DateTimeImmutable('2026-11-14 00:00:00', new DateTimeZone('Europe/Berlin'));
		$found = false;
		foreach ($repository->ordersBetween($from, $to) as $row) {
			if ($row['order_no'] === 'TEST-UPSERT-1') {
				$found = true;
				$this->assertSame('2.10', number_format((float) $row['pay_amt'], 2, '.', ''));
				$this->assertSame('9000000000000000099', (string) $row['device_id']);
			}
		}
		$this->assertTrue($found);

		$mysqli = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
		$this->assertInstanceOf(mysqli::class, $mysqli);
		$result = mysqli_query($mysqli, "SELECT raw_json FROM cj_devices WHERE id = '9000000000000000099'");
		$this->assertInstanceOf(mysqli_result::class, $result);
		$stored = mysqli_fetch_assoc($result);
		$this->assertIsArray($stored);
		$this->assertStringNotContainsString('devicePwd', (string) $stored['raw_json']);
		$this->assertStringNotContainsString('darf-nicht-in-die-datenbank', (string) $stored['raw_json']);
		mysqli_query($mysqli, "DELETE FROM cj_orders WHERE order_no = 'TEST-UPSERT-1'");
		mysqli_query($mysqli, "DELETE FROM cj_devices WHERE id = '9000000000000000099'");
		mysqli_close($mysqli);
	}

	private function installSchema(): void
	{
		$mysqli = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
		if (!$mysqli) {
			return;
		}
		$sql = (string) file_get_contents(__DIR__ . '/../app/db/migrations/20261113_coffeeji.sql');
		if (mysqli_multi_query($mysqli, $sql)) {
			do {
				if ($result = mysqli_store_result($mysqli)) {
					mysqli_free_result($result);
				}
			} while (mysqli_more_results($mysqli) && mysqli_next_result($mysqli));
		}
		mysqli_close($mysqli);
	}
}
