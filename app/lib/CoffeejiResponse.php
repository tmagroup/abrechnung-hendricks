<?php

declare(strict_types=1);

class CoffeejiResponse
{
	/**
	 * @return array<string, mixed>
	 */
	public static function parse(string $body): array
	{
		$decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
		if (!is_array($decoded)) {
			throw new CoffeejiException('Die Coffeeji-Antwort ist kein JSON.');
		}

		$code = (int) ($decoded['code'] ?? 0);
		$success = $decoded['success'] ?? null;
		if ($code !== 200 || $success === false) {
			$message = (string) ($decoded['msg'] ?? $decoded['message'] ?? 'Unbekannter Fehler');
			throw new CoffeejiException($message, $code);
		}

		$clean = self::stripSecrets($decoded);
		if (!is_array($clean)) {
			throw new CoffeejiException('Die Coffeeji-Antwort ist kein JSON.');
		}

		return $clean;
	}

	public static function stripSecrets(mixed $value): mixed
	{
		if (!is_array($value)) {
			return $value;
		}

		$out = array();
		foreach ($value as $key => $item) {
			if (is_string($key) && strcasecmp($key, 'devicePwd') === 0) {
				continue;
			}
			$out[$key] = self::stripSecrets($item);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $parsed
	 * @return list<array<string, mixed>>
	 */
	public static function records(array $parsed): array
	{
		$data = $parsed['data'] ?? null;
		if (!is_array($data)) {
			return array();
		}
		if (isset($data['records']) && is_array($data['records'])) {
			return array_values(array_filter($data['records'], 'is_array'));
		}
		if (array_is_list($data)) {
			return array_values(array_filter($data, 'is_array'));
		}

		return array();
	}

	/**
	 * @param array<string, mixed> $parsed
	 */
	public static function pages(array $parsed): int
	{
		$data = $parsed['data'] ?? null;
		if (!is_array($data)) {
			return 1;
		}
		$pages = (int) ($data['pages'] ?? 1);

		return max(1, $pages);
	}

	public static function containsSecret(mixed $value): bool
	{
		if (!is_array($value)) {
			return false;
		}
		foreach ($value as $key => $item) {
			if (is_string($key) && strcasecmp($key, 'devicePwd') === 0) {
				return true;
			}
			if (self::containsSecret($item)) {
				return true;
			}
		}

		return false;
	}
}
