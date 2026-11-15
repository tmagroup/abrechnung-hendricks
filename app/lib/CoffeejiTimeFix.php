<?php

declare(strict_types=1);

/**
 * Rechnet gespeicherte Coffeeji-Zeiten aus raw_json neu nach Europe/Berlin.
 * Mehrfach ausführbar: die ursprüngliche Wanduhr bleibt unter source_* erhalten.
 */
class CoffeejiTimeFix
{
	/** @var array<string, list<string>> */
	private const ORDER_FIELDS = array(
		'create_time' => array('source_create_time', 'createTime', 'create_time'),
		'out_stock_time' => array('source_out_stock_time', 'outStockTime', 'out_stock_time'),
		'refund_time' => array('source_refund_time', 'refundTime', 'refund_time'),
	);

	public function __construct(private mysqli $db)
	{
	}

	public static function isDryRun(array $argv): bool
	{
		foreach ($argv as $arg) {
			if ($arg === '--dry-run') {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param list<string> $columns
	 * @return array{changed: bool, values: array<string, ?string>, raw_json: ?string}
	 */
	public static function planOrder(array $row, array $columns): array
	{
		$columns = array_values(array_intersect($columns, array_keys(self::ORDER_FIELDS)));
		if (!in_array('create_time', $columns, true)) {
			$columns[] = 'create_time';
		}
		$decoded = self::decodeObject($row['raw_json'] ?? null);
		$currentRaw = is_string($row['raw_json'] ?? null) ? $row['raw_json'] : null;
		$values = array();
		foreach ($columns as $column) {
			$values[$column] = self::stored($row[$column] ?? null);
		}
		if (!$decoded['ok']) {
			return array('changed' => false, 'values' => $values, 'raw_json' => $currentRaw);
		}

		$raw = $decoded['raw'];
		$zone = self::zoneOf($raw, $row['time_zone_id'] ?? null);
		$changed = false;
		foreach ($columns as $column) {
			$source = self::firstClock($raw, self::ORDER_FIELDS[$column]);
			if ($source === null) {
				$source = $row[$column] ?? null;
			}
			if ($source === null || (is_string($source) && trim($source) === '')) {
				continue;
			}
			$target = CoffeejiTime::storeSource($source, $zone);
			if ($target === null) {
				continue;
			}
			$values[$column] = $target;
			if (self::stored($row[$column] ?? null) === $target) {
				continue;
			}
			$changed = true;
			$literal = CoffeejiOrder::sourceLiteral($source);
			if ($literal !== null) {
				$raw[self::ORDER_FIELDS[$column][0]] = $literal;
			}
			$raw[$column] = $target;
		}
		if (!$changed) {
			return array('changed' => false, 'values' => $values, 'raw_json' => $currentRaw);
		}
		if ($zone !== '' && (!isset($raw['time_zone_id']) || trim((string) $raw['time_zone_id']) === '')) {
			$raw['time_zone_id'] = $zone;
		}
		$json = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($json === false) {
			return array('changed' => false, 'values' => self::storedValues($row, $columns), 'raw_json' => $currentRaw);
		}

		return array('changed' => true, 'values' => $values, 'raw_json' => $json);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array{changed: bool, heart_time: ?string}
	 */
	public static function planDevice(array $row): array
	{
		$current = self::stored($row['heart_time'] ?? null);
		$decoded = self::decodeObject($row['raw_json'] ?? null);
		if (!$decoded['ok']) {
			return array('changed' => false, 'heart_time' => $current);
		}
		$source = self::firstClock($decoded['raw'], array('heartTime', 'heart_time'));
		if ($source === null) {
			return array('changed' => false, 'heart_time' => $current);
		}
		$zone = self::zoneOf($decoded['raw'], null);
		$target = CoffeejiTime::storeSource($source, $zone);
		if ($target === null) {
			return array('changed' => false, 'heart_time' => $current);
		}

		return array(
			'changed' => $current !== $target,
			'heart_time' => $target,
		);
	}

	/**
	 * @return array{dry_run: bool, orders_changed: int, orders_unchanged: int, devices_changed: int, devices_unchanged: int}
	 */
	public function run(bool $dryRun): array
	{
		$orders = $this->correctOrders($dryRun);
		$devices = $this->correctDevices($dryRun);

		return array(
			'dry_run' => $dryRun,
			'orders_changed' => $orders['changed'],
			'orders_unchanged' => $orders['unchanged'],
			'devices_changed' => $devices['changed'],
			'devices_unchanged' => $devices['unchanged'],
		);
	}

	public function summary(array $result): string
	{
		$prefix = $result['dry_run'] ? 'Trockenlauf. ' : '';

		return $prefix
			. 'Bestellungen geändert: ' . $result['orders_changed']
			. ', unverändert: ' . $result['orders_unchanged']
			. '. Geräte geändert: ' . $result['devices_changed']
			. ', unverändert: ' . $result['devices_unchanged'] . '.';
	}

	/**
	 * @return array{changed: int, unchanged: int}
	 */
	private function correctOrders(bool $dryRun): array
	{
		$columns = $this->orderClockColumns();
		$select = array_merge(array('id', 'time_zone_id', 'raw_json'), $columns);
		$sql = 'SELECT ' . implode(', ', $select) . ' FROM cj_orders';
		$result = $this->db->query($sql);
		if (!$result) {
			throw new CoffeejiException('Bestellungen konnten nicht gelesen werden.');
		}
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		$result->free();

		$changed = 0;
		$unchanged = 0;
		$updates = array();
		foreach ($rows as $row) {
			$plan = self::planOrder($row, $columns);
			if (!$plan['changed']) {
				$unchanged++;
				continue;
			}
			$changed++;
			$updates[] = array('id' => (int) $row['id'], 'plan' => $plan, 'columns' => $columns);
		}
		if (!$dryRun && $updates !== array()) {
			$this->writeOrders($updates);
		}

		return array('changed' => $changed, 'unchanged' => $unchanged);
	}

	/**
	 * @param list<array{id: int, plan: array{changed: bool, values: array<string, ?string>, raw_json: ?string}, columns: list<string>}> $updates
	 */
	private function writeOrders(array $updates): void
	{
		$this->db->begin_transaction();
		try {
			foreach ($updates as $update) {
				$plan = $update['plan'];
				$assignments = array();
				$types = '';
				$values = array();
				foreach ($update['columns'] as $column) {
					$target = $plan['values'][$column] ?? null;
					if ($target === null) {
						continue;
					}
					$assignments[] = $column . ' = ?';
					$types .= 's';
					$values[] = $target;
				}
				$assignments[] = 'raw_json = ?';
				$assignments[] = 'updated_at = NOW()';
				$types .= 's';
				$values[] = (string) $plan['raw_json'];
				$types .= 'i';
				$values[] = $update['id'];
				$sql = 'UPDATE cj_orders SET ' . implode(', ', $assignments) . ' WHERE id = ?';
				$this->execute($sql, $types, $values);
			}
			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollback();
			throw $e;
		}
	}

	/**
	 * @return array{changed: int, unchanged: int}
	 */
	private function correctDevices(bool $dryRun): array
	{
		$result = $this->db->query('SELECT id, heart_time, raw_json FROM cj_devices');
		if (!$result) {
			throw new CoffeejiException('Geräte konnten nicht gelesen werden.');
		}
		$changed = 0;
		$unchanged = 0;
		$updates = array();
		while ($row = $result->fetch_assoc()) {
			$plan = self::planDevice($row);
			if (!$plan['changed'] || $plan['heart_time'] === null) {
				$unchanged++;
				continue;
			}
			$changed++;
			$updates[] = array('id' => (string) $row['id'], 'heart_time' => $plan['heart_time']);
		}
		$result->free();
		if (!$dryRun && $updates !== array()) {
			$this->writeDevices($updates);
		}

		return array('changed' => $changed, 'unchanged' => $unchanged);
	}

	/**
	 * @param list<array{id: string, heart_time: string}> $updates
	 */
	private function writeDevices(array $updates): void
	{
		$this->db->begin_transaction();
		try {
			foreach ($updates as $update) {
				$this->execute(
					'UPDATE cj_devices SET heart_time = ?, updated_at = NOW() WHERE id = ?',
					'ss',
					array($update['heart_time'], $update['id'])
				);
			}
			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollback();
			throw $e;
		}
	}

	/**
	 * @return list<string>
	 */
	private function orderClockColumns(): array
	{
		$columns = array('create_time');
		$result = $this->db->query(
			"SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cj_orders'
			AND COLUMN_NAME IN ('out_stock_time', 'refund_time')"
		);
		if (!$result) {
			return $columns;
		}
		while ($row = $result->fetch_assoc()) {
			$name = (string) $row['name'];
			if (isset(self::ORDER_FIELDS[$name]) && !in_array($name, $columns, true)) {
				$columns[] = $name;
			}
		}
		$result->free();

		return $columns;
	}

	/**
	 * @param list<mixed> $values
	 */
	private function execute(string $sql, string $types, array $values): void
	{
		$stmt = $this->db->prepare($sql);
		if (!$stmt) {
			throw new CoffeejiException('Die Zeitkorrektur konnte nicht vorbereitet werden.');
		}
		$params = array($types);
		foreach ($values as $key => $value) {
			$params[] = &$values[$key];
		}
		call_user_func_array(array($stmt, 'bind_param'), $params);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new CoffeejiException('Die Zeitkorrektur konnte nicht geschrieben werden.');
		}
		$stmt->close();
	}

	/**
	 * @param array<string, mixed> $raw
	 * @param list<string> $keys
	 */
	private static function firstClock(array $raw, array $keys): mixed
	{
		foreach ($keys as $key) {
			if (!array_key_exists($key, $raw)) {
				continue;
			}
			$value = $raw[$key];
			if ($value === null || (is_string($value) && trim($value) === '')) {
				continue;
			}

			return is_string($value) ? trim($value) : $value;
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	private static function zoneOf(array $raw, mixed $columnZone): string
	{
		foreach (array('timeZoneId', 'time_zone_id') as $key) {
			if (!isset($raw[$key])) {
				continue;
			}
			$zone = trim((string) $raw[$key]);
			if ($zone !== '') {
				return $zone;
			}
		}

		return trim((string) $columnZone);
	}

	/**
	 * @return array{ok: bool, raw: array<string, mixed>}
	 */
	private static function decodeObject(mixed $json): array
	{
		if ($json === null || (is_string($json) && trim($json) === '')) {
			return array('ok' => true, 'raw' => array());
		}
		if (!is_string($json)) {
			return array('ok' => false, 'raw' => array());
		}
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return array('ok' => false, 'raw' => array());
		}
		if ($decoded === array()) {
			return array('ok' => true, 'raw' => array());
		}
		if (array_is_list($decoded)) {
			return array('ok' => false, 'raw' => array());
		}

		return array('ok' => true, 'raw' => $decoded);
	}

	private static function stored(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}
		$raw = trim((string) $value);
		if ($raw === '') {
			return null;
		}
		if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $raw, $match) === 1) {
			return $match[1];
		}

		return $raw;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param list<string> $columns
	 * @return array<string, ?string>
	 */
	private static function storedValues(array $row, array $columns): array
	{
		$values = array();
		foreach ($columns as $column) {
			$values[$column] = self::stored($row[$column] ?? null);
		}

		return $values;
	}
}
