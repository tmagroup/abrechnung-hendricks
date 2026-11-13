<?php

declare(strict_types=1);

class CoffeejiClient
{
	public const ALLOWED_PATHS = array(
		'/coffee/newThird/device/page',
		'/coffee/newThird/device/detail/',
		'/coffee/newThird/material/',
		'/coffee/newThird/order/getOrderList',
		'/coffee/newThird/goods/list',
		'/coffee/newThird/goods/deviceGoodsList',
	);

	/** @var callable|null */
	private $transport;

	public function __construct(
		private string $baseUrl,
		private string $key,
		private string $secret,
		private ?CoffeejiLogger $logger = null,
		?callable $transport = null,
		private int $timeout = 25
	) {
		$this->transport = $transport;
	}

	public static function assertPathAllowed(string $path): void
	{
		$exact = array(
			'/coffee/newThird/device/page',
			'/coffee/newThird/order/getOrderList',
			'/coffee/newThird/goods/list',
			'/coffee/newThird/goods/deviceGoodsList',
		);
		if (in_array($path, $exact, true)) {
			return;
		}
		if (preg_match('#^/coffee/newThird/device/detail/[0-9]{1,32}$#', $path) === 1) {
			return;
		}
		if (preg_match('#^/coffee/newThird/material/[0-9]{1,32}$#', $path) === 1) {
			return;
		}

		throw new CoffeejiException('Dieser Coffeeji-Endpunkt ist nicht freigegeben.');
	}

	/**
	 * @return array<string, mixed>
	 */
	public function devicePage(int $current, int $size): array
	{
		return $this->request('POST', '/coffee/newThird/device/page', array(
			'current' => $current,
			'size' => $size,
		));
	}

	/**
	 * @return array<string, mixed>
	 */
	public function deviceDetail(string $id): array
	{
		$this->assertId($id);

		return $this->request('GET', '/coffee/newThird/device/detail/' . $id, null);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function material(string $deviceId): array
	{
		$this->assertId($deviceId);

		return $this->request('GET', '/coffee/newThird/material/' . $deviceId, null);
	}

	/**
	 * Bestellliste nur per GET mit Query-Parametern. POST ist in der Praxis wirkungslos.
	 *
	 * @return array<string, mixed>
	 */
	public function orderList(int $current, int $size, string $startDate, string $endDate): array
	{
		return $this->request('GET', '/coffee/newThird/order/getOrderList', null, array(
			'current' => $current,
			'size' => $size,
			'startDate' => $startDate,
			'endDate' => $endDate,
		));
	}

	/**
	 * @param array<string, scalar> $filters
	 * @return array<string, mixed>
	 */
	public function goodsList(int $current, int $size, array $filters = array()): array
	{
		$body = array('current' => $current, 'size' => $size);
		foreach ($filters as $key => $value) {
			if (in_array($key, array('deviceTypeName', 'deviceTypeId', 'no', 'name'), true)) {
				$body[$key] = $value;
			}
		}

		return $this->request('POST', '/coffee/newThird/goods/list', $body);
	}

	/**
	 * @param list<string> $deviceIds
	 * @return array<string, mixed>
	 */
	public function deviceGoodsList(array $deviceIds): array
	{
		$ids = array();
		foreach ($deviceIds as $id) {
			$this->assertId((string) $id);
			$ids[] = (string) $id;
		}

		return $this->request('POST', '/coffee/newThird/goods/deviceGoodsList', array(
			'deviceIds' => $ids,
		));
	}

	private function assertId(string $id): void
	{
		if (preg_match('/^[0-9]{1,32}$/', $id) !== 1) {
			throw new CoffeejiException('Ungültige Kennung.');
		}
	}

	/**
	 * @param array<string, mixed>|null $json
	 * @param array<string, scalar> $query
	 * @return array<string, mixed>
	 */
	private function request(string $method, string $path, ?array $json, array $query = array()): array
	{
		self::assertPathAllowed($path);
		$headers = CoffeejiSignature::headers($this->key, $this->secret);
		$url = rtrim($this->baseUrl, '/') . $path;
		if ($query !== array()) {
			$url .= '?' . http_build_query($query);
		}
		$body = $json === null ? null : json_encode($json, JSON_UNESCAPED_UNICODE);
		if ($json !== null && $body === false) {
			throw new CoffeejiException('Die Anfrage konnte nicht kodiert werden.');
		}

		$headerLines = array(
			'Accept: application/json',
			'key: ' . $headers['key'],
			'timestamp: ' . $headers['timestamp'],
			'key-md5: ' . $headers['key-md5'],
		);
		if ($body !== null) {
			$headerLines[] = 'Content-Type: application/json; charset=utf-8';
		}

		try {
			$response = $this->send($method, $url, $headerLines, $body);
			$parsed = CoffeejiResponse::parse($response['body']);
			$count = count(CoffeejiResponse::records($parsed));
			$this->logger?->info($method . ' ' . $path . ' code=200 records=' . $count);
			return $parsed;
		} catch (CoffeejiException $e) {
			$this->logger?->error($method . ' ' . $path . ' ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * @param list<string> $headerLines
	 * @return array{status: int, body: string}
	 */
	private function send(string $method, string $url, array $headerLines, ?string $body): array
	{
		if ($this->transport !== null) {
			$result = ($this->transport)($method, $url, $headerLines, $body);
			if (!is_array($result) || !isset($result['status'], $result['body'])) {
				throw new CoffeejiException('Ungültige Transport-Antwort.');
			}

			return array(
				'status' => (int) $result['status'],
				'body' => (string) $result['body'],
			);
		}

		if (!function_exists('curl_init')) {
			throw new CoffeejiException('cURL ist auf dem Server nicht verfügbar.');
		}

		$ch = curl_init($url);
		if ($ch === false) {
			throw new CoffeejiException('Die Verbindung konnte nicht geöffnet werden.');
		}
		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => $headerLines,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
		);
		if ($body !== null) {
			$options[CURLOPT_POSTFIELDS] = $body;
		}
		curl_setopt_array($ch, $options);
		$raw = curl_exec($ch);
		if ($raw === false) {
			curl_close($ch);
			throw new CoffeejiException('Coffeeji ist nicht erreichbar.');
		}
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);
		if ($status < 200 || $status >= 300) {
			throw new CoffeejiException('Coffeeji hat HTTP ' . $status . ' geantwortet.');
		}

		return array('status' => $status, 'body' => (string) $raw);
	}
}
