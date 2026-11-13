<?php

declare(strict_types=1);

class CoffeejiSignature
{
	public static function timestampMs(?int $nowMs = null): string
	{
		if ($nowMs === null) {
			$nowMs = (int) floor(microtime(true) * 1000);
		}

		return (string) $nowMs;
	}

	public static function keyMd5(string $key, string $secret, string $timestamp): string
	{
		return md5($key . $secret . $timestamp);
	}

	/**
	 * @return array{key: string, timestamp: string, key-md5: string}
	 */
	public static function headers(string $key, string $secret, ?string $timestamp = null): array
	{
		$timestamp = $timestamp ?? self::timestampMs();

		return array(
			'key' => $key,
			'timestamp' => $timestamp,
			'key-md5' => self::keyMd5($key, $secret, $timestamp),
		);
	}
}
