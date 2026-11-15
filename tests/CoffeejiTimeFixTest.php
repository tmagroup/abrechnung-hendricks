<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiTimeFixTest extends TestCase
{
	private ?mysqli $db = null;

	protected function setUp(): void
	{
		if (!defined('DB_HOSTNAME')) {
			define('DB_HOSTNAME', getenv('DB_HOSTNAME') ?: '127.0.0.1');
			define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
			define('DB_PASSWORD', getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '');
			define('DB_NAME', getenv('DB_NAME') ?: 'hendricks_test');
		}

		try {
			$db = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
			if (!$db) {
				$this->markTestSkipped('Keine Testdatenbank.');
			}
			mysqli_set_charset($db, 'utf8mb4');
			$this->db = $db;
			$this->installSchema();
			mysqli_query($db, 'DELETE FROM cj_orders');
			mysqli_query($db, 'DELETE FROM cj_devices');
		} catch (Throwable $e) {
			$this->markTestSkipped('Keine Testdatenbank: ' . $e->getMessage());
		}
	}

	protected function tearDown(): void
	{
		if (!$this->db instanceof mysqli) {
			return;
		}
		$this->dropColumnIfExists('out_stock_time');
		$this->dropColumnIfExists('refund_time');
		mysqli_query($this->db, 'DELETE FROM cj_orders');
		mysqli_query($this->db, 'DELETE FROM cj_devices');
		mysqli_close($this->db);
	}

	public function testDryRunCountsWithoutWritingAndSecondApplyIsStable(): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$this->insertOrder('DEMO-TZ-UTC8', '2026-11-13 00:59:28', 'UTC+8', array(
			'order_no' => 'DEMO-TZ-UTC8',
			'create_time' => '2026-11-13 00:59:28',
			'time_zone_id' => 'UTC+8',
		));
		$this->insertOrder('DEMO-TZ-UTC1', '2026-11-14 21:52:56', 'UTC+01:00', array(
			'order_no' => 'DEMO-TZ-UTC1',
			'create_time' => '2026-11-14 21:52:56',
			'time_zone_id' => 'UTC+01:00',
		));
		$this->insertOrder('A261012145202000000099', '2026-10-12 07:52:03', 'UTC+01:00', array(
			'order_no' => 'A261012145202000000099',
			'create_time' => '2026-10-12 07:52:03',
			'time_zone_id' => 'UTC+01:00',
		));
		$this->insertDevice('9000000000000000771', '2026-11-15 08:15:00', array(
			'id' => '9000000000000000771',
			'heartTime' => '2026-11-15 08:15:00',
			'timeZoneId' => 'UTC+8',
		));
		$this->insertDevice('9000000000000000772', '2026-11-15 09:00:00', array(
			'id' => '9000000000000000772',
			'heartTime' => '2026-11-15 09:00:00',
		));

		$fix = new CoffeejiTimeFix($db);
		$dry = $fix->run(true);

		$this->assertTrue($dry['dry_run']);
		$this->assertSame(2, $dry['orders_changed']);
		$this->assertSame(1, $dry['orders_unchanged']);
		$this->assertSame(0, $dry['orders_skipped']);
		$this->assertSame(1, $dry['devices_changed']);
		$this->assertSame(1, $dry['devices_unchanged']);
		$summary = $fix->summary($dry);
		$this->assertStringContainsString('Trockenlauf', $summary);
		$this->assertStringContainsString('A261012145202000000099: 2026-10-12 07:52:03 -> 2026-10-12 08:52:03', $summary);
		$this->assertSame('2026-11-13 00:59:28', $this->orderTime('DEMO-TZ-UTC8'));
		$this->assertSame('2026-10-12 07:52:03', $this->orderTime('A261012145202000000099'));
		$this->assertSame('2026-11-15 08:15:00', $this->deviceTime('9000000000000000771'));
		$this->assertSame('2026-11-15 09:00:00', $this->deviceTime('9000000000000000772'));

		$applied = $fix->run(false);
		$this->assertFalse($applied['dry_run']);
		$this->assertSame(2, $applied['orders_changed']);
		$this->assertSame(1, $applied['devices_changed']);
		$this->assertSame('2026-11-12 17:59:28', $this->orderTime('DEMO-TZ-UTC8'));
		$this->assertSame('2026-11-14 21:52:56', $this->orderTime('DEMO-TZ-UTC1'));
		$this->assertSame('2026-10-12 08:52:03', $this->orderTime('A261012145202000000099'));
		$this->assertSame('2026-11-15 01:15:00', $this->deviceTime('9000000000000000771'));
		$this->assertSame('2026-11-15 09:00:00', $this->deviceTime('9000000000000000772'));
		$raw = json_decode($this->orderRaw('DEMO-TZ-UTC8'), true);
		$this->assertIsArray($raw);
		$this->assertSame('2026-11-13 00:59:28', $raw['source_create_time']);

		$again = $fix->run(false);
		$this->assertSame(0, $again['orders_changed']);
		$this->assertSame(3, $again['orders_unchanged']);
		$this->assertSame(0, $again['devices_changed']);
		$this->assertSame(2, $again['devices_unchanged']);
		$this->assertSame('2026-11-12 17:59:28', $this->orderTime('DEMO-TZ-UTC8'));
		$this->assertSame('2026-10-12 08:52:03', $this->orderTime('A261012145202000000099'));
		$this->assertSame('2026-11-15 01:15:00', $this->deviceTime('9000000000000000771'));
		$this->assertTrue(CoffeejiTimeFix::isDryRun(array('coffeeji_fix_times.php', '--dry-run')));
		$this->assertFalse(CoffeejiTimeFix::isDryRun(array('coffeeji_fix_times.php')));
	}

	public function testPlanOrderIsIdempotentFromLegacyRawJson(): void
	{
		$legacy = array(
			'create_time' => '2026-11-13 00:59:28',
			'time_zone_id' => 'UTC+8',
			'raw_json' => json_encode(array(
				'order_no' => 'DEMO-TZ-1',
				'create_time' => '2026-11-13 00:59:28',
				'time_zone_id' => 'UTC+8',
			), JSON_UNESCAPED_UNICODE),
		);
		$plan = CoffeejiTimeFix::planOrder($legacy, array('create_time'));
		$this->assertTrue($plan['changed']);
		$this->assertSame('2026-11-12 17:59:28', $plan['values']['create_time']);

		$stored = array(
			'create_time' => $plan['values']['create_time'],
			'time_zone_id' => 'UTC+8',
			'raw_json' => $plan['raw_json'],
		);
		$second = CoffeejiTimeFix::planOrder($stored, array('create_time'));
		$this->assertFalse($second['changed']);
		$this->assertSame('2026-11-12 17:59:28', $second['values']['create_time']);

		$fromColumn = CoffeejiTimeFix::planOrder(array(
			'create_time' => '2026-11-13 00:59:28',
			'time_zone_id' => 'UTC+8',
			'raw_json' => null,
		), array('create_time'));
		$this->assertTrue($fromColumn['changed']);
		$this->assertSame('2026-11-12 17:59:28', $fromColumn['values']['create_time']);
		$replay = CoffeejiTimeFix::planOrder(array(
			'create_time' => $fromColumn['values']['create_time'],
			'time_zone_id' => 'UTC+8',
			'raw_json' => $fromColumn['raw_json'],
		), array('create_time'));
		$this->assertFalse($replay['changed']);
	}

	public function testLegacySnakeCaseUsesCreateTimeAndDoesNotShiftTwice(): void
	{
		$orderNo = 'A261012145202000000099';
		$legacy = array(
			'order_no' => $orderNo,
			'create_time' => '2026-10-12 07:52:03',
			'time_zone_id' => 'UTC+01:00',
			'raw_json' => json_encode(array(
				'order_no' => $orderNo,
				'create_time' => '2026-10-12 07:52:03',
				'time_zone_id' => 'UTC+01:00',
			), JSON_UNESCAPED_UNICODE),
		);
		$plan = CoffeejiTimeFix::planOrder($legacy, array('create_time'));
		$this->assertTrue($plan['changed']);
		$this->assertFalse($plan['skipped']);
		$this->assertSame('2026-10-12 08:52:03', $plan['values']['create_time']);
		$raw = json_decode((string) $plan['raw_json'], true);
		$this->assertIsArray($raw);
		$this->assertSame('2026-10-12 07:52:03', $raw['create_time']);
		$this->assertSame('2026-10-12 07:52:03', $raw['source_create_time']);
		$this->assertArrayNotHasKey('createTime', $raw);

		$stored = array(
			'order_no' => $orderNo,
			'create_time' => $plan['values']['create_time'],
			'time_zone_id' => 'UTC+01:00',
			'raw_json' => $plan['raw_json'],
		);
		$second = CoffeejiTimeFix::planOrder($stored, array('create_time'));
		$this->assertFalse($second['changed']);
		$this->assertSame('2026-10-12 08:52:03', $second['values']['create_time']);

		$alreadyBerlin = array(
			'order_no' => $orderNo,
			'create_time' => '2026-10-12 08:52:03',
			'time_zone_id' => 'UTC+01:00',
			'raw_json' => json_encode(array(
				'order_no' => $orderNo,
				'create_time' => '2026-10-12 08:52:03',
				'time_zone_id' => 'UTC+01:00',
			), JSON_UNESCAPED_UNICODE),
		);
		$guarded = CoffeejiTimeFix::planOrder($alreadyBerlin, array('create_time'));
		$this->assertFalse($guarded['changed']);
		$this->assertSame('2026-10-12 08:52:03', $guarded['values']['create_time']);

		$mismatch = CoffeejiTimeFix::planOrder(array(
			'order_no' => $orderNo,
			'create_time' => '2026-10-12 12:00:00',
			'time_zone_id' => 'UTC+01:00',
			'raw_json' => json_encode(array(
				'order_no' => $orderNo,
				'create_time' => '2026-10-12 12:00:00',
				'time_zone_id' => 'UTC+01:00',
			), JSON_UNESCAPED_UNICODE),
		), array('create_time'));
		$this->assertFalse($mismatch['changed']);
		$this->assertTrue($mismatch['skipped']);
	}

	public function testPortalClockColumnsAreCorrectedWhenPresent(): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$this->assertTrue(mysqli_query($db, 'ALTER TABLE cj_orders ADD COLUMN out_stock_time DATETIME NULL'));
		$this->assertTrue(mysqli_query($db, 'ALTER TABLE cj_orders ADD COLUMN refund_time DATETIME NULL'));
		$this->insertOrder('DEMO-TZ-EXTRA', '2026-11-13 00:59:28', 'UTC+8', array(
			'createTime' => '2026-11-13 00:59:28',
			'timeZoneId' => 'UTC+8',
			'outStockTime' => '2026-11-13 01:10:00',
			'refundTime' => '2026-11-13 02:00:00',
		), '2026-11-13 01:10:00', '2026-11-13 02:00:00');

		$fix = new CoffeejiTimeFix($db);
		$dry = $fix->run(true);
		$this->assertSame(1, $dry['orders_changed']);
		$this->assertSame('2026-11-13 01:10:00', $this->columnTime('DEMO-TZ-EXTRA', 'out_stock_time'));

		$fix->run(false);
		$this->assertSame('2026-11-12 17:59:28', $this->orderTime('DEMO-TZ-EXTRA'));
		$this->assertSame('2026-11-12 18:10:00', $this->columnTime('DEMO-TZ-EXTRA', 'out_stock_time'));
		$this->assertSame('2026-11-12 19:00:00', $this->columnTime('DEMO-TZ-EXTRA', 'refund_time'));

		$again = $fix->run(false);
		$this->assertSame(0, $again['orders_changed']);
		$this->assertSame('2026-11-12 18:10:00', $this->columnTime('DEMO-TZ-EXTRA', 'out_stock_time'));
		$this->assertSame('2026-11-12 19:00:00', $this->columnTime('DEMO-TZ-EXTRA', 'refund_time'));
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	private function insertOrder(
		string $orderNo,
		string $createTime,
		string $zone,
		array $raw,
		?string $outStock = null,
		?string $refund = null
	): void {
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$json = json_encode($raw, JSON_UNESCAPED_UNICODE);
		$this->assertIsString($json);
		$hasExtra = $this->columnExists('out_stock_time') && $this->columnExists('refund_time');
		if ($hasExtra) {
			$stmt = $db->prepare(
				'INSERT INTO cj_orders (
					order_no, status, create_time, out_stock_time, refund_time, time_zone_id, raw_json, created_at, updated_at
				) VALUES (?, 2, ?, ?, ?, ?, ?, NOW(), NOW())'
			);
			$this->assertInstanceOf(mysqli_stmt::class, $stmt);
			$stmt->bind_param('ssssss', $orderNo, $createTime, $outStock, $refund, $zone, $json);
		} else {
			$stmt = $db->prepare(
				'INSERT INTO cj_orders (
					order_no, status, create_time, time_zone_id, raw_json, created_at, updated_at
				) VALUES (?, 2, ?, ?, ?, NOW(), NOW())'
			);
			$this->assertInstanceOf(mysqli_stmt::class, $stmt);
			$stmt->bind_param('ssss', $orderNo, $createTime, $zone, $json);
		}
		$this->assertTrue($stmt->execute());
		$stmt->close();
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	private function insertDevice(string $id, string $heart, array $raw): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$json = json_encode($raw, JSON_UNESCAPED_UNICODE);
		$this->assertIsString($json);
		$name = 'Musterautomat';
		$stmt = $db->prepare(
			'INSERT INTO cj_devices (id, name, heart_time, raw_json, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())'
		);
		$this->assertInstanceOf(mysqli_stmt::class, $stmt);
		$stmt->bind_param('ssss', $id, $name, $heart, $json);
		$this->assertTrue($stmt->execute());
		$stmt->close();
	}

	private function orderTime(string $orderNo): ?string
	{
		return $this->columnTime($orderNo, 'create_time');
	}

	private function columnTime(string $orderNo, string $column): ?string
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$this->assertContains($column, array('create_time', 'out_stock_time', 'refund_time'));
		$stmt = $db->prepare('SELECT ' . $column . ' AS value FROM cj_orders WHERE order_no = ?');
		$this->assertInstanceOf(mysqli_stmt::class, $stmt);
		$stmt->bind_param('s', $orderNo);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		$stmt->close();
		if (!is_array($row) || $row['value'] === null) {
			return null;
		}

		return (string) $row['value'];
	}

	private function orderRaw(string $orderNo): string
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$stmt = $db->prepare('SELECT raw_json FROM cj_orders WHERE order_no = ?');
		$this->assertInstanceOf(mysqli_stmt::class, $stmt);
		$stmt->bind_param('s', $orderNo);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		$stmt->close();
		$this->assertIsArray($row);

		return (string) $row['raw_json'];
	}

	private function deviceTime(string $id): ?string
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$stmt = $db->prepare('SELECT heart_time FROM cj_devices WHERE id = ?');
		$this->assertInstanceOf(mysqli_stmt::class, $stmt);
		$stmt->bind_param('s', $id);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		$stmt->close();
		if (!is_array($row) || $row['heart_time'] === null) {
			return null;
		}

		return (string) $row['heart_time'];
	}

	private function dropColumnIfExists(string $column): void
	{
		if (!in_array($column, array('out_stock_time', 'refund_time'), true) || !$this->columnExists($column)) {
			return;
		}
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		mysqli_query($db, 'ALTER TABLE cj_orders DROP COLUMN ' . $column);
	}

	private function columnExists(string $column): bool
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$stmt = $db->prepare(
			'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
		);
		$this->assertInstanceOf(mysqli_stmt::class, $stmt);
		$table = 'cj_orders';
		$stmt->bind_param('ss', $table, $column);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		$stmt->close();

		return is_array($row) && (int) $row['c'] === 1;
	}

	private function installSchema(): void
	{
		$db = $this->db;
		if (!$db instanceof mysqli) {
			return;
		}
		$check = mysqli_query($db, "SHOW TABLES LIKE 'cj_orders'");
		if ($check && mysqli_fetch_row($check)) {
			mysqli_free_result($check);
			return;
		}
		if ($check) {
			mysqli_free_result($check);
		}
		$sql = (string) file_get_contents(__DIR__ . '/../app/db/migrations/20261113_coffeeji.sql');
		if (mysqli_multi_query($db, $sql)) {
			do {
				if ($result = mysqli_store_result($db)) {
					mysqli_free_result($result);
				}
			} while (mysqli_more_results($db) && mysqli_next_result($db));
		}
	}
}
