<?php

declare(strict_types=1);

/**
 * Rechnet gespeicherte Coffeeji-Zeiten nach Europe/Berlin.
 * raw_json ist in der bestehenden Datenbank das normalisierte Objekt
 * (create_time, time_zone_id), nicht die API-Zeile. Die ursprüngliche Wanduhr
 * bleibt stehen; source_create_time markiert, dass sie schon verrechnet wurde.
 * Die Bestellnummer trägt dieselbe Uhr wie UTC+8 und dient nur zur Kontrolle.
 */
class CoffeejiTimeFix
{
	/** @var array<string, list<string>> */
	private const ORDER_FIELDS = array(
		'create_time' => array('source_create_time', 'createTime', 'create_time'),
		'out_stock_time' => array('source_out_stock_time', 'outStockTime', 'out_stock_time'),
		'refund_time' => array('source_refund_time', 'refundTime', 'refund_time'),
	);

	public const ORDER_SKEW_SECONDS = 2;

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
	 * Uhr aus der Bestellnummer (A + JJMMTTHHMMSS). Das ist die UTC+8-Wanduhr,
	 * oft eine Sekunde vor createTime. Liefert null, wenn das Muster fehlt.
	 */
	public static function orderNoClock(string $orderNo): ?string
	{
		if (preg_match('/^A(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $orderNo, $match) !== 1) {
			return null;
		}
		$built = sprintf(
			'%04d-%02d-%02d %02d:%02d:%02d',
			2000 + (int) $match[1],
			(int) $match[2],
			(int) $match[3],
			(int) $match[4],
			(int) $match[5],
			(int) $match[6]
		);
		$parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $built, new DateTimeZone('UTC'));
		$errors = DateTimeImmutable::getLastErrors();
		if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $built) {
			return null;
		}
		if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
			return null;
		}

		return $built;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param list<string> $columns
	 * @return array{changed: bool, skipped: bool, from: ?string, to: ?string, values: array<string, ?string>, raw_json: ?string}
	 */
	public static function planOrder(array $row, array $columns): array
	{
		$columns = array_values(array_intersect($columns, array_keys(self::ORDER_FIELDS)));
		if (!in_array('create_time', $columns, true)) {
			$columns[] = 'create_time';
		}
		$decoded = self::decodeObject($row['raw_json'] ?? null);
		$currentRaw = is_string($row['raw_json'] ?? null) ? $row['raw_json'] : null;
		$values = self::storedValues($row, $columns);
		$empty = array(
			'changed' => false,
			'skipped' => false,
			'from' => $values['create_time'] ?? null,
			'to' => $values['create_time'] ?? null,
			'values' => $values,
			'raw_json' => $currentRaw,
		);
		if (!$decoded['ok']) {
			return $empty;
		}

		$raw = $decoded['raw'];
		$zone = self::zoneOf($raw, $row['time_zone_id'] ?? null);
		$orderNo = trim((string) ($row['order_no'] ?? $raw['order_no'] ?? $raw['orderNo'] ?? ''));
		$changed = false;
		$skipped = false;
		foreach ($columns as $column) {
			$picked = self::pickSource($raw, self::ORDER_FIELDS[$column], $row[$column] ?? null);
			if ($picked['source'] === null) {
				continue;
			}
			$target = CoffeejiTime::storeSource($picked['source'], $zone);
			if ($target === null) {
				continue;
			}
			$current = self::stored($row[$column] ?? null);
			if ($column === 'create_time' && !$picked['stable']) {
				$decision = self::guardLegacyCreateTime($orderNo, $current, $target);
				if ($decision === 'keep') {
					$values[$column] = $current;
					continue;
				}
				if ($decision === 'skip') {
					$skipped = true;
					$values[$column] = $current;
					continue;
				}
			}
			$values[$column] = $target;
			if ($current === $target) {
				continue;
			}
			$changed = true;
			$literal = CoffeejiOrder::sourceLiteral($picked['source']);
			if ($literal !== null) {
				$raw[self::ORDER_FIELDS[$column][0]] = $literal;
			}
		}
		if ($skipped && !$changed) {
			$empty['skipped'] = true;

			return $empty;
		}
		if (!$changed) {
			return $empty;
		}
		if ($zone !== '' && (!isset($raw['time_zone_id']) || trim((string) $raw['time_zone_id']) === '')) {
			$raw['time_zone_id'] = $zone;
		}
		$json = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($json === false) {
			return $empty;
		}

		return array(
			'changed' => true,
			'skipped' => false,
			'from' => self::stored($row['create_time'] ?? null),
			'to' => $values['create_time'] ?? null,
			'values' => $values,
			'raw_json' => $json,
		);
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
	 * @return array{
	 *   dry_run: bool,
	 *   orders_changed: int,
	 *   orders_unchanged: int,
	 *   orders_skipped: int,
	 *   devices_changed: int,
	 *   devices_unchanged: int,
	 *   examples: list<array{order_no: string, from: string, to: string}>,
	 *   examples_more: int
	 * }
	 */
	public function run(bool $dryRun): array
	{
		$orders = $this->correctOrders($dryRun);
		$devices = $this->correctDevices($dryRun);

		return array(
			'dry_run' => $dryRun,
			'orders_changed' => $orders['changed'],
			'orders_unchanged' => $orders['unchanged'],
			'orders_skipped' => $orders['skipped'],
			'devices_changed' => $devices['changed'],
			'devices_unchanged' => $devices['unchanged'],
			'examples' => $orders['examples'],
			'examples_more' => $orders['examples_more'],
		);
	}

	/**
	 * @param array<string, mixed> $result
	 */
	public function summary(array $result): string
	{
		$prefix = $result['dry_run'] ? 'Trockenlauf. ' : '';
		$text = $prefix
			. 'Bestellungen geändert: ' . $result['orders_changed']
			. ', unverändert: ' . $result['orders_unchanged'];
		if (($result['orders_skipped'] ?? 0) > 0) {
			$text .= ', übersprungen: ' . $result['orders_skipped'];
		}
		$text .= '. Geräte geändert: ' . $result['devices_changed']
			. ', unverändert: ' . $result['devices_unchanged'] . '.';
		foreach ($result['examples'] ?? array() as $example) {
			$text .= "\n" . $example['order_no'] . ': ' . $example['from'] . ' -> ' . $example['to'];
		}
		if (($result['examples_more'] ?? 0) > 0) {
			$text .= "\n… und " . $result['examples_more'] . ' weitere.';
		}

		return $text;
	}

	/**
	 * @return array{changed: int, unchanged: int, skipped: int, examples: list<array{order_no: string, from: string, to: string}>, examples_more: int}
	 */
	private function correctOrders(bool $dryRun): array
	{
		$columns = $this->orderClockColumns();
		$select = array_merge(array('id', 'order_no', 'time_zone_id', 'raw_json'), $columns);
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
		$skipped = 0;
		$examples = array();
		$updates = array();
		foreach ($rows as $row) {
			$plan = self::planOrder($row, $columns);
			if (!$plan['changed']) {
				if ($plan['skipped']) {
					$skipped++;
				} else {
					$unchanged++;
				}
				continue;
			}
			$changed++;
			if (count($examples) < 10 && $plan['from'] !== null && $plan['to'] !== null) {
				$examples[] = array(
					'order_no' => trim((string) ($row['order_no'] ?? '')),
					'from' => $plan['from'],
					'to' => $plan['to'],
				);
			}
			$updates[] = array('id' => (int) $row['id'], 'plan' => $plan, 'columns' => $columns);
		}
		if (!$dryRun && $updates !== array()) {
			$this->writeOrders($updates);
		}

		return array(
			'changed' => $changed,
			'unchanged' => $unchanged,
			'skipped' => $skipped,
			'examples' => $examples,
			'examples_more' => max(0, $changed - count($examples)),
		);
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
	 * @return array{source: mixed, stable: bool}
	 */
	private static function pickSource(array $raw, array $keys, mixed $column): array
	{
		foreach ($keys as $index => $key) {
			if (!array_key_exists($key, $raw)) {
				continue;
			}
			$value = $raw[$key];
			if ($value === null || (is_string($value) && trim($value) === '')) {
				continue;
			}
			$stable = $index === 0 || in_array($key, array('createTime', 'outStockTime', 'refundTime'), true);

			return array(
				'source' => is_string($value) ? trim($value) : $value,
				'stable' => $stable,
			);
		}
		if ($column === null || (is_string($column) && trim((string) $column) === '')) {
			return array('source' => null, 'stable' => false);
		}

		return array(
			'source' => is_string($column) ? trim((string) $column) : $column,
			'stable' => false,
		);
	}

	/**
	 * Ohne Marker: die Bestellnummer (UTC+8) prüft, ob die Spalte schon Berlin ist.
	 * Sonst würde eine zweite Runde die Sommerzeit noch einmal drauflegen.
	 */
	private static function guardLegacyCreateTime(string $orderNo, ?string $stored, ?string $target): string
	{
		$clock = self::orderNoClock($orderNo);
		if ($clock === null) {
			return 'apply';
		}
		$berlin = CoffeejiTime::storeSource($clock, 'UTC+8');
		if ($berlin === null) {
			return 'apply';
		}
		if (self::agrees($stored, $berlin)) {
			return 'keep';
		}
		if (self::agrees($target, $berlin)) {
			return 'apply';
		}

		return 'skip';
	}

	private static function agrees(?string $left, ?string $right): bool
	{
		if ($left === null || $right === null) {
			return false;
		}
		$a = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $left, CoffeejiTime::zone());
		$b = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $right, CoffeejiTime::zone());
		if (!$a || !$b) {
			return false;
		}

		return abs($a->getTimestamp() - $b->getTimestamp()) <= self::ORDER_SKEW_SECONDS;
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
