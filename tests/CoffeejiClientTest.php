<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiClientTest extends TestCase
{
	public function testOrderListIsGetAndStripsDevicePassword(): void
	{
		$log = tempnam(sys_get_temp_dir(), 'cj');
		$this->assertIsString($log);
		$calls = array();
		$client = $this->client($log, $calls, json_encode(array(
			'code' => 200,
			'success' => true,
			'data' => array(
				'records' => array(array(
					'orderNo' => 'DEMO-1001',
					'deviceId' => '9000000000000000001',
					'devicePwd' => 'darf-nicht-ankommen',
					'payAmt' => '1.20',
					'status' => 2,
					'createTime' => '2026-11-13 10:00:00',
				)),
				'pages' => 1,
			),
		), JSON_UNESCAPED_UNICODE));

		$parsed = $client->orderList(1, 20, '2026-11-12', '2026-11-13');

		$this->assertSame('GET', $calls[0]['method']);
		$this->assertStringContainsString('/coffee/newThird/order/getOrderList?', $calls[0]['url']);
		$this->assertStringContainsString('startDate=2026-11-12', $calls[0]['url']);
		$this->assertStringContainsString('endDate=2026-11-13', $calls[0]['url']);
		$this->assertNull($calls[0]['body']);
		$this->assertFalse(CoffeejiResponse::containsSecret($parsed));
		$logged = (string) file_get_contents($log);
		$this->assertStringNotContainsString('test-secret', $logged);
		$this->assertStringNotContainsString('darf-nicht-ankommen', $logged);
		@unlink($log);
	}

	public function testAllowedPathsAndBlockedWrites(): void
	{
		CoffeejiClient::assertPathAllowed('/coffee/newThird/device/page');
		CoffeejiClient::assertPathAllowed('/coffee/newThird/goods/list');
		CoffeejiClient::assertPathAllowed('/coffee/newThird/goods/deviceGoodsList');
		CoffeejiClient::assertPathAllowed('/coffee/newThird/device/detail/9000000000000000001');
		CoffeejiClient::assertPathAllowed('/coffee/newThird/material/9000000000000000001');

		$blocked = array(
			'/coffee/newThird/goods/save',
			'/coffee/newThird/goods/update',
			'/coffee/newThird/goods/listing',
			'/coffee/newThird/goods/delisting',
			'/coffee/newThird/material/replenish/9000000000000000001',
			'/coffee/newThird/order/produceCoffee',
			'/coffee/newThird/active/submit',
		);
		foreach ($blocked as $path) {
			try {
				CoffeejiClient::assertPathAllowed($path);
				$this->fail($path . ' darf nicht erlaubt sein.');
			} catch (CoffeejiException $e) {
				$this->assertStringContainsString('nicht freigegeben', $e->getMessage());
			}
		}
	}

	public function testClientSourceHasNoWriteEndpoints(): void
	{
		$source = (string) file_get_contents(__DIR__ . '/../app/lib/CoffeejiClient.php');
		foreach (array('goods/save', 'goods/update', 'goods/listing', 'goods/delisting', 'replenish', 'produceCoffee', '/active/') as $needle) {
			$this->assertStringNotContainsString($needle, $source);
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $calls
	 */
	private function client(string $log, array &$calls, string|false $body): CoffeejiClient
	{
		return new CoffeejiClient(
			'https://vdopen.coffeeji.com',
			'test-key',
			'test-secret',
			new CoffeejiLogger($log),
			function (string $method, string $url, array $headers, ?string $requestBody) use (&$calls, $body): array {
				$calls[] = array(
					'method' => $method,
					'url' => $url,
					'headers' => $headers,
					'body' => $requestBody,
				);

				return array('status' => 200, 'body' => (string) $body);
			}
		);
	}
}
