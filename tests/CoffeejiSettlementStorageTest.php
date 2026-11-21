<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiSettlementStorageTest extends TestCase
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
		if (DB_NAME !== 'hendricks_test') {
			$this->markTestSkipped('Abrechnungstests laufen nur auf hendricks_test.');
		}
		$db = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
		if (!$db) {
			$this->markTestSkipped('Keine Testdatenbank: ' . mysqli_connect_error());
		}
		mysqli_set_charset($db, 'utf8mb4');
		$this->db = $db;
		$this->ensureCoffeeji();
	}

	protected function tearDown(): void
	{
		if (!$this->db instanceof mysqli) {
			return;
		}
		mysqli_query($this->db, "DELETE li FROM invoice_list2 li INNER JOIN invoice2 i ON i.id = li.invoice_id WHERE i.created_by = 9906182 OR i.excel_date IN ('coffeeji', 'LEGACY', 'LEGACY-SETTLE')");
		mysqli_query($this->db, "DELETE FROM invoice2 WHERE excel_date IN ('coffeeji', 'LEGACY', 'LEGACY-SETTLE') OR created_by = 9906182");
		mysqli_query($this->db, "DELETE FROM cj_vat_products WHERE evidence LIKE 'SETTLE-%' OR goods_name LIKE 'SETTLE %'");
		mysqli_query($this->db, "DELETE FROM cj_orders WHERE order_no LIKE 'SETTLE-%'");
		mysqli_close($this->db);
	}

	public function testMigrationKeepsExistingTotalAndDefaultsSourceToExcel(): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		mysqli_query($db, 'DROP TABLE IF EXISTS invoice_list2');
		mysqli_query($db, 'DROP TABLE IF EXISTS invoice2');
		mysqli_query($db, 'DROP TABLE IF EXISTS cj_vat_products');
		$this->runPhpSchema($db);
		mysqli_query($db, "INSERT INTO invoice2 (start_date, end_date, excel_date, total_amount, created_at, created_by)
			VALUES ('2026-09-01', '2026-09-30', 'LEGACY-SETTLE', 12.34, '2026-10-01 08:00:00', 9906182)");
		$legacyId = (int) $db->insert_id;
		$this->runMigration($db);
		$this->runMigration($db);

		$row = $this->one('SELECT total_amount, source, locked_at FROM invoice2 WHERE id = ' . $legacyId);
		$this->assertSame('12.34', number_format((float) $row['total_amount'], 2, '.', ''));
		$this->assertSame('excel', (string) $row['source']);
		$this->assertNull($row['locked_at']);
		$this->assertTrue($this->columnExists('invoice_list2', 'vat_rate'));
		$this->assertTrue($this->columnExists('invoice_list2', 'order_no'));
		$again = $this->one('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = "' . DB_NAME . '" AND table_name = "cj_vat_products"');
		$this->assertSame('1', (string) $again['c']);
	}

	public function testSavedSettlementMatchesPreviewAndIgnoresLaterOrderChanges(): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$this->ensureInvoices();
		$this->runMigration($db);
		mysqli_query($db, "DELETE FROM cj_orders WHERE order_no LIKE 'SETTLE-%'");

		$store = new CoffeejiSettlementStore($db);
		$store->saveVatRule(array(
			'goods_name' => 'SETTLE Espresso',
			'rate' => 19,
			'milk_type' => 'keine',
			'valid_from' => '2026-01-01',
		));
		$latteId = $store->saveVatRule(array(
			'goods_name' => 'SETTLE Latte',
			'rate' => 7,
			'milk_type' => 'frischmilch',
			'milk_share' => 80,
			'evidence' => 'SETTLE-REZEPT',
			'valid_from' => '2026-11-01',
		));
		try {
			$store->saveVatRule(array(
				'goods_name' => 'SETTLE Latte',
				'rate' => 7,
				'milk_type' => 'pflanzlich',
				'milk_share' => 80,
				'evidence' => 'SETTLE-NEIN',
				'valid_from' => '2026-11-01',
			));
			$this->fail('7 % ohne tierische Milch darf nicht gespeichert werden.');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('7 %', $e->getMessage());
		}

		$this->insertOrder('SETTLE-1', '100', 'H-101', 'SETTLE Cappuccino', '2.50', '现金支付', 2, null, '2026-11-10 10:00:00');
		$this->insertOrder('SETTLE-2', '100', 'H-101', 'SETTLE Espresso', '1.50', '刷卡支付', 1, 1, '2026-11-10 11:00:00');
		$this->insertOrder('SETTLE-3', '100', 'H-101', 'SETTLE Espresso', '4.00', '免费', 2, 2, '2026-11-11 09:00:00');
		$this->insertOrder('SETTLE-4', '102', 'H-102', 'SETTLE Espresso', '3.00', '兑换订单', 2, 2, '2026-11-11 10:00:00');
		$this->insertOrder('SETTLE-5', '102', 'H-102', 'SETTLE Espresso', '9.00', '现金支付', 6, 0, '2026-11-11 12:00:00');
		$this->insertOrder('SETTLE-6', '102', 'H-102', 'SETTLE Latte', '1.00', '刷卡支付', 2, 2, '2026-11-16 08:00:00');
		$this->insertOrder('SETTLE-7', '100', 'H-101', 'SETTLE Espresso', '8.00', '现金支付', 2, 2, '2026-10-31 23:59:59');

		$preview = $store->preview('2026-11-01', '2026-11-30', 'The Hendricks Coffee');
		$this->assertSame('5.00', $preview['totals']['amount']);
		$this->assertSame(500, $preview['totals']['amount_cents']);
		$this->assertSame(3, $preview['totals']['count']);
		$this->assertSame('2.50', $preview['totals']['cash']);
		$this->assertSame('2.50', $preview['totals']['card']);
		$this->assertSame('12.00', $preview['totals']['portal_revenue']);
		$this->assertSame(1, $preview['totals']['open']);
		$this->assertSame(array('SETTLE Cappuccino'), $preview['unconfigured']);
		$this->assertSame('2026-11-01 00:00:00', $preview['from']->format('Y-m-d H:i:s'));
		$this->assertSame('2026-12-01 00:00:00', $preview['to']->format('Y-m-d H:i:s'));

		$invoiceId = $store->save($preview, 9906182);
		$saved = $store->load($invoiceId);
		$this->assertSame('coffeeji', (string) $saved['invoice']['source']);
		$this->assertNotEmpty($saved['invoice']['locked_at']);
		$this->assertSame('5.00', number_format((float) $saved['invoice']['total_amount'], 2, '.', ''));
		$this->assertSame('2.50', number_format((float) $saved['invoice']['cash_amount'], 2, '.', ''));
		$this->assertSame('2.50', number_format((float) $saved['invoice']['card_amount'], 2, '.', ''));
		$this->assertSame('The Hendricks Coffee', (string) $saved['invoice']['merchant']);
		$this->assertCount(3, $saved['lines']);
		$rates = array();
		foreach ($saved['lines'] as $line) {
			$rates[(string) $line['order_no']] = number_format((float) $line['vat_rate'], 2, '.', '');
			$this->assertSame((string) $line['order_no'], (string) $line['order_number']);
		}
		$this->assertSame('19.00', $rates['SETTLE-1']);
		$this->assertSame('19.00', $rates['SETTLE-2']);
		$this->assertSame('7.00', $rates['SETTLE-6']);

		$html = CoffeejiSettlementPdf::html($saved['invoice'], $saved['lines']);
		$this->assertStringContainsString('>7 %<', $html);
		$this->assertStringContainsString('>19 %<', $html);
		$this->assertStringContainsString('The Hendricks Coffee', $html);
		$this->assertStringStartsWith('%PDF', CoffeejiSettlementPdf::render($html));

		mysqli_query($db, "UPDATE cj_orders SET pay_amt = 99.00, status = 0 WHERE order_no = 'SETTLE-1'");
		mysqli_query($db, 'UPDATE cj_vat_products SET rate = 19.00 WHERE id = ' . (int) $latteId);
		$again = $store->load($invoiceId);
		$this->assertSame('5.00', number_format((float) $again['invoice']['total_amount'], 2, '.', ''));
		$againRates = array();
		foreach ($again['lines'] as $line) {
			$againRates[(string) $line['order_no']] = number_format((float) $line['amount'], 2, '.', '') . '@' . number_format((float) $line['vat_rate'], 2, '.', '');
		}
		$this->assertSame('2.50@19.00', $againRates['SETTLE-1']);
		$this->assertSame('1.00@7.00', $againRates['SETTLE-6']);

		$later = $store->preview('2026-11-01', '2026-11-30', 'The Hendricks Coffee');
		$this->assertNotSame($preview['totals']['amount'], $later['totals']['amount']);
		$this->assertSame('2.50', $later['totals']['amount']);
	}

	public function testLegacyExcelInvoiceRemainsAPdf(): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$this->ensureInvoices();
		$this->runMigration($db);
		mysqli_query($db, "INSERT INTO invoice2 (start_date, end_date, excel_date, total_amount, created_at, created_by, source)
			VALUES ('2026-10-01', '2026-10-31', 'LEGACY', 13.00, '2026-11-02 09:00:00', 9906182, 'excel')");
		$invoiceId = (int) $db->insert_id;
		$stmt = $db->prepare('INSERT INTO invoice_list2 (invoice_id, machine_no, machine_name, dealer, amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)');
		$this->assertInstanceOf(mysqli_stmt::class, $stmt);
		$machine = 'H-9';
		$name = 'Bahnhof';
		$dealer = 'Alt';
		$cash = '10.00';
		$card = '3.00';
		$cashName = 'cash';
		$cardName = 'card';
		$stmt->bind_param('isssss', $invoiceId, $machine, $name, $dealer, $cash, $cashName);
		$stmt->execute();
		$stmt->bind_param('isssss', $invoiceId, $machine, $name, $dealer, $card, $cardName);
		$stmt->execute();
		$stmt->close();

		$loaded = (new CoffeejiSettlementStore($db))->load($invoiceId);
		$html = CoffeejiSettlementPdf::html($loaded['invoice'], $loaded['lines']);
		$this->assertSame('excel', (string) $loaded['invoice']['source']);
		$this->assertStringContainsString('Bar</td><td>10,00 €', $html);
		$this->assertStringContainsString('Karte</td><td>3,00 €', $html);
		$this->assertStringContainsString('H-9', $html);
		$this->assertStringContainsString('MwSt (7%)', $html);
		$this->assertStringStartsWith('%PDF', CoffeejiSettlementPdf::render($html));
	}

	private function ensureCoffeeji(): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$found = mysqli_query($db, "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "' AND table_name = 'cj_orders'");
		$this->assertInstanceOf(mysqli_result::class, $found);
		$row = mysqli_fetch_assoc($found);
		mysqli_free_result($found);
		if ((int) ($row['c'] ?? 0) !== 1) {
			$this->runFile($db, dirname(__DIR__) . '/app/db/migrations/20261113_coffeeji.sql');
		}
		$this->runFile($db, dirname(__DIR__) . '/app/db/migrations/20261115_coffeeji_orders_portal.sql');
	}

	private function ensureInvoices(): void
	{
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		if (!$this->tableExists('invoice2')) {
			$this->runPhpSchema($db);
		}
	}

	private function runMigration(mysqli $db): void
	{
		$this->runFile($db, dirname(__DIR__) . '/app/db/migrations/20261121_abrechnung_coffeeji.sql');
	}

	private function runPhpSchema(mysqli $db): void
	{
		require_once dirname(__DIR__) . '/tests/schema/invoice_base.php';
		$this->runSql($db, invoice_base_sql());
	}

	private function runFile(mysqli $db, string $path): void
	{
		$this->runSql($db, (string) file_get_contents($path));
	}

	private function runSql(mysqli $db, string $sql): void
	{
		$parts = preg_split("/;\\s*\\n/", $sql) ?: array();
		foreach ($parts as $part) {
			$statement = trim($part);
			if ($statement === '' || !str_contains($statement, ' ')) {
				continue;
			}
			$result = mysqli_query($db, $statement);
			if ($result === false) {
				$this->fail(mysqli_error($db) . "\n" . substr($statement, 0, 240));
			}
			if ($result instanceof mysqli_result) {
				mysqli_free_result($result);
			}
		}
	}

	private function insertOrder(
		string $no,
		string $device,
		string $ext,
		string $goods,
		string $amount,
		string $pay,
		int $status,
		?int $out,
		string $created
	): void {
		$db = $this->db;
		$this->assertInstanceOf(mysqli::class, $db);
		$stmt = $db->prepare('INSERT INTO cj_orders (
			order_no, device_id, device_ext_no, device_name, goods_name, price, pay_amt, pay_way_name,
			status, out_stock_status, create_time, created_at, updated_at
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
		$this->assertInstanceOf(mysqli_stmt::class, $stmt);
		$name = 'Standort ' . $ext;
		$stmt->bind_param('ssssssssiis', $no, $device, $ext, $name, $goods, $amount, $amount, $pay, $status, $out, $created);
		$this->assertTrue($stmt->execute(), $stmt->error);
		$stmt->close();
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

	private function columnExists(string $table, string $column): bool
	{
		$row = $this->one('SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = "' . DB_NAME . '" AND TABLE_NAME = "' . $table . '" AND COLUMN_NAME = "' . $column . '"');

		return (int) $row['c'] === 1;
	}

	private function tableExists(string $table): bool
	{
		$row = $this->one('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = "' . DB_NAME . '" AND table_name = "' . $table . '"');

		return (int) $row['c'] === 1;
	}
}
