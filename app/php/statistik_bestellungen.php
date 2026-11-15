<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$now = CoffeejiTime::now();
	$filter = CoffeejiPortal::statsFilter($_GET, $now);
	$path = 'statistik_bestellungen';
	$query = new CoffeejiPortalQuery();
	$bundle = array('state' => 'missing');
	try {
		if ($query->tablesReady()) {
			$stats = $query->statistics($filter);
			if (($_GET['export'] ?? '') === 'csv') {
				$lines = array();
				foreach ($stats['machines'] as $row) {
					$lines[] = array(
						(string) $row['device_ext_no'],
						(string) $row['device_name'],
						(string) $row['device_no'],
						(string) $row['count'],
						hc_csv_money((float) $row['amount']),
						(string) $row['open'],
					);
				}
				hc_send_csv('bestellstatistik.csv', array('Maschinennummer', 'Maschinenname', 'Maschinen-ID', 'Bestellungen', 'Umsatz', 'davon offen'), $lines);
			}
			$page = CoffeejiPortal::paginate($stats['machines'], (int) $filter['page'], (int) $filter['per_page']);
			$bundle = $stats;
			$bundle['state'] = 'ready';
			$bundle['page_rows'] = $page['rows'];
			$bundle['meta'] = $page['meta'];
		}
	} catch (Throwable $e) {
		(new CoffeejiLogger(COFFEEJI_LOG))->error('Bestellstatistik: ' . $e->getMessage());
		$bundle = array('state' => 'error', 'message' => 'Die Statistik konnte nicht gelesen werden.');
	}

	$fromLabel = $filter['from']->format('d.m.Y');
	$toLabel = $filter['to']->modify('-1 second')->format('d.m.Y');
	$page_title = 'Bestellstatistik';
	$page_subtitle = 'Je Maschine · ' . $fromLabel . ' bis ' . $toLabel . ' · nur lokale Daten';
	$page_foot = 'Hendricks Coffee · Zeiten in MEZ · Umsatz zählt Herstellung und fertig';
	$page_banner_html = hc_portal_grain($path, $filter);
	$page_banner_html .= '<a class="btn d" href="' . hc_h(hc_portal_href($path, $filter, array('export' => 'csv', 'seite' => null))) . '">Export</a>';
	include_once 'include/header.php';

	if (!empty($bundle['truncated'])) {
		echo hc_notice('gelb', 'Auswertung gekürzt', 'Es werden höchstens 20.000 Bestellungen je Abruf ausgewertet.');
	}
	if (($bundle['state'] ?? '') !== 'ready') {
		echo hc_notice('gelb', 'Bestellstatistik noch nicht verbunden', ($bundle['state'] ?? '') === 'missing'
			? 'Die Coffeeji-Tabellen fehlen noch. Bitte die Migration app/db/migrations/20261113_coffeeji.sql einspielen.'
			: (string) ($bundle['message'] ?? 'Die Statistik konnte nicht gelesen werden.'));
		include_once 'include/footer.php';
		return;
	}
?>
<form class="hc-filters" method="get" action="<?php echo appUrl($path); ?>">
	<input type="hidden" name="korn" value="<?php echo hc_h($filter['grain']); ?>">
	<label>Maschinennummer
		<input class="form-control" type="text" name="maschinennummer" value="<?php echo hc_h($filter['device_no']); ?>" maxlength="80">
	</label>
	<label>Maschinenname
		<input class="form-control" type="text" name="maschinenname" value="<?php echo hc_h($filter['device_name']); ?>" maxlength="120">
	</label>
	<label>von
		<input class="form-control" type="date" name="von" value="<?php echo hc_h($filter['from']->format('Y-m-d')); ?>">
	</label>
	<label>bis
		<input class="form-control" type="date" name="bis" value="<?php echo hc_h($filter['to']->modify('-1 second')->format('Y-m-d')); ?>">
	</label>
	<div class="hc-actions">
		<button class="btn d" type="submit">Suchen</button>
		<a class="btn" href="<?php echo appUrl($path); ?>">Zurücksetzen</a>
	</div>
</form>
<p class="muted">Täglich, monatlich und jährlich gruppieren den Umsatz im gewählten Zeitraum. Gezählt werden Bestellungen in Herstellung und fertig.</p>
<div class="tiles">
	<div class="tile">
		<div class="l"><span class="bl"><?php echo hc_icon('cart', 18); ?></span>Anzahl Transaktionen</div>
		<div class="v"><?php echo (int) $bundle['summary']['revenue_count']; ?></div>
		<div class="d"><span>Herstellung und fertig</span></div>
	</div>
	<div class="tile hero">
		<div class="l"><span class="bl"><?php echo hc_icon('euro', 18); ?></span>Umsatz</div>
		<div class="v"><?php echo hc_money((float) $bundle['summary']['revenue']); ?></div>
		<div class="d"><span>Zahlbetrag dieser Bestellungen</span></div>
	</div>
	<div class="tile">
		<div class="l"><span class="bl"><?php echo hc_icon('alert', 18); ?></span>davon offen</div>
		<div class="v"><?php echo (int) $bundle['summary']['open']; ?></div>
		<div class="d"><span>noch in Herstellung oder Ausgabe offen</span></div>
	</div>
</div>
<div class="panel" style="margin-bottom:18px">
	<div class="ph"><div><h3>Umsatz im Zeitraum</h3><small><?php echo hc_h(array('tag' => 'Tage', 'monat' => 'Monate', 'jahr' => 'Jahre')[$filter['grain']]); ?></small></div></div>
	<?php echo hc_bar_chart($bundle['periods']); ?>
</div>
<div class="panel">
	<div class="ph"><div><h3>Maschinen</h3><small>Maschinen-ID kommt aus dem Geräteabruf, sonst aus der Bestellung.</small></div></div>
	<div class="hc-scroll">
		<table class="hc-table">
			<thead>
				<tr>
					<th>Maschinennummer</th>
					<th>Maschinenname</th>
					<th>Maschinen-ID</th>
					<th class="r">Bestellungen</th>
					<th class="r">Umsatz</th>
					<th class="r">davon offen</th>
				</tr>
			</thead>
			<tbody>
			<?php if ($bundle['page_rows'] === array()) { ?>
				<tr><td colspan="6">Keine Umsätze im Filter.</td></tr>
			<?php } ?>
			<?php foreach ($bundle['page_rows'] as $row) { ?>
				<tr>
					<td><?php echo hc_h($row['device_ext_no']); ?></td>
					<td><?php echo hc_h($row['device_name']); ?></td>
					<td><?php echo hc_h($row['device_no']); ?></td>
					<td class="r"><?php echo (int) $row['count']; ?></td>
					<td class="r"><?php echo hc_money((float) $row['amount']); ?></td>
					<td class="r"><?php echo (int) $row['open'] > 0 ? '<span class="chip wait"><i></i>' . (int) $row['open'] . '</span>' : '0'; ?></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
	<?php echo hc_portal_pager($path, $filter, (int) $bundle['meta']['page'], (int) $bundle['meta']['pages'], (int) $bundle['meta']['per_page'], (int) $bundle['meta']['total']); ?>
</div>
<?php include_once 'include/footer.php'; ?>
