<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	afterLogin();
	$page_title	= "Abrechnungen";
	include_once "include/header.php";
	
	// Statistiken für die Karten
	$stat_total     = countRec("invoice", "deleted_at IS NULL");
	$stat_current   = countRec("invoice", "deleted_at IS NOT NULL AND DATE_FORMAT(created_at, '%Y-%m') = '".date('Y-m')."'");
	$latest         = query("SELECT start_date FROM invoice WHERE deleted_at IS NULL ORDER BY start_date DESC LIMIT 1");
	$stat_latest    = (!empty($latest[0]['start_date'])) ? date("d. M Y", strtotime($latest[0]['start_date'])) : "–";
	$stat_archived  = countRec("invoice", "deleted_at IS NOT NULL");
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" />
<div class="content-wrapper">
    <div class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1 class="m-0">Abrechnungen</h1>
				</div>
				<div class="col-sm-6 text-right">
					<a href="<?php echo appUrl('abrechnung_gesamt'); ?>" class="btn btn-coffee">
						<i class="fas fa-plus"></i>&nbsp; Neue Abrechnung
					</a>
				</div>
			</div>
		</div>
    </div>
 
    <section class="content">
		<div class="container-fluid">
			<!-- Statistik-Karten -->
			<div class="row mb-4">
				<div class="col-md-6 col-lg-3 mb-3">
					<div class="stat-card d-flex align-items-center">
						<div class="stat-icon mr-3"><i class="fas fa-file-invoice"></i></div>
						<div>
							<div class="stat-label">Abrechnungen gesamt</div>
							<div class="stat-value"><?php echo intval($stat_total); ?></div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-3 mb-3">
					<div class="stat-card d-flex align-items-center">
						<div class="stat-icon mr-3"><i class="fas fa-calendar-month"></i></div>
						<div>
							<div class="stat-label">Aktueller Monat</div>
							<div class="stat-value"><?php echo intval($stat_current); ?></div>
							<div class="stat-sub"><?php echo date('F Y'); ?></div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-3 mb-3">
					<div class="stat-card d-flex align-items-center">
						<div class="stat-icon mr-3"><i class="fas fa-clock"></i></div>
						<div>
							<div class="stat-label">Letzte Abrechnung</div>
							<div class="stat-value" style="font-size:1.25rem;"><?php echo $stat_latest; ?></div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-3 mb-3">
					<div class="stat-card d-flex align-items-center">
						<div class="stat-icon mr-3"><i class="fas fa-box-archive"></i></div>
						<div>
							<div class="stat-label">Archivierte Dokumente</div>
							<div class="stat-value"><?php echo intval($stat_archived); ?></div>
						</div>
					</div>
				</div>
			</div>

			<!-- Abrechnungs-Tabelle -->
			<div class="card">
				<div class="card-header">
					<i class="fas fa-mug-hot mr-2"></i> Alle Abrechnungen
				</div>
				<div class="card-body">
					<table id="abrechungen" class="display" style="width:100%">
						<thead>
							<tr>
								<th>Abrechnungsnr</th>
								<th>Zeitraum</th>
								<th>Status</th>
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
            "zeroRecords": "Keine Abrechnungen gefunden",
            "processing": "Lade..."
        },
        "ajax": {
            "url": "<?= $app_url.'abrechnung_gesamt_list_ajax'; ?>",
            "type": "POST"
        }
    });
	
	$(document).on('click', '.delete-btn', function () {
        var id = $(this).data('id');

        if (confirm("Möchtest du diese Abrechnung wirklich löschen?")) {
            $.ajax({
                url: "<?= $app_url."abrechnung_gesamt_list_delete"; ?>",
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