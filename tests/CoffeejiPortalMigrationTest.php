<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiPortalMigrationTest extends TestCase
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
		$db = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
		if (!$db) {
			$this->markTestSkipped('Keine Testdatenbank: ' . mysqli_connect_error());
		}
		mysqli_set_charset($db, 'utf8mb4');
		$this->db = $db;
		$this->installBase();
	}

	protected function tearDown(): void
	{
		if (!$this->db instanceof mysqli) {
			return;
		}
		mysqli_query($this->db, "DELETE FROM cj_orders WHERE order_no LIKE 'PORTAL-%'");
		mysqli_query($this->db, "DELETE FROM cj_devices WHERE id IN ('9100000000000000101','9100000000000000102')");
		mysqli_close($this->db);
	}

	public function testMigrationIsIdempotentAndBackfillsRawJson(): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$this->runMigration($db);
		$this->runMigration($db);
		$this->assertTrue($this->columnExists('out_stock_status'));

		$raw = json_encode(array(
			'outStockStatus' => 2,
			'outStockTime' => '2026-11-14 21:53:10',
			'refundTime' => '2026-11-14 22:00:00',
			'failRemark' => 'Mahlwerk blockiert',
			'payWay' => 2,
		), JSON_UNESCAPED_UNICODE);
		$this->assertIsString($raw);
		$stmt = $db->prepare('INSERT INTO cj_orders (
			order_no, device_id, goods_name, price, pay_amt, status, create_time, raw_json, created_at, updated_at
		) VALUES (?, ?, ?, 1.50, 1.50, 2, ?, ?, NOW(), NOW())');
		$this->assertInstanceOf(mysqli_stmt::class, $stmt);
		$no = 'PORTAL-BACKFILL';
		$device = '9100000000000000101';
		$goods = 'Hausmischung';
		$created = '2026-11-14 21:52:56';
		$stmt->bind_param('sssss', $no, $device, $goods, $created, $raw);
		$stmt->execute();
		$stmt->close();

		$this->runMigration($db);
		$row = $this->one("SELECT pay_way, out_stock_status, out_stock_time, refund_time, fail_remark FROM cj_orders WHERE order_no = 'PORTAL-BACKFILL'");
		$this->assertSame('2', (string) $row['pay_way']);
		$this->assertSame('2', (string) $row['out_stock_status']);
		$this->assertSame('2026-11-14 21:53:10', (string) $row['out_stock_time']);
		$this->assertSame('2026-11-14 22:00:00', (string) $row['refund_time']);
		$this->assertSame('Mahlwerk blockiert', (string) $row['fail_remark']);

		mysqli_query($db, "UPDATE cj_orders SET out_stock_status = 9 WHERE order_no = 'PORTAL-BACKFILL'");
		$this->runMigration($db);
		$kept = $this->one("SELECT out_stock_status FROM cj_orders WHERE order_no = 'PORTAL-BACKFILL'");
		$this->assertSame('9', (string) $kept['out_stock_status']);
	}

	public function testUpsertWritesPortalFieldsAndQueryMatchesAggregation(): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$this->runMigration($db);
		mysqli_query($db, "INSERT INTO cj_devices (id, device_no, device_ext_no, name, created_at, updated_at)
			VALUES ('9100000000000000101', 'aaa111', 'H-101', 'Musterkiosk', NOW(), NOW())");
		mysqli_query($db, "INSERT INTO cj_devices (id, device_no, device_ext_no, name, created_at, updated_at)
			VALUES ('9100000000000000102', 'bbb222', 'H-102', 'Bahnhof', NOW(), NOW())");

		$repository = new CoffeejiRepository();
		$this->assertTrue($repository->orderDetailsReady());
		$orders = array(
			$this->api('PORTAL-1', '9100000000000000101', 'H-101', 'Musterkiosk', 'Hausmischung', 2, 2, '2.50', 'Cash', '2026-11-10 10:00:00', ''),
			$this->api('PORTAL-2', '9100000000000000101', 'H-101', 'Musterkiosk', 'Espresso', 1, 1, '1.50', 'Card', '2026-11-10 11:00:00', 'Becher leer'),
			$this->api('PORTAL-3', '9100000000000000102', 'H-102', 'Bahnhof', 'Hausmischung', 2, 2, '4.00', 'Free', '2026-11-11 09:00:00', ''),
			$this->api('PORTAL-4', '9100000000000000102', 'H-102', 'Bahnhof', 'Hausmischung', 6, 0, '9.00', 'Cash', '2026-11-11 12:00:00', ''),
			$this->api('PORTAL-%', '9100000000000000101', 'H-101', 'Musterkiosk', 'Hausmischung', 2, 2, '1.00', 'Cash', '2026-11-12 08:00:00', ''),
			$this->api('PORTAL-ALT', '9100000000000000101', 'H-101', 'Musterkiosk', 'Hausmischung', 2, 2, '3.00', 'Cash', '2026-10-01 08:00:00', ''),
		);
		$normalized = array();
		foreach ($orders as $order) {
			$normalized[] = CoffeejiOrder::normalize($order);
		}
		$first = $repository->upsertOrders($normalized);
		$this->assertSame(6, $first['insert']);
		$second = $repository->upsertOrders($normalized);
		$this->assertSame(6, $second['unchanged']);
		$normalized[1]['out_stock_status'] = 2;
		$third = $repository->upsertOrders(array($normalized[1]));
		$this->assertSame(1, $third['update']);

		$stored = $this->one("SELECT out_stock_status, fail_remark, pay_way_name FROM cj_orders WHERE order_no = 'PORTAL-2'");
		$this->assertSame('2', (string) $stored['out_stock_status']);
		$this->assertSame('Becher leer', (string) $stored['fail_remark']);

		$filter = CoffeejiPortal::orderFilter(array(
			'von' => '2026-11-01',
			'bis' => '2026-11-15',
			'pro' => '50',
			'bestellnummer' => 'PORTAL-',
		), new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('Europe/Berlin')));
		$query = new CoffeejiPortalQuery();
		$result = $query->searchOrders($filter);
		$this->assertSame(5, $result['total']);
		$this->assertSame(18.0, $result['amount']);
		$this->assertSame(9.0, $result['revenue']);
		$this->assertSame(4, $result['revenue_count']);
		$this->assertSame('aaa111', (string) $result['rows'][0]['machine_id']);

		$literal = CoffeejiPortal::orderFilter(array(
			'von' => '2026-11-01',
			'bis' => '2026-11-15',
			'bestellnummer' => 'PORTAL-%',
			'pro' => '10',
		), new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('Europe/Berlin')));
		$escaped = $query->searchOrders($literal);
		$this->assertSame(1, $escaped['total']);
		$this->assertSame('PORTAL-%', $escaped['rows'][0]['order_no']);

		$statsFilter = CoffeejiPortal::statsFilter(array(
			'von' => '2026-11-01',
			'bis' => '2026-11-15',
			'korn' => 'tag',
			'maschinennummer' => 'H-101',
			'bestellnummer' => 'PORTAL-',
		), new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('Europe/Berlin')));
		$stats = $query->statistics($statsFilter);
		$this->assertSame(5.0, $stats['summary']['revenue']);
		$this->assertSame(3, $stats['summary']['revenue_count']);
		$this->assertCount(1, $stats['machines']);
		$days = array();
		foreach ($stats['periods'] as $period) {
			$days[$period['key']] = $period['amount'];
		}
		$this->assertSame(4.0, $days['2026-11-10']);
		$this->assertSame(0.0, $days['2026-11-11']);

		$months = $query->statistics(CoffeejiPortal::statsFilter(array(
			'von' => '2026-10-01',
			'bis' => '2026-11-15',
			'korn' => 'monat',
			'bestellnummer' => 'PORTAL-',
		), new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('Europe/Berlin'))));
		$byMonth = array();
		foreach ($months['periods'] as $period) {
			$byMonth[$period['key']] = $period['amount'];
		}
		$this->assertSame(3.0, $byMonth['2026-10']);
		$this->assertSame(9.0, $byMonth['2026-11']);
	}

	private function installBase(): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$found = mysqli_query($db, "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "' AND table_name = 'cj_orders'");
		$this->assertInstanceOf(mysqli_result::class, $found);
		$row = mysqli_fetch_assoc($found);
		mysqli_free_result($found);
		if ((int) ($row['c'] ?? 0) === 1) {
			return;
		}
		$this->runFile($db, dirname(__DIR__) . '/app/db/migrations/20261113_coffeeji.sql');
	}

	private function runMigration(mysqli $db): void
	{
		$this->runFile($db, dirname(__DIR__) . '/app/db/migrations/20261115_coffeeji_orders_portal.sql');
	}

	private function runFile(mysqli $db, string $path): void
	{
		$sql = (string) file_get_contents($path);
		$parts = preg_split("/;\\s*\\n/", $sql) ?: array();
		foreach ($parts as $part) {
			$statement = trim($part);
			if ($statement === '') {
				continue;
			}
			$result = mysqli_query($db, $statement);
			if ($result === false) {
				$this->fail(mysqli_error($db) . "\n" . $statement);
			}
			if ($result instanceof mysqli_result) {
				mysqli_free_result($result);
			}
		}
	}

	private function columnExists(string $name): bool
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$stmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
		$this->assertInstanceOf(mysqli_stmt::class, $stmt);
		$schema = DB_NAME;
		$table = 'cj_orders';
		$stmt->bind_param('sss', $schema, $table, $name);
		$stmt->execute();
		$res = $stmt->get_result();
		$row = $res ? $res->fetch_assoc() : null;
		$stmt->close();

		return (int) ($row['c'] ?? 0) === 1;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function one(string $sql): array
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$result = mysqli_query($db, $sql);
		$this->assertInstanceOf(mysqli_result::class, $result);
		$row = mysqli_fetch_assoc($result);
		mysqli_free_result($result);
		$this->assertIsArray($row);

		return $row;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function api(
		string $no,
		string $deviceId,
		string $ext,
		string $name,
		string $goods,
		int $status,
		int $out,
		string $amount,
		string $pay,
		string $time,
		string $fail
	): array {
		return array(
			'orderNo' => $no,
			'deviceId' => $deviceId,
			'deviceExtNo' => $ext,
			'deviceName' => $name,
			'goodsName' => $goods,
			'price' => $amount,
			'payAmt' => $amount,
			'payWay' => 2,
			'payWayName' => $pay,
			'status' => $status,
			'outStockStatus' => $out,
			'outStockTime' => $status === 2 ? '2026-11-10 10:05:00' : '',
			'refundStatus' => '',
			'failRemark' => $fail,
			'createTime' => $time,
			'timeZoneId' => 'UTC+01:00',
		);
	}
}
