<?php 
	if(!defined("APP_FOLDER")) exit("No direct script access allowed");
	afterLogin();

	$request = AbrechnungListQuery::fromPost($_POST);
	$totalData = countRec("invoice2","deleted_at IS NULL");

	$where = "deleted_at IS NULL";
	$types = "";
	$params = array();
	if ($request["search"] !== "") {
		$where .= " AND (DATE_FORMAT(start_date, '%d %b, %Y') LIKE ? ESCAPE '\\\\' OR DATE_FORMAT(end_date, '%d %b, %Y') LIKE ? ESCAPE '\\\\' OR CAST(id AS CHAR) LIKE ? ESCAPE '\\\\')";
		$types = "sss";
		$params = array($request["like"], $request["like"], $request["like"]);
	}

	$sql = "SELECT * FROM invoice2 WHERE $where ORDER BY {$request["order"]} {$request["dir"]} LIMIT {$request["start"]}, {$request["limit"]}";
	$result = selectPrepared($sql, $types, $params);
	$filtered = selectPrepared("SELECT COUNT(*) AS c FROM invoice2 WHERE $where", $types, $params);
	$totalFiltered = (int) ($filtered[0]["c"] ?? 0);
	$data = array();

	foreach($result as $row){
		$monthsAbbr = ["Jan"=>"Jan","Feb"=>"Feb","Mar"=>"Mär","Apr"=>"Apr","May"=>"Mai","Jun"=>"Jun","Jul"=>"Jul","Aug"=>"Aug","Sep"=>"Sep","Oct"=>"Okt","Nov"=>"Nov","Dec"=>"Dez"];
		$start_date_m = date("M",strtotime($row['start_date']));
		$start_date = date("d",strtotime($row['start_date']));
		$start_date .= ' '.$monthsAbbr[$start_date_m].', ';
		$start_date .= date("Y",strtotime($row['start_date']));
		
		$end_date_m = date("M",strtotime($row['end_date']));
		$end_date = date("d",strtotime($row['end_date']));
		$end_date .= ' '.$monthsAbbr[$end_date_m].', ';
		$end_date .= date("Y",strtotime($row['end_date']));
		
		$show_date = $start_date.' - '.$end_date;
		
		$isArchived = !empty($row['deleted_at']);
		$zeitpunktAbgelaufen = (!empty($row['end_date']) && strtotime($row['end_date']) < time());
		if ($isArchived) {
			$status = '<span class="badge badge-archiv">Archiviert</span>';
		} elseif ($zeitpunktAbgelaufen) {
			$status = '<span class="badge badge-erledigt">Erledigt</span>';
		} else {
			$status = '<span class="badge badge-offen">Offen</span>';
		}
		$quelle = (($row['source'] ?? '') === 'coffeeji') ? 'Coffeeji' : 'Excel';
		
		$action	= '<a href="'.$app_url."abrechnung_gesamt/".base64_encode($row['id']).'?is_download=yes" class="btn btn-coffee btn-sm">Herunterladen</a> ';
		$action	.= '<button type="button" data-id="'.base64_encode($row['id']).'" class="btn btn-danger btn-sm delete-btn">Löschen</button>';
		$data[] = [
					'#'.$row['id'],
					$show_date,
					hc_money((float) ($row['total_amount'] ?? 0)),
					hc_h($quelle),
					$status,
					$action
				];
		
	} 

	echo json_encode([
		"draw"            => intval($_POST['draw'] ?? 0),
		"recordsTotal"    => intval($totalData),
		"recordsFiltered" => intval($totalFiltered),
		"data"            => $data
	]);

?>
