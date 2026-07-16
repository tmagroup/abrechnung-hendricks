<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	afterLogin();
	$page_title	= "Upload Excel Sheet Convert to PDF";
	include_once "include/header.php";
?>
<div class="content-wrapper">
    <div class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1 class="m-0 text-dark">Upload Excel Sheet Convert to PDF</h1>
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
						<form onsubmit="return validation();"  method="post" enctype="multipart/form-data" target="_blank">
							<div class="card-body">
								<div class="row">
									<div class="col-md-4">
										<div class="form-group">
											<label for="standard">Upload Excel Sheet Convert to PDF</label>
											<input type="file" class="form-control" id="excel_sheet" name="excel_sheet"  value="<?php echo setValue("excel_sheet"); ?>" accept=".xls,.xlsx,application/vnd.ms-excel" />
											<small id="error_excel_sheet" class="form-text" style="color:red;"><?php echo errorMsg("excel_sheet"); ?></small>
										</div>
									</div>
								</div>
							</div>
							<div class="card-footer">
								<input type="submit" name="add_excel" value="Create PDF" class="btn btn-primary" />
								<input type="reset" name="reset" value="Cancel" class="btn btn-danger" />
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
		
		var excel_sheet 	= document.getElementById("excel_sheet").value;
		
		if(excel_sheet == "")
		{
			document.getElementById("error_excel_sheet").innerHTML = "Excel sheet  is required.";
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