<?php 
	if(!defined("APP_FOLDER")) exit("No direct script access allowed");
	afterLogin();
	$columns = ['id', 'start_date', 'id'];
	$limit  = $_POST['length'];
	$start  = $_POST['start'];
	$order  = $columns[$_POST['order'][0]['column']];
	$dir    = $_POST['order'][0]['dir'];
	$search = $_POST['search']['value'];

	$totalData = countRec("commission_recipient","deleted_at IS NULL");

	$searchSql = "";
	if (!empty($search)) {
		$searchSql = "company LIKE '%$search%' OR first_name LIKE '%$search%' OR name LIKE '%$search%' OR commission LIKE '%$search%'";
	}

	$searchSql = ($searchSql != "") ?  " AND ".$searchSql : "";

	$sql = "SELECT * FROM commission_recipient WHERE deleted_at IS NULL $searchSql ORDER BY $order $dir LIMIT $start, $limit";
	$result = query($sql);
	$totalFiltered = countRec("commission_recipient","deleted_at IS NULL $searchSql");
	
	foreach($result as $row){		
		$action	= '<button type="button" data-id="'.base64_encode($row['id']).'" data-company="'.$row['company'].'" data-first_name="'.$row['first_name'].'" data-name="'.$row['name'].'" data-street="'.$row['street'].'" data-zip_code="'.$row['zip_code'].'" data-location="'.$row['location'].'" data-email="'.$row['email'].'" data-phone="'.$row['phone'].'" data-bank_recipient="'.$row['bank_recipient'].'" data-iban_no="'.$row['iban_no'].'" data-bic="'.$row['bic'].'" data-commission="'.$row['commission'].'" class="btn btn-secondary btn-sm edit-btn">Edit</button>';
		$action	.= ' <button type="button" data-id="'.base64_encode($row['id']).'" class="btn btn-danger btn-sm delete-btn">Delete</button>';
		$data[] = [
					$row['company'],
					$row['first_name'],
					$row['name'],
					$row['commission'],
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
