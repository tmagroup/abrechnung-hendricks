<?php

declare(strict_types=1);

class CoffeejiMaterial
{
	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function normalize(array $row): array
	{
		$materialId = trim((string) ($row['materialId'] ?? ''));
		if ($materialId === '') {
			$materialId = trim((string) ($row['id'] ?? ''));
		}
		$name = trim((string) ($row['name'] ?? ''));
		$position = trim((string) ($row['position'] ?? ''));
		$key = $materialId !== '' ? $materialId : substr($name . '|' . $position, 0, 80);

		return array(
			'material_key' => $key,
			'material_id' => $materialId,
			'name' => $name,
			'position' => $position,
			'capacity' => self::qty($row['capacity'] ?? 0),
			'residue_qty' => self::qty($row['residueQty'] ?? 0),
			'warn_capacity' => self::qty($row['warnCapacity'] ?? 0),
			'unit' => trim((string) ($row['unit'] ?? '')),
		);
	}

	public static function qty(mixed $value): string
	{
		if (is_string($value)) {
			$value = str_replace(',', '.', $value);
		}

		return number_format((float) $value, 2, '.', '');
	}
}
