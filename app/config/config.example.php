cat > app/config/config.example.php <<'EOF'
<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	$app_url = 'https://abrechnung.thehendricks-coffee.de/';
	$default_file = 'detailabrechnung.php';

	$db_hostname = 'localhost';
	$db_username = 'abrechnung';
	$db_password = 'CHANGE_ME';
	$db_name = 'abrechnung';

	$app_session = 'execel_admin';
?>
EOF
