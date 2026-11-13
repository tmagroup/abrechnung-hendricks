<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiSignatureTest extends TestCase
{
	public function testKeyMd5IsLowercaseConcatenation(): void
	{
		$key = 'test-key';
		$secret = 'test-secret';
		$timestamp = '1700000000000';

		$hash = CoffeejiSignature::keyMd5($key, $secret, $timestamp);

		$this->assertSame(md5($key . $secret . $timestamp), $hash);
		$this->assertSame(strtolower($hash), $hash);
		$this->assertSame(32, strlen($hash));
	}

	public function testHeadersUseTheSameTimestamp(): void
	{
		$headers = CoffeejiSignature::headers('abc', 'xyz', '1752731459238');

		$this->assertSame('abc', $headers['key']);
		$this->assertSame('1752731459238', $headers['timestamp']);
		$this->assertSame(md5('abcxyz1752731459238'), $headers['key-md5']);
	}
}
