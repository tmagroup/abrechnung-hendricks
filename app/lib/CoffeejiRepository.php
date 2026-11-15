<?php

declare(strict_types=1);

class CoffeejiRepository
{
	private ?mysqli $db = null;

	private ?bool $orderDetails = null;

	public function tablesReady(): bool
	{
		$db = $this->db();
		$sql = "SELECT COUNT(*) AS c FROM information_schema.tables
			WHERE table_schema = ? AND table_name IN (
				'cj_devices','cj_materials','cj_material_levels','cj_orders','cj_sync_runs','cj_webhook_log'
			)";
		$rows = $this->fetchAll($db, $sql, 's', array(DB_NAME));

		return isset($rows[0]['c']) && (int) $rows[0]['c'] === 6;
	}

	public function startRun(string $job): int
	{
		$db = $this->db();
		$stmt = $db->prepare('INSERT INTO cj_sync_runs (job, status, started_at) VALUES (?, ?, NOW())');
		if (!$stmt) {
			throw new CoffeejiException('Sync-Lauf konnte nicht angelegt werden.');
		}
		$status = 'running';
		$stmt->bind_param('ss', $job, $status);
		$stmt->execute();
		$id = (int) $stmt->insert_id;
		$stmt->close();

		return $id;
	}

	public function finishRun(int $id, string $status, string $message, int $records): void
	{
		$db = $this->db();
		$stmt = $db->prepare('UPDATE cj_sync_runs SET status = ?, message = ?, records_count = ?, finished_at = NOW() WHERE id = ?');
		if (!$stmt) {
			throw new CoffeejiException('Sync-Lauf konnte nicht abgeschlossen werden.');
		}
		$stmt->bind_param('ssii', $status, $message, $records, $id);
		$stmt->execute();
		$stmt->close();
	}

	public function lastSuccess(string $job): ?string
	{
		$rows = $this->fetchAll(
			$this->db(),
			"SELECT finished_at FROM cj_sync_runs WHERE job = ? AND status = 'ok' ORDER BY id DESC LIMIT 1",
			's',
			array($job)
		);
		if (!isset($rows[0]['finished_at']) || $rows[0]['finished_at'] === null) {
			return null;
		}

		return (string) $rows[0]['finished_at'];
	}

	/**
	 * @param list<array<string, mixed>> $devices
	 */
	public function upsertDevices(array $devices): int
	{
		$db = $this->db();
		$sql = 'INSERT INTO cj_devices (
				id, device_no, device_ext_no, name, status, device_run_status, exception_remark,
				scarce_status, scarce_remark, heart_time, device_type_name, soft_run_version,
				raw_json, synced_at, created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
			ON DUPLICATE KEY UPDATE
				device_no = VALUES(device_no),
				device_ext_no = VALUES(device_ext_no),
				name = VALUES(name),
				status = VALUES(status),
				device_run_status = VALUES(device_run_status),
				exception_remark = VALUES(exception_remark),
				scarce_status = VALUES(scarce_status),
				scarce_remark = VALUES(scarce_remark),
				heart_time = VALUES(heart_time),
				device_type_name = VALUES(device_type_name),
				soft_run_version = VALUES(soft_run_version),
				raw_json = VALUES(raw_json),
				synced_at = NOW(),
				updated_at = NOW()';
		$stmt = $db->prepare($sql);
		if (!$stmt) {
			throw new CoffeejiException('Geräte konnten nicht gespeichert werden.');
		}
		$count = 0;
		foreach ($devices as $device) {
			if (($device['id'] ?? '') === '') {
				continue;
			}
			$raw = $device['raw'] ?? array();
			if (is_array($raw)) {
				$raw = CoffeejiResponse::stripSecrets($raw);
			}
			$json = json_encode($raw, JSON_UNESCAPED_UNICODE);
			if ($json === false) {
				$json = '{}';
			}
			$id = (string) $device['id'];
			$deviceNo = (string) $device['device_no'];
			$ext = (string) $device['device_ext_no'];
			$name = (string) $device['name'];
			$status = $device['status'] === null ? null : (string) $device['status'];
			$run = $device['device_run_status'] === null ? null : (string) $device['device_run_status'];
			$exception = (string) $device['exception_remark'];
			$scarce = $device['scarce_status'] === null ? null : (string) $device['scarce_status'];
			$scarceRemark = (string) $device['scarce_remark'];
			$heart = $device['heart_time'];
			$type = (string) $device['device_type_name'];
			$version = (string) $device['soft_run_version'];
			$stmt->bind_param(
				'sssssssssssss',
				$id,
				$deviceNo,
				$ext,
				$name,
				$status,
				$run,
				$exception,
				$scarce,
				$scarceRemark,
				$heart,
				$type,
				$version,
				$json
			);
			$stmt->execute();
			$count++;
		}
		$stmt->close();

		return $count;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function devices(): array
	{
		return $this->fetchAll(
			$this->db(),
			'SELECT id, device_no, device_ext_no, name, status, device_run_status, exception_remark,
				scarce_status, scarce_remark, heart_time, device_type_name, soft_run_version
			FROM cj_devices ORDER BY name ASC, device_ext_no ASC',
			'',
			array()
		);
	}

	/**
	 * @param list<array<string, mixed>> $materials
	 */
	public function upsertMaterials(string $deviceId, array $materials): int
	{
		$db = $this->db();
		$existing = $this->fetchAll(
			$db,
			'SELECT material_key, residue_qty FROM cj_materials WHERE device_id = ?',
			's',
			array($deviceId)
		);
		$known = array();
		foreach ($existing as $row) {
			$known[(string) $row['material_key']] = (string) $row['residue_qty'];
		}

		$upsert = $db->prepare('INSERT INTO cj_materials (
				device_id, material_key, material_id, name, position, capacity, residue_qty, warn_capacity, unit, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
			ON DUPLICATE KEY UPDATE
				material_id = VALUES(material_id),
				name = VALUES(name),
				position = VALUES(position),
				capacity = VALUES(capacity),
				residue_qty = VALUES(residue_qty),
				warn_capacity = VALUES(warn_capacity),
				unit = VALUES(unit),
				updated_at = NOW()');
		$history = $db->prepare('INSERT INTO cj_material_levels (
				device_id, material_key, name, residue_qty, capacity, warn_capacity, unit, recorded_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
		if (!$upsert || !$history) {
			throw new CoffeejiException('Füllstände konnten nicht gespeichert werden.');
		}

		$count = 0;
		foreach ($materials as $material) {
			$key = (string) ($material['material_key'] ?? '');
			if ($key === '') {
				continue;
			}
			$materialId = (string) $material['material_id'];
			$name = (string) $material['name'];
			$position = (string) $material['position'];
			$capacity = (string) $material['capacity'];
			$residue = (string) $material['residue_qty'];
			$warn = (string) $material['warn_capacity'];
			$unit = (string) $material['unit'];
			$upsert->bind_param('sssssssss', $deviceId, $key, $materialId, $name, $position, $capacity, $residue, $warn, $unit);
			$upsert->execute();
			$changed = !isset($known[$key]) || abs((float) $known[$key] - (float) $residue) > 0.009;
			if ($changed) {
				$history->bind_param('sssssss', $deviceId, $key, $name, $residue, $capacity, $warn, $unit);
				$history->execute();
			}
			$count++;
		}
		$upsert->close();
		$history->close();

		return $count;
	}

	/**
	 * @return array<string, list<array<string, mixed>>>
	 */
	public function materialsGrouped(): array
	{
		$rows = $this->fetchAll(
			$this->db(),
			'SELECT device_id, material_key, material_id, name, position, capacity, residue_qty, warn_capacity, unit
			FROM cj_materials ORDER BY position ASC, name ASC',
			'',
			array()
		);
		$grouped = array();
		foreach ($rows as $row) {
			$grouped[(string) $row['device_id']][] = $row;
		}

		return $grouped;
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @return array{insert: int, update: int, unchanged: int}
	 */
	public function upsertOrders(array $orders): array
	{
		$numbers = array();
		foreach ($orders as $order) {
			$no = trim((string) ($order['order_no'] ?? ''));
			if ($no !== '') {
				$numbers[$no] = $no;
			}
		}
		$existing = array();
		$details = $this->orderDetailsReady();
		if ($numbers !== array()) {
			$placeholders = implode(',', array_fill(0, count($numbers), '?'));
			$types = str_repeat('s', count($numbers));
			$columns = 'order_no, device_id, device_ext_no, device_name, goods_name, price, pay_amt,
					pay_way_name, status, refund_status, create_time, time_zone_id';
			if ($details) {
				$columns .= ', pay_way, out_stock_status, out_stock_time, refund_time, fail_remark';
			}
			$rows = $this->fetchAll(
				$this->db(),
				"SELECT $columns FROM cj_orders WHERE order_no IN ($placeholders)",
				$types,
				array_values($numbers)
			);
			foreach ($rows as $row) {
				$existing[(string) $row['order_no']] = $row;
			}
		}

		$plan = CoffeejiUpsert::plan($existing, $orders, $details);
		foreach ($plan['insert'] as $order) {
			$this->insertOrder($order);
		}
		foreach ($plan['update'] as $order) {
			$this->updateOrder($order);
		}

		return array(
			'insert' => count($plan['insert']),
			'update' => count($plan['update']),
			'unchanged' => $plan['unchanged'],
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function ordersBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		$columns = 'order_no, device_id, device_ext_no, device_name, goods_name, price, pay_amt,
				pay_way_name, status, refund_status, create_time, time_zone_id';
		if ($this->orderDetailsReady()) {
			$columns .= ', out_stock_status';
		}

		return $this->fetchAll(
			$this->db(),
			"SELECT $columns
			FROM cj_orders
			WHERE create_time >= ? AND create_time < ?
			ORDER BY create_time DESC",
			'ss',
			array($from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'))
		);
	}

	public function webhookHashAccepted(string $hash): bool
	{
		$rows = $this->fetchAll(
			$this->db(),
			"SELECT id FROM cj_webhook_events WHERE payload_hash = ? AND processed_status = 'accepted' LIMIT 1",
			's',
			array($hash)
		);

		return $rows !== array();
	}

	public function insertWebhookEvent(
		string $eventType,
		string $deviceId,
		string $deviceNo,
		string $orderNo,
		string $payloadJson,
		string $payloadHash,
		string $processedStatus,
		?string $errorMessage
	): int {
		$db = $this->db();
		$received = gmdate('Y-m-d H:i:s');
		$processedAt = $processedStatus === 'received' ? null : $received;
		$error = $errorMessage === null ? null : self::clip(CoffeejiLogger::redact($errorMessage), 240);
		$stmt = $db->prepare('INSERT INTO cj_webhook_events (
				event_type, device_id, device_no, order_no, payload_json, payload_hash,
				received_at, processed_status, processed_at, error_message
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
		if (!$stmt) {
			throw new CoffeejiException('Webhook-Ereignis konnte nicht gespeichert werden.');
		}
		$eventType = self::clip($eventType, 32);
		$deviceId = self::nullIfEmpty(self::clip($deviceId, 32));
		$deviceNo = self::nullIfEmpty(self::clip($deviceNo, 64));
		$orderNo = self::nullIfEmpty(self::clip($orderNo, 80));
		$stmt->bind_param(
			'ssssssssss',
			$eventType,
			$deviceId,
			$deviceNo,
			$orderNo,
			$payloadJson,
			$payloadHash,
			$received,
			$processedStatus,
			$processedAt,
			$error
		);
		$ok = $stmt->execute();
		$id = (int) $stmt->insert_id;
		$stmt->close();
		if (!$ok || $id < 1) {
			throw new CoffeejiException('Webhook-Ereignis konnte nicht gespeichert werden.');
		}

		return $id;
	}

	public function markWebhookEvent(int $id, string $status, ?string $errorMessage): void
	{
		$error = $errorMessage === null ? null : self::clip(CoffeejiLogger::redact($errorMessage), 240);
		$now = gmdate('Y-m-d H:i:s');
		$this->execute(
			$this->db(),
			'UPDATE cj_webhook_events SET processed_status = ?, processed_at = ?, error_message = ? WHERE id = ?',
			'sssi',
			array(self::clip($status, 32), $now, $error, $id)
		);
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	public function applyWebhookDevice(string $deviceId, string $deviceNo, array $fields): void
	{
		$deviceId = self::clip(trim($deviceId), 32);
		$deviceNo = self::clip(trim($deviceNo), 64);
		if ($deviceId === '' && $deviceNo === '') {
			return;
		}
		$db = $this->db();
		$row = null;
		if ($deviceId !== '') {
			$found = $this->fetchAll($db, 'SELECT id FROM cj_devices WHERE id = ? LIMIT 1', 's', array($deviceId));
			$row = $found[0] ?? null;
		}
		if ($row === null && $deviceNo !== '') {
			$found = $this->fetchAll($db, 'SELECT id FROM cj_devices WHERE device_no = ? ORDER BY updated_at DESC LIMIT 1', 's', array($deviceNo));
			$row = $found[0] ?? null;
		}

		if ($row === null) {
			if ($deviceId === '') {
				return;
			}
			$run = array_key_exists('device_run_status', $fields) && $fields['device_run_status'] !== null ? (string) $fields['device_run_status'] : null;
			$exception = array_key_exists('exception_remark', $fields) ? (string) $fields['exception_remark'] : '';
			$scarce = array_key_exists('scarce_status', $fields) && $fields['scarce_status'] !== null ? (string) $fields['scarce_status'] : null;
			$scarceRemark = array_key_exists('scarce_remark', $fields) ? (string) $fields['scarce_remark'] : '';
			$this->execute(
				$db,
				'INSERT INTO cj_devices (
					id, device_no, name, device_run_status, exception_remark, scarce_status, scarce_remark, created_at, updated_at
				) VALUES (?, ?, \'\', ?, ?, ?, ?, NOW(), NOW())',
				'ssssss',
				array($deviceId, $deviceNo, $run, $exception, $scarce, $scarceRemark)
			);

			return;
		}

		$sets = array();
		$types = '';
		$params = array();
		if (array_key_exists('device_run_status', $fields)) {
			$sets[] = 'device_run_status = ?';
			$types .= 's';
			$params[] = $fields['device_run_status'] === null ? null : (string) $fields['device_run_status'];
		}
		if (array_key_exists('exception_remark', $fields)) {
			$sets[] = 'exception_remark = ?';
			$types .= 's';
			$params[] = (string) $fields['exception_remark'];
		}
		if (array_key_exists('scarce_status', $fields)) {
			$sets[] = 'scarce_status = ?';
			$types .= 's';
			$params[] = $fields['scarce_status'] === null ? null : (string) $fields['scarce_status'];
		}
		if (array_key_exists('scarce_remark', $fields)) {
			$sets[] = 'scarce_remark = ?';
			$types .= 's';
			$params[] = (string) $fields['scarce_remark'];
		}
		if ($deviceNo !== '') {
			$sets[] = 'device_no = ?';
			$types .= 's';
			$params[] = $deviceNo;
		}
		if ($sets === array()) {
			return;
		}
		$sets[] = 'updated_at = NOW()';
		$types .= 's';
		$params[] = (string) $row['id'];
		$this->execute($db, 'UPDATE cj_devices SET ' . implode(', ', $sets) . ' WHERE id = ?', $types, $params);
	}

	/**
	 * @param array<string, mixed> $order
	 */
	public function applyWebhookOrder(array $order): void
	{
		$orderNo = self::clip(trim((string) ($order['order_no'] ?? '')), 80);
		if ($orderNo === '') {
			return;
		}
		$db = $this->db();
		$existing = $this->fetchAll(
			$db,
			'SELECT order_no, create_time, raw_json FROM cj_orders WHERE order_no = ? LIMIT 1',
			's',
			array($orderNo)
		);
		$payload = $order['payload'] ?? array();
		if (!is_array($payload)) {
			$payload = array();
		}
		$currentRaw = isset($existing[0]['raw_json']) && is_string($existing[0]['raw_json']) ? $existing[0]['raw_json'] : null;
		$json = self::mergeWebhookRaw($currentRaw, $payload);
		$deviceId = self::clip(trim((string) ($order['device_id'] ?? '')), 32);
		$status = (int) ($order['status'] ?? 0);
		$refund = $order['refund_status'] ?? null;
		$refundParam = ($refund === null || $refund === '') ? null : (string) (int) $refund;
		$pay = array_key_exists('pay_amt', $order) && $order['pay_amt'] !== null ? CoffeejiOrder::money($order['pay_amt']) : null;
		$created = $order['create_time'] ?? null;
		if ($created !== null) {
			$created = (string) $created;
		}
		$details = $this->orderDetailsReady();
		$portal = self::webhookPortalFields($payload);

		if ($existing === array()) {
			if ($details) {
				$this->execute(
					$db,
					'INSERT INTO cj_orders (
						order_no, device_id, device_ext_no, device_name, goods_name, price, pay_amt,
						pay_way_name, status, refund_status, create_time, time_zone_id,
						pay_way, out_stock_status, out_stock_time, refund_time, fail_remark, raw_json,
						synced_at, created_at, updated_at
					) VALUES (?, ?, \'\', \'\', \'\', 0, ?, \'\', ?, ?, ?, \'UTC+8\', ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())',
					'sssissssssss',
					array(
						$orderNo,
						$deviceId,
						$pay ?? '0.00',
						$status,
						$refundParam,
						$created,
						$portal['pay_way'] ?? null,
						$portal['out_stock_status'] ?? null,
						$portal['out_stock_time'] ?? null,
						$portal['refund_time'] ?? null,
						$portal['fail_remark'] ?? null,
						$json,
					)
				);

				return;
			}
			$this->execute(
				$db,
				'INSERT INTO cj_orders (
					order_no, device_id, device_ext_no, device_name, goods_name, price, pay_amt,
					pay_way_name, status, refund_status, create_time, time_zone_id, raw_json,
					synced_at, created_at, updated_at
				) VALUES (?, ?, \'\', \'\', \'\', 0, ?, \'\', ?, ?, ?, \'UTC+8\', ?, NULL, NOW(), NOW())',
				'sssisss',
				array($orderNo, $deviceId, $pay ?? '0.00', $status, $refundParam, $created, $json)
			);

			return;
		}

		$sets = array('status = ?', 'raw_json = ?', 'updated_at = NOW()');
		$types = 'is';
		$params = array($status, $json);
		if ($details) {
			foreach (array('pay_way', 'out_stock_status', 'out_stock_time', 'refund_time', 'fail_remark') as $column) {
				if (!array_key_exists($column, $portal)) {
					continue;
				}
				$sets[] = $column . ' = ?';
				$types .= 's';
				$params[] = $portal[$column];
			}
		}
		if ($deviceId !== '') {
			$sets[] = 'device_id = ?';
			$types .= 's';
			$params[] = $deviceId;
		}
		if ($pay !== null) {
			$sets[] = 'pay_amt = ?';
			$types .= 's';
			$params[] = $pay;
		}
		if ($refundParam !== null) {
			$sets[] = 'refund_status = ?';
			$types .= 's';
			$params[] = $refundParam;
		}
		$currentCreated = $existing[0]['create_time'] ?? null;
		if (($currentCreated === null || $currentCreated === '') && $created !== null && $created !== '') {
			$sets[] = 'create_time = ?';
			$types .= 's';
			$params[] = $created;
		}
		$types .= 's';
		$params[] = $orderNo;
		$this->execute($db, 'UPDATE cj_orders SET ' . implode(', ', $sets) . ' WHERE order_no = ?', $types, $params);
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	public function latestWebhookAlerts(): array
	{
		if (!$this->tableExists('cj_webhook_events')) {
			return array();
		}
		$rows = $this->fetchAll(
			$this->db(),
			"SELECT e.device_id, e.event_type, e.received_at
			FROM cj_webhook_events e
			INNER JOIN (
				SELECT device_id, event_type, MAX(id) AS max_id
				FROM cj_webhook_events
				WHERE event_type IN ('device_fault', 'material_alarm')
					AND processed_status IN ('accepted', 'duplicate')
					AND device_id IS NOT NULL
					AND device_id <> ''
				GROUP BY device_id, event_type
			) latest ON latest.max_id = e.id",
			'',
			array()
		);
		$out = array();
		foreach ($rows as $row) {
			$local = CoffeejiTime::utcStoredToLocal((string) ($row['received_at'] ?? ''));
			if ($local === null) {
				continue;
			}
			$out[(string) $row['device_id']][(string) $row['event_type']] = $local;
		}

		return $out;
	}

	public function orderDetailsReady(): bool
	{
		if ($this->orderDetails !== null) {
			return $this->orderDetails;
		}
		$rows = $this->fetchAll(
			$this->db(),
			'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
			'sss',
			array(DB_NAME, 'cj_orders', 'out_stock_status')
		);

		$this->orderDetails = isset($rows[0]['c']) && (int) $rows[0]['c'] === 1;

		return $this->orderDetails;
	}

	/**
	 * @param array<string, mixed> $order
	 */
	private function insertOrder(array $order): void
	{
		if ($this->orderDetailsReady()) {
			$sql = 'INSERT INTO cj_orders (
					order_no, device_id, device_ext_no, device_name, goods_name, price, pay_amt,
					pay_way_name, status, refund_status, create_time, time_zone_id,
					pay_way, out_stock_status, out_stock_time, refund_time, fail_remark, raw_json,
					synced_at, created_at, updated_at
				) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())';
			$this->bindOrderDetails($sql, $order, false);
			return;
		}
		$sql = 'INSERT INTO cj_orders (
				order_no, device_id, device_ext_no, device_name, goods_name, price, pay_amt,
				pay_way_name, status, refund_status, create_time, time_zone_id, raw_json,
				synced_at, created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())';
		$this->bindOrder($sql, $order);
	}

	/**
	 * @param array<string, mixed> $order
	 */
	private function updateOrder(array $order): void
	{
		if ($this->orderDetailsReady()) {
			$sql = 'UPDATE cj_orders SET
					device_id = ?, device_ext_no = ?, device_name = ?, goods_name = ?, price = ?, pay_amt = ?,
					pay_way_name = ?, status = ?, refund_status = ?, create_time = ?, time_zone_id = ?,
					pay_way = ?, out_stock_status = ?, out_stock_time = ?, refund_time = ?, fail_remark = ?, raw_json = ?,
					synced_at = NOW(), updated_at = NOW()
				WHERE order_no = ?';
			$this->bindOrderDetails($sql, $order, true);
			return;
		}
		$sql = 'UPDATE cj_orders SET
				device_id = ?, device_ext_no = ?, device_name = ?, goods_name = ?, price = ?, pay_amt = ?,
				pay_way_name = ?, status = ?, refund_status = ?, create_time = ?, time_zone_id = ?, raw_json = ?,
				synced_at = NOW(), updated_at = NOW()
			WHERE order_no = ?';
		$db = $this->db();
		$stmt = $db->prepare($sql);
		if (!$stmt) {
			throw new CoffeejiException('Bestellung konnte nicht aktualisiert werden.');
		}
		$deviceId = (string) $order['device_id'];
		$ext = (string) $order['device_ext_no'];
		$deviceName = (string) $order['device_name'];
		$goods = (string) $order['goods_name'];
		$price = (string) $order['price'];
		$pay = (string) $order['pay_amt'];
		$way = (string) $order['pay_way_name'];
		$status = (int) $order['status'];
		$refund = $order['refund_status'] === null ? null : (string) $order['refund_status'];
		$created = $order['create_time'];
		$zone = (string) $order['time_zone_id'];
		$json = CoffeejiOrder::storedJson($order);
		$orderNo = (string) $order['order_no'];
		$stmt->bind_param(
			'sssssssisssss',
			$deviceId,
			$ext,
			$deviceName,
			$goods,
			$price,
			$pay,
			$way,
			$status,
			$refund,
			$created,
			$zone,
			$json,
			$orderNo
		);
		$stmt->execute();
		$stmt->close();
	}

	/**
	 * Schreibt die Portal-Spalten mit. Liegt vor bindOrder, damit der bisherige Speichervorgang unverändert bleibt.
	 *
	 * @param array<string, mixed> $order
	 */
	private function bindOrderDetails(string $sql, array $order, bool $update): void
	{
		$db = $this->db();
		$stmt = $db->prepare($sql);
		if (!$stmt) {
			throw new CoffeejiException('Bestellung konnte nicht gespeichert werden.');
		}
		$orderNo = (string) $order['order_no'];
		$deviceId = (string) ($order['device_id'] ?? '');
		$ext = (string) ($order['device_ext_no'] ?? '');
		$deviceName = (string) ($order['device_name'] ?? '');
		$goods = (string) ($order['goods_name'] ?? '');
		$price = (string) ($order['price'] ?? '0.00');
		$pay = (string) ($order['pay_amt'] ?? '0.00');
		$way = (string) ($order['pay_way_name'] ?? '');
		$status = (int) ($order['status'] ?? 0);
		$refund = ($order['refund_status'] ?? null) === null || ($order['refund_status'] ?? '') === ''
			? null
			: (string) $order['refund_status'];
		$created = $order['create_time'] ?? null;
		$zone = (string) ($order['time_zone_id'] ?? '');
		$payWay = ($order['pay_way'] ?? null) === null || ($order['pay_way'] ?? '') === ''
			? null
			: (string) (int) $order['pay_way'];
		$outStatus = ($order['out_stock_status'] ?? null) === null || ($order['out_stock_status'] ?? '') === ''
			? null
			: (string) (int) $order['out_stock_status'];
		$outTime = $order['out_stock_time'] ?? null;
		$refundTime = $order['refund_time'] ?? null;
		$fail = (string) ($order['fail_remark'] ?? '');
		$json = CoffeejiOrder::storedJson($order);
		if ($update) {
			$stmt->bind_param(
				'sssssssissssssssss',
				$deviceId,
				$ext,
				$deviceName,
				$goods,
				$price,
				$pay,
				$way,
				$status,
				$refund,
				$created,
				$zone,
				$payWay,
				$outStatus,
				$outTime,
				$refundTime,
				$fail,
				$json,
				$orderNo
			);
		} else {
			$stmt->bind_param(
				'ssssssssisssssssss',
				$orderNo,
				$deviceId,
				$ext,
				$deviceName,
				$goods,
				$price,
				$pay,
				$way,
				$status,
				$refund,
				$created,
				$zone,
				$payWay,
				$outStatus,
				$outTime,
				$refundTime,
				$fail,
				$json
			);
		}
		$stmt->execute();
		$stmt->close();
	}

	/**
	 * @param array<string, mixed> $order
	 */
	private function bindOrder(string $sql, array $order): void
	{
		$db = $this->db();
		$stmt = $db->prepare($sql);
		if (!$stmt) {
			throw new CoffeejiException('Bestellung konnte nicht gespeichert werden.');
		}
		$orderNo = (string) $order['order_no'];
		$deviceId = (string) $order['device_id'];
		$ext = (string) $order['device_ext_no'];
		$deviceName = (string) $order['device_name'];
		$goods = (string) $order['goods_name'];
		$price = (string) $order['price'];
		$pay = (string) $order['pay_amt'];
		$way = (string) $order['pay_way_name'];
		$status = (int) $order['status'];
		$refund = $order['refund_status'] === null ? null : (string) $order['refund_status'];
		$created = $order['create_time'];
		$zone = (string) $order['time_zone_id'];
		$json = CoffeejiOrder::storedJson($order);
		$stmt->bind_param(
			'ssssssssissss',
			$orderNo,
			$deviceId,
			$ext,
			$deviceName,
			$goods,
			$price,
			$pay,
			$way,
			$status,
			$refund,
			$created,
			$zone,
			$json
		);
		$stmt->execute();
		$stmt->close();
	}

	private function tableExists(string $name): bool
	{
		$rows = $this->fetchAll(
			$this->db(),
			'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
			'ss',
			array(DB_NAME, $name)
		);

		return isset($rows[0]['c']) && (int) $rows[0]['c'] === 1;
	}

	/**
	 * @param list<mixed> $params
	 */
	private function execute(mysqli $db, string $sql, string $types, array $params): void
	{
		$stmt = $db->prepare($sql);
		if (!$stmt) {
			throw new CoffeejiException('Die Speicherung ist fehlgeschlagen.');
		}
		if ($types !== '' && $params !== array()) {
			$stmt->bind_param($types, ...$params);
		}
		$ok = $stmt->execute();
		$stmt->close();
		if (!$ok) {
			throw new CoffeejiException('Die Speicherung ist fehlgeschlagen.');
		}
	}

	/**
	 * Portal-Felder nur, wenn der Webhook sie mitschickt. Fehlende Schlüssel lassen gespeicherte Spalten stehen.
	 *
	 * @param array<string, mixed> $payload
	 * @return array<string, ?string>
	 */
	private static function webhookPortalFields(array $payload): array
	{
		$out = array();
		if (array_key_exists('payWay', $payload) || array_key_exists('pay_way', $payload)) {
			$value = CoffeejiOrder::nullableInt($payload['payWay'] ?? $payload['pay_way'] ?? null);
			$out['pay_way'] = $value === null ? null : (string) $value;
		}
		if (array_key_exists('outStockStatus', $payload) || array_key_exists('out_stock_status', $payload)) {
			$value = CoffeejiOrder::nullableInt($payload['outStockStatus'] ?? $payload['out_stock_status'] ?? null);
			$out['out_stock_status'] = $value === null ? null : (string) $value;
		}
		if (array_key_exists('outStockTime', $payload) || array_key_exists('out_stock_time', $payload)) {
			$out['out_stock_time'] = CoffeejiTime::storeWebhook($payload['outStockTime'] ?? $payload['out_stock_time'] ?? null);
		}
		if (array_key_exists('refundTime', $payload) || array_key_exists('refund_time', $payload)) {
			$out['refund_time'] = CoffeejiTime::storeWebhook($payload['refundTime'] ?? $payload['refund_time'] ?? null);
		}
		if (array_key_exists('failRemark', $payload) || array_key_exists('fail_remark', $payload)) {
			$out['fail_remark'] = self::clip(trim((string) ($payload['failRemark'] ?? $payload['fail_remark'] ?? '')), 2000);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function mergeWebhookRaw(?string $current, array $payload): string
	{
		$base = array();
		if ($current !== null && $current !== '') {
			$decoded = json_decode($current, true);
			if (is_array($decoded)) {
				$base = $decoded;
			}
		}
		$json = json_encode(CoffeejiResponse::stripSecrets(array_merge($base, $payload)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $json === false ? '{}' : $json;
	}

	private static function clip(string $value, int $max): string
	{
		if (strlen($value) <= $max) {
			return $value;
		}

		return substr($value, 0, $max);
	}

	private static function nullIfEmpty(string $value): ?string
	{
		return $value === '' ? null : $value;
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
	private function fetchAll(mysqli $db, string $sql, string $types, array $params): array
	{
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
