<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiWebhookStorageTest extends TestCase
{
	private const TOKEN = '0123456789abcdef0123456789abcdef';
	private const SECRET = 'darf-nicht-gespeichert-werden';

	private ?CoffeejiRepository $repository = null;
	private string $logFile = '';

	protected function setUp(): void
	{
		if (!defined('DB_HOSTNAME')) {
			define('DB_HOSTNAME', getenv('DB_HOSTNAME') ?: '127.0.0.1');
			define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
			define('DB_PASSWORD', getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '');
			define('DB_NAME', getenv('DB_NAME') ?: 'hendricks_test');
		}
		$this->logFile = sys_get_temp_dir() . '/cj-webhook-storage-' . bin2hex(random_bytes(4)) . '.log';

		try {
			$this->installSchemas();
			$repository = new CoffeejiRepository();
			if (!$repository->tablesReady() || !$this->tableReady()) {
				$this->markTestSkipped('Coffeeji-Tabellen sind nicht vorhanden.');
			}
			$this->repository = $repository;
			$this->cleanup();
		} catch (Throwable $e) {
			$this->markTestSkipped('Keine Testdatenbank: ' . $e->getMessage());
		}
	}

	protected function tearDown(): void
	{
		if ($this->repository instanceof CoffeejiRepository) {
			try {
				$this->cleanup();
			} catch (Throwable $e) {
			}
		}
		if ($this->logFile !== '' && is_file($this->logFile)) {
			unlink($this->logFile);
		}
	}

	public function testDeviceFaultMaterialAlarmAndOrderPush(): void
	{
		$repository = $this->repository();
		$repository->upsertDevices(array(
			CoffeejiDevice::normalize(array(
				'id' => '9000000000000000201',
				'deviceNo' => 'WHTESTFAULT00001',
				'deviceExtNo' => 'DEMO-WH-1',
				'name' => 'Bleibt',
				'status' => 1,
				'deviceRunStatus' => 1,
				'scarceStatus' => 2,
				'scarceRemark' => 'vorher',
			)),
			CoffeejiDevice::normalize(array(
				'id' => '9000000000000000202',
				'deviceNo' => 'WHTESTMAT0000001',
				'name' => 'Mühle',
				'status' => 1,
				'deviceRunStatus' => 1,
				'scarceStatus' => 1,
			)),
		));
		$repository->upsertOrders(array($this->existingOrder('TEST-WH-KEEP', '9000000000000000201')));

		$fault = $this->post($this->fault('9000000000000000201'));
		$material = $this->post(array(
			'deviceId' => '9000000000000000202',
			'deviceNo' => 'WHTESTMAT0000001',
			'deviceScarceStatus' => 3,
			'scarceRemark' => 'MAT1 已到达缺货值',
			'timestamp' => 1700000000000,
			'devicePwd' => self::SECRET,
		));
		$order = $this->post(array(
			'deviceId' => '9000000000000000201',
			'deviceNo' => 'WHTESTFAULT00001',
			'orderNo' => 'TEST-WH-KEEP',
			'orderStatus' => 7,
			'payAmount' => 2.5,
			'refundAmount' => 2.5,
			'timeStamp' => 1700000000000,
			'devicePwd' => self::SECRET,
			'extra' => array('devicePwd' => 'auch-nicht'),
		));

		foreach (array($fault, $material, $order) as $result) {
			$this->assertSame(200, $result->status);
			$this->assertSame(CoffeejiWebhook::SUCCESS_BODY, $result->body);
		}

		$faultRow = $this->device('9000000000000000201');
		$this->assertSame('Bleibt', $faultRow['name']);
		$this->assertSame(2, (int) $faultRow['device_run_status']);
		$this->assertSame('E0: Becherspender', $faultRow['exception_remark']);
		$this->assertSame(2, (int) $faultRow['scarce_status']);
		$this->assertSame('vorher', $faultRow['scarce_remark']);

		$materialRow = $this->device('9000000000000000202');
		$this->assertSame('Mühle', $materialRow['name']);
		$this->assertSame(1, (int) $materialRow['device_run_status']);
		$this->assertSame(3, (int) $materialRow['scarce_status']);
		$this->assertSame('MAT1 已到达缺货值', $materialRow['scarce_remark']);

		$orderRow = $this->order('TEST-WH-KEEP');
		$this->assertSame(0, (int) $orderRow['status']);
		$this->assertSame(1, (int) $orderRow['refund_status']);
		$this->assertSame('Probegetränk', $orderRow['goods_name']);
		$this->assertSame('2.50', number_format((float) $orderRow['pay_amt'], 2, '.', ''));
		$this->assertStringContainsString('"orderStatus":7', (string) $orderRow['raw_json']);
		$this->assertStringNotContainsString(self::SECRET, (string) $orderRow['raw_json']);
		$this->assertStringNotContainsString('devicePwd', (string) $orderRow['raw_json']);

		$events = $this->events();
		$this->assertCount(3, $events);
		$types = array();
		foreach ($events as $event) {
			$types[] = $event['event_type'];
			$this->assertSame('accepted', $event['processed_status']);
			$this->assertSame(64, strlen((string) $event['payload_hash']));
			$this->assertNotFalse(DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $event['received_at'], new DateTimeZone('UTC')));
			$received = new DateTimeImmutable((string) $event['received_at'], new DateTimeZone('UTC'));
			$this->assertLessThan(180, abs($received->getTimestamp() - time()));
			$this->assertStringNotContainsString(self::SECRET, (string) $event['payload_json']);
			$this->assertStringNotContainsString('devicePwd', (string) $event['payload_json']);
			$this->assertStringNotContainsString('auch-nicht', (string) $event['payload_json']);
		}
		sort($types);
		$this->assertSame(array('device_fault', 'material_alarm', 'order'), $types);

		$alerts = $repository->latestWebhookAlerts();
		$this->assertArrayHasKey('9000000000000000201', $alerts);
		$this->assertArrayHasKey('device_fault', $alerts['9000000000000000201']);
		$this->assertSame(
			$alerts['9000000000000000201']['device_fault'],
			CoffeejiReadModel::pickWebhookAt($faultRow, $alerts['9000000000000000201'])
		);
		$log = (string) file_get_contents($this->logFile);
		$this->assertStringNotContainsString(self::SECRET, $log);
		$this->assertStringNotContainsString(self::TOKEN, $log);
	}

	public function testDuplicateIsAcknowledgedWithoutApplyingAgain(): void
	{
		$this->post($this->fault('9000000000000000204'));
		$mysqli = $this->mysqli();
		mysqli_query($mysqli, "UPDATE cj_devices SET device_run_status = 1, exception_remark = 'manuell' WHERE id = '9000000000000000204'");
		mysqli_close($mysqli);

		$again = $this->post($this->fault('9000000000000000204'));
		$this->assertSame(CoffeejiWebhook::SUCCESS_BODY, $again->body);

		$device = $this->device('9000000000000000204');
		$this->assertSame(1, (int) $device['device_run_status']);
		$this->assertSame('manuell', $device['exception_remark']);

		$events = $this->events();
		$this->assertCount(2, $events);
		$statuses = array($events[0]['processed_status'], $events[1]['processed_status']);
		sort($statuses);
		$this->assertSame(array('accepted', 'duplicate'), $statuses);
	}

	public function testNewOrderStubAndLaterStatusStayIdempotent(): void
	{
		$first = $this->post(array(
			'deviceId' => '9000000000000000203',
			'deviceNo' => 'WHTESTORDER00001',
			'orderNo' => 'TEST-WH-NEW',
			'orderStatus' => 1,
			'timeStamp' => '2026-11-14 15:00:00',
			'devicePwd' => self::SECRET,
		));
		$second = $this->post(array(
			'deviceId' => '9000000000000000203',
			'deviceNo' => 'WHTESTORDER00001',
			'orderNo' => 'TEST-WH-NEW',
			'orderStatus' => 2,
			'payAmount' => '3.20',
			'timestamp' => 1700000000000,
		));

		$this->assertSame(CoffeejiWebhook::SUCCESS_BODY, $first->body);
		$this->assertSame(CoffeejiWebhook::SUCCESS_BODY, $second->body);
		$row = $this->order('TEST-WH-NEW');
		$this->assertSame(2, (int) $row['status']);
		$this->assertSame('3.20', number_format((float) $row['pay_amt'], 2, '.', ''));
		$this->assertSame('', (string) $row['goods_name']);
		$this->assertSame('2026-11-14 08:00:00', $row['create_time']);
		$this->assertNull($row['refund_status']);
	}

	public function testWebhookOrderKeepsPortalColumnsAndWritesThemWhenPresent(): void
	{
		$this->runPortalMigration();
		$repository = new CoffeejiRepository();
		$this->assertTrue($repository->orderDetailsReady());
		$this->repository = $repository;

		$mysqli = $this->mysqli();
		$stmt = $mysqli->prepare('INSERT INTO cj_orders (
			order_no, device_id, goods_name, price, pay_amt, pay_way_name, pay_way, status,
			refund_status, out_stock_status, create_time, out_stock_time, refund_time, fail_remark,
			raw_json, created_at, updated_at
		) VALUES (?, ?, ?, 4.00, 4.00, ?, ?, 2, NULL, 2, ?, ?, NULL, ?, ?, NOW(), NOW())');
		$this->assertInstanceOf(mysqli_stmt::class, $stmt);
		$no = 'TEST-WH-PORTAL';
		$device = '9000000000000000206';
		$goods = 'Hausmischung';
		$wayName = 'Karte';
		$payWay = '3';
		$created = '2026-11-14 10:00:00';
		$outTime = '2026-11-14 10:05:00';
		$fail = 'bleibt';
		$raw = json_encode(array(
			'order_no' => $no,
			'goods_name' => $goods,
			'pay_way' => 3,
			'out_stock_status' => 2,
			'out_stock_time' => $outTime,
			'fail_remark' => $fail,
		), JSON_UNESCAPED_UNICODE);
		$this->assertIsString($raw);
		$stmt->bind_param('sssssssss', $no, $device, $goods, $wayName, $payWay, $created, $outTime, $fail, $raw);
		$this->assertTrue($stmt->execute());
		$stmt->close();
		mysqli_close($mysqli);

		$statusOnly = $this->post(array(
			'deviceId' => $device,
			'deviceNo' => 'WHTESTPORTAL0001',
			'orderNo' => $no,
			'orderStatus' => 1,
			'payAmount' => 4.5,
			'timeStamp' => 1700000000000,
		));
		$this->assertSame(CoffeejiWebhook::SUCCESS_BODY, $statusOnly->body);
		$kept = $this->portalOrder($no);
		$this->assertSame(1, (int) $kept['status']);
		$this->assertSame('4.50', number_format((float) $kept['pay_amt'], 2, '.', ''));
		$this->assertSame('Hausmischung', $kept['goods_name']);
		$this->assertSame('3', (string) $kept['pay_way']);
		$this->assertSame('2', (string) $kept['out_stock_status']);
		$this->assertSame($outTime, (string) $kept['out_stock_time']);
		$this->assertSame($fail, (string) $kept['fail_remark']);
		$this->assertStringContainsString('"orderStatus":1', (string) $kept['raw_json']);
		$this->assertStringContainsString('"out_stock_status":2', (string) $kept['raw_json']);

		$createdNew = $this->post(array(
			'deviceId' => $device,
			'deviceNo' => 'WHTESTPORTAL0001',
			'orderNo' => 'TEST-WH-PORTAL-NEW',
			'orderStatus' => 1,
			'payAmount' => 2.2,
			'payWay' => 2,
			'outStockStatus' => 1,
			'outStockTime' => '2026-11-14 21:53:10',
			'refundTime' => '2026-11-14 22:00:00',
			'failRemark' => 'Becher leer',
			'timeStamp' => 1700000000000,
		));
		$this->assertSame(CoffeejiWebhook::SUCCESS_BODY, $createdNew->body);
		$fresh = $this->portalOrder('TEST-WH-PORTAL-NEW');
		$this->assertSame(1, (int) $fresh['status']);
		$this->assertSame('2', (string) $fresh['pay_way']);
		$this->assertSame('1', (string) $fresh['out_stock_status']);
		$this->assertSame('2026-11-14 21:53:10', (string) $fresh['out_stock_time']);
		$this->assertSame('2026-11-14 22:00:00', (string) $fresh['refund_time']);
		$this->assertSame('Becher leer', (string) $fresh['fail_remark']);

		$later = $this->post(array(
			'deviceId' => $device,
			'orderNo' => 'TEST-WH-PORTAL-NEW',
			'orderStatus' => 2,
			'timeStamp' => 1700000001000,
		));
		$this->assertSame(CoffeejiWebhook::SUCCESS_BODY, $later->body);
		$after = $this->portalOrder('TEST-WH-PORTAL-NEW');
		$this->assertSame(2, (int) $after['status']);
		$this->assertSame('2', (string) $after['pay_way']);
		$this->assertSame('1', (string) $after['out_stock_status']);
		$this->assertSame('Becher leer', (string) $after['fail_remark']);
	}

	public function testInvalidBodyIsStoredWithoutSecrets(): void
	{
		$result = $this->postRaw('kein-json {"devicePwd":"' . self::SECRET . '"} token=' . self::TOKEN);
		$this->assertSame(CoffeejiWebhook::SUCCESS_BODY, $result->body);

		$events = $this->events();
		$this->assertCount(1, $events);
		$this->assertSame('invalid', $events[0]['event_type']);
		$this->assertSame('error', $events[0]['processed_status']);
		$this->assertStringNotContainsString(self::SECRET, (string) $events[0]['payload_json']);
		$this->assertStringNotContainsString(self::TOKEN, (string) $events[0]['payload_json']);
		$log = (string) file_get_contents($this->logFile);
		$this->assertStringNotContainsString(self::SECRET, $log);
		$this->assertStringNotContainsString(self::TOKEN, $log);
	}

	public function testApplyFailureStillStoresAndAcknowledges(): void
	{
		$body = $this->encode($this->fault('9000000000000000205'));
		$webhook = new CoffeejiWebhook(
			new CoffeejiWebhookConfig(self::TOKEN, array(), 65536),
			new CoffeejiWebhookFailingRepository(),
			new CoffeejiLogger($this->logFile)
		);
		$result = $webhook->handle(
			'POST',
			$body,
			array('token' => self::TOKEN),
			array('REMOTE_ADDR' => '203.0.113.10', 'CONTENT_LENGTH' => strlen($body))
		);

		$this->assertSame(200, $result->status);
		$this->assertSame(CoffeejiWebhook::SUCCESS_BODY, $result->body);
		$events = $this->events();
		$this->assertCount(1, $events);
		$this->assertSame('error', $events[0]['processed_status']);
		$this->assertStringNotContainsString(self::SECRET, (string) $events[0]['payload_json']);
		$this->assertStringNotContainsString('devicePwd', (string) $events[0]['payload_json']);
		$found = $this->mysqli();
		$device = mysqli_query($found, "SELECT id FROM cj_devices WHERE id = '9000000000000000205'");
		$this->assertInstanceOf(mysqli_result::class, $device);
		$this->assertSame(0, $device->num_rows);
		mysqli_close($found);
		$log = (string) file_get_contents($this->logFile);
		$this->assertStringContainsString('Übernahme fehlgeschlagen', $log);
		$this->assertStringNotContainsString(self::SECRET, $log);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function post(array $payload): CoffeejiWebhookResult
	{
		return $this->postRaw($this->encode($payload));
	}

	private function postRaw(string $body): CoffeejiWebhookResult
	{
		$webhook = new CoffeejiWebhook(
			new CoffeejiWebhookConfig(self::TOKEN, array('203.0.113.10'), 65536),
			$this->repository(),
			new CoffeejiLogger($this->logFile)
		);

		return $webhook->handle(
			'POST',
			$body,
			array('token' => self::TOKEN),
			array('REMOTE_ADDR' => '203.0.113.10', 'CONTENT_LENGTH' => strlen($body))
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fault(string $id): array
	{
		return array(
			'deviceId' => $id,
			'deviceNo' => 'WHTESTFAULT00001',
			'deviceRunStatus' => 2,
			'exceptionRemark' => 'E0: Becherspender',
			'timestamp' => 1700000000000,
			'devicePwd' => self::SECRET,
			'nested' => array('devicePwd' => 'auch-nicht'),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function existingOrder(string $orderNo, string $deviceId): array
	{
		return array(
			'order_no' => $orderNo,
			'device_id' => $deviceId,
			'device_ext_no' => 'DEMO-WH-1',
			'device_name' => 'Bleibt',
			'goods_name' => 'Probegetränk',
			'price' => '1.80',
			'pay_amt' => '1.80',
			'pay_way_name' => 'Bargeld',
			'status' => 2,
			'refund_status' => null,
			'create_time' => '2026-11-14 10:00:00',
			'time_zone_id' => 'UTC+01:00',
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function encode(array $payload): string
	{
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE);
		$this->assertNotFalse($json);

		return $json;
	}

	private function repository(): CoffeejiRepository
	{
		$this->assertInstanceOf(CoffeejiRepository::class, $this->repository);

		return $this->repository;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function device(string $id): array
	{
		$rows = $this->rows("SELECT id, name, device_run_status, exception_remark, scarce_status, scarce_remark FROM cj_devices WHERE id = '" . $id . "'");
		$this->assertArrayHasKey(0, $rows);

		return $rows[0];
	}

	/**
	 * @return array<string, mixed>
	 */
	/**
	 * @return array<string, mixed>
	 */
	private function portalOrder(string $orderNo): array
	{
		$rows = $this->rows(
			"SELECT order_no, goods_name, pay_amt, status, pay_way, out_stock_status, out_stock_time, refund_time, fail_remark, raw_json
			FROM cj_orders WHERE order_no = '" . $orderNo . "'"
		);
		$this->assertArrayHasKey(0, $rows);

		return $rows[0];
	}

	private function runPortalMigration(): void
	{
		$mysqli = $this->mysqli();
		$sql = (string) file_get_contents(dirname(__DIR__) . '/app/db/migrations/20261115_coffeeji_orders_portal.sql');
		$parts = preg_split("/;\\s*\\n/", $sql) ?: array();
		foreach ($parts as $part) {
			$statement = trim($part);
			if ($statement === '') {
				continue;
			}
			$result = mysqli_query($mysqli, $statement);
			$this->assertNotFalse($result, mysqli_error($mysqli));
			if ($result instanceof mysqli_result) {
				mysqli_free_result($result);
			}
		}
		mysqli_close($mysqli);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function order(string $orderNo): array
	{
		$rows = $this->rows("SELECT order_no, goods_name, pay_amt, status, refund_status, create_time, raw_json FROM cj_orders WHERE order_no = '" . $orderNo . "'");
		$this->assertArrayHasKey(0, $rows);

		return $rows[0];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function events(): array
	{
		return $this->rows('SELECT event_type, device_id, order_no, payload_json, payload_hash, received_at, processed_status FROM cj_webhook_events ORDER BY id ASC');
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function rows(string $sql): array
	{
		$mysqli = $this->mysqli();
		$result = mysqli_query($mysqli, $sql);
		$this->assertInstanceOf(mysqli_result::class, $result);
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		mysqli_close($mysqli);

		return $rows;
	}

	private function tableReady(): bool
	{
		$mysqli = $this->mysqli();
		$result = mysqli_query($mysqli, "SHOW TABLES LIKE 'cj_webhook_events'");
		$ready = $result instanceof mysqli_result && $result->num_rows === 1;
		mysqli_close($mysqli);

		return $ready;
	}

	private function installSchemas(): void
	{
		$mysqli = $this->mysqli();
		foreach (array('20261113_coffeeji.sql', '20261114_coffeeji_webhook.sql') as $file) {
			$sql = (string) file_get_contents(dirname(__DIR__) . '/app/db/migrations/' . $file);
			if (!mysqli_multi_query($mysqli, $sql)) {
				mysqli_close($mysqli);

				return;
			}
			do {
				if ($result = mysqli_store_result($mysqli)) {
					mysqli_free_result($result);
				}
			} while (mysqli_more_results($mysqli) && mysqli_next_result($mysqli));
		}
		mysqli_close($mysqli);
	}

	private function cleanup(): void
	{
		$mysqli = $this->mysqli();
		mysqli_query($mysqli, "DELETE FROM cj_webhook_events WHERE device_id LIKE '90000000000000002%' OR order_no LIKE 'TEST-WH-%' OR device_no LIKE 'WHTEST%' OR event_type = 'invalid'");
		mysqli_query($mysqli, "DELETE FROM cj_orders WHERE order_no LIKE 'TEST-WH-%'");
		mysqli_query($mysqli, "DELETE FROM cj_devices WHERE id LIKE '90000000000000002%'");
		mysqli_close($mysqli);
	}

	private function mysqli(): mysqli
	{
		$mysqli = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
		$this->assertInstanceOf(mysqli::class, $mysqli);
		mysqli_set_charset($mysqli, 'utf8mb4');

		return $mysqli;
	}
}

class CoffeejiWebhookFailingRepository extends CoffeejiRepository
{
	/**
	 * @param array<string, mixed> $fields
	 */
	public function applyWebhookDevice(string $deviceId, string $deviceNo, array $fields): void
	{
		throw new CoffeejiException('Übernahme fehlgeschlagen.');
	}
}
