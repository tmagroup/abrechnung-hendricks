<?php

declare(strict_types=1);

class CoffeejiTime
{
	public const TZ = 'Europe/Berlin';

	public static function zone(): DateTimeZone
	{
		return new DateTimeZone(self::TZ);
	}

	public static function now(?DateTimeImmutable $now = null): DateTimeImmutable
	{
		return $now ?? new DateTimeImmutable('now', self::zone());
	}

	public static function parseLocal(mixed $value): ?DateTimeImmutable
	{
		if ($value === null) {
			return null;
		}
		if (is_int($value) || (is_string($value) && preg_match('/^\d{11,}$/', $value) === 1)) {
			$ms = (int) $value;
			if ($ms > 20000000000) {
				$ms = (int) floor($ms / 1000);
			}
			return (new DateTimeImmutable('@' . $ms))->setTimezone(self::zone());
		}

		$raw = trim((string) $value);
		if ($raw === '') {
			return null;
		}

		try {
			if (preg_match('/(?:[zZ]|[+\-]\d{2}:?\d{2})$/', $raw) === 1) {
				$dt = new DateTimeImmutable($raw);
			} else {
				$dt = new DateTimeImmutable($raw, self::zone());
			}
		} catch (Exception $e) {
			return null;
		}

		return $dt->setTimezone(self::zone());
	}

	public static function store(mixed $value): ?string
	{
		$dt = self::parseLocal($value);
		if ($dt === null) {
			return null;
		}

		return $dt->format('Y-m-d H:i:s');
	}

	/**
	 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
	 */
	public static function window(string $mode, ?DateTimeImmutable $now = null): array
	{
		$now = self::now($now);
		$today = $now->setTime(0, 0);
		if ($mode === 'recent') {
			return array($today->modify('-1 day'), $today->modify('+1 day'));
		}
		if ($mode === 'backfill') {
			return array($today->modify('-34 days'), $today->modify('+1 day'));
		}

		throw new InvalidArgumentException('Unbekanntes Zeitfenster.');
	}
}
