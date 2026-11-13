<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$page_title = "Passwort & Sicherheit";
	$page_subtitle = "Konto und Passwort";
	$all_users = selectPrepared("SELECT id, name, email, mobile, password FROM manage_user", "", array());
	$legacy_password_count = 0;
	$current_user = array();
	$current_user_id = (int)getLoginId();
	foreach($all_users as $user_row)
	{
		if(!isPasswordHash((string)($user_row['password'] ?? '')) && (string)($user_row['password'] ?? '') !== '')
		{
			$legacy_password_count++;
		}

		if((int)$user_row['id'] === $current_user_id)
		{
			$current_user = $user_row;
		}
	}
	$current_user_has_legacy_password = !empty($current_user) && !isPasswordHash((string)($current_user['password'] ?? ''));
	include_once "include/header.php";
?>
<div class="content-wrapper">
    <div class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1 class="m-0 text-dark">Passwort & Sicherheit</h1>
				</div>
			</div>
		</div>
    </div>
 
    <section class="content">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">Angemeldetes Konto</h3>
						</div>
						<div class="card-body">
							<p><strong>Name:</strong> <?php echo htmlspecialchars($current_user['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
							<p><strong>E-Mail:</strong> <?php echo htmlspecialchars($current_user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
							<p><strong>Mobil:</strong> <?php echo htmlspecialchars($current_user['mobile'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
							<p><strong>Passwort:</strong> <?php echo $current_user_has_legacy_password ? '<span class="text-warning">noch im Klartext</span>' : '<span class="text-success">gehasht</span>'; ?></p>
						</div>
					</div>

					<div class="card card-warning">
						<div class="card-header">
							<h3 class="card-title">Alte Passwörter umstellen</h3>
						</div>
						<div class="card-body">
							<p>Klartext-Passwörter: <strong><?php echo (int)$legacy_password_count; ?></strong></p>
							<p class="text-muted mb-3">Einmal ausführen, damit künftige Anmeldungen nur noch mit Hash arbeiten.</p>
							<form method="post">
								<button type="submit" name="migrate_passwords" class="btn btn-warning" <?php echo $legacy_password_count === 0 ? 'disabled' : ''; ?>>
									Klartext-Passwörter hashen
								</button>
							</form>
						</div>
					</div>
				</div>

				<div class="col-lg-6">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">Passwort ändern</h3>
						</div>
						<form method="post">
							<div class="card-body">
								<div class="form-group">
									<label>Aktuelles Passwort</label>
									<input type="password" name="current_password" class="form-control" required />
								</div>
								<div class="form-group">
									<label>Neues Passwort</label>
									<input type="password" name="new_password" class="form-control" minlength="10" required />
									<small class="text-muted">Mindestens 10 Zeichen. Steht oben noch ein Klartext-Passwort, zuerst die Umstellung ausführen.</small>
								</div>
								<div class="form-group mb-0">
									<label>Neues Passwort wiederholen</label>
									<input type="password" name="confirm_password" class="form-control" minlength="10" required />
								</div>
							</div>
							<div class="card-footer">
								<button type="submit" name="change_password" class="btn btn-primary">Passwort speichern</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
    </section>
</div>
<?php include_once "include/footer.php"; ?>
