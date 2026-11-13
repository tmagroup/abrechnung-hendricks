<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$page_title = 'Monatsabrechnung';
	$page_subtitle = 'Platzhalter · die Berechnung folgt';
	include_once 'include/header.php';
?>
<div class="panel">
	<div class="ph">
		<div>
			<span class="soon">BALD</span>
			<h3 style="margin-top:12px">Automatische Monatsabrechnung</h3>
			<small>Noch ohne Berechnung. Die bestehenden Excel-Abrechnungen bleiben maßgeblich.</small>
		</div>
	</div>
	<?php echo hc_notice('gelb', 'Diese Seite rechnet noch nicht.', 'Umsätze aus der Coffeeji-API werden hier später je Maschine, Zahlart und Provisionsempfänger zusammengefasst. Bis dahin bitte Detailabrechnung, Abrechnung Gesamt und den Excel-Upload wie gewohnt nutzen. Deren Ergebnisse ändern sich durch diese Seite nicht.'); ?>
	<div class="filter-row">
		<a class="btn d" href="<?php echo appUrl('detailabrechnung'); ?>">Zur Detailabrechnung</a>
		<a class="btn" href="<?php echo appUrl('abrechnung_gesamt'); ?>">Zur Abrechnung Gesamt</a>
		<a class="btn" href="<?php echo appUrl('excel_upload'); ?>">Zum Excel-Upload</a>
	</div>
</div>
<?php include_once 'include/footer.php'; ?>
