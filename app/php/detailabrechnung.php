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
					<h1 class="m-0 text-dark">Detailabrechnung</h1>
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
									<div class="col-md-4">
										<div class="form-group">
											<label for="start_date">Zeitraum beginn</label>
											<input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo setValue("start_date"); ?>" />
											<small id="error_start_date" class="form-text" style="color:red;"><?php echo errorMsg("start_date"); ?></small>
										</div>
									</div>
									
									<div class="col-md-4">
										<div class="form-group">
											<label for="end_date">Zeitraum ende</label>
											<input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo setValue("end_date"); ?>" />
											<small id="error_end_date" class="form-text" style="color:red;"><?php echo errorMsg("end_date"); ?></small>
										</div>
									</div>
								
									<div class="col-md-4">
										<div class="form-group">
											<label for="excel_sheet">Upload Excel Sheet Convert to PDF</label>
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
		
		var start_date 	= document.getElementById("start_date").value;
		var end_date 	= document.getElementById("end_date").value;
		var excel_sheet = document.getElementById("excel_sheet").value;
				
		if(start_date == "")
		{
			document.getElementById("error_start_date").innerHTML = "Zeitraum beginn is required.";
			flag = false;
		}
		else
		{
			document.getElementById("error_start_date").innerHTML = "";
		}
		
		if(end_date == "")
		{
			document.getElementById("error_end_date").innerHTML = "Zeitraum ende is required.";
			flag = false;
		}
		else
		{
			document.getElementById("error_end_date").innerHTML = "";
		}
		
		
		if(excel_sheet == "")
		{
			document.getElementById("error_excel_sheet").innerHTML = "Excel sheet is required.";
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