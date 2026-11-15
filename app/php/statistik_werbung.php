<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$now = CoffeejiTime::now();
	$from = $now->setTime(0, 0)->modify('-29 days');
	$page_title = 'Werbestatistik';
	$page_subtitle = 'Programm, Klicks und Wiedergaben · keine lokalen Daten';
	$page_foot = 'Hendricks Coffee · Werbestatistik ohne Coffeeji-Daten';
	include_once 'include/header.php';
?>
<form class="hc-filters" method="get" action="<?php echo appUrl('statistik_werbung'); ?>">
	<label>Programmname
		<input class="form-control" type="text" name="programm" value="<?php echo hc_h((string) ($_GET['programm'] ?? '')); ?>" maxlength="120">
	</label>
	<label>Maschinennummer
		<input class="form-control" type="text" name="maschinennummer" value="<?php echo hc_h((string) ($_GET['maschinennummer'] ?? '')); ?>" maxlength="80">
	</label>
	<label>Start
		<input class="form-control" type="datetime-local" name="start" value="<?php echo hc_h((string) ($_GET['start'] ?? ($from->format('Y-m-d') . 'T' . $from->format('H:i')))); ?>">
	</label>
	<label>Ende
		<input class="form-control" type="datetime-local" name="ende" value="<?php echo hc_h((string) ($_GET['ende'] ?? ($now->format('Y-m-d') . 'T' . $now->format('H:i')))); ?>">
	</label>
	<div class="hc-actions">
		<button class="btn d" type="submit">Suchen</button>
		<a class="btn" href="<?php echo appUrl('statistik_werbung'); ?>">Zurücksetzen</a>
	</div>
</form>
<?php echo hc_notice('gelb', 'Keine Daten', 'Coffeeji stellt diese Werte nicht über die Schnittstelle bereit.'); ?>
<div class="panel">
	<div class="ph">
		<div>
			<h3>Ereignisse</h3>
			<small>Maschinennummer, Programmcode, Programmname, Start, Ende, Klicks, Wiedergaben</small>
		</div>
	</div>
	<div class="hc-scroll">
		<table class="hc-table">
			<thead>
				<tr>
					<th>Maschinennummer</th>
					<th>Programmcode</th>
					<th>Programmname</th>
					<th>Start</th>
					<th>Ende</th>
					<th class="r">Klicks</th>
					<th class="r">Wiedergaben</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colspan="7">Keine Daten – Coffeeji stellt diese Werte nicht über die Schnittstelle bereit.</td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class="pager"><span>Gesamt 0</span></div>
</div>
<?php include_once 'include/footer.php'; ?>
