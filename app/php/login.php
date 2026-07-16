<?php 
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	beforeLogin();
	
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Excel To PDF Admin</title>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/all.min.css" />
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/adminlte.min.css" />
		<link rel="stylesheet" href="<?php echo $app_url; ?>assets/css/toastr.min.css">
		<script src="<?php echo $app_url; ?>assets/js/jquery.min.js"></script>
		<script src="<?php echo $app_url; ?>assets/js/bootstrap.bundle.js"></script>
		<script src="<?php echo $app_url; ?>assets/js/adminlte.js"></script>
		<script src="<?php echo $app_url; ?>assets/js/toastr.min.js"></script>
	</head>
	<body class="hold-transition login-page">
		<div class="login-box">
			<div class="login-logo">
				<h2>Excel To PDF Admin</h2>
			</div>
			<div class="card">
				<div class="card-body login-card-body">
					<p class="login-box-msg"><?php echo getMsg(); ?></p>
					<form method="post">
						<div class="input-group mb-3">
							<input type="text" class="form-control" placeholder="Email address or Mobile number" name='user_name' />
							<div class="input-group-append">
								<div class="input-group-text">
									<span class="fas fa-envelope"></span>
								</div>
							</div>
						</div>
						<div style='color:red;'><?php echo errorMsg("user_name"); ?></div>
						<div class="input-group mb-3">
							<input type="password" class="form-control" placeholder="Enter your password" name='password' />
							<div class="input-group-append">
								<div class="input-group-text">
									<span class="fas fa-lock"></span>
								</div>
							</div>
						</div>
						<div style='color:red;'><?php echo errorMsg("password"); ?></div>
						<div class="row">
							<div class="col-8">
								<div class="icheck-primary">
									&nbsp;<input type="checkbox" name='remeber' id="remember">
									<label for="remember">
										Remember Me
									</label>
								</div>
							</div>
							<div class="col-4">
								<button type="submit" name='login' class="btn btn-primary btn-block">Sign In</button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</body>
</html>
