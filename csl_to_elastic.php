<?php

// Core functions to populate Elasticsearch database from a CSL record

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/elastic.php');

//----------------------------------------------------------------------------------------
// Extract one or more sets of terms for the reference, grouped by language
function csl_to_terms ($csl)
{
	$lang = array();
	
	$keys = array('title', 'container-title', 'volume', 'issue', 'page');
	
	// get languages that we have
	
	if (isset($csl->multi))
	{
		if (isset($csl->multi->_key->title))
		{		
			foreach ($csl->multi->_key->title as $language => $v)
			{
				switch ($language)
				{
					case 'mul':
						break;
						
					default:
						if (!isset($lang[$language]))
						{
							$lang[$language] = new stdclass;
						}
						$lang[$language]->title = $v;
						break;
				}
			}
		}
		
		if (isset($csl->multi->_key->{'container-title'}))
		{		
			foreach ($csl->multi->_key->{'container-title'} as $language => $v)
			{
				switch ($language)
				{
					case 'mul':
						break;
						
					default:
						if (!isset($lang[$language]))
						{
							$lang[$language] = new stdclass;
						}
						$lang[$language]->{'container-title'} = $v;
						break;
				}
			}
		}
		
	}
	
	if (count($lang) == 0)
	{
		$lang['und'] = new stdclass;
	}
	
	// authors	
	foreach ($lang as $l => $data)
	{
		$lang[$l]->authors = array();
	}	

	if (isset($csl->author))
	{
		// assume author is a single string
		foreach ($csl->author as $author)
		{
			if (isset($author->literal))
			{
				// 大橋広好(Hiroyoshi Ohashi)
				if (preg_match('/^(.*)\s*[\/|\(]([^\)]+)/', $author->literal, $m))
				{
					// print_r($m);
					
					$languages = array_keys($lang);
					
					// print_r($languages);
					
					$l1 = '';
					$l2 = '';
					
					if (preg_match('/\p{Han}+/u', $m[1]))
					{
						$l1 = 'zh';
						if (in_array('ja', $languages))
						{
							$l1 = 'ja';
						}
						$lang[$l1]->authors[] = $m[1];
						
						// remove matched language
						$languages = array_diff($languages, array($l1));
						
						// print_r($languages);
						
						// add other version of name to other keys
						foreach ($languages as $l)
						{
							$lang[$l]->authors[] = $m[2];						
						}
					}
					
					if (preg_match('/\p{Han}+/u', $m[2]))
					{
						$l2 = 'zh';
						if (in_array('ja', $languages))
						{
							$l2 = 'ja';
						}
						
						$lang[$l2]->authors[] = $m[2];
						
						// remove matched language
						$languages = array_diff($languages, array($l2));
						
						// print_r($languages);
						
						// add other version of name to other keys
						foreach ($languages as $l)
						{
							$lang[$l]->authors[] = $m[1];						
						}
						
						
					}					

					
					// classify
				}
				else
				{
					foreach ($lang as $l => $data)
					{
						$lang[$l]->authors[] = $author->literal;
					}			
			
				}
			}
		}

	}
	
	// go through the keys adding non-language values if needed
	foreach ($lang as $l => $data)
	{
		if (!isset($lang[$l]->title) && isset($csl->title))
		{
			$lang[$l]->title = $csl->title;
		}

		if (!isset($lang[$l]->{'container-title'}) && isset($csl->{'container-title'}))
		{
			$lang[$l]->{'container-title'} = $csl->{'container-title'};
		}

	}	
	
	// numerical keys
	foreach (array('volume', 'issue', 'page') as $k)
	{
		if (isset($csl->{$k}))
		{
			foreach ($lang as $l => $data)
			{
				$lang[$l]->{$k} = $csl->{$k};
			}
		}
	
	}
	
	if (isset($csl->issued))
	{
		foreach ($lang as $l => $data)
		{
			$lang[$l]->year = $csl->issued->{'date-parts'}[0][0];
		}	
	}

	return $lang;

}

//----------------------------------------------------------------------------------------
function csl_to_elastic ($csl)
{
	$doc = new stdclass;
	
	// things we will display
	$doc->search_display = new stdclass;
	
	// things we will search on
	$doc->search_data = new stdclass;
	$doc->search_data->fulltext_terms = array();
	$doc->search_data->fulltext_terms_boosted = array();
	
	$doc->search_data->timestamp = time();
		
	$doc->id = $csl->id;
	
	foreach ($csl as $k => $v)
	{	
		switch ($k)
		{
			case 'type':
				switch ($v)
				{
					case 'article-journal':
					case 'journal-article':
						$doc->type = 'article';
						break;
						
					case 'book':
						$doc->type = 'book';
						break;
						
					default:
						$doc->type = $v;
						break;
				}
				break;
		
			case 'DOI':
				$doc->search_display->doi = strtolower($v);
				$doc->search_data->fulltext_terms[] = $v;				
				break;
				
			case 'HANDLE':
				$doc->search_display->handle = $v;
				break;								
				
			case 'JSTOR':
				$doc->search_display->jstor = $v;
				break;				
												
			case 'URL':
				$doc->search_display->url = $v;
				break;	
				
			case 'WIKIDATA':
				$doc->search_display->wikidata = $v;
				break;	
				
			case 'ZOOBANK':
				$doc->search_display->zoobank = strtolower($v);
				$doc->search_data->fulltext_terms[] = $v;				
				break;
				
			case 'link':
			
				$pdf = array();
				foreach ($v as $link)
				{
					if (isset($link->{'content-type'}) && $link->{'content-type'} == 'application/pdf')
					{
						$pdf[] = $link->URL;
						
						if (isset($link->thumbnailUrl))
						{
							$doc->search_display->thumbnailUrl = $link->thumbnailUrl;
						}
					}				
				}
				
				if (count($pdf) > 0)
				{
					$doc->search_display->pdf = $pdf;
				}
				
				break;
														
				
			default:
				break;
		}
	}
	
	$terms = csl_to_terms($csl);
	
	
	// Get titles as these are the main thing we will search on
	$titles = array();
	
	foreach ($terms as $language => $data)
	{
		if (isset($data->title))
		{
			$titles[$language] = $data->title;
		}
	}
	
	if (count($titles) > 0)
	{	
		// Add titles to boosted terms
		$unique_titles = array_unique(array_values($titles));
		
		$doc->search_data->fulltext_terms_boosted = array_merge ($doc->search_data->fulltext_terms_boosted, $unique_titles);
		
		// Get a name to display
		$doc->search_display->name = $titles;
	}
	
	
	// Create citation strings for searching and also for matching (reconcile API)
	// Note that this is an array so we can support matching in multiple languages
	
	$doc->search_data->bibliographicCitation = array();
	
    $keys = array('authors', 'year', 'title', 'container-title', 'volume', 'issue', 'page');
	
	foreach ($terms as $language => $data)
	{
		$str = array();
		
		foreach ($keys as $k)
		{
			if (isset($data->{$k}))
			{
				switch ($k)
				{
					case 'authors':
						$str[] = join(' ', $data->{$k});
						break;
						
					default:
						$str[] = $data->{$k};
						break;
				
				}
			
			}
		}
		$citation_string = join(' ', $str);
		
		if (!in_array($citation_string, $doc->search_data->bibliographicCitation))
		{
			 $doc->search_data->bibliographicCitation[] = $citation_string;
		}	
	}	
	
	// Add citation strings to fulltext terms
	$doc->search_data->fulltext_terms = array_merge ($doc->search_data->fulltext_terms, $doc->search_data->bibliographicCitation);
	
	// Generate search terms
	$doc->search_data->fulltext = join(" ", $doc->search_data->fulltext_terms);
	unset($doc->search_data->fulltext_terms);

	$doc->search_data->fulltext_boosted = join(" ", $doc->search_data->fulltext_terms_boosted);
	unset($doc->search_data->fulltext_terms_boosted);
	
	// Add CSL for display
	$doc->search_display->csl = $csl;
	
	// empty for debugging	
	//$doc->search_display->csl = new stdclass;
	
	return $doc;
}


//----------------------------------------------------------------------------------------
function upload ($csl)
{
	global $elastic;

	$doc = csl_to_elastic($csl);

	print_r($doc);
	//print_r($csl);
	
	$elastic_doc = new stdclass;
	$elastic_doc->doc = $doc;
	$elastic_doc->doc_as_upsert = true;
	$elastic->send('POST',  '_doc/' . urlencode($elastic_doc->doc->id). '/_update', json_encode($elastic_doc));					
}





?>
