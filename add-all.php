<?php

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/csl.php');
require_once(dirname(__FILE__) . '/csl_to_elastic.php');


$basedir = $config['cache'];

$files1 = scandir($basedir);

$count = 1;

foreach ($files1 as $directory)
{
	if (preg_match('/^\d+$/', $directory))
	{	
		// echo "directory= $directory\n";
			
		$files2 = scandir($basedir . '/' . $directory);
		
		// print_r($files2);

		foreach ($files2 as $filename)
		{
			// echo $filename . "\n";
			
			if (preg_match('/^Q\d+\.json$/', $filename))
			{
				/*
				$path_name = $basedir . '/' . $directory . '/' . $filename;
				
				$json = file_get_contents($path_name);
				
				echo $json;
				*/
				
				$id = str_replace('.json', '', $filename);
				
				// echo $id . "\n";
				
				$obj = wikidata_to_csl($id);
		
				if ($obj)
				{
					print_r($obj);
				
				
					//echo $obj->id . "\n";
			
					//upload($obj);
	
					// Give server a break every 100 items
					if (($count++ % 50) == 0)
					{
						$rand = rand(1000000, 3000000);
						echo "\n ...sleeping for " . round(($rand / 1000000),2) . ' seconds' . "\n\n";
						usleep($rand);
					}
				}
				else
				{
					echo $id . "\n";
					echo "*** problem ***\n";
				}
				
				
				// Give server a break every 100 items
				if (($count++ % 50) == 0)
				{
					$rand = rand(1000000, 3000000);
					echo "\n ...sleeping for " . round(($rand / 1000000),2) . ' seconds' . "\n\n";
					usleep($rand);
				}
				
			
			}
		}
	}
}

?>
