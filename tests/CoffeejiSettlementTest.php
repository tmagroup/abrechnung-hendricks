<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiSettlementTest extends TestCase
{
	public function testRevenueMatchesOrdersPageExceptFreeAndRedemption(): void
	{
		$orders = array(
			$this->order('A', 2, '2.50', '2026-11-10 10:00:00', '现金支付', 'H-101', 'Espresso'),
			$this->order('B', 1, '1.50', '2026-11-10 11:00:00', '刷卡支付', 'H-101', 'Espresso', 1),
			$this->order('C', 2, '4.00', '2026-11-11 09:00:00', '免费', 'H-102', 'Espresso'),
			$this->order('D', 2, '3.00', '2026-11-11 10:00:00', '兑换订单', 'H-102', 'Espresso'),
			$this->order('E', 6, '9.00', '2026-11-11 12:00:00', '现金', 'H-102', 'Espresso'),
			$this->order('F', 0, '5.00', '2026-11-12 12:00:00', 'Cash', 'H-101', 'Espresso'),
			$this->order('G', 7, '8.00', '2026-11-12 13:00:00', 'Card', 'H-101', 'Espresso'),
			$this->order('H', 2, '1.00', '2026-11-12 14:00:00', '微信支付', 'H-101', 'Espresso'),
		);
		$from = new DateTimeImmutable('2026-11-01 00:00:00', new DateTimeZone('Europe/Berlin'));
		$to = new DateTimeImmutable('2026-12-01 00:00:00', new DateTimeZone('Europe/Berlin'));
		$built = CoffeejiSettlement::build($orders, $from, $to, array(), 'The Hendricks Coffee');
		$pay = CoffeejiPortal::byPayWay($orders, $from, $to);
		$indexed = array();
		foreach ($pay as $row) {
			$indexed[$row['key']] = $row;
		}
		$portalCents = 0;
		foreach ($orders as $order) {
			$created = CoffeejiTime::parseLocal($order['create_time']);
			$included = CoffeejiOrder::countsForPortalRevenue((int) $order['status'])
				&& $created instanceof DateTimeImmutable
				&& $created >= $from
				&& $created < $to;
			$this->assertSame($included, CoffeejiOrder::isSale($order) && $created >= $from && $created < $to);
			if ($included) {
				$portalCents += CoffeejiSettlement::toCents($order['pay_amt']);
			}
		}

		$this->assertSame($portalCents, $built['totals']['portal_revenue_cents']);
		$this->assertSame(4.0, $indexed['gratis']['amount']);
		$this->assertSame(3.0, $indexed['einloesung']['amount']);
		$this->assertSame(250, $built['totals']['cash_cents']);
		$this->assertSame(150, $built['totals']['card_cents']);
		$this->assertSame(100, $built['totals']['other_cents']);
		$this->assertSame(500, $built['totals']['amount_cents']);
		$this->assertSame(3, $built['totals']['count']);
		$this->assertSame(1, $built['totals']['open']);
		$this->assertSame(400, $built['totals']['excluded_gratis_cents']);
		$this->assertSame(1, $built['totals']['excluded_gratis_count']);
		$this->assertSame(1, $built['totals']['excluded_einloesung_count']);
		$numbers = array_column($built['lines'], 'order_no');
		$this->assertSame(array('A', 'B', 'H'), $numbers);
	}

	public function testGroupsByTheSameMachineAsThePortal(): void
	{
		$orders = array(
			$this->order('A', 2, '2.50', '2026-11-10 10:00:00', 'Cash', 'H-101', 'Espresso', null, '100'),
			$this->order('B', 2, '1.50', '2026-11-10 11:00:00', 'Card', 'H-101', 'Espresso', null, '100'),
			$this->order('C', 2, '4.00', '2026-11-10 12:00:00', 'Cash', 'H-102', 'Espresso', null, '200'),
			$this->order('D', 2, '9.00', '2026-11-10 13:00:00', 'Free', 'H-102', 'Espresso', null, '200'),
		);
		$from = new DateTimeImmutable('2026-11-01 00:00:00', new DateTimeZone('Europe/Berlin'));
		$to = new DateTimeImmutable('2026-11-16 00:00:00', new DateTimeZone('Europe/Berlin'));
		$built = CoffeejiSettlement::build($orders, $from, $to, array(), 'The Hendricks Coffee');
		$portal = CoffeejiPortal::byMachine($orders, $from, $to);

		$this->assertCount(2, $portal);
		$this->assertCount(2, $built['machines']);
		$this->assertSame('H-101', $built['machines'][0]['machine_no']);
		$this->assertSame('100', $built['machines'][0]['machine_key']);
		$this->assertSame(400, $built['machines'][0]['amount_cents']);
		$this->assertSame(2, $built['machines'][0]['orders']);
		$this->assertSame('H-102', $built['machines'][1]['machine_no']);
		$this->assertSame(400, $built['machines'][1]['amount_cents']);
		$this->assertSame(1, $built['machines'][1]['orders']);
		$this->assertSame('The Hendricks Coffee', $built['machines'][0]['dealer']);
	}

	public function testVatIsRoundedOnTheRateTotalAndUnknownProductsAreListed(): void
	{
		$rules = array(
			array(
				'goods_name' => 'Latte',
				'rate' => 7,
				'milk_type' => 'frischmilch',
				'milk_share' => 80,
				'evidence' => 'Rezeptur',
				'hot_water' => false,
				'valid_from' => '2026-11-15',
			),
		);
		$orders = array(
			$this->order('1', 2, '0.10', '2026-11-10 10:00:00', 'Cash', 'H-101', 'Espresso'),
			$this->order('2', 2, '0.10', '2026-11-10 10:01:00', 'Cash', 'H-101', 'Espresso'),
			$this->order('3', 2, '0.10', '2026-11-10 10:02:00', 'Cash', 'H-101', 'Espresso'),
			$this->order('4', 2, '1.00', '2026-11-14 23:59:59', 'Card', 'H-101', 'Latte'),
			$this->order('5', 2, '1.00', '2026-11-15 00:00:00', 'Card', 'H-101', 'Latte'),
		);
		$from = new DateTimeImmutable('2026-11-01 00:00:00', new DateTimeZone('Europe/Berlin'));
		$to = new DateTimeImmutable('2026-12-01 00:00:00', new DateTimeZone('Europe/Berlin'));
		$built = CoffeejiSettlement::build($orders, $from, $to, $rules, 'Hausmarke');

		$this->assertSame(5, CoffeejiSettlement::includedTaxCents(30, 19));
		$this->assertNotSame(6, CoffeejiSettlement::includedTaxCents(30, 19));
		$vat = array();
		foreach ($built['vat'] as $row) {
			$vat[$row['rate']] = $row;
		}
		$this->assertSame(130, $vat[19]['gross_cents']);
		$this->assertSame(CoffeejiSettlement::includedTaxCents(130, 19), $vat[19]['tax_cents']);
		$this->assertSame(130 - $vat[19]['tax_cents'], $vat[19]['net_cents']);
		$this->assertSame(100, $vat[7]['gross_cents']);
		$this->assertSame(CoffeejiSettlement::includedTaxCents(100, 7), $vat[7]['tax_cents']);
		$this->assertSame(array('Espresso', 'Latte'), $built['unconfigured']);
		$rates = array();
		foreach ($built['lines'] as $line) {
			$rates[$line['order_no']] = $line['vat_rate'];
		}
		$this->assertSame(19, $rates['4']);
		$this->assertSame(7, $rates['5']);
		$this->assertSame('Hausmarke', $built['lines'][0]['dealer']);
	}

	public function testMonthBoundsUseBerlinMidnightIncludingDst(): void
	{
		$march = CoffeejiSettlement::period('2026-03-01', '2026-03-31');
		$this->assertSame('2026-03-01 00:00:00', $march['from']->format('Y-m-d H:i:s'));
		$this->assertSame('+01:00', $march['from']->format('P'));
		$this->assertSame('2026-04-01 00:00:00', $march['to']->format('Y-m-d H:i:s'));
		$this->assertSame('+02:00', $march['to']->format('P'));
		$this->assertSame(31 * 86400 - 3600, $march['to']->getTimestamp() - $march['from']->getTimestamp());

		$october = CoffeejiSettlement::period('2026-10-01', '2026-10-31');
		$this->assertSame('+02:00', $october['from']->format('P'));
		$this->assertSame('2026-11-01 00:00:00', $october['to']->format('Y-m-d H:i:s'));
		$this->assertSame('+01:00', $october['to']->format('P'));
		$this->assertSame(31 * 86400 + 3600, $october['to']->getTimestamp() - $october['from']->getTimestamp());

		$orders = array(
			$this->order('early', 2, '1.00', '2026-02-28 23:59:59', 'Cash', 'H-1', 'Espresso'),
			$this->order('start', 2, '1.00', '2026-03-01 00:00:00', 'Cash', 'H-1', 'Espresso'),
			$this->order('last', 2, '1.00', '2026-03-31 23:59:59', 'Cash', 'H-1', 'Espresso'),
			$this->order('next', 2, '1.00', '2026-04-01 00:00:00', 'Cash', 'H-1', 'Espresso'),
			$this->order('oct-last', 2, '1.00', '2026-10-31 23:59:59', 'Cash', 'H-1', 'Espresso'),
			$this->order('nov-start', 2, '1.00', '2026-11-01 00:00:00', 'Cash', 'H-1', 'Espresso'),
		);
		$marchBuilt = CoffeejiSettlement::build($orders, $march['from'], $march['to'], array(), '');
		$octoberBuilt = CoffeejiSettlement::build($orders, $october['from'], $october['to'], array(), '');

		$this->assertSame(array('start', 'last'), array_column($marchBuilt['lines'], 'order_no'));
		$this->assertSame(array('oct-last'), array_column($octoberBuilt['lines'], 'order_no'));
		$this->assertSame(CoffeejiSettlement::MERCHANT_DEFAULT, $marchBuilt['merchant']);

		$previous = CoffeejiSettlement::previousMonth(new DateTimeImmutable('2026-11-21 15:00:00', new DateTimeZone('Europe/Berlin')));
		$this->assertSame(array('start' => '2026-10-01', 'end' => '2026-10-31'), $previous);
		$afterNewYear = CoffeejiSettlement::previousMonth(new DateTimeImmutable('2026-01-01 00:30:00', new DateTimeZone('Europe/Berlin')));
		$this->assertSame(array('start' => '2025-12-01', 'end' => '2025-12-31'), $afterNewYear);
		$dstDay = CoffeejiSettlement::previousMonth(new DateTimeImmutable('2026-03-29 12:00:00', new DateTimeZone('Europe/Berlin')));
		$this->assertSame(array('start' => '2026-02-01', 'end' => '2026-02-28'), $dstDay);
	}

	public function testMerchantSettingPrefersEnvironmentThenConfig(): void
	{
		$this->assertSame('Filiale', CoffeejiSettlement::merchant(' Filiale ', 'Config'));
		$this->assertSame('Config', CoffeejiSettlement::merchant('   ', 'Config'));
		$this->assertSame('Config', CoffeejiSettlement::merchant(null, 'Config'));
		$this->assertSame(CoffeejiSettlement::MERCHANT_DEFAULT, CoffeejiSettlement::merchant(null, '  '));
	}

	public function testListQueryAndIdsRejectUncheckedInput(): void
	{
		$query = AbrechnungListQuery::fromPost(array(
			'order' => array(array('column' => '1; DROP TABLE invoice2', 'dir' => 'asc; delete')),
			'start' => '-5',
			'length' => '99999',
			'search' => array('value' => "%_' OR 1=1"),
		));

		$this->assertSame('start_date', $query['order']);
		$this->assertSame('DESC', $query['dir']);
		$this->assertSame(0, $query['start']);
		$this->assertSame(200, $query['limit']);
		$this->assertSame('%\\%\\_\' OR 1=1%', $query['like']);
		$this->assertContains($query['order'], AbrechnungListQuery::COLUMNS);

		$this->assertSame(15, AbrechnungId::fromBase64(base64_encode('15')));
		$this->assertNull(AbrechnungId::fromBase64(base64_encode("1' OR 1=1")));
		$this->assertNull(AbrechnungId::fromBase64(base64_encode('-3')));
		$this->assertNull(AbrechnungId::fromBase64('%%%'));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function order(
		string $no,
		int $status,
		string $amount,
		string $time,
		string $pay,
		string $ext,
		string $goods,
		?int $out = null,
		string $deviceId = '100'
	): array {
		return array(
			'order_no' => $no,
			'device_id' => $deviceId,
			'device_ext_no' => $ext,
			'device_name' => 'Standort ' . $ext,
			'goods_name' => $goods,
			'pay_amt' => $amount,
			'pay_way_name' => $pay,
			'status' => $status,
			'out_stock_status' => $out,
			'create_time' => $time,
		);
	}
}
