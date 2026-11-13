<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiResponseTest extends TestCase
{
	public function testParseDropsDevicePasswordAndKeepsLargeIdsAsStrings(): void
	{
		$encoded = '{"code":200,"success":true,"msg":"ok","data":{"records":[{"id":9999999999999999999,"name":"Musterautomat","devicePwd":"nicht-speichern","deviceExt":{"devicePwd":"auch-nicht"}}],"total":1,"pages":1}}';

		$parsed = CoffeejiResponse::parse($encoded);

		$this->assertFalse(CoffeejiResponse::containsSecret($parsed));
		$this->assertSame('9999999999999999999', $parsed['data']['records'][0]['id']);
		$this->assertSame('Musterautomat', $parsed['data']['records'][0]['name']);
		$this->assertArrayNotHasKey('devicePwd', $parsed['data']['records'][0]);
		$this->assertArrayNotHasKey('devicePwd', $parsed['data']['records'][0]['deviceExt']);
	}

	public function testErrorMessageIsPreservedWithoutSecrets(): void
	{
		try {
			CoffeejiResponse::parse('{"code":400,"success":false,"msg":"密钥错误","devicePwd":"geheim"}');
			$this->fail('Fehlerantwort muss eine Ausnahme werfen.');
		} catch (CoffeejiException $e) {
			$this->assertSame('密钥错误', $e->getMessage());
			$this->assertSame(400, $e->apiCode());
			$this->assertStringNotContainsString('geheim', $e->getMessage());
		}
	}

	public function testDeviceNormalizerDropsPasswordBeforeStorageShape(): void
	{
		$device = CoffeejiDevice::normalize(array(
			'id' => '9000000000000000001',
			'deviceNo' => 'AABB',
			'deviceExtNo' => 'DEMO-101',
			'name' => 'Musterkiosk',
			'status' => 1,
			'deviceRunStatus' => 1,
			'devicePwd' => 'hash',
		));

		$this->assertSame('9000000000000000001', $device['id']);
		$this->assertArrayNotHasKey('devicePwd', $device['raw']);
		$this->assertFalse(CoffeejiResponse::containsSecret($device));
	}
}
