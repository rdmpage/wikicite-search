<?php

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/csl.php');
require_once(dirname(__FILE__) . '/csl_to_elastic.php');


$basedir = $config['cache'];

$files1 = scandir($basedir);


$basename = 'wikicite';

$count = 1;
$total = 0;

$chunksize = 1000;
$chunk_files = array();

$rows = array();

$done = false;


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
				
				$csl = wikidata_to_csl($id);
		
				if ($csl)
				{
					// print_r($csl);
					
					$doc = csl_to_elastic($csl);
					
					// Action
					$meta = new stdclass;
					$meta->index = new stdclass;
					$meta->index->_index = $config['elastic_options']['index'];	
					$meta->index->_id = $doc->id;
					$meta->index->_type = '_doc';		
				
					$rows[] = json_encode($meta);
					$rows[] = json_encode($doc);
					
					$count++;	
					$total++;

				}
				else
				{
					echo $id . "\n";
					echo "*** problem ***\n";
				}
				
				
				if ($count % $chunksize == 0)
				{
					echo "Processed $total items\n";
					$output_filename = $basename . '-' . $total . '.json';
		
					$chunk_files[] = $output_filename;
		
					file_put_contents($output_filename, join("\n", $rows)  . "\n");
		
					$count = 1;
					$rows = array();
		
					/*
					if ($total >= 10000)
					{
						$done = true;
					}
					*/
				
		
				}
				
			
			}
			
			if ($done) break;
		}
	}
	if ($done) break;
}

// Left over?
if (count($rows) > 0)
{
	$output_filename = $basename . '-' . $total . '.json';
	
	$chunk_files[] = $output_filename;
	
	file_put_contents($output_filename, join("\n", $rows)  . "\n");
}


// output
echo "--- curl upload.sh ---\n";
$curl = "#!/bin/sh\n\n";
foreach ($chunk_files as $filename)
{
	$curl .= "echo '$filename'\n";
	
	$url = $config['elastic_options']['protocol']
		. '://' . $config['elastic_options']['user']
		. ':' . $config['elastic_options']['password']
		. '@' .	$config['elastic_options']['host']
		. '/' .	$config['elastic_options']['index']
		. '/_bulk';
	
	$curl .= "curl $url -H 'Content-Type: application/x-ndjson' -XPOST --data-binary '@$filename'  --progress-bar > /dev/null\n";
	$curl .= "echo 'Pausing...'\n";
	$curl .= "sleep 10\n";
	$curl .= "echo ''\n";
	
}

file_put_contents(dirname(__FILE__) . '/go.sh', $curl);




?>
