<?php

// Fetch from Wikidata

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/wikidata.php');

$filename = "ids.txt";
//$filename = "cockroaches.txt";
//$filename = "test.txt";

$filename = "Araneae.txt";
$filename = "ion.txt";
$filename = "if.txt";
$filename = "extra.txt";
//$filename = '0524-4994.txt';
//$filename = "ipni.txt";
//$filename = "handle.txt";
//$filename = "more.txt";
//$filename = "have.txt";
$filename = "test.txt";
//$filename = "junk/extra.txt";

$count = 1;

$force = true;
//$force = false;

$file_handle = fopen($filename, "r");
while (!feof($file_handle)) 
{
	$id = trim(fgets($file_handle));
	
	if ($id != "")
	{

		echo "$id\n";
			
		$call_count = fetch_one($id, $force);
		
		$count += $call_count;
	
		// Give server a break every 10 items
		if (($count % 4) == 0)
		{
			$rand = rand(2000000, 9000000);
			echo "\n ...sleeping for " . round(($rand / 1000000),2) . ' seconds' . "\n\n";
			usleep($rand);
		}	
	}
}	

?>
