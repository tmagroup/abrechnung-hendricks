<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	afterLogin();
	$page_title	= "Abrechungen";
	include_once "include/header.php";
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" />
<div class="content-wrapper">
    <div class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1 class="m-0 text-dark">Abrechungen</h1>
				</div>
				<div class="col-sm-6">
				</div>
			</div>
		</div>
    </div>
 
    <section class="content">
		<div class="container-fluid">
			<table id="abrechungen" class="display">
				<thead>
					<tr>
						<th>Abrechungsnr</th>
						<th>Zeitraum</th>
						<th>Action</th>
					</tr>
				</thead>
			</table>
		</div>
    </section>
</div>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    var table = $('#abrechungen').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "<?= $app_url.'abrechungen_ajax'; ?>",
            "type": "POST"
        }
    });
	
	$(document).on('click', '.delete-btn', function () {
        var id = $(this).data('id');

        if (confirm("Are you sure you want to delete?")) {
            $.ajax({
                url: "<?= $app_url."abrechungen_delete"; ?>",
                type: "POST",
                data: { action: 'delete', id: id },
				dataType: "json",
                success: function (response) {
					console.log(response.status);
                    if (response.status) {
						toastr.success(response.msg)
                        table.ajax.reload(null, false);
                    } else {
                        alert("Delete failed");
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