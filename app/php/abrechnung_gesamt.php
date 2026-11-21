<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$page_title	= "Abrechnung Gesamt";
	$page_subtitle = "Zeitraum aus den Bestellungen";
	$page_banner_html = '<a class="btn d" href="' . appUrl('abrechnung_gesamt_list') . '">Gespeicherte Abrechnungen</a>';
	include_once "include/header.php";
	$hc_start = $hc_start ?? '';
	$hc_end = $hc_end ?? '';
	$hc_preview = $hc_preview ?? null;
	$hc_abrechnung_error = $hc_abrechnung_error ?? '';
?>
<div class="content-wrapper">
    <div class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-12">
					<h1 class="m-0 text-dark">Abrechnung Gesamt</h1>
				</div>
			</div>
		</div>
    </div>

    <section class="content">
		<div class="container-fluid">
			<div class="row">
				<div class="col-12">
					<div class="card">
						<form method="post">
							<div class="card-body">
								<p>Die Abrechnung wird aus den Bestellungen gerechnet, mit derselben Umsatzregel wie unter Bestellungen (Status in Herstellung und fertig). Gratis und Einlösung entfallen. Beginn und Ende sind inklusive, jeweils ab 00:00 Uhr Europe/Berlin. Intern läuft das Ende bis zum nächsten Tag 00:00 Uhr, exklusiv.</p>
								<p>Standard-Steuersatz ohne hinterlegte Produktregel: 19&nbsp;%. Die steuerliche Einordnung ist durch den Steuerberater zu bestätigen. Beträge sind brutto.</p>
								<?php if ($hc_abrechnung_error !== '') { ?>
									<?php echo hc_notice('rot', 'Abrechnung', $hc_abrechnung_error); ?>
								<?php } ?>
								<div class="row">
									<div class="col-md-4">
										<div class="form-group">
											<label for="start_date">Zeitraum Beginn</label>
											<input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo hc_h($hc_start); ?>" required />
										</div>
									</div>
									<div class="col-md-4">
										<div class="form-group">
											<label for="end_date">Zeitraum Ende</label>
											<input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo hc_h($hc_end); ?>" required />
										</div>
									</div>
								</div>
							</div>
							<div class="card-footer">
								<button type="submit" name="vorschau" value="1" class="btn btn-primary">Vorschau</button>
								<button type="submit" name="speichern" value="1" class="btn btn-coffee">Abrechnung speichern &amp; PDF</button>
							</div>
						</form>
					</div>

					<?php if (is_array($hc_preview)) { ?>
						<?php $totals = $hc_preview['totals']; ?>
						<div class="card">
							<div class="card-header">Vorschau <?php echo hc_h($hc_preview['start_date']); ?> bis <?php echo hc_h($hc_preview['end_date']); ?></div>
							<div class="card-body">
								<?php if ($hc_preview['existing'] !== array()) { ?>
									<?php
										$vorhanden = array();
										foreach ($hc_preview['existing'] as $alt) {
											$quelle = (($alt['source'] ?? 'excel') === 'coffeeji') ? 'Coffeeji' : 'Excel';
											$vorhanden[] = '#' . (int) $alt['id'] . ' (' . $quelle . ')';
										}
									?>
									<?php echo hc_notice('gelb', 'Zeitraum bereits abgerechnet', 'Für diesen Zeitraum gibt es schon ' . implode(', ', $vorhanden) . '. Eine weitere Abrechnung wird zusätzlich gespeichert und ändert die vorhandene nicht.'); ?>
									<form method="post" class="mb-3">
										<input type="hidden" name="start_date" value="<?php echo hc_h($hc_start); ?>" />
										<input type="hidden" name="end_date" value="<?php echo hc_h($hc_end); ?>" />
										<label><input type="checkbox" name="trotzdem" value="1" /> Trotzdem speichern</label>
										<button type="submit" name="speichern" value="1" class="btn btn-coffee btn-sm">Abrechnung speichern &amp; PDF</button>
									</form>
								<?php } ?>
								<?php if ((int) $totals['open_in_period'] > 0) { ?>
									<?php echo hc_notice('gelb', 'Noch offene Bestellungen', (int) $totals['open_in_period'] . ' Bestellungen im Zeitraum sind noch offen. Davon gehen ' . (int) $totals['open'] . ' in diese Abrechnung ein. Nach dem Speichern bleibt der Betrag stehen, auch wenn Status oder Erstattung später nachgezogen werden.'); ?>
								<?php } ?>
								<?php if ($hc_preview['unconfigured'] !== array()) { ?>
									<?php echo hc_notice('gelb', 'Produkte ohne Steuer-Konfiguration', 'Diese Produkte sind nicht hinterlegt und werden mit 19 % angesetzt: ' . implode(', ', $hc_preview['unconfigured']) . '.'); ?>
								<?php } ?>
								<p>
									Umsatz wie bei den Bestellungen (Status 1 und 2): <b><?php echo hc_money((float) $totals['portal_revenue']); ?></b>
									(<?php echo (int) $totals['portal_count']; ?> Bestellungen).
									Abzüglich Gratis <?php echo hc_money((float) $totals['excluded_gratis']); ?>
									(<?php echo (int) $totals['excluded_gratis_count']; ?>)
									und Einlösung <?php echo hc_money((float) $totals['excluded_einloesung']); ?>
									(<?php echo (int) $totals['excluded_einloesung_count']; ?>).
									Abrechnung: <b><?php echo hc_money((float) $totals['amount']); ?></b>.
								</p>
								<p>Bar <?php echo hc_money((float) $totals['cash']); ?>, Karte <?php echo hc_money((float) $totals['card']); ?><?php if ((int) $totals['other_cents'] !== 0) { ?>, Sonstige <?php echo hc_money((float) $totals['other']); ?><?php } ?>. Händler im PDF: <?php echo hc_h($hc_preview['merchant']); ?>.</p>
								<table class="hc-table">
									<thead>
										<tr>
											<th>Maschinen-Nr</th>
											<th>Standort</th>
											<th>Händler</th>
											<th>Bestellungen</th>
											<th class="r">Betrag</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($hc_preview['machines'] as $machine) { ?>
											<tr>
												<td><?php echo hc_h($machine['machine_no']); ?></td>
												<td><?php echo hc_h($machine['machine_name']); ?></td>
												<td><?php echo hc_h($machine['dealer']); ?></td>
												<td><?php echo (int) $machine['orders']; ?></td>
												<td class="r"><?php echo hc_money((float) $machine['amount']); ?></td>
											</tr>
										<?php } ?>
										<?php if ($hc_preview['machines'] === array()) { ?>
											<tr><td colspan="5">Keine abrechenbaren Bestellungen im Zeitraum.</td></tr>
										<?php } ?>
									</tbody>
								</table>
								<?php if ($hc_preview['vat'] !== array()) { ?>
									<table class="hc-table">
										<thead>
											<tr><th>MwSt-Satz</th><th class="r">Netto</th><th class="r">Steuer</th><th class="r">Brutto</th></tr>
										</thead>
										<tbody>
											<?php foreach ($hc_preview['vat'] as $vat) { ?>
												<tr>
													<td><?php echo (int) $vat['rate']; ?> %</td>
													<td class="r"><?php echo hc_money((float) $vat['net']); ?></td>
													<td class="r"><?php echo hc_money((float) $vat['tax']); ?></td>
													<td class="r"><?php echo hc_money((float) $vat['gross']); ?></td>
												</tr>
											<?php } ?>
										</tbody>
									</table>
								<?php } ?>
							</div>
						</div>
					<?php } ?>

					<div class="card">
						<div class="card-header">Altdaten (Excel)</div>
						<form method="post" enctype="multipart/form-data" target="_blank">
							<div class="card-body">
								<p>Bestehende Excel-Abrechnungen bleiben gespeichert und als PDF abrufbar. Neue Zeiträume bitte aus den Bestellungen rechnen. Der Upload ist nur für alte Dateien.</p>
								<div class="row">
									<div class="col-md-4">
										<div class="form-group">
											<label for="excel_start_date">Zeitraum Beginn</label>
											<input type="date" id="excel_start_date" name="start_date" class="form-control" value="<?php echo hc_h(setValue('start_date')); ?>" />
											<small class="form-text" style="color:red;"><?php echo errorMsg('start_date'); ?></small>
										</div>
									</div>
									<div class="col-md-4">
										<div class="form-group">
											<label for="excel_end_date">Zeitraum Ende</label>
											<input type="date" id="excel_end_date" name="end_date" class="form-control" value="<?php echo hc_h(setValue('end_date')); ?>" />
											<small class="form-text" style="color:red;"><?php echo errorMsg('end_date'); ?></small>
										</div>
									</div>
									<div class="col-md-4">
										<div class="form-group">
											<label for="excel_sheet">Excel-Datei</label>
											<input type="file" class="form-control" id="excel_sheet" name="excel_sheet" accept=".xls,.xlsx,.csv,application/vnd.ms-excel" />
											<small id="error_excel_sheet" class="form-text" style="color:red;"><?php echo errorMsg('excel_sheet'); ?></small>
										</div>
									</div>
								</div>
							</div>
							<div class="card-footer">
								<input type="submit" name="add_excel" value="Altdaten hochladen" class="btn btn-primary" />
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
    </section>
</div>
<script>
	document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(function (form) {
		form.addEventListener('submit', function (event) {
			var file = document.getElementById('excel_sheet');
			var slot = document.getElementById('error_excel_sheet');
			if (!file || file.value !== '') {
				if (slot) slot.textContent = '';
				return;
			}
			if (slot) slot.textContent = 'Excel-Datei ist erforderlich.';
			event.preventDefault();
		});
	});
</script>
<?php
	include_once "include/footer.php";
?>
