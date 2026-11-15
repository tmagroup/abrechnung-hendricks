<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$now = CoffeejiTime::now();
	$filter = CoffeejiPortal::orderFilter($_GET, $now);
	$query = new CoffeejiPortalQuery();
	$bundle = array('state' => 'missing');
	try {
		if ($query->tablesReady()) {
			if (($_GET['export'] ?? '') === 'csv') {
				$export = $query->searchOrders($filter, true);
				$lines = array();
				foreach ($export['rows'] as $order) {
					$lines[] = hc_order_csv_line($order);
				}
				hc_send_csv('bestellungen.csv', hc_order_csv_headers(), $lines);
			}
			$bundle = $query->searchOrders($filter);
			$bundle['state'] = 'ready';
			$bundle['payways'] = $query->payWayNames();
		}
	} catch (Throwable $e) {
		(new CoffeejiLogger(COFFEEJI_LOG))->error('Bestellverwaltung: ' . $e->getMessage());
		$bundle = array('state' => 'error', 'message' => 'Die Bestellungen konnten nicht gelesen werden.');
	}

	$fromLabel = $filter['from']->format('d.m.Y');
	$toLabel = $filter['to']->modify('-1 second')->format('d.m.Y');
	$page_title = 'Bestellverwaltung';
	$page_subtitle = 'Lokale Coffeeji-Daten · ' . $fromLabel . ' bis ' . $toLabel . ' · Zeiten in MEZ';
	$page_foot = 'Hendricks Coffee · Zeiten in MEZ · Umsatz zählt Herstellung und fertig, offene Bestellungen bleiben markiert';
	$page_banner_html = '<a class="btn d" href="' . hc_h(hc_portal_href('bestellungen', $filter, array('export' => 'csv', 'seite' => null))) . '">Export</a>';
	include_once 'include/header.php';

	if (($bundle['state'] ?? '') !== 'ready') {
		echo hc_notice('gelb', 'Bestellverwaltung noch nicht verbunden', ($bundle['state'] ?? '') === 'missing'
			? 'Die Coffeeji-Tabellen fehlen noch. Bitte die Migration app/db/migrations/20261113_coffeeji.sql einspielen.'
			: (string) ($bundle['message'] ?? 'Die Bestellungen konnten nicht gelesen werden.'));
		include_once 'include/footer.php';
		return;
	}
	if (empty($bundle['details'])) {
		echo hc_notice('gelb', 'Ausgabefelder fehlen noch', 'Bitte app/db/migrations/20261115_coffeeji_orders_portal.sql einspielen. Bis dahin bleiben Ausgabe, Erstattung und Fehlergrund leer. Der nächste Bestell-Sync füllt sie aus der Schnittstelle.');
	}
?>
<form class="hc-filters" method="get" action="<?php echo appUrl('bestellungen'); ?>">
	<label>Bestellnummer
		<input class="form-control" type="text" name="bestellnummer" value="<?php echo hc_h($filter['order_no']); ?>" maxlength="80">
	</label>
	<label>Maschinenname
		<input class="form-control" type="text" name="maschinenname" value="<?php echo hc_h($filter['device_name']); ?>" maxlength="120">
	</label>
	<label>Maschinennummer
		<input class="form-control" type="text" name="maschinennummer" value="<?php echo hc_h($filter['device_no']); ?>" maxlength="80">
	</label>
	<label>Zahlart
		<select class="form-control" name="zahlart">
			<option value="">Alle</option>
			<?php foreach ($bundle['payways'] as $way) { ?>
				<option value="<?php echo hc_h($way); ?>" <?php echo $filter['pay_way_name'] === $way ? 'selected' : ''; ?>><?php echo hc_h(hc_payway($way)); ?></option>
			<?php } ?>
		</select>
	</label>
	<label>Bestellstatus
		<select class="form-control" name="bestellstatus">
			<option value="">Alle</option>
			<?php foreach (CoffeejiPortal::ORDER_STATUSES as $code => $label) { ?>
				<option value="<?php echo (int) $code; ?>" <?php echo $filter['status'] === $code ? 'selected' : ''; ?>><?php echo hc_h($label); ?></option>
			<?php } ?>
		</select>
	</label>
	<label>Ausgabestatus
		<select class="form-control" name="ausgabestatus">
			<option value="">Alle</option>
			<?php foreach (CoffeejiPortal::DELIVERY_STATUSES as $code => $label) { ?>
				<option value="<?php echo (int) $code; ?>" <?php echo $filter['out_stock_status'] === $code ? 'selected' : ''; ?>><?php echo hc_h($label); ?></option>
			<?php } ?>
		</select>
	</label>
	<label>Bestellzeit von
		<input class="form-control" type="date" name="von" value="<?php echo hc_h($filter['from']->format('Y-m-d')); ?>">
	</label>
	<label>bis
		<input class="form-control" type="date" name="bis" value="<?php echo hc_h($filter['to']->modify('-1 second')->format('Y-m-d')); ?>">
	</label>
	<div class="hc-actions">
		<button class="btn d" type="submit">Suchen</button>
		<a class="btn" href="<?php echo appUrl('bestellungen'); ?>">Zurücksetzen</a>
	</div>
</form>

<div class="tiles">
	<div class="tile">
		<div class="l"><span class="bl"><?php echo hc_icon('cart', 18); ?></span>Anzahl</div>
		<div class="v"><?php echo (int) $bundle['total']; ?></div>
		<div class="d"><span>im Filter</span></div>
	</div>
	<div class="tile hero">
		<div class="l"><span class="bl"><?php echo hc_icon('euro', 18); ?></span>Summe Betrag</div>
		<div class="v"><?php echo hc_money((float) $bundle['amount']); ?></div>
		<div class="d"><span>alle gefilterten Bestellungen</span></div>
	</div>
	<div class="tile">
		<div class="l"><span class="bl"><?php echo hc_icon('receipt', 18); ?></span>Umsatz</div>
		<div class="v"><?php echo hc_money((float) $bundle['revenue']); ?></div>
		<div class="d"><span><?php echo (int) $bundle['revenue_count']; ?> in Herstellung oder fertig</span></div>
	</div>
	<div class="tile">
		<div class="l"><span class="bl"><?php echo hc_icon('alert', 18); ?></span>Offen</div>
		<div class="v"><?php echo (int) $bundle['open']; ?></div>
		<div class="d"><span>Herstellung oder Ausgabe offen</span></div>
	</div>
</div>

<div class="panel">
	<div class="ph">
		<div>
			<h3>Bestellungen</h3>
			<small>Umsatz wie im Händlerportal: Status „In Herstellung“ und „Fertig“. Hängende Zeilen sind als offen markiert.</small>
		</div>
	</div>
	<div class="hc-scroll">
		<table class="hc-table hc-dense">
			<thead>
				<tr>
					<th>Bestellnummer</th>
					<th>Maschinennummer</th>
					<th>Maschinenname</th>
					<th>Produkt</th>
					<th class="r">Betrag</th>
					<th>Zahlart</th>
					<th>Bestellstatus</th>
					<th>Ausgabestatus</th>
					<th>Erstattungsstatus</th>
					<th>Bestellzeit</th>
					<th>Ausgabezeit</th>
					<th>Erstattungszeit</th>
					<th>Zeitzone</th>
					<th>Grund für Herstellungsfehler</th>
				</tr>
			</thead>
			<tbody>
			<?php if ($bundle['rows'] === array()) { ?>
				<tr><td colspan="14">Keine Bestellungen im Filter.</td></tr>
			<?php } ?>
			<?php foreach ($bundle['rows'] as $order) {
				$status = (int) ($order['status'] ?? 0);
			?>
				<tr>
					<td><?php echo hc_h($order['order_no']); ?></td>
					<td><?php echo hc_h($order['machine_no']); ?></td>
					<td><?php echo hc_h($order['device_name']); ?></td>
					<td><?php echo hc_h($order['goods_name']); ?></td>
					<td class="r"><?php echo hc_money((float) $order['pay_amt']); ?></td>
					<td><?php echo hc_h(hc_payway((string) ($order['pay_way_name'] ?? ''))); ?></td>
					<td><span class="amp <?php echo hc_h(hc_order_tone($status)); ?>"><i></i><?php echo hc_h(hc_portal_status($status)); ?></span> <?php echo hc_open_mark($status, $order['out_stock_status'] ?? null); ?></td>
					<td><?php echo hc_h(hc_portal_delivery($order['out_stock_status'] ?? null)); ?></td>
					<td><?php echo hc_h(hc_portal_refund($order['refund_status'] ?? null)); ?></td>
					<td><?php echo hc_h(hc_stamp($order['create_time'] ?? null)); ?></td>
					<td><?php echo hc_h(hc_stamp(isset($order['out_stock_time']) ? (string) $order['out_stock_time'] : null)); ?></td>
					<td><?php echo hc_h(hc_stamp(isset($order['refund_time']) ? (string) $order['refund_time'] : null)); ?></td>
					<td><?php echo hc_h($order['time_zone_id'] ?? ''); ?></td>
					<td class="clip"><?php echo hc_h($order['fail_remark'] ?? ''); ?></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
	<?php echo hc_portal_pager('bestellungen', $filter, (int) $bundle['page'], (int) $bundle['pages'], (int) $bundle['per_page'], (int) $bundle['total']); ?>
</div>
<?php include_once 'include/footer.php'; ?>
