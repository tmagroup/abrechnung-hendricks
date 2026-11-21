<?php

declare(strict_types=1);

/**
 * Steuersätze der Gesamtabrechnung.
 *
 * Automatenabgabe gilt als Lieferung. Ohne hinterlegte Produktregel gilt 19 %.
 * 7 % nur, wenn das Produkt ausdrücklich konfiguriert ist: Milchmischgetränk mit
 * mindestens 75 % tierischer Milch (Frischmilch oder Milchpulver) und Nachweis,
 * oder reines heißes Wasser. Die Regeln sind ab gültig_ab wirksam, damit ein
 * späterer Rezepturwechsel alte Monate nicht umschreibt.
 *
 * Die Einordnung ist vom Steuerberater zu bestätigen. Der Ausgangszustand ist
 * 19 % für alle Produkte.
 */
class CoffeejiVat
{
	public const DEFAULT_RATE = 19;

	public const REDUCED_RATE = 7;

	/** @var list<string> */
	public const MILK_TYPES = array('frischmilch', 'milchpulver', 'topping', 'pflanzlich', 'keine');

	/** @var list<string> */
	public const ANIMAL_MILK = array('frischmilch', 'milchpulver');

	/**
	 * @param array<string, mixed> $rule
	 * @return list<string>
	 */
	public static function validate(array $rule): array
	{
		$errors = array();
		$name = trim((string) ($rule['goods_name'] ?? ''));
		$productId = trim((string) ($rule['product_id'] ?? ''));
		if ($name === '' && $productId === '') {
			$errors[] = 'goods_name oder product_id ist erforderlich.';
		}
		if (mb_strlen($name) > 255) {
			$errors[] = 'goods_name ist zu lang.';
		}
		if (mb_strlen($productId) > 64) {
			$errors[] = 'product_id ist zu lang.';
		}
		$rate = self::rateOrNull($rule['rate'] ?? null);
		if ($rate === null) {
			$errors[] = 'Der Satz muss 7 oder 19 sein.';
		}
		$milk = mb_strtolower(trim((string) ($rule['milk_type'] ?? 'keine')), 'UTF-8');
		if ($milk === '') {
			$milk = 'keine';
		}
		if (!in_array($milk, self::MILK_TYPES, true)) {
			$errors[] = 'Milchart ist ungültig.';
		}
		$share = self::share($rule['milk_share'] ?? null);
		if (self::shareProvided($rule['milk_share'] ?? null) && $share === null) {
			$errors[] = 'Milchanteil ist ungültig.';
		}
		$evidence = trim((string) ($rule['evidence'] ?? ''));
		if (mb_strlen($evidence) > 2000) {
			$errors[] = 'Nachweis ist zu lang.';
		}
		$hot = self::hotWater($rule['hot_water'] ?? false);
		if (self::day($rule['valid_from'] ?? null) === null) {
			$errors[] = 'gültig_ab muss ein Datum JJJJ-MM-TT sein.';
		}
		if ($rate === self::REDUCED_RATE) {
			$milkOk = in_array($milk, self::ANIMAL_MILK, true)
				&& $share !== null
				&& $share >= 75
				&& $evidence !== '';
			if (!$milkOk && !$hot) {
				$errors[] = '7 % nur bei Frischmilch oder Milchpulver mit mindestens 75 % Milchanteil und Nachweis, oder bei heißem Wasser.';
			}
		}

		return $errors;
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	public static function assertValid(array $rule): void
	{
		$errors = self::validate($rule);
		if ($errors !== array()) {
			throw new InvalidArgumentException(implode(' ', $errors));
		}
	}

	/**
	 * @param array<string, mixed> $rule
	 * @return array{goods_name: ?string, product_id: ?string, rate: int, milk_share: ?float, milk_type: string, evidence: ?string, hot_water: bool, valid_from: string}
	 */
	public static function normalize(array $rule): array
	{
		self::assertValid($rule);
		$name = trim((string) ($rule['goods_name'] ?? ''));
		$productId = trim((string) ($rule['product_id'] ?? ''));
		$evidence = trim((string) ($rule['evidence'] ?? ''));
		$milk = mb_strtolower(trim((string) ($rule['milk_type'] ?? 'keine')), 'UTF-8');
		if ($milk === '') {
			$milk = 'keine';
		}
		$rate = self::rateOrNull($rule['rate'] ?? null);
		$day = self::day($rule['valid_from'] ?? null);

		return array(
			'goods_name' => $name !== '' ? $name : null,
			'product_id' => $productId !== '' ? $productId : null,
			'rate' => (int) $rate,
			'milk_share' => self::share($rule['milk_share'] ?? null),
			'milk_type' => $milk,
			'evidence' => $evidence !== '' ? $evidence : null,
			'hot_water' => self::hotWater($rule['hot_water'] ?? false),
			'valid_from' => (string) $day,
		);
	}

	/**
	 * @param array<string, mixed> $order
	 * @param list<array<string, mixed>> $rules
	 * @return array{rate: int, configured: bool}
	 */
	public static function resolve(array $order, array $rules): array
	{
		$created = CoffeejiTime::parseLocal($order['create_time'] ?? null);
		$day = $created instanceof DateTimeImmutable
			? $created->setTimezone(CoffeejiTime::zone())->format('Y-m-d')
			: '';
		$name = self::normalizeName((string) ($order['goods_name'] ?? ''));
		if ($name === '') {
			$name = self::normalizeName('Ohne Bezeichnung');
		}
		$productId = self::orderProductId($order);
		$byId = null;
		$byName = null;
		foreach ($rules as $rule) {
			if (!is_array($rule) || self::validate($rule) !== array()) {
				continue;
			}
			$from = self::day($rule['valid_from'] ?? null);
			if ($day === '' || $from === null || $from > $day) {
				continue;
			}
			$ruleId = (int) ($rule['id'] ?? 0);
			$ruleName = self::normalizeName((string) ($rule['goods_name'] ?? ''));
			$ruleProduct = trim((string) ($rule['product_id'] ?? ''));
			$rate = self::rateOrNull($rule['rate'] ?? null);
			if ($rate === null) {
				continue;
			}
			$candidate = array('valid_from' => $from, 'id' => $ruleId, 'rate' => $rate);
			if ($productId !== '' && $ruleProduct !== '' && $ruleProduct === $productId) {
				if (self::prefer($candidate, $byId)) {
					$byId = $candidate;
				}
			}
			if ($ruleName !== '' && $ruleName === $name) {
				if (self::prefer($candidate, $byName)) {
					$byName = $candidate;
				}
			}
		}
		$chosen = $byId ?? $byName;
		if ($chosen === null) {
			return array('rate' => self::DEFAULT_RATE, 'configured' => false);
		}

		return array('rate' => (int) $chosen['rate'], 'configured' => true);
	}

	public static function normalizeName(string $name): string
	{
		$name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

		return mb_strtolower($name, 'UTF-8');
	}

	/**
	 * @param array<string, mixed> $order
	 */
	public static function orderProductId(array $order): string
	{
		foreach (array('product_id', 'goods_id', 'goodsId') as $key) {
			$value = trim((string) ($order[$key] ?? ''));
			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}

	/**
	 * @param array{valid_from: string, id: int, rate: int} $candidate
	 * @param array{valid_from: string, id: int, rate: int}|null $current
	 */
	private static function prefer(array $candidate, ?array $current): bool
	{
		if ($current === null) {
			return true;
		}
		if ($candidate['valid_from'] > $current['valid_from']) {
			return true;
		}
		if ($candidate['valid_from'] < $current['valid_from']) {
			return false;
		}

		return $candidate['id'] >= $current['id'];
	}

	private static function rateOrNull(mixed $rate): ?int
	{
		if (is_int($rate)) {
			$value = $rate;
		} elseif (is_float($rate)) {
			$value = (int) round($rate);
		} elseif (is_string($rate) && preg_match('/^\d+(?:\.0+)?$/', trim($rate)) === 1) {
			$value = (int) round((float) $rate);
		} else {
			return null;
		}
		if ($value !== self::DEFAULT_RATE && $value !== self::REDUCED_RATE) {
			return null;
		}

		return $value;
	}

	private static function shareProvided(mixed $value): bool
	{
		if ($value === null) {
			return false;
		}

		return trim((string) $value) !== '';
	}

	private static function share(mixed $value): ?float
	{
		if (!self::shareProvided($value)) {
			return null;
		}
		$text = str_replace(',', '.', trim((string) $value));
		if (!is_numeric($text)) {
			return null;
		}

		return round((float) $text, 2);
	}

	private static function hotWater(mixed $value): bool
	{
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value) || is_float($value)) {
			return (int) $value === 1;
		}
		$text = mb_strtolower(trim((string) $value), 'UTF-8');

		return in_array($text, array('1', 'true', 'ja', 'yes', 'heiss', 'heiß', 'heißes wasser', 'heisses wasser'), true);
	}

	private static function day(mixed $value): ?string
	{
		$text = trim((string) $value);
		if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $text, $match) !== 1) {
			return null;
		}
		$day = $match[1];
		$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day, CoffeejiTime::zone());
		if (!$parsed || $parsed->format('Y-m-d') !== $day) {
			return null;
		}

		return $day;
	}
}
