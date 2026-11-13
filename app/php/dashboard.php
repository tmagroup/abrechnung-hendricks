<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$now = CoffeejiTime::now();
	$user = hc_current_user();
	$first = hc_first_name((string) ($user['name'] ?? ''));
	$page_title = 'Guten Tag' . ($first !== '' ? ', ' . $first : '');
	$page_subtitle = 'Premium Kaffee · Live-Übersicht · ' . $now->format('d.m.Y, H:i') . ' Uhr MEZ';

	$bundle = array('state' => 'setup', 'message' => 'Die Live-Übersicht ist noch nicht eingerichtet.');
	try {
		$bundle = (new CoffeejiReadModel(new CoffeejiRepository()))->dashboard($now);
	} catch (Throwable $e) {
		(new CoffeejiLogger(COFFEEJI_LOG))->error('Dashboard: ' . $e->getMessage());
		$bundle = array(
			'state' => 'error',
			'message' => 'Die Live-Daten konnten gerade nicht gelesen werden. Die Abrechnung ist davon nicht betroffen.',
		);
	}

	$live = false;
	if (($bundle['state'] ?? '') === 'ready' && !empty($bundle['last_orders_sync'])) {
		$synced = CoffeejiTime::parseLocal($bundle['last_orders_sync']);
		$live = $synced !== null && $synced > $now->modify('-15 minutes');
	}
	$page_banner_html = '<div class="chip ' . ($live ? 'live' : 'wait') . '"><i></i>' . ($live ? 'Live' : 'Sync ausstehend') . '</div>';
	$page_banner_html .= '<div class="chip">' . hc_icon('calendar', 16) . hc_h($now->format('d.m.Y')) . '</div>';

	include_once 'include/header.php';

	if (($bundle['state'] ?? '') !== 'ready') {
		echo hc_notice('gelb', 'Noch keine Live-Daten', (string) ($bundle['message'] ?? ''));
		include_once 'include/footer.php';
		return;
	}

	$summary = $bundle['summary'];
	$monthName = hc_month_long((int) $now->format('n'));
	$prevName = hc_month_short((int) $summary['prev_month_start']->format('n'));
	$prevLong = hc_month_long((int) $summary['prev_month_start']->format('n'));
	$day = (int) $now->format('j');
	$maxDrink = 1;
	foreach ($summary['drinks'] as $drink) {
		$maxDrink = max($maxDrink, (int) $drink['count']);
	}
?>
<div class="tiles">
	<div class="tile hero">
		<div class="l"><span class="bl"><?php echo hc_icon('euro', 18); ?></span>Umsatz heute</div>
		<div class="v"><?php echo hc_money((float) $summary['today']['amount']); ?></div>
		<div class="d"><?php echo hc_delta($summary['today_delta']); ?><span>ggü. Vortag <?php echo hc_h($now->format('H:i')); ?></span></div>
	</div>
	<div class="tile">
		<div class="l"><span class="bl"><?php echo hc_icon('calendar', 18); ?></span>Umsatz <?php echo hc_h($monthName); ?></div>
		<div class="v"><?php echo hc_money((float) $summary['month']['amount']); ?></div>
		<div class="d"><?php echo hc_delta($summary['month_delta']); ?><span>ggü. 1.–<?php echo $day; ?>. <?php echo hc_h($prevName); ?></span></div>
	</div>
	<div class="tile">
		<div class="l"><span class="bl"><?php echo hc_icon('calendar', 18); ?></span>Umsatz Vormonat</div>
		<div class="v"><?php echo hc_money((float) $summary['prev_month']['amount']); ?></div>
		<div class="d"><span class="pl n"><?php echo hc_h($prevLong . ' ' . $summary['prev_month_start']->format('Y')); ?></span><span>abgeschlossen</span></div>
	</div>
	<div class="tile">
		<div class="l"><span class="bl"><?php echo hc_icon('cart', 18); ?></span>Verkäufe <?php echo hc_h($monthName); ?></div>
		<div class="v"><?php echo (int) $summary['month']['count']; ?></div>
		<div class="d"><?php echo hc_delta($summary['sales_delta']); ?><span>heute <?php echo (int) $summary['today_count']; ?> · <?php echo hc_h($prevName); ?> <?php echo (int) $summary['prev_month']['count']; ?></span></div>
	</div>
</div>

<div class="sec">
	<h2>Meine Maschinen</h2>
	<small>Status, Störungen und Füllstände</small>
	<a href="<?php echo appUrl('maschinen'); ?>">Alle Maschinen →</a>
</div>
<?php if ($bundle['devices'] === array()) { ?>
	<?php echo hc_notice('gelb', 'Noch keine Maschinen', 'Der Abruf hat noch keine Geräte gespeichert. Sobald der Cron läuft, erscheinen die Karten hier.'); ?>
<?php } else { ?>
	<div class="mgrid">
		<?php foreach ($bundle['devices'] as $device) { echo hc_machine_card($device); } ?>
	</div>
	<p class="hint">Dunkle Marke im Balken = Warnschwelle. Liegt der Füllstand darunter, wird der Balken rot.</p>
<?php } ?>

<div class="sec">
	<h2>Verkäufe &amp; Umsatz</h2>
	<small>Letzte 30 Tage und aktuelle Bestellungen</small>
</div>
<div class="two">
	<div class="panel">
		<div class="ph">
			<div>
				<h3>Umsatzverlauf</h3>
				<small>Tagesumsatz aller Maschinen · nur ausgegebene Verkäufe</small>
			</div>
			<div class="r">Ø <?php echo hc_money((float) $summary['avg_per_day']); ?> / Tag</div>
		</div>
		<?php echo hc_bar_chart($summary['chart']); ?>
		<div class="ph" style="margin-top:18px"><div><h3>Beliebteste Getränke im <?php echo hc_h($monthName); ?></h3></div></div>
		<?php if ($summary['drinks'] === array()) { ?>
			<p class="muted">In diesem Monat gibt es noch keine ausgegebenen Verkäufe.</p>
		<?php } ?>
		<?php foreach ($summary['drinks'] as $drink) { ?>
			<div class="prod">
				<span class="n"><?php echo hc_h($drink['name']); ?></span>
				<span class="b"><i style="width:<?php echo (int) round($drink['count'] / $maxDrink * 100); ?>%"></i></span>
				<span class="c"><?php echo (int) $drink['count']; ?></span>
			</div>
		<?php } ?>
	</div>
	<div class="panel">
		<div class="ph">
			<div>
				<h3>Letzte Verkäufe</h3>
				<small>Live · Zeiten in MEZ</small>
			</div>
			<a class="r" href="<?php echo appUrl('live_verkaeufe'); ?>">Alle →</a>
		</div>
		<?php echo hc_sales_html($bundle['recent']); ?>
	</div>
</div>
<?php include_once 'include/footer.php'; ?>
