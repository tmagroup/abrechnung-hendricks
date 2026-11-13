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
			'heart_time' => CoffeejiTime::store($row['heartTime'] ?? null),
			'device_type_name' => trim((string) ($row['deviceTypeName'] ?? '')),
			'soft_run_version' => trim((string) ($row['softRunVersion'] ?? '')),
			'raw' => $row,
		);
	}

	private static function nullableInt(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		return (int) $value;
	}
}
