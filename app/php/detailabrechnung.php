<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	afterLogin();
	$page_title	= "Detailabrechnung";
	include_once "include/header.php";
?>
<div class="content-wrapper">
    <div class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1 class="m-0">Detailabrechnung</h1>
				</div>
			</div>
		</div>
    </div>
 
    <section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header">
					<i class="fas fa-mug-hot mr-2"></i> Neue Detailabrechnung erstellen
				</div>
				<form onsubmit="return validation();" method="post" enctype="multipart/form-data" target="_blank">
					<div class="card-body">
						<!-- Zweistufige Anzeige -->
						<div class="hc-steps">
							<div class="hc-step active" id="step-1">
								<span class="step-num">1</span>
								<span>Abrechnungszeitraum wählen</span>
							</div>
							<div class="hc-step" id="step-2">
								<span class="step-num">2</span>
								<span>Excel-Datei hochladen</span>
							</div>
						</div>

						<div class="row">
							<div class="col-md-4">
								<div class="form-group">
									<label for="start_date">Zeitraum Beginn</label>
									<input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo setValue("start_date"); ?>" />
									<small id="error_start_date" class="form-text" style="color:red;"><?php echo errorMsg("start_date"); ?></small>
								</div>
							</div>
							
							<div class="col-md-4">
								<div class="form-group">
									<label for="end_date">Zeitraum Ende</label>
									<input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo setValue("end_date"); ?>" />
									<small id="error_end_date" class="form-text" style="color:red;"><?php echo errorMsg("end_date"); ?></small>
								</div>
							</div>
						</div>

						<hr style="border-color:#eadfcd;" />

						<div class="form-group">
							<label>Excel-Datei (wird zu PDF konvertiert)</label>
							<input type="file" class="d-none" id="excel_sheet" name="excel_sheet" value="<?php echo setValue("excel_sheet"); ?>" accept=".xls,.xlsx,application/vnd.ms-excel" />
							<div class="upload-zone" id="upload_zone" onclick="document.getElementById('excel_sheet').click();">
								<span class="upload-icon"><i class="fas fa-cloud-arrow-up"></i></span>
								<div class="upload-title">Excel-Datei hier ablegen oder klicken zum Hochladen</div>
								<div class="upload-hint">Unterstützte Formate: .xlsx, .xls</div>
							</div>
							<small id="error_excel_sheet" class="form-text" style="color:red;"><?php echo errorMsg("excel_sheet"); ?></small>
						</div>
					</div>
					<div class="card-footer">
						<input type="submit" name="add_excel" value="Erstellen" class="btn btn-primary" />
						<input type="reset" name="reset" value="Abbrechen" class="btn btn-danger" />
					</div>
				</form>
			</div>
		</div>
    </section>
</div>
 <script>
	function validation()
	{
		var flag = true;
		
		var start_date 	= document.getElementById("start_date").value;
		var end_date 	= document.getElementById("end_date").value;
		var excel_sheet = document.getElementById("excel_sheet").value;
				
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
	
	// Upload-Zone: Datei-Name anzeigen + Erfolgszustand (wie im Screenshot)
	document.addEventListener("DOMContentLoaded", function() {
		var input   = document.getElementById("excel_sheet");
		var zone    = document.getElementById("upload_zone");
		var zoneTitle  = zone.querySelector(".upload-title");
		var zoneHint   = zone.querySelector(".upload-hint");
		var zoneIcon   = zone.querySelector(".upload-icon");
		
		input.addEventListener("change", function() {
			if (input.files.length > 0) {
				var fileName = input.files[0].name;
				zone.classList.add("has-file");
				zoneIcon.innerHTML = '<i class="fas fa-file-excel"></i>';
				zoneTitle.innerHTML = fileName + " erfolgreich hochgeladen";
				zoneHint.innerHTML = "Klicken zum Ersetzen der Datei";
				// Schritt 2 als aktiv markieren
				document.getElementById("step-2").classList.add("active");
			} else {
				zone.classList.remove("has-file");
				zoneIcon.innerHTML = '<i class="fas fa-cloud-arrow-up"></i>';
				zoneTitle.innerHTML = "Excel-Datei hier ablegen oder klicken zum Hochladen";
				zoneHint.innerHTML = "Unterstützte Formate: .xlsx, .xls";
			}
		});
		
		// Reset-Zustand wiederherstellen
		var form = input.closest("form");
		form.addEventListener("reset", function() {
			setTimeout(function() {
				zone.classList.remove("has-file");
				zoneIcon.innerHTML = '<i class="fas fa-cloud-arrow-up"></i>';
				zoneTitle.innerHTML = "Excel-Datei hier ablegen oder klicken zum Hochladen";
				zoneHint.innerHTML = "Unterstützte Formate: .xlsx, .xls";
				document.getElementById("step-2").classList.remove("active");
			}, 10);
		});
		
		// Datum gewählt => Schritt-1-Rand
		var startDate = document.getElementById("start_date");
		var endDate   = document.getElementById("end_date");
		[startDate, endDate].forEach(function(el) {
			el.addEventListener("change", function() {
				if (startDate.value != "" && endDate.value != "") {
					document.getElementById("step-1").style.boxShadow = "0 0 0 2px #a67c52";
				}
			});
		});
	});
</script>
<?php
	include_once "include/footer.php";
?>