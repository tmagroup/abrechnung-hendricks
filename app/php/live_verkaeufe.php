<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$now = CoffeejiTime::now();
	$deviceFilter = trim((string) ($_GET['geraet'] ?? ''));
	$page_title = 'Live-Verkäufe';
	$page_subtitle = 'Heute · Aktualisierung alle 30 Sekunden · Zeiten in MEZ';
	$page_banner_html = '<div class="chip live"><i></i>Live</div><div class="chip" id="live-stamp">' . hc_h($now->format('d.m.Y, H:i')) . ' Uhr</div>';
	include_once 'include/header.php';

	try {
		$model = new CoffeejiReadModel(new CoffeejiRepository());
		$bundle = $model->live($now, $deviceFilter !== '' ? $deviceFilter : null);
		$machines = $model->machines();
	} catch (Throwable $e) {
		(new CoffeejiLogger(COFFEEJI_LOG))->error('Live-Verkäufe: ' . $e->getMessage());
		$bundle = array('state' => 'error', 'message' => 'Die Verkäufe konnten nicht gelesen werden.');
		$machines = array('state' => 'error', 'devices' => array());
	}

	if (($bundle['state'] ?? '') !== 'ready') {
		echo hc_notice('gelb', 'Live-Verkäufe noch nicht verbunden', (string) $bundle['message']);
		include_once 'include/footer.php';
		return;
	}
?>
<div class="tiles" style="margin-bottom:18px">
	<div class="tile hero">
		<div class="l"><span class="bl"><?php echo hc_icon('euro', 18); ?></span>Umsatz heute</div>
		<div class="v" id="live-sum"><?php echo hc_money((float) $bundle['sales']['amount']); ?></div>
		<div class="d"><span>nur ausgegebene Verkäufe</span></div>
	</div>
	<div class="tile">
		<div class="l"><span class="bl"><?php echo hc_icon('cart', 18); ?></span>Ausgegebene Verkäufe</div>
		<div class="v" id="live-count"><?php echo (int) $bundle['sales']['count']; ?></div>
		<div class="d"><span>Status fertig</span></div>
	</div>
</div>
<form class="filter-row" method="get" action="<?php echo appUrl('live_verkaeufe'); ?>">
	<div>
		<label for="geraet">Maschine</label>
		<select class="form-control" id="geraet" name="geraet">
			<option value="">Alle Maschinen</option>
			<?php foreach (($machines['devices'] ?? array()) as $device) { ?>
				<option value="<?php echo hc_h($device['id']); ?>" <?php echo $deviceFilter === (string) $device['id'] ? 'selected' : ''; ?>><?php echo hc_h($device['name']); ?></option>
			<?php } ?>
		</select>
	</div>
	<button class="btn d" type="submit">Filtern</button>
</form>
<div class="panel">
	<div class="ph"><div><h3>Bestellungen von heute</h3><small>Alle Status, Umsatz zählt nur ausgegebene Verkäufe</small></div></div>
	<div id="live-list"><?php echo hc_render_live_table($bundle['orders']); ?></div>
</div>
<script>
	setInterval(function () {
		$.getJSON('<?php echo appUrl('live_verkaeufe_ajax'); ?>', { geraet: $('#geraet').val() }, function (data) {
			if (!data || !data.ok) { return; }
			$('#live-list').html(data.html);
			$('#live-sum').text(data.sum);
			$('#live-count').text(data.count);
			$('#live-stamp').text(data.updated);
		});
	}, 30000);
</script>
<?php include_once 'include/footer.php'; ?>
