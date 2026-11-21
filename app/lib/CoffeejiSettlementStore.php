<?php

declare(strict_types=1);

/**
 * Speichert die Vorschau als unveränderliche Abrechnung.
 * invoice2 ist der Kopf, invoice_list2 die einzelnen Bestellungen.
 */
class CoffeejiSettlementStore
{
	private ?mysqli $db;

	private bool $ownsConnection = false;

	public function __construct(?mysqli $db = null)
	{
		$this->db = $db;
	}

	public function ready(): bool
	{
		return $this->columnExists('invoice2', 'source')
			&& $this->columnExists('invoice2', 'locked_at')
			&& $this->columnExists('invoice2', 'vat_breakdown')
			&& $this->columnExists('invoice_list2', 'vat_rate')
			&& $this->columnExists('invoice_list2', 'order_no')
			&& $this->tableExists('cj_vat_products');
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function vatRules(): array
	{
		if (!$this->tableExists('cj_vat_products')) {
			return array();
		}
		$rows = $this->fetch(
			'SELECT id, goods_name, product_id, rate, milk_share, milk_type, evidence, hot_water, valid_from
			FROM cj_vat_products ORDER BY valid_from ASC, id ASC',
			'',
			array()
		);
		$rules = array();
		foreach ($rows as $row) {
			$share = $row['milk_share'];
			$rules[] = array(
				'id' => (int) $row['id'],
				'goods_name' => $row['goods_name'],
				'product_id' => $row['product_id'],
				'rate' => (int) round((float) $row['rate']),
				'milk_share' => $share === null || $share === '' ? null : (float) $share,
				'milk_type' => (string) $row['milk_type'],
				'evidence' => $row['evidence'],
				'hot_water' => (int) $row['hot_water'] === 1,
				'valid_from' => substr((string) $row['valid_from'], 0, 10),
			);
		}

		return $rules;
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	public function saveVatRule(array $rule): int
	{
		if (!$this->tableExists('cj_vat_products')) {
			throw new CoffeejiException('Die Tabelle cj_vat_products fehlt. Migration 20261121_abrechnung_coffeeji.sql einspielen.');
		}
		$normalized = CoffeejiVat::normalize($rule);
		$this->execute(
			'INSERT INTO cj_vat_products (goods_name, product_id, rate, milk_share, milk_type, evidence, hot_water, valid_from, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
			'ssssssiss',
			array(
				$normalized['goods_name'],
				$normalized['product_id'],
				$normalized['rate'] . '.00',
				$normalized['milk_share'] === null ? null : number_format($normalized['milk_share'], 2, '.', ''),
				$normalized['milk_type'],
				$normalized['evidence'],
				$normalized['hot_water'] ? 1 : 0,
				$normalized['valid_from'],
				(new DateTimeImmutable('now', CoffeejiTime::zone()))->format('Y-m-d H:i:s'),
			)
		);

		return (int) $this->db()->insert_id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function preview(string $start, string $end, string $merchant): array
	{
		$period = CoffeejiSettlement::period($start, $end);
		$orders = (new CoffeejiPortalQuery())->ordersBetween($period['from'], $period['to']);
		$built = CoffeejiSettlement::build($orders, $period['from'], $period['to'], $this->vatRules(), $merchant);
		$built['start_date'] = $period['start'];
		$built['end_date'] = $period['end'];
		$built['existing'] = $this->existing($period['start'], $period['end']);

		return $built;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function existing(string $start, string $end): array
	{
		$source = $this->columnExists('invoice2', 'source') ? 'source, ' : '';

		return $this->fetch(
			'SELECT id, ' . $source . 'start_date, end_date, total_amount FROM invoice2
			WHERE deleted_at IS NULL AND start_date = ? AND end_date = ? ORDER BY id ASC',
			'ss',
			array($start, $end)
		);
	}

	/**
	 * @param array<string, mixed> $preview
	 */
	public function save(array $preview, int $userId): int
	{
		if (!$this->ready()) {
			throw new CoffeejiException('Die Abrechnungstabellen sind noch nicht erweitert. Migration 20261121_abrechnung_coffeeji.sql einspielen.');
		}
		$totals = $preview['totals'];
		$vat = $this->vatIndex($preview['vat'] ?? array());
		$now = (new DateTimeImmutable('now', CoffeejiTime::zone()))->format('Y-m-d H:i:s');
		$breakdown = array();
		foreach ($preview['vat'] as $row) {
			$breakdown[] = array(
				'rate' => (int) $row['rate'],
				'net' => (string) $row['net'],
				'tax' => (string) $row['tax'],
				'gross' => (string) $row['gross'],
			);
		}
		$json = json_encode($breakdown, JSON_UNESCAPED_UNICODE);
		if ($json === false) {
			throw new CoffeejiException('Die Steueraufstellung konnte nicht gespeichert werden.');
		}
		$db = $this->db();
		$db->begin_transaction();
		try {
			$this->execute(
				'INSERT INTO invoice2 (
					start_date, end_date, excel_date, total_amount, created_at, created_by,
					source, order_count, cash_amount, card_amount, other_amount, open_count, merchant,
					vat_7_net, vat_7_tax, vat_7_gross, vat_19_net, vat_19_tax, vat_19_gross,
					vat_breakdown, locked_at
				) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				'sssssisisssisssssssss',
				array(
					(string) $preview['start_date'],
					(string) $preview['end_date'],
					'coffeeji',
					(string) $totals['amount'],
					$now,
					$userId,
					'coffeeji',
					(int) $totals['count'],
					(string) $totals['cash'],
					(string) $totals['card'],
					(string) $totals['other'],
					(int) $totals['open'],
					(string) $preview['merchant'],
					$vat[7]['net'],
					$vat[7]['tax'],
					$vat[7]['gross'],
					$vat[19]['net'],
					$vat[19]['tax'],
					$vat[19]['gross'],
					$json,
					$now,
				)
			);
			$invoiceId = (int) $db->insert_id;
			foreach ($preview['lines'] as $line) {
				$this->execute(
					'INSERT INTO invoice_list2 (
						invoice_id, machine_no, order_number, order_no, machine_name, dealer, goods_name,
						amount, payment_method, order_status, ship_status, refund_status,
						order_time, ship_time, refund_time, vat_rate
					) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
					'isssssssssssssss',
					array(
						$invoiceId,
						(string) $line['machine_no'],
						(string) $line['order_no'],
						(string) $line['order_no'],
						(string) $line['machine_name'],
						(string) $line['dealer'],
						(string) $line['goods_name'],
						(string) $line['amount'],
						(string) $line['pay_way_name'],
						(string) $line['order_status'],
						(string) $line['ship_status'],
						(string) $line['refund_status'],
						$line['order_time'] !== '' ? (string) $line['order_time'] : null,
						$line['ship_time'] !== '' ? (string) $line['ship_time'] : null,
						$line['refund_time'] !== '' ? (string) $line['refund_time'] : null,
						number_format((int) $line['vat_rate'], 2, '.', ''),
					)
				);
			}
			$db->commit();
		} catch (Throwable $e) {
			$db->rollback();
			if ($e instanceof CoffeejiException) {
				throw $e;
			}
			throw new CoffeejiException('Die Abrechnung konnte nicht gespeichert werden.');
		}

		return $invoiceId;
	}

	/**
	 * @return array{invoice: array<string, mixed>, lines: list<array<string, mixed>>}
	 */
	public function load(int $id): array
	{
		$invoice = $this->fetch('SELECT * FROM invoice2 WHERE id = ? LIMIT 1', 'i', array($id));
		if ($invoice === array()) {
			throw new CoffeejiException('Die Abrechnung wurde nicht gefunden.');
		}
		$lines = $this->fetch(
			'SELECT * FROM invoice_list2 WHERE invoice_id = ? ORDER BY id ASC',
			'i',
			array($id)
		);

		return array('invoice' => $invoice[0], 'lines' => $lines);
	}

	/**
	 * @param list<array<string, mixed>> $vat
	 * @return array<int, array{net: string, tax: string, gross: string}>
	 */
	private function vatIndex(array $vat): array
	{
		$empty = array('net' => '0.00', 'tax' => '0.00', 'gross' => '0.00');
		$index = array(7 => $empty, 19 => $empty);
		foreach ($vat as $row) {
			$rate = (int) ($row['rate'] ?? 0);
			if ($rate !== 7 && $rate !== 19) {
				continue;
			}
			$index[$rate] = array(
				'net' => (string) $row['net'],
				'tax' => (string) $row['tax'],
				'gross' => (string) $row['gross'],
			);
		}

		return $index;
	}

	private function columnExists(string $table, string $column): bool
	{
		$rows = $this->fetch(
			'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
			'sss',
			array($this->schemaName(), $table, $column)
		);

		return isset($rows[0]['c']) && (int) $rows[0]['c'] === 1;
	}

	private function tableExists(string $table): bool
	{
		$rows = $this->fetch(
			'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
			'ss',
			array($this->schemaName(), $table)
		);

		return isset($rows[0]['c']) && (int) $rows[0]['c'] === 1;
	}

	private function schemaName(): string
	{
		return (string) $this->db()->query('SELECT DATABASE() AS db')->fetch_assoc()['db'];
	}

	private function db(): mysqli
	{
		if ($this->db instanceof mysqli) {
			return $this->db;
		}
		$mysqli = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
		if (!$mysqli) {
			throw new CoffeejiException('Die Datenbank ist nicht erreichbar.');
		}
		mysqli_set_charset($mysqli, 'utf8mb4');
		$this->db = $mysqli;
		$this->ownsConnection = true;

		return $mysqli;
	}

	/**
	 * @param list<mixed> $params
	 * @return list<array<string, mixed>>
	 */
	private function fetch(string $sql, string $types, array $params): array
	{
		$stmt = $this->prepare($sql, $types, $params);
		$stmt->execute();
		$result = $stmt->get_result();
		$rows = array();
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
			$result->free();
		}
		$stmt->close();

		return $rows;
	}

	/**
	 * @param list<mixed> $params
	 */
	private function execute(string $sql, string $types, array $params): void
	{
		$stmt = $this->prepare($sql, $types, $params);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new CoffeejiException('Die Abrechnung konnte nicht gespeichert werden.');
		}
		$stmt->close();
	}

	/**
	 * @param list<mixed> $params
	 */
	private function prepare(string $sql, string $types, array $params): mysqli_stmt
	{
		$stmt = $this->db()->prepare($sql);
		if (!$stmt) {
			throw new CoffeejiException('Die Abfrage konnte nicht vorbereitet werden.');
		}
		if ($types !== '' && $params !== array()) {
			$refs = array();
			foreach ($params as $index => $value) {
				$refs[$index] = $params[$index];
			}
			$stmt->bind_param($types, ...$refs);
		}

		return $stmt;
	}
}
