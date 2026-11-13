<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$page_title = 'Maschinen';
	$page_subtitle = 'Status, Störungen und Herzschlag';
	include_once 'include/header.php';

	try {
		$bundle = (new CoffeejiReadModel(new CoffeejiRepository()))->machines();
	} catch (Throwable $e) {
		(new CoffeejiLogger(COFFEEJI_LOG))->error('Maschinen: ' . $e->getMessage());
		$bundle = array('state' => 'error', 'message' => 'Die Maschinenliste konnte nicht gelesen werden.');
	}

	if (($bundle['state'] ?? '') !== 'ready') {
		echo hc_notice('gelb', 'Maschinen noch nicht verbunden', (string) $bundle['message']);
		include_once 'include/footer.php';
		return;
	}
?>
<?php if ($bundle['devices'] === array()) { ?>
	<?php echo hc_notice('gelb', 'Keine Maschinen', 'Es wurden noch keine Geräte synchronisiert.'); ?>
<?php } else { ?>
	<div class="mgrid">
		<?php foreach ($bundle['devices'] as $device) {
			echo hc_machine_card($device);
		} ?>
	</div>
<?php } ?>
<div class="panel" style="margin-top:18px">
	<div class="ph"><div><h3>Geräte im Überblick</h3><small>Zuletzt gemeldeter Herzschlag in MEZ</small></div></div>
	<table class="hc-table">
		<thead>
			<tr>
				<th>Maschine</th>
				<th>Nummer</th>
				<th>Modell</th>
				<th>Software</th>
				<th>Herzschlag</th>
				<th>Status</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($bundle['devices'] as $device) {
				$tone = hc_machine_tone($device); ?>
				<tr>
					<td><b><?php echo hc_h($device['name']); ?></b></td>
					<td><?php echo hc_h($device['device_ext_no']); ?></td>
					<td><?php echo hc_h($device['device_type_name']); ?></td>
					<td><?php echo hc_h($device['soft_run_version']); ?></td>
					<td><?php echo hc_clock($device['heart_time'] ?? null, true); ?></td>
					<td><span class="amp <?php echo hc_h($tone[0]); ?>"><i></i><?php echo hc_h($tone[1]); ?></span></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div>
<?php include_once 'include/footer.php'; ?>
