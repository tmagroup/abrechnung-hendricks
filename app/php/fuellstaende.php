<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$page_title = 'Füllstände';
	$page_subtitle = 'Restmenge je Zutat · Warnmarke im Balken';
	include_once 'include/header.php';

	try {
		$bundle = (new CoffeejiReadModel(new CoffeejiRepository()))->machines();
	} catch (Throwable $e) {
		(new CoffeejiLogger(COFFEEJI_LOG))->error('Füllstände: ' . $e->getMessage());
		$bundle = array('state' => 'error', 'message' => 'Die Füllstände konnten nicht gelesen werden.');
	}

	if (($bundle['state'] ?? '') !== 'ready') {
		echo hc_notice('gelb', 'Füllstände noch nicht verbunden', (string) $bundle['message']);
		include_once 'include/footer.php';
		return;
	}
?>
<p class="hint" style="margin-top:-6px">Die dunkle Marke ist die Warnschwelle. Ein roter Balken liegt auf oder unter dieser Schwelle. Grün steht nur für den Betriebsstatus, nicht für den Füllstand.</p>
<?php if ($bundle['devices'] === array()) { ?>
	<?php echo hc_notice('gelb', 'Keine Füllstände', 'Es wurden noch keine Maschinen synchronisiert.'); ?>
<?php } else { ?>
	<div class="mgrid">
		<?php foreach ($bundle['devices'] as $device) {
			$tone = hc_machine_tone($device); ?>
			<article class="mc <?php echo hc_h($tone[0]); ?>">
				<div class="mh">
					<div class="mi"><?php echo hc_icon('gauge', 28); ?></div>
					<div>
						<b><?php echo hc_h($device['name'] ?: 'Maschine'); ?></b>
						<span><?php echo hc_h($device['device_ext_no']); ?></span>
					</div>
				</div>
				<div style="margin-top:16px">
					<?php if (($device['materials'] ?? array()) === array()) { ?>
						<p class="muted">Für diese Maschine liegen noch keine Zutaten vor.</p>
					<?php } ?>
					<?php foreach ($device['materials'] as $material) { echo hc_fill_bar($material); } ?>
				</div>
			</article>
		<?php } ?>
	</div>
<?php } ?>
<?php include_once 'include/footer.php'; ?>
