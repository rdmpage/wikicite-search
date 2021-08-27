<?php

// Add to Elastic 

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/csl.php');
require_once(dirname(__FILE__) . '/csl_to_elastic.php');


$filename = "extra.txt";
$filename = "test.txt";

$count = 1;

$file_handle = fopen($filename, "r");
while (!feof($file_handle)) 
{
	$id = trim(fgets($file_handle));
		
	if ($id != "")
	{
		$obj = wikidata_to_csl($id);
		
		if ($obj)
		{
			//echo $obj->id . "\n";
			
			// print_r($obj);
			
			upload($obj);
	
			// Give server a break every 100 items
			if (($count++ % 100) == 0)
			{
				$rand = rand(1000000, 3000000);
				echo "\n ...sleeping for " . round(($rand / 1000000),2) . ' seconds' . "\n\n";
				usleep($rand);
			}
		}
	}	
}	

?>



