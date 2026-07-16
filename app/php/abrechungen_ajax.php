<?php 
	if(!defined("APP_FOLDER")) exit("No direct script access allowed");
	afterLogin();
	$columns = ['id', 'start_date', 'id'];
	$limit  = $_POST['length'];
	$start  = $_POST['start'];
	$order  = $columns[$_POST['order'][0]['column']];
	$dir    = $_POST['order'][0]['dir'];
	$search = $_POST['search']['value'];

	$totalData = countRec("invoice","deleted_at IS NULL");

	$searchSql = "";
	if (!empty($search)) {
		$searchSql = "DATE_FORMAT(start_date, '%d %b, %Y') LIKE '%$search%' OR DATE_FORMAT(end_date, '%d %b, %Y') LIKE '%$search%' OR id LIKE '%$search%'";
	}

	
	$searchSql = ($searchSql != "") ?  " AND ".$searchSql : "";

	$sql = "SELECT * FROM invoice WHERE deleted_at IS NULL $searchSql ORDER BY $order $dir LIMIT $start, $limit";
	$result = query($sql);
	$totalFiltered = countRec("invoice","deleted_at IS NULL $searchSql");
	
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
		$action	= '<a href="'.$app_url."detailabrechnung/".base64_encode($row['id']).'?is_download=yes" class="btn btn-primary btn-sm">Download</a> ';
		$action	.= '<button type="button" data-id="'.base64_encode($row['id']).'" class="btn btn-danger btn-sm delete-btn">Delete</button>';
		$data[] = [
					$row['id'],
					$show_date,
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
