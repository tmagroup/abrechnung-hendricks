<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	afterLogin();
	$page_title	= "Provisionsabrechnung";
	$page_subtitle = "Empfänger und Zeitraum wählen, Excel-Datei hochladen";
	$page_banner_html = '<a class="btn d" href="' . appUrl('provisionsabrechung_list') . '">Gespeicherte Abrechnungen</a>';
	include_once "include/header.php";
?>
<div class="content-wrapper">
    <div class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1 class="m-0 text-dark">Provisionsabrechnung</h1>
				</div>
				<div class="col-sm-6">
				</div>
			</div>
		</div>
    </div>
 
    <section class="content">
		<div class="container-fluid">
			<div class="row">
				<div class="col-12">
					<div class="card">
						<form onsubmit="return validation();" method="post" enctype="multipart/form-data" target="_blank">
							<div class="card-body">
								<div class="row">
									<div class="col-md-3">
										<div class="form-group">
											<label for="commission_recipient_id">Provisionsempfänger</label>
											<select id="commission_recipient_id" name="commission_recipient_id" class="form-control">
												<option value="">Bitte wählen</option>
												<?php 
													$commission_recipient = select('commission_recipient','deleted_at IS NULL');
													foreach($commission_recipient as $cr){
												?>
														<option value="<?= $cr['id']; ?>"><?= $cr['company']." - ".$cr['first_name']." - ".$cr['name']." - ".$cr['commission']; ?></option>
												<?php
													}
												?>
											</select>											
											<small id="error_commission_recipient_id" class="form-text" style="color:red;"><?php echo errorMsg("commission_recipient_id"); ?></small>
										</div>
									</div>
									
									<div class="col-md-3">
										<div class="form-group">
											<label for="start_date">Zeitraum Beginn</label>
											<input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo setValue("start_date"); ?>" />
											<small id="error_start_date" class="form-text" style="color:red;"><?php echo errorMsg("start_date"); ?></small>
										</div>
									</div>
									
									<div class="col-md-3">
										<div class="form-group">
											<label for="end_date">Zeitraum Ende</label>
											<input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo setValue("end_date"); ?>" />
											<small id="error_end_date" class="form-text" style="color:red;"><?php echo errorMsg("end_date"); ?></small>
										</div>
									</div>
								
									<div class="col-md-3">
										<div class="form-group">
											<label for="excel_sheet">Excel-Datei (wird zu PDF)</label>
											<input type="file" class="form-control" id="excel_sheet" name="excel_sheet" value="<?php echo setValue("excel_sheet"); ?>" accept=".xls,.xlsx,application/vnd.ms-excel" />
											<small id="error_excel_sheet" class="form-text" style="color:red;"><?php echo errorMsg("excel_sheet"); ?></small>
										</div>
									</div>
								</div>
							</div>
							<div class="card-footer">
								<input type="submit" name="add_excel" value="Erstellen" class="btn btn-primary" />
								<input type="reset" name="reset" value="Abbrechen" class="btn btn-danger" />
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
    </section>
</div>
 <script>
	function validation()
	{
		var flag = true;
		
		var commission_recipient_id = document.getElementById("commission_recipient_id").value;
		var start_date 	= document.getElementById("start_date").value;
		var end_date 	= document.getElementById("end_date").value;
		var excel_sheet = document.getElementById("excel_sheet").value;
		
		if(commission_recipient_id == "")
		{
			document.getElementById("error_commission_recipient_id").innerHTML = "Provisionsempfänger ist erforderlich.";
			flag = false;
		}
		else
		{
			document.getElementById("error_commission_recipient_id").innerHTML = "";
		}
		
		if(start_date == "")
		{
			document.getElementById("error_start_date").innerHTML = "Zeitraum Beginn ist erforderlich.";
			flag = false;
		}
		else
		{
			document.getElementById("error_start_date").innerHTML = "";
		}
		
		if(end_date == "")
		{
			document.getElementById("error_end_date").innerHTML = "Zeitraum Ende ist erforderlich.";
			flag = false;
		}
		else
		{
			document.getElementById("error_end_date").innerHTML = "";
		}
		
		
		if(excel_sheet == "")
		{
			document.getElementById("error_excel_sheet").innerHTML = "Excel-Datei ist erforderlich.";
			flag = false;
		}
		else
		{
			document.getElementById("error_excel_sheet").innerHTML = "";
		}
		
		if(flag == false)
		{
			return false;
		}
		
	}
</script>
<?php
	include_once "include/footer.php";
?>