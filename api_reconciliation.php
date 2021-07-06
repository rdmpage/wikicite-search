<?php

require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/reconciliation_api.php');
require_once (dirname(__FILE__) . '/search.php');
require_once (dirname(__FILE__) . '/utils.php');
//require_once (dirname(__FILE__) . '/api_utils.php');


//----------------------------------------------------------------------------------------
class BibFinderService extends ReconciliationService
{
	//----------------------------------------------------------------------------------------------
	function __construct()
	{
		global $config;
		
		$this->name 			= 'BibFinder';
		
		$this->identifierSpace 	= $config['web_server'];
		$this->schemaSpace 		= 'http://rdf.freebase.com/ns/type.object.id';
		$this->Types();
		
		//$view_url = $config['web_server'] . '/api.php?id={{id}}';
		$view_url = '';

		$preview_url = '';	
		$width = 430;
		$height = 300;
		
		if ($view_url != '')
		{
			$this->View($view_url);
		}
		if ($preview_url != '')
		{
			$this->Preview($preview_url, $width, $height);
		}
	}
	
	//----------------------------------------------------------------------------------------------
	function Types()
	{
		$type = new stdclass;
		$type->id = 'https://schema.org/CreativeWork';
		$type->name = 'CreativeWork';
		$this->defaultTypes[] = $type;
	} 	
		
	// Elastic 
	//----------------------------------------------------------------------------------------------
	// Handle an individual query
	function OneQuery($query_key, $text, $limit = 1, $properties = null)
	{
		global $config;
		
		
		$search_result = do_search($text);
	
		// print_r($search_result);

		$hits = array();

		$threshold 	= 0.9;
		$max_d 		= 0.0;

		foreach ($search_result->{'@graph'}[0]->dataFeedElement as $item)
		{
			// an item may have > 1 string representation
			foreach ($item->bibliographicCitation as $bibliographicCitation)
			{		
				$result = compare($text, $bibliographicCitation, true);
	
				//print_r($result);	
	
				//echo $item->bibliographicCitation . "\n";
	
				// get hits
				if ($result->p > $threshold && $result->p >= $max_d)
				{
					$hit = new stdclass;
		
					$hit->id = $item->{'@id'};
					$hit->query = $text;
					$hit->bibliographicCitation = $bibliographicCitation;
					$hit->d = $result->p;
		
					if (isset($item->doi))
					{
						$hit->doi = $item->doi;
					}		
		
					if ($result->p > $max_d)
					{
						$max_d = $result->p;
						$hits = array();
		
					}
					$hits[] =  $hit;
				}
			}
		}
		
		// output in reconciliation format	
		
		$n = count($hits);
		$n = min($n, 3);
		
		for ($i = 0; $i < $n; $i++)
		{
			$recon_hit = new stdclass;
			$recon_hit->id 		= $hits[$i]->id;	
			$recon_hit->name 	= $hits[$i]->bibliographicCitation;	
			$recon_hit->score = $hits[$i]->d;
			$recon_hit->match = ($recon_hit->score > 0.8);
			$this->StoreHit($query_key, $recon_hit);							
		}		
		
	}	
	
}

$service = new BibFinderService();


if (0)
{
	file_put_contents('/tmp/q.txt', $_REQUEST['queries'], FILE_APPEND);
}


$service->Call($_REQUEST);

?>