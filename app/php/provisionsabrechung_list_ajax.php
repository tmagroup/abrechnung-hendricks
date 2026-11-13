<?php 
	if(!defined("APP_FOLDER")) exit("No direct script access allowed");
	afterLogin();
	$columns = ['invoice3.id', 'invoice3.start_date', 'commission_recipient.company', 'commission_recipient.first_name', 'commission_recipient.name'];
	$limit  = $_POST['length'];
	$start  = $_POST['start'];
	$order  = $columns[$_POST['order'][0]['column']];
	$dir    = $_POST['order'][0]['dir'];
	$search = $_POST['search']['value'];
	$totalData = countRec("invoice3","deleted_at IS NULL");

	$searchSql = "";
	if (!empty($search)) {
		$searchSql = "DATE_FORMAT(start_date, '%d %b, %Y') LIKE '%$search%' OR DATE_FORMAT(end_date, '%d %b, %Y') LIKE '%$search%' OR invoice3.id LIKE '%$search%' OR commission_recipient.company LIKE '%$search%' OR commission_recipient.first_name LIKE '%$search%' OR commission_recipient.name LIKE '%$search%'";
	}
	$left_join = " LEFT JOIN commission_recipient ON commission_recipient.id = invoice3.commission_recipient_id ";
	$searchSql = ($searchSql != "") ?  " AND ".$searchSql : "";

	$sql = "SELECT invoice3.*, commission_recipient.company as company, commission_recipient.first_name as first_name, commission_recipient.name as name FROM invoice3 $left_join WHERE invoice3.deleted_at IS NULL $searchSql ORDER BY $order $dir LIMIT $start, $limit";
	
	$sql2 = "SELECT invoice3.id as name FROM invoice3 $left_join WHERE invoice3.deleted_at IS NULL $searchSql";
	$result2 = query($sql2);
	$totalFiltered = count($result2);
	
	$result = query($sql);
	
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
		
		$action	= '<a href="'.$app_url."provisionsabrechung/".base64_encode($row['id']).'?is_download=yes" class="btn btn-primary btn-sm">Download</a> ';
		$action	.= '<button type="button" data-id="'.base64_encode($row['id']).'" class="btn btn-danger btn-sm delete-btn">Löschen</button>';
		$data[] = [
					$row['id'],
					$show_date,
					$row['company'],
					$row['first_name'].' '.$row['name'],
					$action
				];
	} 

	echo json_encode([
		"draw"            => intval($_POST['draw']),
		"recordsTotal"    => intval($totalData),
		"recordsFiltered" => intval($totalFiltered),
		"data"            => $data
	]);

?>
