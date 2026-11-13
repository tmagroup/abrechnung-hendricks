<?php

declare(strict_types=1);

class CoffeejiRepository
{
	private ?mysqli $db = null;

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
		if ($numbers !== array()) {
			$placeholders = implode(',', array_fill(0, count($numbers), '?'));
			$types = str_repeat('s', count($numbers));
			$rows = $this->fetchAll(
				$this->db(),
				"SELECT order_no, device_id, device_ext_no, device_name, goods_name, price, pay_amt,
					pay_way_name, status, refund_status, create_time, time_zone_id
				FROM cj_orders WHERE order_no IN ($placeholders)",
				$types,
				array_values($numbers)
			);
			foreach ($rows as $row) {
				$existing[(string) $row['order_no']] = $row;
			}
		}

		$plan = CoffeejiUpsert::plan($existing, $orders);
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
		return $this->fetchAll(
			$this->db(),
			'SELECT order_no, device_id, device_ext_no, device_name, goods_name, price, pay_amt,
				pay_way_name, status, refund_status, create_time, time_zone_id
			FROM cj_orders
			WHERE create_time >= ? AND create_time < ?
			ORDER BY create_time DESC',
			'ss',
			array($from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'))
		);
	}

	/**
	 * @param array<string, mixed> $order
	 */
	private function insertOrder(array $order): void
	{
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
		$json = json_encode(CoffeejiResponse::stripSecrets($order), JSON_UNESCAPED_UNICODE);
		if ($json === false) {
			$json = '{}';
		}
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
		$json = json_encode(CoffeejiResponse::stripSecrets($order), JSON_UNESCAPED_UNICODE);
		if ($json === false) {
			$json = '{}';
		}
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
