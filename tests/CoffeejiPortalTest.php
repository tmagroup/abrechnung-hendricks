<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiPortalTest extends TestCase
{
	public function testPortalRevenueIncludesMakingAndCompleteOnly(): void
	{
		$this->assertSame(array(1, 2), CoffeejiOrder::PORTAL_REVENUE_STATUSES);
		$this->assertTrue(CoffeejiOrder::countsForPortalRevenue(1));
		$this->assertTrue(CoffeejiOrder::countsForPortalRevenue(2));
		foreach (array(0, 6, 7) as $status) {
			$this->assertFalse(CoffeejiOrder::countsForPortalRevenue($status));
		}
		$this->assertTrue(CoffeejiOrder::isSale(array('status' => 2)));
		$this->assertTrue(CoffeejiOrder::isSale(array('status' => 1)));
		$this->assertFalse(CoffeejiOrder::isSale(array('status' => 0)));
		$this->assertTrue(CoffeejiOrder::isOpen(1, 2));
		$this->assertTrue(CoffeejiOrder::isOpen(2, 1));
		$this->assertFalse(CoffeejiOrder::isOpen(2, 2));
		$this->assertFalse(CoffeejiOrder::isOpen(2, null));
	}

	public function testDefaultOrderRangeIsThirtyCalendarDays(): void
	{
		$now = new DateTimeImmutable('2026-11-15 18:40:00', new DateTimeZone('Europe/Berlin'));
		$range = CoffeejiPortal::defaultOrderRange($now);

		$this->assertSame('2026-10-17 00:00:00', $range['from']->format('Y-m-d H:i:s'));
		$this->assertSame('2026-11-16 00:00:00', $range['to']->format('Y-m-d H:i:s'));
		$this->assertSame(30, (int) $range['from']->diff($range['to'])->days);
	}

	public function testFilterKeepsStatusDeliveryAndWindowEdges(): void
	{
		$now = new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('Europe/Berlin'));
		$filter = CoffeejiPortal::orderFilter(array(
			'von' => '2026-11-01',
			'bis' => '2026-11-15',
			'bestellstatus' => '1',
			'ausgabestatus' => '1',
			'bestellnummer' => 'a2611',
			'maschinennummer' => 'H-101',
			'zahlart' => 'Cash',
			'pro' => '15',
			'seite' => '4',
		), $now);

		$this->assertSame(10, $filter['per_page']);
		$this->assertSame(1, $filter['status']);
		$this->assertSame(1, $filter['out_stock_status']);
		$this->assertSame('2026-11-01 00:00:00', $filter['from']->format('Y-m-d H:i:s'));
		$this->assertSame('2026-11-16 00:00:00', $filter['to']->format('Y-m-d H:i:s'));

		$inside = $this->order('A2611-1', 1, '2.00', '2026-11-01 00:00:00', 1, 'Cash', 'H-101');
		$onEnd = $this->order('A2611-2', 1, '2.00', '2026-11-16 00:00:00', 1, 'Cash', 'H-101');
		$otherStatus = $this->order('A2611-3', 2, '2.00', '2026-11-02 10:00:00', 1, 'Cash', 'H-101');
		$otherPay = $this->order('A2611-4', 1, '2.00', '2026-11-02 10:00:00', 1, 'Card', 'H-101');
		$otherMachine = $this->order('A2611-5', 1, '2.00', '2026-11-02 10:00:00', 1, 'Cash', 'H-102');

		$this->assertTrue(CoffeejiPortal::matches($inside, $filter));
		$this->assertFalse(CoffeejiPortal::matches($onEnd, $filter));
		$this->assertFalse(CoffeejiPortal::matches($otherStatus, $filter));
		$this->assertFalse(CoffeejiPortal::matches($otherPay, $filter));
		$this->assertFalse(CoffeejiPortal::matches($otherMachine, $filter));
	}

	public function testPercentInOrderNumberIsLiteral(): void
	{
		$now = new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('Europe/Berlin'));
		$filter = CoffeejiPortal::orderFilter(array(
			'von' => '2026-11-01',
			'bis' => '2026-11-15',
			'bestellnummer' => 'A%1',
		), $now);
		$literal = $this->order('A%100', 2, '1.00', '2026-11-02 08:00:00', 2, 'Cash', 'H-101');
		$plain = $this->order('AB100', 2, '1.00', '2026-11-02 08:00:00', 2, 'Cash', 'H-101');

		$this->assertTrue(CoffeejiPortal::matches($literal, $filter));
		$this->assertFalse(CoffeejiPortal::matches($plain, $filter));
	}

	public function testTotalsCountOpenOrdersInsideRevenue(): void
	{
		$orders = array(
			$this->order('A', 2, '2.50', '2026-11-10 10:00:00', 2, 'Cash', 'H-101'),
			$this->order('B', 1, '1.50', '2026-11-10 11:00:00', 1, 'Card', 'H-101'),
			$this->order('C', 2, '3.00', '2026-11-10 12:00:00', 1, 'Free', 'H-101'),
			$this->order('D', 6, '9.00', '2026-11-10 13:00:00', 0, 'Cash', 'H-101'),
			$this->order('E', 0, '4.00', '2026-11-10 14:00:00', 0, 'Cash', 'H-101'),
		);
		$totals = CoffeejiPortal::totals($orders);

		$this->assertSame(5, $totals['count']);
		$this->assertSame(20.0, $totals['amount']);
		$this->assertSame(7.0, $totals['revenue']);
		$this->assertSame(3, $totals['revenue_count']);
		$this->assertSame(2, $totals['open']);
	}

	public function testGroupsByDayMonthAndYear(): void
	{
		$orders = array(
			$this->order('A', 2, '2.00', '2026-11-10 10:00:00', 2, 'Cash', 'H-101'),
			$this->order('B', 1, '1.00', '2026-11-10 11:00:00', 1, 'Card', 'H-101'),
			$this->order('C', 6, '9.00', '2026-11-12 09:00:00', 0, 'Cash', 'H-101'),
			$this->order('D', 2, '4.00', '2026-10-31 23:00:00', 2, 'Cash', 'H-102'),
			$this->order('E', 2, '5.00', '2026-01-02 08:00:00', 2, 'Exchange', 'H-102'),
			$this->order('F', 2, '8.00', '2025-12-31 23:00:00', 2, 'Cash', 'H-102'),
		);
		$from = new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('Europe/Berlin'));
		$to = new DateTimeImmutable('2026-12-01 00:00:00', new DateTimeZone('Europe/Berlin'));

		$days = CoffeejiPortal::groupByPeriod($orders, 'tag', $from, $to);
		$byDay = array();
		foreach ($days as $day) {
			$byDay[$day['key']] = $day;
		}
		$this->assertSame(3.0, $byDay['2026-11-10']['amount']);
		$this->assertSame(2, $byDay['2026-11-10']['count']);
		$this->assertSame(0.0, $byDay['2026-11-12']['amount']);
		$this->assertSame(4.0, $byDay['2026-10-31']['amount']);
		$this->assertArrayNotHasKey('2025-12-31', $byDay);

		$months = CoffeejiPortal::groupByPeriod($orders, 'monat', $from, $to);
		$byMonth = array();
		foreach ($months as $month) {
			$byMonth[$month['key']] = $month;
		}
		$this->assertSame(5.0, $byMonth['2026-01']['amount']);
		$this->assertSame(0.0, $byMonth['2026-02']['amount']);
		$this->assertSame(4.0, $byMonth['2026-10']['amount']);
		$this->assertSame(3.0, $byMonth['2026-11']['amount']);
		$this->assertCount(11, $months);

		$years = CoffeejiPortal::groupByPeriod($orders, 'jahr', $from, $to);
		$this->assertCount(1, $years);
		$this->assertSame('2026', $years[0]['key']);
		$this->assertSame(12.0, $years[0]['amount']);
		$this->assertSame(4, $years[0]['count']);
	}

	public function testMachineAndProductAggregation(): void
	{
		$orders = array(
			$this->order('A', 2, '2.00', '2026-11-10 10:00:00', 2, 'Cash', 'H-101', 'Hausmischung'),
			$this->order('B', 1, '1.00', '2026-11-10 11:00:00', 1, 'Card', 'H-101', 'Espresso'),
			$this->order('C', 2, '4.00', '2026-11-11 11:00:00', 2, 'Free', 'H-102', 'Hausmischung'),
			$this->order('D', 7, '6.00', '2026-11-11 12:00:00', 0, 'Cash', 'H-102', 'Hausmischung'),
		);
		$from = new DateTimeImmutable('2026-11-01 00:00:00', new DateTimeZone('Europe/Berlin'));
		$to = new DateTimeImmutable('2026-11-16 00:00:00', new DateTimeZone('Europe/Berlin'));
		$machines = CoffeejiPortal::byMachine($orders, $from, $to);

		$this->assertSame('H-102', $machines[0]['device_ext_no']);
		$this->assertSame(4.0, $machines[0]['amount']);
		$this->assertSame(1, $machines[0]['count']);
		$this->assertSame(3.0, $machines[1]['amount']);
		$this->assertSame(1, $machines[1]['open']);

		$products = CoffeejiPortal::byProduct($orders, $from, $to);
		$this->assertCount(3, $products);
		$pay = CoffeejiPortal::byPayWay($orders, $from, $to);
		$indexed = array();
		foreach ($pay as $row) {
			$indexed[$row['key']] = $row;
		}
		$this->assertSame(2.0, $indexed['bar']['amount']);
		$this->assertSame(1.0, $indexed['karte']['amount']);
		$this->assertSame(4.0, $indexed['gratis']['amount']);
		$this->assertSame(0, $indexed['sonstige']['count']);
	}

	public function testPaginationClampsPageAndPageSize(): void
	{
		$rows = array();
		for ($i = 1; $i <= 55; $i++) {
			$rows[] = array('n' => $i);
		}
		$page = CoffeejiPortal::paginate($rows, 3, 20);
		$this->assertSame(20, $page['meta']['per_page']);
		$this->assertSame(3, $page['meta']['pages']);
		$this->assertSame(15, count($page['rows']));
		$this->assertSame(41, $page['rows'][0]['n']);

		$clamped = CoffeejiPortal::paginate($rows, 9, 7);
		$this->assertSame(10, $clamped['meta']['per_page']);
		$this->assertSame(6, $clamped['meta']['page']);
		$this->assertSame(5, count($clamped['rows']));
		$this->assertSame(51, $clamped['rows'][0]['n']);

		$empty = CoffeejiPortal::pageMeta(0, 3, 10);
		$this->assertSame(1, $empty['page']);
		$this->assertSame(0, $empty['offset']);
	}

	public function testNormalizeReadsPortalFieldsAndDropsEmptyClocks(): void
	{
		$order = CoffeejiOrder::normalize(array(
			'orderNo' => 'A261114215256000000001',
			'deviceId' => '100',
			'deviceExtNo' => 'H-101',
			'deviceName' => 'Musterkiosk',
			'goodsName' => 'Hausmischung',
			'price' => 1.5,
			'payAmt' => 1.5,
			'payWay' => 6,
			'payWayName' => 'Exchange',
			'status' => 1,
			'outStockStatus' => 1,
			'outStockTime' => '',
			'refundStatus' => '',
			'refundTime' => '',
			'failRemark' => 'Becher leer',
			'createTime' => '2026-11-14 21:52:56',
			'timeZoneId' => 'UTC+01:00',
		));

		$this->assertSame(6, $order['pay_way']);
		$this->assertSame(1, $order['out_stock_status']);
		$this->assertNull($order['out_stock_time']);
		$this->assertNull($order['refund_status']);
		$this->assertNull($order['refund_time']);
		$this->assertSame('Becher leer', $order['fail_remark']);
		$this->assertSame('2026-11-14 21:52:56', $order['create_time']);
		$this->assertSame('Einlösung', CoffeejiPortal::payLabel('Exchange'));
		$this->assertSame("'=1+1", CoffeejiPortal::csvCell('=1+1'));
	}

	public function testDetailFingerprintIgnoresPortalFieldsWhenColumnsAreMissing(): void
	{
		$left = array(
			'order_no' => 'A-1',
			'device_id' => '1',
			'device_ext_no' => 'H-101',
			'device_name' => 'Musterkiosk',
			'goods_name' => 'Hausmischung',
			'price' => '1.50',
			'pay_amt' => '1.50',
			'pay_way_name' => 'Cash',
			'status' => 2,
			'refund_status' => null,
			'create_time' => '2026-11-10 10:00:00',
			'time_zone_id' => 'UTC+01:00',
			'out_stock_status' => 1,
		);
		$right = $left;
		$right['out_stock_status'] = 2;

		$this->assertNotSame(CoffeejiUpsert::fingerprint($left), CoffeejiUpsert::fingerprint($right));
		$this->assertSame(CoffeejiUpsert::fingerprint($left, false), CoffeejiUpsert::fingerprint($right, false));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function order(
		string $no,
		int $status,
		string $amount,
		string $time,
		?int $out,
		string $pay,
		string $machine,
		string $goods = 'Hausmischung'
	): array {
		return array(
			'order_no' => $no,
			'device_id' => $machine === 'H-101' ? '9101' : '9102',
			'device_ext_no' => $machine,
			'device_no' => $machine === 'H-101' ? 'aaa111' : 'bbb222',
			'device_name' => $machine === 'H-101' ? 'Musterkiosk' : 'Bahnhof',
			'goods_name' => $goods,
			'pay_amt' => $amount,
			'pay_way_name' => $pay,
			'status' => $status,
			'out_stock_status' => $out,
			'create_time' => $time,
		);
	}
}
