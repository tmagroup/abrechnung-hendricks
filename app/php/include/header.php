<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	afterLogin();
?>
<!doctype html>
<html>
	<head>
		<title><?php echo $page_title; ?></title>

		
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- Start CSS -->
		<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/all.min.css">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/adminlte.min.css">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/OverlayScrollbars.min.css">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/toastr.min.css">
		<!-- Start CSS -->

		<!--- start js Files -->
		<script src="<?php echo $app_url; ?>assets/js/jquery.min.js"></script>
		<script src="<?php echo $app_url; ?>assets/js/toastr.min.js"></script>
		<script src="<?php echo $app_url; ?>assets/js/bootstrap.bundle.js"></script>
		<script src="<?php echo $app_url; ?>assets/js/jquery.overlayScrollbars.min.js"></script>
		<script src="<?php echo $app_url; ?>assets/js/adminlte.js"></script>
		<!--- end js Files -->
		<style>
	        .frac {
                display: inline-block;
                position: relative;
                vertical-align: middle;
                letter-spacing: 0.001em;
                text-align: center;
            }
            .frac > span {
                display: block;
                padding: 0.1em;
            }
            .frac span.bottom {
                border-top: thin solid black;
            }
            .frac span.symbol {
                display: none;
            } 
		    [contentEditable=true]:empty:not(:focus):before{
        		content:attr(data-text);
        		color:#888;
        	}
        	#editor {
    		    border: 1px solid grey;
    		    min-height: 100px;
    		    margin: 0px auto 0;
    		    padding:10px;
    	   }
		</style>
	</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm accent-indigo">
		<?php echo getMsg(); ?>
	<div class="wrapper">
		<nav class="main-header navbar navbar-expand navbar-indigo navbar-dark ">
			<ul class="navbar-nav">
				<li class="nav-item">
					<a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
				</li>
			</ul>
			<ul class="navbar-nav ml-auto">
				<li class="nav-item">
					<a class="nav-link" href="<?php echo appUrl('account'); ?>">
						Password Security
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="<?php echo appUrl('logout'); ?>">
						Logout
					</a>
				</li>
			</ul>
		</nav>
		<aside class="main-sidebar sidebar-light-indigo elevation-4">
			<a href="index3.html" class="brand-link">
				<span class="brand-text font-weight-light">Excel Import Admin</span>
			</a>
			<div class="sidebar">
				<nav class="mt-2">
					<ul class="nav nav-pills nav-sidebar flex-column nav-flat nav-legacy nav-compact" data-widget="treeview" role="menu" data-accordion="false">
						<li class="nav-item has-treeview <?php if($page_name == 'detailabrechnung'){ echo 'menu-open'; } ?>">
							<a href="#" class="nav-link <?php if($page_name == 'detailabrechnung'){ echo 'active'; } ?>">
							  
							  <p>
								Detailabrechnung
								<i class="right fas fa-angle-left"></i>
							  </p>
							</a>
							<ul class="nav nav-treeview">
								<li class="nav-item">
									<a href="<?php echo appUrl('detailabrechnung'); ?>" class="nav-link <?php if($page_name == 'detailabrechnung'){ echo 'active'; } ?>">
										<i class="far fa-circle nav-icon"></i>
										<p>Detailabrechnung</p>
									</a>
								</li>
								<li class="nav-item">
									<a href="<?php echo appUrl('abrechungen'); ?>" class="nav-link <?php if($page_name == 'abrechungen'){ echo 'active'; } ?>">
										<i class="far fa-circle nav-icon"></i>
										<p>Abrechungen</p>
									</a>
								</li>
							</ul>
						</li>
						
						<li class="nav-item has-treeview <?php if($page_name == 'abrechnung_gesamt'){ echo 'menu-open'; } ?>">
							<a href="#" class="nav-link <?php if($page_name == 'abrechnung_gesamt'){ echo 'active'; } ?>">
							  
							  <p>
								Abrechnung Gesamt
								<i class="right fas fa-angle-left"></i>
							  </p>
							</a>
							<ul class="nav nav-treeview">
								<li class="nav-item">
									<a href="<?php echo appUrl('abrechnung_gesamt'); ?>" class="nav-link <?php if($page_name == 'abrechnung_gesamt'){ echo 'active'; } ?>">
										<i class="far fa-circle nav-icon"></i>
										<p>Abrechnung Gesamt</p>
									</a>
								</li>
								<li class="nav-item">
									<a href="<?php echo appUrl('abrechnung_gesamt_list'); ?>" class="nav-link <?php if($page_name == 'abrechnung_gesamt_list'){ echo 'active'; } ?>">
										<i class="far fa-circle nav-icon"></i>
										<p>Abrechungen</p>
									</a>
								</li>
							</ul>
						</li>
						
						<li class="nav-item has-treeview <?php if($page_name == 'provisions_empfanger'){ echo 'menu-open'; } ?>">
							<a href="#" class="nav-link <?php if($page_name == 'provisions_empfanger'){ echo 'active'; } ?>">
							  
							  <p>
								Provisions Empfänger
								<i class="right fas fa-angle-left"></i>
							  </p>
							</a>
							<ul class="nav nav-treeview">
								<li class="nav-item">
									<a href="<?php echo appUrl('provisions_empfanger'); ?>" class="nav-link <?php if($page_name == 'provisions_empfanger'){ echo 'active'; } ?>">
										<i class="far fa-circle nav-icon"></i>
										<p>Provisions Empfänger</p>
									</a>
								</li>
							</ul>
						</li>
						
						<li class="nav-item has-treeview <?php if($page_name == 'provisionsabrechung'){ echo 'menu-open'; } ?>">
							<a href="#" class="nav-link <?php if($page_name == 'provisionsabrechung'){ echo 'active'; } ?>">
							  <p>
								Provisionsabrechung
								<i class="right fas fa-angle-left"></i>
							  </p>
							</a>
							<ul class="nav nav-treeview">
								<li class="nav-item">
									<a href="<?php echo appUrl('provisionsabrechung'); ?>" class="nav-link <?php if($page_name == 'provisionsabrechung'){ echo 'active'; } ?>">
										<i class="far fa-circle nav-icon"></i>
										<p>Provisionsabrechung</p>
									</a>
								</li>
								<li class="nav-item">
									<a href="<?php echo appUrl('provisionsabrechung_list'); ?>" class="nav-link <?php if($page_name == 'provisionsabrechung_list'){ echo 'active'; } ?>">
										<i class="far fa-circle nav-icon"></i>
										<p>Abrechungen</p>
									</a>
								</li>
							</ul>
						</li>
						<!--
						<li class="nav-item has-treeview <?php if($page_name == 'dashboard'){ echo 'menu-open'; } ?>">
							<a href="#" class="nav-link <?php if($page_name == 'dashboard'){ echo 'active'; } ?>">
							  <i class="nav-icon fas fa-tachometer-alt"></i>
							  <p>
								Detailabrechnung
								<i class="right fas fa-angle-left"></i>
							  </p>
							</a>
							<ul class="nav nav-treeview">
								<li class="nav-item">
									<a href="<?php echo appUrl('dashboard'); ?>" class="nav-link <?php if($page_name == 'dashboard'){ echo 'active'; } ?>">
										<i class="far fa-circle nav-icon"></i>
										<p>Detailabrechnung</p>
									</a>
								</li>
							</ul>
						</li>
						-->
					</ul>
				</nav>
			</div>
		</aside>
