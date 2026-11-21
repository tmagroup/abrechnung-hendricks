<?php
if (!defined("APP_FOLDER")) exit("No direct script access allowed");

afterLogin();

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($autoload)) {
	require_once $autoload;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

if (isset($_POST["add_excel"])) {
	setRules("start_date", "Zeitraum beginn", "required");
	setRules("end_date", "Zeitraum ende", "required");

	if (validation()) {
		$fileName = $_FILES["excel_sheet"]["name"] ?? '';
		$fileType = $_FILES["excel_sheet"]["type"] ?? '';
		$fileExtension = pathinfo((string) $fileName, PATHINFO_EXTENSION);
		$allowedMimeTypes = [
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'text/csv'
		];
		$allowedExtensions = ['xls', 'xlsx', 'csv'];

		if (!in_array($fileType, $allowedMimeTypes, true)) {
			setMsg("Nur Excel-Dateien (XLS, XLSX) und CSV sind erlaubt.", 1);
			redirect();
		} else if (!in_array(strtolower($fileExtension), $allowedExtensions, true)) {
			setMsg("Nur Excel-Dateien (XLS, XLSX) und CSV sind erlaubt.", 1);
			redirect();
		} else {
			try {
				$filePath = $_FILES['excel_sheet']['tmp_name'];
				$spreadsheet = IOFactory::load($filePath);
				$sheet = $spreadsheet->getActiveSheet();
				$total_amount = 0;
				$excel_date = "";

				$match_excel_columns = [
					'A' => 'invoice_list_order_no',
					'B' => 'invoice_list_machine_no',
					'C' => 'invoice_list_machine_name',
					'D' => 'invoice_list_dealer',
					'E' => 'invoice_list_goods_name',
					'F' => 'invoice_list_amount',
					'G' => 'invoice_list_payment_method',
					'H' => 'invoice_list_order_status',
					'I' => 'invoice_list_ship_status',
					'J' => 'invoice_list_refund_status',
					'K' => 'invoice_list_order_time',
					'L' => 'invoice_list_ship_time',
					'M' => 'invoice_list_refund_time',
				];

				$match_db_columns = [
					'order_number' => 'invoice_list_order_no',
					'machine_no' => 'invoice_list_machine_no',
					'machine_name' => 'invoice_list_machine_name',
					'dealer' => 'invoice_list_dealer',
					'goods_name' => 'invoice_list_goods_name',
					'amount' => 'invoice_list_amount',
					'payment_method' => 'invoice_list_payment_method',
					'order_status' => 'invoice_list_order_status',
					'ship_status' => 'invoice_list_ship_status',
					'refund_status' => 'invoice_list_refund_status',
					'order_time' => 'invoice_list_order_time',
					'ship_time' => 'invoice_list_ship_time',
					'refund_time' => 'invoice_list_refund_time',
				];

				foreach ($match_db_columns as $field) {
					${$field} = [];
				}

				foreach ($sheet->getRowIterator() as $key => $row) {
					$cellIterator = $row->getCellIterator();
					$cellIterator->setIterateOnlyExistingCells(false);
					if ($key > 3) {
						foreach ($cellIterator as $cell_key => $cell) {
							if (!isset($match_excel_columns[$cell_key])) {
								continue;
							}
							${$match_excel_columns[$cell_key]}[] = $cell->getValue();
							if ($match_excel_columns[$cell_key] == 'invoice_list_amount') {
								$total_amount += $cell->getValue();
							}
						}
					} else {
						foreach ($cellIterator as $cell_key => $cell) {
							if ($cell_key == "A" && $key == 2) {
								$excel_date = $cell->getValue();
								$date_array = explode("-", (string) $cell->getValue());
								if (isset($date_array[0]) && isset($date_array[1])) {
									$excel_date = $date_array[1] . $date_array[0];
								}
							}
						}
					}
				}

				$data_invoice = [];
				$data_invoice['start_date'] = $_POST['start_date'];
				$data_invoice['end_date'] = $_POST['end_date'];
				$data_invoice['excel_date'] = $excel_date;
				$data_invoice['total_amount'] = $total_amount;
				$data_invoice['created_at'] = date("Y-m-d H:i:s");
				$data_invoice['created_by'] = getLoginId();
				$invoice_id = insert("invoice2", $data_invoice);

				foreach ($invoice_list_machine_no as $key => $val) {
					$data_invoice_list = [];
					$data_invoice_list['invoice_id'] = $invoice_id;
					$data_invoice_list['machine_no'] = $val;
					foreach ($match_db_columns as $db_column => $excel_field) {
						$data_invoice_list[$db_column] = ${$excel_field}[$key] ?? null;
					}
					insert("invoice_list2", $data_invoice_list);
				}
				setMsg("Die Excel-Datei wurde verarbeitet.");
				redirect("abrechnung_gesamt/" . base64_encode((string) $invoice_id));
			} catch (\Exception $ex) {
				setMsg("Die Excel-Datei konnte nicht gelesen werden.", 1);
				redirect("abrechnung_gesamt");
			}
		}
	}
}

if (isset($page_var1)) {
	$invoice_id = AbrechnungId::fromBase64($page_var1);
	if ($invoice_id === null) {
		setMsg("Die Abrechnung wurde nicht gefunden.", 1);
		redirect("abrechnung_gesamt_list");
	}
	try {
		$loaded = (new CoffeejiSettlementStore())->load($invoice_id);
		$download = isset($_GET['is_download']) && (string) $_GET['is_download'] === 'yes';
		CoffeejiSettlementPdf::stream($loaded['invoice'], $loaded['lines'], $download);
		exit;
	} catch (Throwable $ex) {
		setMsg("Das PDF konnte nicht erzeugt werden.", 1);
		redirect("abrechnung_gesamt_list");
	}
}

$hc_period_default = CoffeejiSettlement::previousMonth(CoffeejiTime::now());
$hc_start = isset($_POST['start_date']) ? (string) $_POST['start_date'] : $hc_period_default['start'];
$hc_end = isset($_POST['end_date']) ? (string) $_POST['end_date'] : $hc_period_default['end'];
$hc_preview = null;
$hc_abrechnung_error = '';

if (isset($_POST['vorschau']) || isset($_POST['speichern'])) {
	try {
		$store = new CoffeejiSettlementStore();
		$hc_preview = $store->preview($hc_start, $hc_end, abrechnung_haendler());
		if (isset($_POST['speichern'])) {
			if ((int) $hc_preview['totals']['count'] === 0) {
				$hc_abrechnung_error = 'Im Zeitraum liegen keine abrechenbaren Bestellungen.';
			} elseif ($hc_preview['existing'] !== array() && (string) ($_POST['trotzdem'] ?? '') !== '1') {
				$hc_abrechnung_error = 'Für diesen Zeitraum gibt es bereits eine Abrechnung. Zum Speichern die Bestätigung unten setzen.';
			} elseif (!$store->ready()) {
				$hc_abrechnung_error = 'Die Abrechnungstabellen sind noch nicht erweitert. Migration 20261121_abrechnung_coffeeji.sql einspielen.';
			} else {
				$new_id = $store->save($hc_preview, (int) getLoginId());
				redirect('abrechnung_gesamt/' . base64_encode((string) $new_id));
			}
		}
	} catch (InvalidArgumentException $ex) {
		$hc_abrechnung_error = $ex->getMessage();
		$hc_preview = null;
	} catch (CoffeejiException $ex) {
		$hc_abrechnung_error = $ex->getMessage();
		$hc_preview = null;
	}
}
