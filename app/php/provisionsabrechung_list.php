<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	afterLogin();
	$page_title	= "Gespeicherte Abrechnungen";
	$page_subtitle = "Provisionsabrechnung";
	$page_banner_html = '<a class="btn d" href="' . appUrl('provisionsabrechung') . '">Neue Abrechnung erstellen</a>';
	include_once "include/header.php";
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" />
<div class="content-wrapper">
    <section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header">
					<?php echo hc_icon('receipt', 16); ?> Alle Abrechnungen
				</div>
				<div class="card-body">
					<table id="abrechungen" class="display" style="width:100%">
						<thead>
							<tr>
								<th>Abrechnungsnr</th>
								<th>Zeitraum</th>
								<th>Firma</th>
								<th>Name</th>
								<th class="r">Betrag</th>
								<th>Aktion</th>
							</tr>
						</thead>
					</table>
				</div>
			</div>
		</div>
    </section>
</div>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    var table = $('#abrechungen').DataTable({
        "processing": true,
        "serverSide": true,
        "language": {
            "search": "Suchen:",
            "lengthMenu": "_MENU_ Einträge pro Seite",
            "info": "Zeige _START_ bis _END_ von _TOTAL_ Abrechnungen",
            "infoEmpty": "Keine Abrechnungen vorhanden",
            "infoFiltered": "(gefiltert aus _MAX_ Einträgen)",
            "zeroRecords": "Keine Abrechnungen gefunden",
            "emptyTable": "Keine Abrechnungen vorhanden",
            "processing": "Lade...",
            "paginate": { "first": "Erste", "last": "Letzte", "next": "Weiter", "previous": "Zurück" }
        },
        "columnDefs": [
            { "className": "r", "targets": 4 },
            { "orderable": false, "targets": 5 }
        ],
        "ajax": {
            "url": "<?= $app_url.'provisionsabrechung_list_ajax'; ?>",
            "type": "POST"
        }
    });
	
	$(document).on('click', '.delete-btn', function () {
        var id = $(this).data('id');

        if (confirm("Möchtest du diese Abrechnung wirklich löschen?")) {
            $.ajax({
                url: "<?= $app_url."provisionsabrechung_list_delete"; ?>",
                type: "POST",
                data: { action: 'delete', id: id },
				dataType: "json",
                success: function (response) {
					console.log(response.status);
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
