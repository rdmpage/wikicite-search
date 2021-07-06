<?php


$q = 'Woo, F.C., Guo, Y. &amp; Feng, P. (1986) Notes on the genus <i>Eucorydia</i> of China (1). <i>Entomotaxonomia</i> 8, 153–157.';

$q = 'Roth, L. M. 1972. The male genitalia of Blattaria IX. Blaberidae. Gyna spp. (Perisphaeriinae) Phoraspis, Thorax, and Phlebonotus (Epilamprinae). Transactions of the American Entomological Society 98(2):203';

$q = 'Roth, L. M. 1977. A taxonomic revision of the Panesthiinae of the world. I. The Panesthiinae of Australia (Dictyoptera: Blattaria: Blaberidae). Australian Journal of Zoology Suppl. Ser. 48:1-112';

if (isset($_GET['q']))
{
	$q = $_GET['q'];
}


function main($q)
{
	$obj = new stdclass;
	
	$obj->query = $q;
	
	// clean
	$obj->query = strip_tags($obj->query);

	$obj->found = false;

	// reconciliation API(s)
	
	$query = new stdclass;
	$key = 'q0';
	$query->{$key} = new stdclass;
	$query->{$key}->query = $obj->query;
	$query->{$key}->limit = 3;
	
	$endpoints = array(
		'http://localhost/~rpage/wikicite-search/api_reconciliation.php?queries=',
		//'https://biostor.org/reconcile?queries=',
	);
	
	$i = 0;
	$n = count($endpoints);
	
	while (!$obj->found && $i < $n)
	{
		$url = $endpoints[$i] . urlencode(json_encode($query));
		
		// echo $url . "\n";
	
		$opts = array(
		  CURLOPT_URL =>$url,
		  CURLOPT_FOLLOWLOCATION => TRUE,
		  CURLOPT_RETURNTRANSFER => TRUE
		);
	
		$ch = curl_init();
		curl_setopt_array($ch, $opts);
		$data = curl_exec($ch);
		$info = curl_getinfo($ch); 
		curl_close($ch);
		
		//echo $data;
		
	
		if ($data != '')
		{
			$response = json_decode($data);
		
			// echo "Response\n";
			// print_r($response);
			
			if (isset($response->{$key}->result))
			{
				if (isset($response->{$key}->result[0]))
				{
					if ($response->{$key}->result[0]->match)
					{
						$obj->found = true;
						$obj->score = $response->{$key}->result[0]->score;						
						$obj->id = $response->{$key}->result[0]->id;																		
					}
				}			
			}
		
		}
		
		$i++;		
	
	}

	echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	echo "\n";

}


main($q);

?>
