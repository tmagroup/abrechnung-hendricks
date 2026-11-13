<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');

	afterLogin();
	$page_title = 'Excel-Upload';
	$page_subtitle = 'Excel-Datei in ein PDF umwandeln';
	include_once 'include/header.php';
?>
<div class="content-wrapper">
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header">Excel-Datei hochladen</div>
				<form onsubmit="return validation();" method="post" enctype="multipart/form-data" target="_blank">
					<div class="card-body">
						<div class="form-group">
							<label for="excel_sheet">Excel-Datei</label>
							<input type="file" class="form-control" id="excel_sheet" name="excel_sheet" accept=".xls,.xlsx,application/vnd.ms-excel" />
							<small id="error_excel_sheet" class="form-text" style="color:#B3332A;"><?php echo errorMsg('excel_sheet'); ?></small>
						</div>
					</div>
					<div class="card-footer">
						<input type="submit" name="add_excel" value="PDF erstellen" class="btn btn-primary" />
						<input type="reset" name="reset" value="Abbrechen" class="btn btn-danger" />
					</div>
				</form>
			</div>
		</div>
	</section>
</div>
<script>
	function validation() {
		var excel_sheet = document.getElementById('excel_sheet').value;
		if (excel_sheet == '') {
			document.getElementById('error_excel_sheet').innerHTML = 'Bitte eine Excel-Datei wählen.';
			return false;
		}
		document.getElementById('error_excel_sheet').innerHTML = '';
	}
</script>
<?php include_once 'include/footer.php'; ?>
