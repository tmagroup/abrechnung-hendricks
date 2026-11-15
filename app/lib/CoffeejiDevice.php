<?php

declare(strict_types=1);

class CoffeejiDevice
{
	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function normalize(array $row): array
	{
		$row = CoffeejiResponse::stripSecrets($row);
		if (!is_array($row)) {
			$row = array();
		}

		return array(
			'id' => trim((string) ($row['id'] ?? '')),
			'device_no' => trim((string) ($row['deviceNo'] ?? '')),
			'device_ext_no' => trim((string) ($row['deviceExtNo'] ?? '')),
			'name' => trim((string) ($row['name'] ?? '')),
			'status' => self::nullableInt($row['status'] ?? null),
			'device_run_status' => self::nullableInt($row['deviceRunStatus'] ?? null),
			'exception_remark' => trim((string) ($row['exceptionRemark'] ?? '')),
			'scarce_status' => self::nullableInt($row['scarceStatus'] ?? null),
			'scarce_remark' => trim((string) ($row['scarceRemark'] ?? '')),
			'heart_time' => self::heartTime($row),
			'device_type_name' => trim((string) ($row['deviceTypeName'] ?? '')),
			'soft_run_version' => trim((string) ($row['softRunVersion'] ?? '')),
			'raw' => $row,
		);
	}

	/**
	 * Ohne timeZoneId gilt die Gerätezone (Standard UTC-02:00) und wird wie eine
	 * Bestellzeit über CoffeejiTime::storeSource nach Europe/Berlin gelegt.
	 * Leer oder none stellt die alte Wanduhr her. Eine Zone im Payload gewinnt.
	 *
	 * @param array<string, mixed> $row
	 */
	private static function heartTime(array $row): ?string
	{
		$value = $row['heartTime'] ?? null;
		$zone = trim((string) ($row['timeZoneId'] ?? $row['time_zone_id'] ?? ''));
		if ($zone !== '') {
			return CoffeejiTime::storeSource($value, $zone);
		}

		$configured = CoffeejiTime::deviceSourceZone();
		if ($configured === null) {
			return CoffeejiTime::store($value);
		}

		return CoffeejiTime::storeSource($value, $configured);
	}

	private static function nullableInt(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		return (int) $value;
	}
}
