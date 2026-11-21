<?php

declare(strict_types=1);

class AbrechnungId
{
	public static function fromBase64(mixed $value): ?int
	{
		$decoded = base64_decode((string) $value, true);
		if (!is_string($decoded) || preg_match('/^[1-9][0-9]{0,17}$/', $decoded) !== 1) {
			return null;
		}
		$id = (int) $decoded;
		if ((string) $id !== $decoded) {
			return null;
		}

		return $id;
	}
}
