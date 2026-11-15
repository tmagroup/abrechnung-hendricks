<?php

declare(strict_types=1);

class CoffeejiTime
{
	public const TZ = 'Europe/Berlin';

	/** Ost-8, wenn die Nutzlast keine timeZoneId trägt. So steht es in der Coffeeji-Doku. */
	public const DOCUMENTED_ZONE = 'UTC+8';

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

	/**
	 * Wanduhr aus Coffeeji nach Europe/Berlin (MEZ/MESZ).
	 *
	 * Epoch-Werte und Zeichenketten mit Z oder Offset sind absolute Zeitpunkte.
	 * Eine Angabe ohne Offset ist die Wanduhr von $timeZoneId. Fehlt die Zone
	 * oder ist sie leer, gilt die in der Coffeeji-Doku genannte Ost-8-Zeit
	 * (UTC+8). Erkannte Formen: UTC+8, UTC+08:00, UTC+01:00, GMT+8 und IANA-Namen.
	 * parseLocal bleibt für bereits gespeicherte Berlin-Zeiten zuständig.
	 */
	public static function storeSource(mixed $value, ?string $timeZoneId = null): ?string
	{
		$dt = self::parseSource($value, $timeZoneId);
		if ($dt === null) {
			return null;
		}

		return $dt->format('Y-m-d H:i:s');
	}

	public static function parseSource(mixed $value, ?string $timeZoneId = null): ?DateTimeImmutable
	{
		if ($value === null) {
			return null;
		}
		if (self::isAbsolute($value)) {
			return self::parseLocal($value);
		}

		$raw = trim((string) $value);
		if ($raw === '') {
			return null;
		}
		if (preg_match('/(?:[zZ]|[+\-]\d{2}:?\d{2})$/', $raw) === 1) {
			return self::parseLocal($raw);
		}

		$zone = self::resolveZone($timeZoneId);
		if ($zone === null) {
			return self::parseLocal($raw);
		}

		try {
			$dt = new DateTimeImmutable($raw, $zone);
		} catch (Exception $e) {
			return null;
		}

		return $dt->setTimezone(self::zone());
	}

	public static function resolveZone(?string $timeZoneId): ?DateTimeZone
	{
		$id = trim((string) $timeZoneId);
		if ($id === '') {
			$id = self::DOCUMENTED_ZONE;
		}
		$fixed = self::fixedOffset($id);
		if ($fixed !== null) {
			try {
				return new DateTimeZone($fixed);
			} catch (Exception $e) {
				return null;
			}
		}

		try {
			return new DateTimeZone($id);
		} catch (Exception $e) {
			return null;
		}
	}

	private static function isAbsolute(mixed $value): bool
	{
		return is_int($value) || (is_string($value) && preg_match('/^\d{11,}$/', trim($value)) === 1);
	}

	private static function fixedOffset(string $id): ?string
	{
		$id = trim($id);
		if (preg_match('/^(?:UTC|GMT)\s*([+-])\s*(\d{1,2})(?::?(\d{2}))?$/i', $id, $match) !== 1
			&& preg_match('/^([+-])(\d{2})(?::?(\d{2}))?$/', $id, $match) !== 1) {
			return null;
		}
		$hours = (int) $match[2];
		$minutes = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : 0;
		if ($hours > 14 || $minutes > 59) {
			return null;
		}

		return sprintf('%s%02d:%02d', $match[1], $hours, $minutes);
	}
}
