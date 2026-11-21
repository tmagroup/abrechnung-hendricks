<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiVatTest extends TestCase
{
	public function testUnconfiguredProductIsNineteenPercent(): void
	{
		$rate = CoffeejiVat::resolve($this->order('Cappuccino', '2026-11-10 10:00:00'), array());

		$this->assertSame(19, $rate['rate']);
		$this->assertFalse($rate['configured']);
	}

	public function testSevenPercentNeedsAnimalMilkShareAndEvidenceOrHotWater(): void
	{
		$validMilk = $this->rule('Latte', 7, '2026-11-01', 'frischmilch', 75, 'Rezeptur Labor 2026-01');
		$validPowder = $this->rule('Kakao', 7, '2026-11-01', 'milchpulver', 80, 'Lieferantenblatt');
		$validWater = $this->rule('Heißes Wasser', 7, '2026-11-01', 'keine', null, '', true);
		$validStandard = $this->rule('Espresso', 19, '2026-11-01', 'keine', null, '');

		$this->assertSame(array(), CoffeejiVat::validate($validMilk));
		$this->assertSame(array(), CoffeejiVat::validate($validPowder));
		$this->assertSame(array(), CoffeejiVat::validate($validWater));
		$this->assertSame(array(), CoffeejiVat::validate($validStandard));

		$this->assertNotEmpty(CoffeejiVat::validate($this->rule('Latte', 7, '2026-11-01', 'topping', 90, 'Karte')));
		$this->assertNotEmpty(CoffeejiVat::validate($this->rule('Latte', 7, '2026-11-01', 'pflanzlich', 90, 'Karte')));
		$this->assertNotEmpty(CoffeejiVat::validate($this->rule('Latte', 7, '2026-11-01', 'frischmilch', 74.99, 'Karte')));
		$this->assertNotEmpty(CoffeejiVat::validate($this->rule('Latte', 7, '2026-11-01', 'frischmilch', 75, '')));
		$this->assertNotEmpty(CoffeejiVat::validate($this->rule('Latte', 7, '2026-11-01', 'keine', null, '')));
		$this->assertNotEmpty(CoffeejiVat::validate($this->rule('', 19, '2026-11-01', 'keine', null, '')));
		$this->assertNotEmpty(CoffeejiVat::validate($this->rule('Latte', 5, '2026-11-01', 'keine', null, '')));
	}

	public function testValidFromSwitchesInsideTheMonthAndUnknownProductsStayAtNineteen(): void
	{
		$rules = array(
			$this->rule('Cappuccino', 19, '2026-11-01', 'keine', null, 'Standardkaffee'),
			$this->rule('Cappuccino', 7, '2026-11-15', 'frischmilch', 80, 'Rezeptur ab 15.11.'),
		);
		$before = CoffeejiVat::resolve($this->order('Cappuccino', '2026-11-14 23:59:59'), $rules);
		$onDay = CoffeejiVat::resolve($this->order('cappuccino', '2026-11-15 00:00:00'), $rules);
		$unknown = CoffeejiVat::resolve($this->order('Espresso', '2026-11-20 12:00:00'), $rules);

		$this->assertSame(19, $before['rate']);
		$this->assertTrue($before['configured']);
		$this->assertSame(7, $onDay['rate']);
		$this->assertTrue($onDay['configured']);
		$this->assertSame(19, $unknown['rate']);
		$this->assertFalse($unknown['configured']);
	}

	public function testInvalidSevenPercentRuleDoesNotApply(): void
	{
		$rules = array(
			$this->rule('Cappuccino', 7, '2026-01-01', 'pflanzlich', 90, 'Hafer'),
		);
		$rate = CoffeejiVat::resolve($this->order('Cappuccino', '2026-11-10 10:00:00'), $rules);

		$this->assertSame(19, $rate['rate']);
		$this->assertFalse($rate['configured']);
	}

	public function testProductIdBeatsNameAndFutureRulesDoNotApply(): void
	{
		$rules = array(
			array_merge($this->rule('Cappuccino', 19, '2026-01-01', 'keine', null, 'Name'), array('product_id' => null)),
			array_merge($this->rule('Anders', 7, '2026-11-01', 'milchpulver', 90, 'Id-Regel'), array('product_id' => 'P-7', 'goods_name' => null)),
			array_merge($this->rule('Anders', 7, '2026-12-01', 'milchpulver', 90, 'Zukunft'), array('product_id' => 'P-7', 'goods_name' => null)),
		);
		$order = $this->order('Cappuccino', '2026-11-20 08:00:00');
		$order['product_id'] = 'P-7';
		$resolved = CoffeejiVat::resolve($order, $rules);

		$this->assertSame(7, $resolved['rate']);
		$this->assertTrue($resolved['configured']);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function order(string $goods, string $time): array
	{
		return array(
			'goods_name' => $goods,
			'create_time' => $time,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function rule(string $goods, int $rate, string $from, string $milk, ?float $share, string $evidence, bool $hot = false): array
	{
		return array(
			'goods_name' => $goods,
			'product_id' => null,
			'rate' => $rate,
			'milk_type' => $milk,
			'milk_share' => $share,
			'evidence' => $evidence,
			'hot_water' => $hot,
			'valid_from' => $from,
		);
	}
}
