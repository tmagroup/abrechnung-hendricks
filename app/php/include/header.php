<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	afterLogin();
	$hc_user = hc_current_user();
	$hc_name = trim((string) ($hc_user['name'] ?? ''));
	if ($hc_name === '') {
		$hc_name = 'Konto';
	}
	$page_title = $page_title ?? 'Hendricks Coffee';
	$page_subtitle = $page_subtitle ?? '';
	$page_banner_html = $page_banner_html ?? '';
	$nav = array(
		array('group' => 'Übersicht'),
		array('href' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'on' => array('dashboard')),
		array('group' => 'Betrieb'),
		array('href' => 'maschinen', 'label' => 'Maschinen', 'icon' => 'machine', 'tag' => 'NEU', 'on' => array('maschinen')),
		array('href' => 'live_verkaeufe', 'label' => 'Live-Verkäufe', 'icon' => 'pulse', 'tag' => 'NEU', 'on' => array('live_verkaeufe')),
		array('href' => 'fuellstaende', 'label' => 'Füllstände', 'icon' => 'gauge', 'tag' => 'NEU', 'on' => array('fuellstaende')),
		array('group' => 'Abrechnung'),
		array('href' => 'monatsabrechnung', 'label' => 'Monatsabrechnung', 'icon' => 'calendar', 'tag' => 'BALD', 'on' => array('monatsabrechnung')),
		array('href' => 'detailabrechnung', 'label' => 'Detailabrechnung', 'icon' => 'doc', 'on' => array('detailabrechnung', 'abrechungen'), 'children' => array(
			array('href' => 'abrechungen', 'label' => 'Gespeicherte Abrechnungen', 'on' => array('abrechungen')),
		)),
		array('href' => 'abrechnung_gesamt', 'label' => 'Abrechnung Gesamt', 'icon' => 'receipt', 'on' => array('abrechnung_gesamt', 'abrechnung_gesamt_list'), 'children' => array(
			array('href' => 'abrechnung_gesamt_list', 'label' => 'Gespeicherte Abrechnungen', 'on' => array('abrechnung_gesamt_list')),
		)),
		array('href' => 'excel_upload', 'label' => 'Excel-Upload', 'icon' => 'upload', 'on' => array('excel_upload')),
		array('group' => 'Provision'),
		array('href' => 'provisionsabrechung', 'label' => 'Provisionsabrechnung', 'icon' => 'percent', 'on' => array('provisionsabrechung', 'provisionsabrechung_list'), 'children' => array(
			array('href' => 'provisionsabrechung_list', 'label' => 'Gespeicherte Abrechnungen', 'on' => array('provisionsabrechung_list')),
		)),
		array('href' => 'provisions_empfanger', 'label' => 'Provisionsempfänger', 'icon' => 'users', 'on' => array('provisions_empfanger')),
		array('group' => 'Konto'),
		array('href' => 'account', 'label' => 'Passwort & Sicherheit', 'icon' => 'shield', 'on' => array('account')),
	);
?>
<!doctype html>
<html lang="de">
	<head>
		<meta charset="utf-8">
		<title><?php echo hc_h($page_title); ?> · Hendricks Coffee</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/all.min.css">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/adminlte.min.css">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/toastr.min.css">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/hendricks.css">
		<script src="<?php echo $app_url; ?>assets/js/jquery.min.js"></script>
		<script src="<?php echo $app_url; ?>assets/js/toastr.min.js"></script>
		<script src="<?php echo $app_url; ?>assets/js/bootstrap.bundle.js"></script>
	</head>
<body class="hc-app">
	<?php echo getMsg(); ?>
	<button class="nav-toggle" type="button" aria-label="Menü"><?php echo hc_icon('menu', 20); ?></button>
	<aside class="side">
		<div class="badge">
			<a href="<?php echo appUrl('dashboard'); ?>"><img src="<?php echo $app_url; ?>assets/images/logo-white.png" alt="The Hendricks Coffee"></a>
			<span>CRM</span>
		</div>
		<nav class="nav">
			<?php foreach ($nav as $item) { ?>
				<?php if (isset($item['group'])) { ?>
					<div class="grp"><?php echo hc_h($item['group']); ?></div>
				<?php } else { ?>
					<a class="<?php echo hc_nav_on($item['on']); ?>" href="<?php echo appUrl($item['href']); ?>">
						<?php echo hc_icon($item['icon']); ?>
						<?php echo hc_h($item['label']); ?>
						<?php if (!empty($item['tag'])) { ?><span class="tag"><?php echo hc_h($item['tag']); ?></span><?php } ?>
					</a>
					<?php if (!empty($item['children'])) { ?>
						<?php foreach ($item['children'] as $child) { ?>
							<a class="sub <?php echo hc_nav_on($child['on']); ?>" href="<?php echo appUrl($child['href']); ?>">
								<?php echo hc_h($child['label']); ?>
							</a>
						<?php } ?>
					<?php } ?>
				<?php } ?>
			<?php } ?>
		</nav>
		<div class="me">
			<a class="av" href="<?php echo appUrl('account'); ?>"><?php echo hc_h(hc_initials($hc_name)); ?></a>
			<a class="who" href="<?php echo appUrl('account'); ?>"><b><?php echo hc_h($hc_name); ?></b><span>Angemeldet</span></a>
			<a class="out" href="<?php echo appUrl('logout'); ?>" title="Abmelden"><?php echo hc_icon('logout', 18); ?></a>
		</div>
	</aside>
	<main class="main">
		<div class="top">
			<div>
				<h1><?php echo hc_h($page_title); ?></h1>
				<?php if ($page_subtitle !== '') { ?><p><?php echo hc_h($page_subtitle); ?></p><?php } ?>
			</div>
			<?php if ($page_banner_html !== '') { ?><div class="r"><?php echo $page_banner_html; ?></div><?php } ?>
		</div>
		<div class="hc-page">
