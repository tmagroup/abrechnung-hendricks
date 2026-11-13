<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	afterLogin();
	$page_title	= "Provisions Empfänger";
	include_once "include/header.php";
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" />
<div class="content-wrapper">
    <div class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1 class="m-0 text-dark">Provisions Empfänger</h1>
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
						<form id="add_data"  method="post" >
							<div class="card-body">
								<div class="row">
									<div class="col-md-3">
										<div class="form-group">
											<label for="company">Firma <span style="color:red;">*</span></label>
											<input type="text" id="company" name="company" class="form-control" value="<?php echo setValue("company"); ?>" />
											<small id="error_company" class="form-text" style="color:red;"><?php echo errorMsg("company"); ?></small>
										</div>
									</div>
									
									<div class="col-md-3">
										<div class="form-group">
											<label for="first_name">Vorname <span style="color:red;">*</span></label>
											<input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo setValue("first_name"); ?>" />
											<small id="error_first_name" class="form-text" style="color:red;"><?php echo errorMsg("first_name"); ?></small>
										</div>
									</div>
									
									<div class="col-md-3">
										<div class="form-group">
											<label for="name">Name <span style="color:red;">*</span></label>
											<input type="text" id="name" name="name" class="form-control" value="<?php echo setValue("name"); ?>" />
											<small id="error_name" class="form-text" style="color:red;"><?php echo errorMsg("name"); ?></small>
										</div>
									</div>
									
									<div class="col-md-3">
										<div class="form-group">
											<label for="commission">Provision <span style="color:red;">*</span></label>
											<input type="number" id="commission" name="commission" class="form-control" value="<?php echo setValue("commission"); ?>" step="0.01" />
											<small id="error_commission" class="form-text" style="color:red;"><?php echo errorMsg("commission"); ?></small>
										</div>
									</div>
									
								</div>
								<div class="row">
									<div class="col-md-3">
										<div class="form-group">
											<label for="street">Strasse</label>
											<input type="text" id="street" name="street" class="form-control" value="<?php echo setValue("street"); ?>" />
											<small id="error_street" class="form-text" style="color:red;"><?php echo errorMsg("street"); ?></small>
										</div>
									</div>
									
									<div class="col-md-3">
										<div class="form-group">
											<label for="zip_code">PLZ</label>
											<input type="text" id="zip_code" name="zip_code" class="form-control" value="<?php echo setValue("zip_code"); ?>" />
											<small id="error_zip_code" class="form-text" style="color:red;"><?php echo errorMsg("zip_code"); ?></small>
										</div>
									</div>
									
									<div class="col-md-3">
										<div class="form-group">
											<label for="location">Ort</label>
											<input type="text" id="location" name="location" class="form-control" value="<?php echo setValue("location"); ?>" />
											<small id="error_location" class="form-text" style="color:red;"><?php echo errorMsg("location"); ?></small>
										</div>
									</div>
									
									<div class="col-md-3">
										<div class="form-group">
											<label for="email">E-Mail</label>
											<input type="email" id="email" name="email" class="form-control" value="<?php echo setValue("email"); ?>" />
											<small id="error_email" class="form-text" style="color:red;"><?php echo errorMsg("email"); ?></small>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-3">
										<div class="form-group">
											<label for="phone">Telefon</label>
											<input type="text" id="phone" name="phone" class="form-control" value="<?php echo setValue("phone"); ?>" />
											<small id="error_phone" class="form-text" style="color:red;"><?php echo errorMsg("phone"); ?></small>
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label for="bank_recipient">Bankempfänger</label>
											<input type="text" id="bank_recipient" name="bank_recipient" class="form-control" value="<?php echo setValue("bank_recipient"); ?>" />
											<small id="error_bank_recipient" class="form-text" style="color:red;"><?php echo errorMsg("bank_recipient"); ?></small>
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label for="iban_no">IBAN-Nr.</label>
											<input type="text" id="iban_no" name="iban_no" class="form-control" value="<?php echo setValue("iban_no"); ?>" />
											<small id="error_iban_no" class="form-text" style="color:red;"><?php echo errorMsg("iban_no"); ?></small>
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label for="bic">BIC</label>
											<input type="text" id="bic" name="bic" class="form-control" value="<?php echo setValue("bic"); ?>" />
											<small id="error_bic" class="form-text" style="color:red;"><?php echo errorMsg("bic"); ?></small>
										</div>
									</div>
								</div>
							</div>
							<div class="card-footer">
								<input type="submit" name="add_data" id="add_btn" value="Hinzufügen" class="btn btn-primary" />
								<input type="reset" name="reset" id="clear_data" value="Abbrechen" class="btn btn-danger" />
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
    </section>
	
	<section class="content">
		<div class="container-fluid">
			<table id="abrechungen" class="display">
				<thead>
					<tr>
						<th>Firma</th>
						<th>Vorname</th>
						<th>Name</th>
						<th>Provision</th>
						<th>Aktion</th>
					</tr>
				</thead>
			</table>
		</div>
    </section>
</div>

<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
function validation() {
	let flag = true;

	let company = $("#company").val().trim();
	let first_name = $("#first_name").val().trim();
	let name = $("#name").val().trim();
	let commission = $("#commission").val().trim();

	if (company === "") {
		$("#error_company").html("Firma ist erforderlich.");
		flag = false;
	} else $("#error_company").html("");

	if (first_name === "") {
		$("#error_first_name").html("Vorname ist erforderlich.");
		flag = false;
	} else $("#error_first_name").html("");

	if (name === "") {
		$("#error_name").html("Name ist erforderlich.");
		flag = false;
	} else $("#error_name").html("");

	if (commission === "") {
		$("#error_commission").html("Provision ist erforderlich.");
		flag = false;
	} else $("#error_commission").html("");

	return flag;
}

$(document).ready(function () {

	// DataTable
	var table = $('#abrechungen').DataTable({
		processing: true,
		serverSide: true,
		ajax: {
			url: "<?= $app_url.'provisions_empfanger_ajax'; ?>",
			type: "POST"
		}
	});
	
	$(document).on('click', '.edit-btn', function (e) {		
		$("#add_btn").val("Aktualisieren");
		$("#add_btn").removeClass("btn-primary");
		$("#add_btn").addClass("btn-secondary");
		
		$("#company").val($(this).attr("data-company"));
		$("#first_name").val($(this).attr("data-first_name"));
		$("#name").val($(this).attr("data-name"));
		$("#commission").val($(this).attr("data-commission"));
		$("#street").val($(this).attr("data-street"));
		$("#zip_code").val($(this).attr("data-zip_code"));
		$("#location").val($(this).attr("data-location"));
		$("#email").val($(this).attr("data-email"));
		$("#bank_recipient").val($(this).attr("data-bank_recipient"));
		$("#phone").val($(this).attr("data-phone"));
		$("#iban_no").val($(this).attr("data-iban_no"));
		$("#bic").val($(this).attr("data-bic"));
				
		var form = $('#add_data');
		var input = form.find('input[type="hidden"][name="hidden_id"]');

		if (input.length)
			input.remove();		
		form.append('<input type="hidden" name="hidden_id" value="' + $(this).attr("data-id") + '">');
	});
	
	$(document).on('click', '#clear_data', function (e) {
		var form = $('#add_data');
		$("#add_btn").val("Hinzufügen");
		$("#add_btn").removeClass("btn-secondary");
		$("#add_btn").addClass("btn-primary");
		var input = form.find('input[type="hidden"][name="hidden_id"]');
		if (input.length)
			input.remove();
	});

	// AJAX form submit	
	$(document).on('submit', '#add_data', function (e) {
		e.preventDefault();

		if (!validation()) {
			return false;
		}
		
		var clear_data = $("#clear_data");
		var form = $('#add_data');
		$.ajax({
			url: "<?= $app_url.'provisions_empfanger_add_update'; ?>",
			type: "POST",
			data: $(this).serialize(),
			dataType: "json",
			success: function (response) {
				if (response.status) {
					toastr.success(response.msg);
					clear_data.click();
					table.ajax.reload(null, false);
				} else {
					toastr.error(response.msg);
				}
				
				var form = $('#add_data');
				$("#add_btn").val("Hinzufügen");
				$("#add_btn").removeClass("btn-secondary");
				$("#add_btn").addClass("btn-primary");
				var input = form.find('input[type="hidden"][name="hidden_id"]');
				if (input.length)
					input.remove();
			},
			error: function () {
				toastr.error("Das hat nicht geklappt.");
			}
		});
	});

	// Delete
	$(document).on('click', '.delete-btn', function () {
		var id = $(this).data('id');

		if (confirm("Möchtest du diesen Provisionsempfänger wirklich löschen?")) {
			$.ajax({
				url: "<?= $app_url."provisions_empfanger_delete"; ?>",
				type: "POST",
				data: { action: 'delete', id: id },
				dataType: "json",
				success: function (response) {
					if (response.status) {
						toastr.success(response.msg)
						table.ajax.reload(null, false);
					} else {
						alert("Löschen fehlgeschlagen");
					}
				}
			});
		}
	});
});
</script>
<?php
	include_once "include/footer.php";
?>