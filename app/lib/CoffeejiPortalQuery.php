<?php

declare(strict_types=1);

/**
 * Liest Bestellungen nur aus den lokalen cj_-Tabellen.
 */
class CoffeejiPortalQuery
{
	private ?mysqli $db = null;

	private ?bool $details = null;

	public function tablesReady(): bool
	{
		try {
			$rows = $this->fetch(
				'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
				'ss',
				array(DB_NAME, 'cj_orders')
			);
		} catch (Throwable $e) {
			return false;
		}

		return isset($rows[0]['c']) && (int) $rows[0]['c'] === 1;
	}

	public function detailsReady(): bool
	{
		if ($this->details !== null) {
			return $this->details;
		}
		if (!$this->tablesReady()) {
			$this->details = false;

			return false;
		}
		$rows = $this->fetch(
			'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
			'sss',
			array(DB_NAME, 'cj_orders', 'out_stock_status')
		);
		$this->details = isset($rows[0]['c']) && (int) $rows[0]['c'] === 1;

		return $this->details;
	}

	/**
	 * @return list<string>
	 */
	public function payWayNames(): array
	{
		$rows = $this->fetch(
			"SELECT DISTINCT pay_way_name FROM cj_orders WHERE pay_way_name IS NOT NULL AND pay_way_name <> '' ORDER BY pay_way_name ASC",
			'',
			array()
		);
		$names = array();
		foreach ($rows as $row) {
			$names[] = (string) $row['pay_way_name'];
		}

		return $names;
	}

	/**
	 * @param array<string, mixed> $filter
	 * @return array<string, mixed>
	 */
	public function searchOrders(array $filter, bool $export = false): array
	{
		$details = $this->detailsReady();
		$where = $this->where($filter, $details, false);
		$summary = $this->fetch(
			'SELECT COUNT(*) AS anzahl,
				COALESCE(SUM(o.pay_amt), 0) AS summe,
				COALESCE(SUM(CASE WHEN o.status IN (' . $this->revenueSql() . ') THEN o.pay_amt ELSE 0 END), 0) AS umsatz,
				COALESCE(SUM(CASE WHEN o.status IN (' . $this->revenueSql() . ') THEN 1 ELSE 0 END), 0) AS umsatz_anzahl,
				COALESCE(SUM(CASE WHEN o.status = 1 OR ' . ($details ? 'o.out_stock_status = 1' : '0 = 1') . ' THEN 1 ELSE 0 END), 0) AS offen
			FROM cj_orders o
			LEFT JOIN cj_devices d ON d.id = o.device_id
			WHERE ' . $where['sql'],
			$where['types'],
			$where['params']
		);
		$total = (int) ($summary[0]['anzahl'] ?? 0);
		$meta = CoffeejiPortal::pageMeta($total, (int) $filter['page'], (int) $filter['per_page']);
		$limit = $export ? 20000 : $meta['per_page'];
		$offset = $export ? 0 : $meta['offset'];
		$rows = $this->fetch(
			'SELECT ' . $this->columns($details) . '
			FROM cj_orders o
			LEFT JOIN cj_devices d ON d.id = o.device_id
			WHERE ' . $where['sql'] . '
			ORDER BY o.create_time DESC, o.id DESC
			LIMIT ? OFFSET ?',
			$where['types'] . 'ii',
			array_merge($where['params'], array($limit, $offset))
		);

		return array(
			'rows' => array_map(array($this, 'present'), $rows),
			'total' => $total,
			'amount' => round((float) ($summary[0]['summe'] ?? 0), 2),
			'revenue' => round((float) ($summary[0]['umsatz'] ?? 0), 2),
			'revenue_count' => (int) ($summary[0]['umsatz_anzahl'] ?? 0),
			'open' => (int) ($summary[0]['offen'] ?? 0),
			'page' => $meta['page'],
			'pages' => $meta['pages'],
			'per_page' => $meta['per_page'],
			'truncated' => $export && $total > 20000,
			'details' => $details,
		);
	}

	/**
	 * @param array<string, mixed> $filter
	 * @return array<string, mixed>
	 */
	public function statistics(array $filter): array
	{
		$details = $this->detailsReady();
		$where = $this->where($filter, $details, true);
		$rows = $this->fetch(
			'SELECT ' . $this->columns($details) . '
			FROM cj_orders o
			LEFT JOIN cj_devices d ON d.id = o.device_id
			WHERE ' . $where['sql'] . '
			ORDER BY o.create_time ASC, o.id ASC
			LIMIT 20000',
			$where['types'],
			$where['params']
		);
		$orders = array_map(array($this, 'present'), $rows);
		$from = $filter['from'];
		$to = $filter['to'];

		return array(
			'orders' => $orders,
			'truncated' => count($rows) >= 20000,
			'details' => $details,
			'summary' => CoffeejiPortal::totals($orders),
			'periods' => CoffeejiPortal::groupByPeriod($orders, (string) $filter['grain'], $from, $to),
			'machines' => CoffeejiPortal::byMachine($orders, $from, $to),
			'products' => CoffeejiPortal::byProduct($orders, $from, $to),
			'payways' => CoffeejiPortal::byPayWay($orders, $from, $to),
		);
	}

	/**
	 * @param array<string, mixed> $filter
	 * @return array{sql: string, types: string, params: list<mixed>}
	 */
	private function where(array $filter, bool $details, bool $revenueOnly): array
	{
		$sql = array('o.create_time >= ?', 'o.create_time < ?');
		$types = 'ss';
		$params = array(
			$filter['from']->format('Y-m-d H:i:s'),
			$filter['to']->format('Y-m-d H:i:s'),
		);
		$this->like($sql, $types, $params, 'o.order_no', (string) $filter['order_no']);
		if ((string) $filter['device_name'] !== '') {
			$like = $this->likeValue((string) $filter['device_name']);
			$sql[] = "(o.device_name LIKE ? ESCAPE '\\\\' OR d.name LIKE ? ESCAPE '\\\\')";
			$types .= 'ss';
			$params[] = $like;
			$params[] = $like;
		}
		if ((string) $filter['device_no'] !== '') {
			$like = $this->likeValue((string) $filter['device_no']);
			$sql[] = "(o.device_ext_no LIKE ? ESCAPE '\\\\' OR o.device_id LIKE ? ESCAPE '\\\\' OR d.device_no LIKE ? ESCAPE '\\\\')";
			$types .= 'sss';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$this->like($sql, $types, $params, 'o.goods_name', (string) $filter['goods_name']);
		if ((string) $filter['pay_way_name'] !== '') {
			$sql[] = 'o.pay_way_name = ?';
			$types .= 's';
			$params[] = (string) $filter['pay_way_name'];
		}
		if ($revenueOnly) {
			$sql[] = 'o.status IN (' . $this->revenueSql() . ')';
		} elseif ($filter['status'] !== null) {
			$sql[] = 'o.status = ?';
			$types .= 'i';
			$params[] = (int) $filter['status'];
		}
		if ($details && $filter['out_stock_status'] !== null) {
			$sql[] = 'o.out_stock_status = ?';
			$types .= 'i';
			$params[] = (int) $filter['out_stock_status'];
		}

		return array(
			'sql' => implode(' AND ', $sql),
			'types' => $types,
			'params' => $params,
		);
	}

	/**
	 * @param list<string> $sql
	 * @param list<mixed> $params
	 */
	private function like(array &$sql, string &$types, array &$params, string $column, string $value): void
	{
		if ($value === '') {
			return;
		}
		$sql[] = $column . " LIKE ? ESCAPE '\\\\'";
		$types .= 's';
		$params[] = $this->likeValue($value);
	}

	private function likeValue(string $value): string
	{
		$escaped = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $value);

		return '%' . $escaped . '%';
	}

	private function revenueSql(): string
	{
		return implode(',', array_map('intval', CoffeejiOrder::PORTAL_REVENUE_STATUSES));
	}

	private function columns(bool $details): string
	{
		$sql = 'o.id, o.order_no, o.device_id, o.device_ext_no, o.device_name, o.goods_name, o.price, o.pay_amt,
			o.pay_way_name, o.status, o.refund_status, o.create_time, o.time_zone_id,
			d.device_no, d.name AS catalog_name';
		if ($details) {
			$sql .= ', o.pay_way, o.out_stock_status, o.out_stock_time, o.refund_time, o.fail_remark';
		}

		return $sql;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function present(array $row): array
	{
		$name = trim((string) ($row['device_name'] ?? ''));
		if ($name === '') {
			$name = trim((string) ($row['catalog_name'] ?? ''));
		}
		$ext = trim((string) ($row['device_ext_no'] ?? ''));
		$deviceNo = trim((string) ($row['device_no'] ?? ''));
		$id = trim((string) ($row['device_id'] ?? ''));
		$row['device_name'] = $name;
		$row['device_no'] = $deviceNo;
		$row['machine_no'] = $ext !== '' ? $ext : ($deviceNo !== '' ? $deviceNo : $id);
		$row['machine_id'] = $deviceNo !== '' ? $deviceNo : $id;
		$row['out_stock_status'] = array_key_exists('out_stock_status', $row)
			? CoffeejiOrder::nullableInt($row['out_stock_status'])
			: null;
		$row['pay_way'] = array_key_exists('pay_way', $row) ? CoffeejiOrder::nullableInt($row['pay_way']) : null;

		return $row;
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

		return $mysqli;
	}

	/**
	 * @param list<mixed> $params
	 * @return list<array<string, mixed>>
	 */
	private function fetch(string $sql, string $types, array $params): array
	{
		$db = $this->db();
		$stmt = $db->prepare($sql);
		if (!$stmt) {
			throw new CoffeejiException('Die Abfrage konnte nicht vorbereitet werden.');
		}
		if ($types !== '' && $params !== array()) {
			$stmt->bind_param($types, ...$params);
		}
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
}
