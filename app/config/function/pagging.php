<?php
 if ( ! defined('APP_FOLDER')) exit('No direct script access allowed');
		
	if(!function_exists('pagging'))
	{
		function pagging($qry,$per_page=50)
		{
			$total_rec = countRec($qry);
			
			echo '	<ul class="pagination">
						<li class="page-item disabled">
							<a class="page-link" href="#" tabindex="-1">Previous</a>
						</li>
						<li class="page-item"><a class="page-link" href="#">1</a></li>
						<li class="page-item active">
							<a class="page-link" href="#">2</a>
						</li>
						<li class="page-item"><a class="page-link" href="#">3</a></li>
						<li class="page-item">
							<a class="page-link" href="#">Next</a>
						</li>
					</ul>';
		}
	}
	
?>