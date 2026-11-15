<?php

declare(strict_types=1);

class CoffeejiWebhookConfig
{
	/**
	 * @param list<string> $allowedIps
	 */
	public function __construct(
		public string $token,
		public array $allowedIps,
		public int $maxBytes
	) {
	}

	public function enabled(): bool
	{
		$token = trim($this->token);

		return strlen($token) >= CoffeejiWebhook::MIN_TOKEN_LENGTH && strcasecmp($token, 'CHANGE_ME') !== 0;
	}
}

class CoffeejiWebhookResult
{
	public function __construct(
		public int $status,
		public string $body
	) {
	}
}

class CoffeejiWebhook
{
	public const MIN_TOKEN_LENGTH = 16;
	public const DEFAULT_MAX_BYTES = 65536;
	public const SUCCESS_BODY = '{"code":200,"success":true,"data":true,"msg":"Success"}';

	public function __construct(
		private CoffeejiWebhookConfig $config,
		private CoffeejiRepository $repository,
		private ?CoffeejiLogger $logger = null
	) {
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $server
	 */
	public function handle(string $method, string $rawBody, array $query, array $server): CoffeejiWebhookResult
	{
		$denied = $this->guard($method, $rawBody, $query, $server);
		if ($denied !== null) {
			return $denied;
		}

		try {
			return $this->accept($rawBody);
		} catch (Throwable $e) {
			$this->logger?->error('Coffeeji-Webhook: Ereignis konnte nicht gespeichert werden.');

			return self::result(500, '{"code":500,"success":false,"data":false,"msg":"Error"}');
		}
	}

	/**
	 * Webhook-Status 7 bedeutet erstattet/storniert.
	 * In der Bestellliste bedeutet 7 „nicht hergestellt“. Deshalb landet Webhook-7
	 * in cj_orders als Status 0 (Storno) mit refund_status 1. Der Rohwert bleibt im Payload.
	 *
	 * @return array{status: int, refund_status: ?int}
	 */
	public static function mapOrderStatus(int $webhookStatus): array
	{
		if ($webhookStatus === 7) {
			return array('status' => 0, 'refund_status' => 1);
		}

		return array(
			'status' => $webhookStatus,
			'refund_status' => null,
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function detectType(array $payload): ?string
	{
		$orderNo = trim((string) ($payload['orderNo'] ?? ''));
		if ($orderNo !== '' && array_key_exists('orderStatus', $payload)) {
			return 'order';
		}
		if (array_key_exists('deviceRunStatus', $payload) && array_key_exists('exceptionRemark', $payload)) {
			return 'device_fault';
		}
		if (array_key_exists('deviceScarceStatus', $payload) && array_key_exists('scarceRemark', $payload)) {
			return 'material_alarm';
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	public static function clean(array $payload): array
	{
		$clean = CoffeejiResponse::stripSecrets($payload);

		return is_array($clean) ? $clean : array();
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function hashPayload(string $type, array $payload): string
	{
		return hash('sha256', $type . "\n" . self::canonicalize($payload));
	}

	/**
	 * Leere Liste: keine IP-Prüfung. Ungültige Einträge sperren den Empfang.
	 *
	 * @return list<string>
	 */
	public static function parseIpList(string $raw): array
	{
		$raw = trim($raw);
		if ($raw === '') {
			return array();
		}
		$parts = preg_split('/[\s,;]+/', $raw) ?: array();
		$ips = array();
		foreach ($parts as $part) {
			if (filter_var($part, FILTER_VALIDATE_IP)) {
				$ips[$part] = $part;
			}
		}
		if ($ips === array()) {
			return array('*');
		}

		return array_values($ips);
	}

	public static function normalizeMaxBytes(int $max): int
	{
		if ($max < 1024 || $max > 1048576) {
			return self::DEFAULT_MAX_BYTES;
		}

		return $max;
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $server
	 */
	private function guard(string $method, string $rawBody, array $query, array $server): ?CoffeejiWebhookResult
	{
		if (!$this->config->enabled() || !$this->tokenMatches((string) ($query['token'] ?? ''))) {
			return self::result(403, '{"code":403,"success":false,"data":false,"msg":"Forbidden"}');
		}
		if (!$this->ipAllowed((string) ($server['REMOTE_ADDR'] ?? ''))) {
			return self::result(403, '{"code":403,"success":false,"data":false,"msg":"Forbidden"}');
		}
		if (strtoupper($method) !== 'POST') {
			return self::result(405, '{"code":405,"success":false,"data":false,"msg":"Method Not Allowed"}');
		}
		$max = self::normalizeMaxBytes($this->config->maxBytes);
		$length = (int) ($server['CONTENT_LENGTH'] ?? 0);
		if ($length > $max || strlen($rawBody) > $max) {
			return self::result(413, '{"code":413,"success":false,"data":false,"msg":"Payload Too Large"}');
		}

		return null;
	}

	private function accept(string $rawBody): CoffeejiWebhookResult
	{
		$trimmed = ltrim($rawBody);
		$decoded = json_decode($rawBody, true, 32, JSON_BIGINT_AS_STRING);
		$isObject = $trimmed !== '' && $trimmed[0] === '{' && is_array($decoded) && !array_is_list($decoded);
		if ($trimmed === '{}' ) {
			$isObject = true;
			$decoded = array();
		}
		if (!$isObject || !is_array($decoded)) {
			$this->repository->insertWebhookEvent(
				'invalid',
				'',
				'',
				'',
				self::rawEnvelope($rawBody),
				hash('sha256', 'invalid' . "\n" . self::rawEnvelope($rawBody)),
				'error',
				'Kein JSON-Objekt.'
			);
			$this->logger?->error('Coffeeji-Webhook: Body ist kein JSON-Objekt.');

			return self::success();
		}

		$clean = self::clean($decoded);
		$deviceId = trim((string) ($clean['deviceId'] ?? ''));
		$deviceNo = trim((string) ($clean['deviceNo'] ?? ''));
		$orderNo = trim((string) ($clean['orderNo'] ?? ''));
		$type = self::detectType($clean);
		if ($type === null) {
			$this->persist($clean, 'unrecognized', $deviceId, $deviceNo, self::hashPayload('unrecognized', $clean), 'unrecognized', 'Ereignistyp nicht erkannt.', $orderNo);
			$this->logger?->error(self::logLine('Ereignistyp nicht erkannt', 'unrecognized', $deviceId, $orderNo));

			return self::success();
		}
		if ($type !== 'order') {
			$orderNo = '';
		}

		$hash = self::hashPayload($type, $clean);
		$duplicate = $this->repository->webhookHashAccepted($hash);
		$id = $this->persist(
			$clean,
			$type,
			$deviceId,
			$deviceNo,
			$hash,
			$duplicate ? 'duplicate' : 'received',
			null,
			$orderNo
		);
		if ($duplicate) {
			$this->logger?->info(self::logLine('Duplikat', $type, $deviceId, $orderNo));

			return self::success();
		}

		try {
			$this->apply($type, $clean);
			$this->repository->markWebhookEvent($id, 'accepted', null);
			$this->logger?->info(self::logLine('angenommen', $type, $deviceId, $orderNo));
		} catch (Throwable $e) {
			$message = $e instanceof CoffeejiException ? $e->getMessage() : 'Übernahme fehlgeschlagen.';
			try {
				$this->repository->markWebhookEvent($id, 'error', $message);
			} catch (Throwable $ignored) {
			}
			$this->logger?->error(self::logLine($message, $type, $deviceId, $orderNo));
		}

		return self::success();
	}

	/**
	 * @param array<string, mixed> $clean
	 */
	private function apply(string $type, array $clean): void
	{
		$deviceId = trim((string) ($clean['deviceId'] ?? ''));
		$deviceNo = trim((string) ($clean['deviceNo'] ?? ''));
		if ($type === 'device_fault') {
			$this->repository->applyWebhookDevice($deviceId, $deviceNo, array(
				'device_run_status' => self::nullableInt($clean['deviceRunStatus'] ?? null),
				'exception_remark' => trim((string) ($clean['exceptionRemark'] ?? '')),
			));

			return;
		}
		if ($type === 'material_alarm') {
			$this->repository->applyWebhookDevice($deviceId, $deviceNo, array(
				'scarce_status' => self::nullableInt($clean['deviceScarceStatus'] ?? null),
				'scarce_remark' => trim((string) ($clean['scarceRemark'] ?? '')),
			));

			return;
		}

		$mapped = self::mapOrderStatus((int) ($clean['orderStatus'] ?? 0));
		if (array_key_exists('refundAmount', $clean) && (float) CoffeejiOrder::money($clean['refundAmount']) > 0 && $mapped['refund_status'] === null) {
			$mapped['refund_status'] = 1;
		}
		$order = array(
			'order_no' => trim((string) ($clean['orderNo'] ?? '')),
			'device_id' => $deviceId,
			'status' => $mapped['status'],
			'refund_status' => $mapped['refund_status'],
			'create_time' => self::eventTime($clean),
			'payload' => $clean,
		);
		if (array_key_exists('payAmount', $clean)) {
			$order['pay_amt'] = CoffeejiOrder::money($clean['payAmount']);
		}
		$this->repository->applyWebhookOrder($order);
	}

	/**
	 * @param array<string, mixed> $clean
	 */
	private function persist(
		array $clean,
		string $type,
		string $deviceId,
		string $deviceNo,
		string $hash,
		string $status,
		?string $error,
		string $orderNo = ''
	): int {
		$json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			$json = '{}';
		}

		return $this->repository->insertWebhookEvent($type, $deviceId, $deviceNo, $orderNo, $json, $hash, $status, $error);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function eventTime(array $payload): ?string
	{
		foreach (array('timeStamp', 'timestamp') as $key) {
			if (!array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
				continue;
			}

			return CoffeejiTime::storeWebhook($payload[$key]);
		}

		return null;
	}

	private static function nullableInt(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		return (int) $value;
	}

	private function tokenMatches(string $given): bool
	{
		$expected = trim($this->config->token);
		$given = trim($given);
		if ($expected === '' || $given === '') {
			return false;
		}

		return hash_equals(hash('sha256', $expected, true), hash('sha256', $given, true));
	}

	private function ipAllowed(string $ip): bool
	{
		if ($this->config->allowedIps === array()) {
			return true;
		}
		$ip = trim($ip);
		foreach ($this->config->allowedIps as $allowed) {
			if ($allowed === '*') {
				continue;
			}
			if ($ip !== '' && hash_equals($allowed, $ip)) {
				return true;
			}
		}

		return false;
	}

	private static function rawEnvelope(string $raw): string
	{
		$redacted = CoffeejiLogger::redact($raw);
		if (strlen($redacted) > 20000) {
			$redacted = substr($redacted, 0, 20000);
		}
		$json = json_encode(array('raw' => $redacted), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($json === false) {
			return '{"raw":"[nicht speicherbar]"}';
		}

		return $json;
	}

	/**
	 * @param mixed $value
	 */
	private static function canonicalize(mixed $value): string
	{
		$json = json_encode(self::sortKeys($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $json === false ? '' : $json;
	}

	private static function sortKeys(mixed $value): mixed
	{
		if (!is_array($value)) {
			return $value;
		}
		if (array_is_list($value)) {
			$items = array();
			foreach ($value as $item) {
				$items[] = self::sortKeys($item);
			}

			return $items;
		}
		ksort($value);
		$out = array();
		foreach ($value as $key => $item) {
			$out[$key] = self::sortKeys($item);
		}

		return $out;
	}

	private static function logLine(string $message, string $type, string $deviceId, string $orderNo): string
	{
		$order = $orderNo === '' ? '-' : $orderNo;

		return 'Coffeeji-Webhook ' . $message . '. Typ ' . $type . ', Gerät ' . $deviceId . ', Bestellung ' . $order . '.';
	}

	private static function success(): CoffeejiWebhookResult
	{
		return self::result(200, self::SUCCESS_BODY);
	}

	private static function result(int $status, string $body): CoffeejiWebhookResult
	{
		return new CoffeejiWebhookResult($status, $body);
	}
}
