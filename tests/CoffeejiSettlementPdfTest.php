<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoffeejiSettlementPdfTest extends TestCase
{
	public function testLegacyPdfShowsCashAndCardOnTheRightRows(): void
	{
		$invoice = array(
			'id' => 4,
			'source' => 'excel',
			'start_date' => '2026-10-01',
			'end_date' => '2026-10-31',
			'total_amount' => '13.00',
		);
		$lines = array(
			array(
				'machine_no' => 'H-9',
				'machine_name' => 'Bahnhof',
				'dealer' => 'Alt',
				'amount' => '10.00',
				'payment_method' => 'cash',
			),
			array(
				'machine_no' => 'H-9',
				'machine_name' => 'Bahnhof',
				'dealer' => 'Alt',
				'amount' => '3.00',
				'payment_method' => 'card',
			),
		);
		$html = CoffeejiSettlementPdf::html($invoice, $lines);

		$this->assertStringContainsString('Bar</td><td>10,00 €', $html);
		$this->assertStringContainsString('Karte</td><td>3,00 €', $html);
		$this->assertStringContainsString('H-9', $html);
		$this->assertStringContainsString('MwSt (7%)', $html);
		$this->assertStringNotContainsString('Bar</td><td>3,00 €', $html);

		$pdf = CoffeejiSettlementPdf::render($html);
		$this->assertStringStartsWith('%PDF', $pdf);
	}

	public function testCoffeejiPdfShowsBothVatRates(): void
	{
		$invoice = array(
			'id' => 8,
			'source' => 'coffeeji',
			'start_date' => '2026-11-01',
			'end_date' => '2026-11-30',
			'total_amount' => '5.00',
			'cash_amount' => '2.50',
			'card_amount' => '2.50',
			'other_amount' => '0.00',
			'vat_breakdown' => json_encode(array(
				array('rate' => 7, 'net' => '0.93', 'tax' => '0.07', 'gross' => '1.00'),
				array('rate' => 19, 'net' => '3.36', 'tax' => '0.64', 'gross' => '4.00'),
			)),
		);
		$lines = array(
			array(
				'machine_no' => 'H-1',
				'machine_name' => 'Kiosk',
				'dealer' => 'The Hendricks Coffee',
				'amount' => '5.00',
				'payment_method' => '现金支付',
			),
		);
		$html = CoffeejiSettlementPdf::html($invoice, $lines);

		$this->assertStringContainsString('>7 %<', $html);
		$this->assertStringContainsString('>19 %<', $html);
		$this->assertStringContainsString('The Hendricks Coffee', $html);
		$this->assertStringContainsString('Bar</td><td>2,50 €', $html);
		$this->assertStringContainsString('Karte</td><td>2,50 €', $html);
		$this->assertStringNotContainsString('MwSt (7%)', $html);
		$this->assertStringStartsWith('%PDF', CoffeejiSettlementPdf::render($html));
	}
}
