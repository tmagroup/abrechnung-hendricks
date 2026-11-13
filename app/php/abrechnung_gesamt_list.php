<?php
	if(!defined('APP_FOLDER')) exit('No direct script access allowed');
	
	afterLogin();
	$page_title	= "Gespeicherte Abrechnungen";
	$page_subtitle = "Abrechnung Gesamt";
	$page_banner_html = '<a class="btn d" href="' . appUrl('abrechnung_gesamt') . '">Neue Abrechnung erstellen</a>';
	include_once "include/header.php";
	
	// Statistiken für die Karten (dieselbe Tabelle wie die Liste und das Löschen: invoice2)
	$stat_total     = countRec("invoice2", "deleted_at IS NULL");
	$stat_current   = countRec("invoice2", "deleted_at IS NOT NULL AND DATE_FORMAT(created_at, '%Y-%m') = '".date('Y-m')."'");
	$latest         = query("SELECT start_date FROM invoice2 WHERE deleted_at IS NULL ORDER BY start_date DESC LIMIT 1");
	$stat_latest    = "–";
	if (!empty($latest[0]['start_date'])) {
		$latest_ts = strtotime($latest[0]['start_date']);
		$stat_latest = date("d. ", $latest_ts) . hc_month_short((int) date("n", $latest_ts)) . " " . date("Y", $latest_ts);
	}
	$stat_archived  = countRec("invoice2", "deleted_at IS NOT NULL");
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" />
<div class="content-wrapper">
    <section class="content">
		<div class="container-fluid">
			<!-- Statistik-Karten -->
			<div class="row mb-4">
				<div class="col-md-6 col-lg-3 mb-3">
					<div class="stat-card d-flex align-items-center">
						<div class="stat-icon mr-3"><?php echo hc_icon('doc', 18); ?></div>
						<div>
							<div class="stat-label">Abrechnungen gesamt</div>
							<div class="stat-value"><?php echo intval($stat_total); ?></div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-3 mb-3">
					<div class="stat-card d-flex align-items-center">
						<div class="stat-icon mr-3"><?php echo hc_icon('calendar', 18); ?></div>
						<div>
							<div class="stat-label">Aktueller Monat</div>
							<div class="stat-value"><?php echo intval($stat_current); ?></div>
							<div class="stat-sub"><?php echo hc_h(hc_month_long((int) date('n')) . ' ' . date('Y')); ?></div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-3 mb-3">
					<div class="stat-card d-flex align-items-center">
						<div class="stat-icon mr-3"><?php echo hc_icon('receipt', 18); ?></div>
						<div>
							<div class="stat-label">Letzte Abrechnung</div>
							<div class="stat-value" style="font-size:1.25rem;"><?php echo $stat_latest; ?></div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-3 mb-3">
					<div class="stat-card d-flex align-items-center">
						<div class="stat-icon mr-3"><?php echo hc_icon('doc', 18); ?></div>
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
					<?php echo hc_icon('receipt', 16); ?> Alle Abrechnungen
				</div>
				<div class="card-body">
					<table id="abrechungen" class="display" style="width:100%">
						<thead>
							<tr>
								<th>Abrechnungsnr</th>
								<th>Zeitraum</th>
								<th class="r">Betrag</th>
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
            "infoEmpty": "Keine Abrechnungen vorhanden",
            "infoFiltered": "(gefiltert aus _MAX_ Einträgen)",
            "zeroRecords": "Keine Abrechnungen gefunden",
            "emptyTable": "Keine Abrechnungen vorhanden",
            "processing": "Lade...",
            "paginate": { "first": "Erste", "last": "Letzte", "next": "Weiter", "previous": "Zurück" }
        },
        "columnDefs": [
            { "className": "r", "targets": 2 },
            { "orderable": false, "targets": 4 }
        ],
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