<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF der Gesamtabrechnung.
 * Excel-Abrechnungen behalten das bisherige Layout. Bar und Karte waren vertauscht
 * und werden hier den richtigen Summen zugeordnet. Coffeeji-Abrechnungen weisen
 * die enthaltene MwSt je Satz aus.
 */
class CoffeejiSettlementPdf
{
	/**
	 * @param array<string, mixed> $invoice
	 * @param list<array<string, mixed>> $lines
	 */
	public static function html(array $invoice, array $lines): string
	{
		$coffeeji = strtolower(trim((string) ($invoice['source'] ?? 'excel'))) === 'coffeeji';
		$groups = array();
		foreach ($lines as $row) {
			$machine = (string) ($row['machine_no'] ?? '');
			if (!isset($groups[$machine])) {
				$groups[$machine] = array(
					'machine_no' => $machine,
					'machine_name' => '',
					'dealer' => '',
					'orders' => 0,
					'amount' => 0.0,
				);
			}
			$groups[$machine]['orders']++;
			$groups[$machine]['amount'] += (float) ($row['amount'] ?? 0);
			if ($groups[$machine]['machine_name'] === '' && trim((string) ($row['machine_name'] ?? '')) !== '') {
				$groups[$machine]['machine_name'] = (string) $row['machine_name'];
			}
			if ($groups[$machine]['dealer'] === '' && trim((string) ($row['dealer'] ?? '')) !== '') {
				$groups[$machine]['dealer'] = (string) $row['dealer'];
			}
		}

		$table = '<table class="product-table" border="1">';
		$table .= '<tr><th>Maschinen-Nr</th><th>Standort</th><th>Händler</th><th>Bestellungen</th><th>Betrag (€)</th></tr>';
		foreach ($groups as $row) {
			$table .= '<tr>';
			$table .= '<td>' . self::h($row['machine_no']) . '</td>';
			$table .= '<td>' . self::h($row['machine_name']) . '</td>';
			$table .= '<td>' . self::h($row['dealer']) . '</td>';
			$table .= '<td>' . (int) $row['orders'] . '</td>';
			$table .= '<td>' . self::euro($row['amount']) . '</td>';
			$table .= '</tr>';
		}
		$total = (float) ($invoice['total_amount'] ?? 0);
		$table .= '<tr class="bg-gray"><td></td><td></td><td></td><td>Gesamtsumme</td><td>' . self::euro($total) . '</td></tr>';
		if ($coffeeji) {
			$table .= '</table>';
			$table .= self::vatTable(self::breakdown($invoice));
			$pays = self::headerPays($invoice);
			$table .= '<table class="product-table" border="1">';
			$table .= '<tr class="bg-gray"><td></td><td></td><td></td><td>Bar</td><td>' . self::euro($pays['cash']) . '</td></tr>';
			$table .= '<tr class="bg-gray"><td></td><td></td><td></td><td>Karte</td><td>' . self::euro($pays['card']) . '</td></tr>';
			if (abs($pays['other']) > 0.00001) {
				$table .= '<tr class="bg-gray"><td></td><td></td><td></td><td>Sonstige</td><td>' . self::euro($pays['other']) . '</td></tr>';
			}
			$table .= '</table>';
		} else {
			$table .= '<tr class="bg-gray"><td></td><td></td><td></td><td>MwSt (7%)</td><td>' . self::euro((($total * 7) / 100)) . '</td></tr>';
			$pays = self::legacyPays($lines);
			$table .= '<tr class="bg-gray"><td></td><td></td><td></td><td>Bar</td><td>' . self::euro($pays['cash']) . '</td></tr>';
			$table .= '<tr class="bg-gray"><td></td><td></td><td></td><td>Karte</td><td>' . self::euro($pays['card']) . '</td></tr>';
			$table .= '</table>';
		}

		$period = self::periodLabel((string) ($invoice['start_date'] ?? ''), (string) ($invoice['end_date'] ?? ''));

		return '<html><head><meta charset="UTF-8"><style>
			body { font-size: 12px; color:#000; }
			.img-div { text-align: right; }
			.product-table { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 8px; }
			.product-table th, .product-table td { text-align: center; font-size: 12px; padding: 3px; border: 1px solid #000; }
			.product-table th { background-color: #4f81bd; color: #fff; font-weight: bold; }
			.bg-gray { background-color: #d9d9d9; font-weight: bold; }
			h1 { text-align: center; }
			.pdf-footer { position: fixed; bottom: 0; left: 0; right: 0; height: 0; font-size: 10px; text-align: center; color: #000; line-height: 1.5; }
		</style></head><body>
			<div class="img-div">' . self::logo() . '</div>
			<br/><br/>
			<table width="100%"><tr>
				<td align="left"><b>C.A.E GmbH</b><br/>Industriestr.10 <br/>59192 Bergkamen</td>
				<td align="right">Zeitraum.: ' . self::h($period) . '</td>
			</tr></table>
			<h1>Verkaufsstatistik</h1>
			' . $table . '
			<div class="pdf-footer">
				C.A.E GmbH &#8226; Industriestr.10 &#8226; 59192 Bergkamen<br>
				Amtsgericht Hamm &#8226; Handelsregister: HRB 9444 &#8226; Finanzamt Hamm &#8226; Steuernr.: 322/5729/6261
			</div>
		</body></html>';
	}

	public static function render(string $html): string
	{
		if (!class_exists(Dompdf::class)) {
			require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
		}
		$options = new Options();
		$options->set('defaultFont', 'DejaVu Sans');
		$options->set('isHtml5ParserEnabled', true);
		$options->set('isRemoteEnabled', false);
		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();

		return $dompdf->output();
	}

	/**
	 * @param array<string, mixed> $invoice
	 * @param list<array<string, mixed>> $lines
	 */
	public static function stream(array $invoice, array $lines, bool $download): void
	{
		if (!class_exists(Dompdf::class)) {
			require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
		}
		$options = new Options();
		$options->set('defaultFont', 'DejaVu Sans');
		$options->set('isHtml5ParserEnabled', true);
		$options->set('isRemoteEnabled', false);
		$dompdf = new Dompdf($options);
		$dompdf->loadHtml(self::html($invoice, $lines));
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();
		$id = (int) ($invoice['id'] ?? 0);
		$dompdf->stream('verkaufsstatistik_' . $id . '.pdf', array('Attachment' => $download));
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 */
	private static function vatTable(array $rows): string
	{
		$html = '<table class="product-table" border="1">';
		$html .= '<tr><th>MwSt-Satz</th><th>Netto</th><th>Steuer</th><th>Brutto</th></tr>';
		foreach ($rows as $row) {
			$html .= '<tr class="bg-gray">';
			$html .= '<td>' . (int) $row['rate'] . ' %</td>';
			$html .= '<td>' . self::euro((float) $row['net']) . '</td>';
			$html .= '<td>' . self::euro((float) $row['tax']) . '</td>';
			$html .= '<td>' . self::euro((float) $row['gross']) . '</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';

		return $html;
	}

	/**
	 * @param array<string, mixed> $invoice
	 * @return list<array<string, mixed>>
	 */
	private static function breakdown(array $invoice): array
	{
		$decoded = array();
		if (!empty($invoice['vat_breakdown'])) {
			$json = json_decode((string) $invoice['vat_breakdown'], true);
			if (is_array($json)) {
				foreach ($json as $row) {
					if (!is_array($row)) {
						continue;
					}
					$decoded[] = array(
						'rate' => (int) ($row['rate'] ?? 0),
						'net' => (string) ($row['net'] ?? '0.00'),
						'tax' => (string) ($row['tax'] ?? '0.00'),
						'gross' => (string) ($row['gross'] ?? '0.00'),
					);
				}
			}
		}
		if ($decoded === array()) {
			foreach (array(7, 19) as $rate) {
				$key = 'vat_' . $rate;
				if (!array_key_exists($key . '_gross', $invoice) || $invoice[$key . '_gross'] === null) {
					continue;
				}
				$decoded[] = array(
					'rate' => $rate,
					'net' => (string) $invoice[$key . '_net'],
					'tax' => (string) $invoice[$key . '_tax'],
					'gross' => (string) $invoice[$key . '_gross'],
				);
			}
		}

		return CoffeejiSettlement::vatForPdf($decoded);
	}

	/**
	 * @param array<string, mixed> $invoice
	 * @return array{cash: float, card: float, other: float}
	 */
	private static function headerPays(array $invoice): array
	{
		return array(
			'cash' => (float) ($invoice['cash_amount'] ?? 0),
			'card' => (float) ($invoice['card_amount'] ?? 0),
			'other' => (float) ($invoice['other_amount'] ?? 0),
		);
	}

	/**
	 * @param list<array<string, mixed>> $lines
	 * @return array{cash: float, card: float}
	 */
	private static function legacyPays(array $lines): array
	{
		$cash = 0.0;
		$card = 0.0;
		foreach ($lines as $row) {
			$method = strtolower(trim((string) ($row['payment_method'] ?? '')));
			$amount = (float) ($row['amount'] ?? 0);
			if ($method === 'card') {
				$card += $amount;
			} elseif ($method === 'cash') {
				$cash += $amount;
			}
		}

		return array('cash' => $cash, 'card' => $card);
	}

	private static function periodLabel(string $start, string $end): string
	{
		$months = array('Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mär', 'Apr' => 'Apr', 'May' => 'Mai', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Aug', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Dez');
		$format = static function (string $value) use ($months): string {
			$stamp = strtotime($value);
			if ($stamp === false) {
				return $value;
			}
			$month = date('M', $stamp);

			return date('d', $stamp) . ' ' . ($months[$month] ?? $month) . ', ' . date('Y', $stamp);
		};
		if ($start === '' && $end === '') {
			return '';
		}

		return $format($start) . ' - ' . $format($end);
	}

	private static function euro(float $amount): string
	{
		return number_format($amount, 2, ',', '') . ' €';
	}

	private static function h(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}

	private static function logo(): string
	{
		$path = dirname(__DIR__) . '/assets/abrechnung/logo.b64';
		if (!is_file($path)) {
			return '';
		}
		$raw = trim((string) file_get_contents($path));
		if ($raw === '') {
			return '';
		}

		return '<img src="data:image/jpeg;base64,' . $raw . '" width="200" />';
	}
}
