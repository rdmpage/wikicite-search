<?php

// Elastic search

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/elastic.php');

//----------------------------------------------------------------------------------------
// Return a property value if it exists, otherwise an empty string
function get_property_value ($item, $key, $propertyName)
{
	$value = '';
	
	if (isset($item->{$key}))
	{
		$n = count($item->{$key});
		$i = 0;
		while ($value == '' && ($i < $n) )
		{
			if ($item->{$key}[$i]->name == $propertyName)
			{
				$value = $item->{$key}[$i]->value;
			}	
			
			$i++;	
		}
	}
	
	return $value;
}


//----------------------------------------------------------------------------------------
// Add a property value to an item. $key is the predicate that has the property,
// e.g. "identifier" 
function add_property_value (&$item, $key, $propertyName, $propertyValue)
{
	$found = false;
	
	$found = (get_property_value($item, $key, $propertyName) == $propertyValue);
	
	if (!$found)
	{
		// If we don't have this key then create it
		if (!isset($item->{$key}))
		{
			$item->{$key} = array();		
		}	
	
		$property = new stdclass;
		$property->{"@type"} = "PropertyValue";
		$property->name  = $propertyName;
		$property->value = $propertyValue;
		$item->{$key}[] = $property;
	}
}



//----------------------------------------------------------------------------------------

function do_search($q, $limit = 5)
{
	global $elastic;

	$json = '{
	"size":20,
		"query": {
		   "multi_match" : {
		  "query": "<QUERY>",
		  "fields":["search_data.fulltext", "search_data.fulltext_boosted^4"] 
		}
	},

	"highlight": {
		  "pre_tags": [
			 "<mark>"
		  ],
		  "post_tags": [
			 "<\/mark>"
		  ],
		  "fields": {
			 "search_data.fulltext": {},
			 "search_data.fulltext_boosted": {}
		  }
	   }

	}';

	$json = str_replace('<QUERY>', $q, $json);
	//$json = str_replace('<SIZE>', $limit, $json);

	$response = $elastic->send('POST',  '_search?pretty', $json);					

	// debugging
	//echo $response;

	$obj = json_decode($response);

	// process and convert to RDF

	// schema.org DataFeed
	$output = new stdclass;

	$output->{'@context'} = (object)array
		(
			'@vocab'	 			=> 'http://schema.org/',
			'goog' 					=> 'http://schema.googleapis.com/',
			'resultScore'		 	=> 'goog:resultScore'
		);

	$output->{'@graph'} = array();
	$output->{'@graph'}[0] = new stdclass;
	$output->{'@graph'}[0]->{'@id'} = "http://example.rss";
	$output->{'@graph'}[0]->{'@type'} = "DataFeed";
	$output->{'@graph'}[0]->dataFeedElement = array();
	
	if (isset($obj->hits))
	{
		$num_hits = 0;
		
		// Elastic 7.6.2
		if (isset($obj->hits->total->value))
		{
			$num_hits = $obj->hits->total->value;
		}
		else
		{
			$num_hits = $obj->hits->total;			
		}
		
		$time = '';
		if ($obj->took > 1000)
		{
			$time = '(' . floor($obj->took/ 1000) . ' seconds)';
		}
		else
		{
			$time = '(' . round($obj->took/ 1000, 2) . ' seconds)';
		}
		
		if ($num_hits == 0)
		{
			// Describe search
			$output->{'@graph'}[0]->description = "No results " . $time;
		}
		else
		{
			// Describe search
			if ($obj->hits->total->value == 1)
			{
				$output->{'@graph'}[0]->description = "One hit ";
			}
			else
			{
				$output->{'@graph'}[0]->description = $obj->hits->total->value . " hits ";
			}
			
			$output->{'@graph'}[0]->description .=  $time;
			
			$output->{'@graph'}[0]->query = $q;

			foreach ($obj->hits->hits as $hit)
			{
				$item = new stdclass;
				
				$item = new stdclass;
				$item->{'@id'} = $hit->_id;
				$item->{'@type'} = "DataFeedItem";
				
				$item->resultScore = $hit->_score;
				
				$item->name = $hit->_source->search_display->name;
			
				// highlight
				$item->description = join(' … ', $hit->highlight->{'search_data.fulltext'});
				
				// bibliographic string
				$item->bibliographicCitation = $hit->_source->search_data->bibliographicCitation;
				
				// DOI
				if (isset($hit->_source->search_display->doi))
				{
					$item->doi = $hit->_source->search_display->doi;
				}
				
				// PDF
				if (isset($hit->_source->search_display->pdf))
				{
					$item->contentUrl = $hit->_source->search_display->pdf;
				}
				
				// CSL


				$output->{'@graph'}[0]->dataFeedElement[] = $item;
			}			
	
		}

	}

	//print_r($output);

	return $output;
}


// test
if (0)
{
	$q = 'freshwater crayfish';
	$q = 'David Blair';
	
	$result = do_search($q);
	
	// print_r($result);
	
	//header("Content-type: application/json");
	//echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	
	
	$locale = 'en';
	//$locale = 'zh';
	
	// to html
	
	echo '<style> 
		mark { background-color:transparent; font-weight:bold; } 
		body { font-family:sans-serif;}
		a:link { color:#1a0dab; text-decoration:none; }
		a:visited { color:#660099; text-decoration:none; }
		a:hover { text-decoration:underline; }
	</style>';
	
	foreach ($result->{'@graph'}[0]->dataFeedElement as $item)
	{
		echo '<div style="padding:4px;margin-bottom:1em;width:60%;">';
		
		// name
		$name = '<span style="font-size:0.8em;">[' . $item->{'@id'} . '] </span>';
		
		if (isset($item->name->{$locale}))
		{
			$name .= $item->name->{$locale};
		}
		else
		{
			// pick one		
			$values = get_object_vars($item->name);
			reset($values);
			$first_key = key($values);
		
			$name .= $item->name->$first_key;
		}
		
		//  style="color:#1a0dab;">
		echo '<div style="font-size:1.2em;margin:4px;">';
		echo '<a href="https://alec-demo.herokuapp.com/?id=' . $item->{'@id'} . '" target="_new">';
		echo $name;
		echo '</a>';
		echo '</div>';

		// citation
		echo '<div style=font-size:0.8em;color:#007600;margin:4px;">' . $item->bibliographicCitation[0] . '</div>';

		
		// highlights
		echo '<div style="font-size:0.8em;color:#222;margin:4px;">';
		echo $item->description;
		echo '</div>';
		
		if (isset($item->doi))
		{
			echo '<div>';
			echo $item->doi;
			echo '</div>';
		}
	
		echo '</div>';
	}
	



}


?>
