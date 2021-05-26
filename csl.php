<?php

// Wikidata to CSL

require_once(dirname(__FILE__) . '/wikidata.php');


//----------------------------------------------------------------------------------------
function literal_value_simple($obj)
{
	$result = '';
	
	foreach ($obj as $k => $v)
	{
		if ($v->rank == 'normal')
		{
			$result = $v->mainsnak->datavalue->value;
		}
	}

	return $result;

}

//----------------------------------------------------------------------------------------
function literal_value_multilingual($obj)
{
	$result = array();
	
	foreach ($obj as $k => $v)
	{
		if ($v->rank == 'normal')
		{
			$language = $v->mainsnak->datavalue->value->language;
			$value = $v->mainsnak->datavalue->value->text;
			
			if (!isset($result[$language]))
			{
				$result[$language] = array();
			}
			$result[$language][] = $value;
		}
	}

	return $result;

}

//----------------------------------------------------------------------------------------
function date_value($obj)
{
	$result = array();
	
	foreach ($obj as $k => $v)
	{
		if ($v->rank == 'normal')
		{
			$value = $v->mainsnak->datavalue->value;
			
			if (preg_match('/\+(?<year>[0-9]{4})-00-00/', $value->time, $m))
			{
				$result[] = (Integer)$m['year'];
			}
			else
			{
				if (preg_match('/\+(?<year>[0-9]{4})-(?<month>[0-1][0-9])-00/', $value->time, $m))
				{
					$result[] = (Integer)$m['year'];
					$result[] = (Integer)$m['month'];
				}
				else
				{
					if (preg_match('/\+(?<year>[0-9]{4})-(?<month>[0-1][0-9])-(?<day>[0-3][0-9])/', $value->time, $m))
					{
						$result[] = (Integer)$m['year'];
						$result[] = (Integer)$m['month'];
						$result[] = (Integer)$m['day'];
					}
				}
			}
		}
	}

	return $result;

}

//----------------------------------------------------------------------------------------
function ordered_simple_literal($obj)
{
	$result = array();
	
	foreach ($obj as $k => $v)
	{
		if ($v->rank == 'normal')
		{
			$value = $v->mainsnak->datavalue->value;

			if (isset($v->qualifiers))
			{
				if (isset($v->qualifiers->{'P1545'}))
				{
					$order = $v->qualifiers->{'P1545'}[0]->datavalue->value;
					$result[$order] = $value;
				}
			
			}

		}
	}

	return $result;

}

//----------------------------------------------------------------------------------------
// Get serial order qualifier for a given claim
function claim_serial_order($v)
{
	$order = 0;
	
	if ($v->rank == 'normal')
	{
		if (isset($v->qualifiers))
		{
			if (isset($v->qualifiers->{'P1545'}))
			{
				$order = $v->qualifiers->{'P1545'}[0]->datavalue->value;
			}
		
		}

	}

	return $order;

}



//----------------------------------------------------------------------------------------
// get journal name(s) and ISSN(s), if any
function get_container_info($id, &$obj)
{
	$json = get_one($id);
	$wd = json_decode($json);
	
	foreach ($wd->entities->{$id}->claims as $k => $claim)
	{
		switch ($k)
		{
			case 'P236':
				if (!isset($obj->ISSN))
				{
					$obj->ISSN = array();
				}
				foreach ($claim as $c)
				{
					$obj->ISSN[] = $c->mainsnak->datavalue->value;
				}			
				break;
				
			case 'P1476':
				$values = literal_value_multilingual ($claim);
				
				// print_r($values);
			
				if (!isset($obj->multi))
				{
					$obj->multi = new stdclass;
					$obj->multi->_key = new stdclass;
				}
			
				$obj->multi->_key->{'container-title'} = new stdclass;
			
				foreach ($values as $language => $text)
				{
					$obj->multi->_key->{'container-title'}->{$language} = $text[0];
				
					// use one value as title
					if (!isset($obj->{'container-title'}))
					{
						$obj->{'container-title'} = $text[0];
					}
				}			
				break;
			
			default:
				break;
		}
	}

}


//----------------------------------------------------------------------------------------
// Get author name and any identifiers
function get_author_info($id, $order, &$obj)
{
	$json = get_one($id);
	$wd = json_decode($json);
	
	if (!isset($obj->authors))
	{
		$obj->authors = array();
	}
	
	$author = new stdclass;
	$author->literal = "";
	
	// name is a label
	
	// we should have an English label...
	if ($author->literal == '')
	{
		if (isset($wd->entities->{$id}->labels->en))
		{
			$author->literal = $wd->entities->{$id}->labels->en->value;
		}
	}

	// Chinese
	if ($author->literal == '')
	{
		if (isset($wd->entities->{$id}->labels->zh))
		{
			$author->literal = $wd->entities->{$id}->labels->zh->value;
		}
	}

	// Japanese
	if ($author->literal == '')
	{
		if (isset($wd->entities->{$id}->labels->ja))
		{
			$author->literal = $wd->entities->{$id}->labels->ja->value;
		}
	}
	
	// If we have nothing at this point...?
	
	
	if ($author->literal == '')
	{
		$author->literal = '[unknown]';
	}
	
	
	
	foreach ($wd->entities->{$id}->claims as $k => $claim)
	{
		
		switch ($k)
		{
			
			case 'P496':
				$author->ORCID = 'https://orcid.org/' . literal_value_simple($claim);
				break;		
			
			default:
				break;
		}
	}


	$obj->authors[$order] = $author;
}

//----------------------------------------------------------------------------------------

function wikidata_to_csl($id)
{
	$wikidata_to_csl = array(
		'P304' => 'page',
		'P356' => 'DOI',
		'P433' => 'issue',
		'P478' => 'volume',
		'P1476' => 'title',	
		'P577' => 'issued',	
		'P2093' => 'author',	
	);

	$json = get_one($id);
	$wd = json_decode($json);
	
	print_r($wd);
	
	$obj = new stdclass;
	$obj->id = $id;

	foreach ($wd->entities->{$id}->claims as $k => $claim)
	{
		switch ($k)
		{
			// instance 
			case 'P31':
				$instances = array();
				foreach ($claim as $c)
				{
					$instances[] = $c->mainsnak->datavalue->value->id;		
				}
				
				$type = '';
				
				if ($type == '')
				{
					if (in_array('Q13442814', $instances))
					{
						$type = 'article-journal';
					}
				}
				if ($type == '')
				{

					if (in_array('Q18918145', $instances))
					{
						$type = 'article-journal';
					}
				}
				if ($type == '')
				{
					if (in_array('Q191067', $instances))
					{
						$type = 'article-journal';
					}
				}
				if ($type == '')
				{
					if (in_array('Q47461344', $instances))
					{
						$type = 'book';
					}
				}
				if ($type == '')
				{
					if (in_array('Q571', $instances))
					{
						$type = 'book';
					}
				}
				if ($type == '')
				{					
					if (in_array('Q3331189', $instances))
					{
						$type = 'book';
					}
				}
				if ($type == '')
				{
					if (in_array('Q1980247', $instances))
					{
						$type = 'chapter';
					}
				}

				if ($type == '')
				{
					if (in_array('Q1266946', $instances))
					{
						$type = 'thesis';
					}
				}

				if ($type == '')
				{
					if (in_array('Q187685', $instances))
					{
						$type = 'thesis';
					}
				}
				
				if ($type == '')
				{
					// assume it's an article for now
					$type = 'article-journal';
				}
				
				$obj->type = $type;
				break;
	
			// simple values
			case 'P304':
			case 'P433':
			case 'P478':
		
				$value = literal_value_simple($claim);
				if ($value != '')
				{
					$obj->{$wikidata_to_csl[$k]} = $value;
				}
		
				break;
			
			// DOI
			case 'P356':
		
				$value = literal_value_simple($claim);
				if ($value != '')
				{
					$obj->{$wikidata_to_csl[$k]} = strtolower($value);
				}
		
				break;
			
			
			// title
			case 'P1476':
				$values = literal_value_multilingual ($claim);
			
				// print_r($values);
			
				if (!isset($obj->multi))
				{
					$obj->multi = new stdclass;
					$obj->multi->_key = new stdclass;
				}
			
				$obj->multi->_key->{$wikidata_to_csl[$k]} = new stdclass;
			
				foreach ($values as $language => $text)
				{
					$obj->multi->_key->{$wikidata_to_csl[$k]}->{$language} = $text[0];
				
					// use one value as title
					if (!isset($obj->{$wikidata_to_csl[$k]}))
					{
						$obj->{$wikidata_to_csl[$k]} = $text[0];
					}
				}
			
				break;
			
			// publication date
			case 'P577':
				$obj->{$wikidata_to_csl[$k]} = new stdclass;
				$obj->{$wikidata_to_csl[$k]}->{'date-parts'} = array();
				$obj->{$wikidata_to_csl[$k]}->{'date-parts'}[] = date_value($claim);	
				break;
			
			// author as string
			case 'P2093':
				$authorstrings = ordered_simple_literal($claim);
			
				if (!isset($obj->authors))
				{
					$obj->authors = array();
				}
			
				foreach ($authorstrings as $order => $string)
				{
					$author = new stdclass;
					$author->literal = $string;
			
					$obj->authors[(Integer)$order] = $author;
				}			
				break;
					
			// author as thing
			case 'P50':
				foreach ($claim as $c)
				{
					$order = claim_serial_order($c);
			
					$id = $c->mainsnak->datavalue->value->id;	
					get_author_info($id, $order, $obj);
				}		
				break;
		
			// container 
			case 'P1433':
				$mainsnak = $claim[0]->mainsnak;			
				$container_id = $mainsnak->datavalue->value->id;			
				get_container_info($container_id, $obj);			
				break;
		
			// PDF
			
			case 'P724': // Internet Archive
				$value = literal_value_simple($claim);
				
				if ($value != '')
				{
					// thumbnailUrl = "//archive.org/download/" + id + "/page/cover_thumb.jpg";


					$link = new stdclass;
					$link->URL = 'https://archive.org/download/' . $value . '/' . $value . '.pdf';
					$link->{'content-type'} = 'application/pdf';
					
					// my hack
					$link->thumbnailUrl = 'https://archive.org/download/' . $value . '/page/cover_thumb.jpg';
					
					if (!isset($obj->link))
					{
						$obj->link = array();
					}
					$obj->link[] = $link;
				}
				break;
				
			case 'P953': // fulltext 
				foreach ($claim as $c)
				{
					$link = new stdclass;
					// $link->URL = $c->mainsnak->datavalue->value->value;
					
					if (isset($c->qualifiers))
					{
						// PDF?
						if (isset($c->qualifiers->{'P2701'}))
						{
							if ($c->qualifiers->{'P2701'}[0]->datavalue->value->id == 'Q42332')
							{
								$link->{'content-type'} = 'application/pdf';
							};
						}
						
						// Archived?
						if (isset($c->qualifiers->{'P1065'}))
						{
							$link->URL = $c->qualifiers->{'P1065'}[0]->datavalue->value;
							// direct link to PDF
							$link->URL = str_replace("/http", "if_/http", $link->URL);
						}						
					}
					
					if (isset($link->URL) && (isset($link->{'content-type'}) && $link->{'content-type'} == 'application/pdf'))
					{					
						if (!isset($obj->link))
						{
							$obj->link = array();
						}
						$obj->link[] = $link;
					
					}
				}		
				break;
			
	
			default:
				break;
		}

	}


	// post process

	// create ordered list of authors
	if (isset($obj->authors))
	{
		// print_r($obj->authors);

		$obj->author = array();

		ksort($obj->authors, SORT_NUMERIC);
		foreach ($obj->authors as $author)
		{
			$obj->author[] = $author;
		}

		unset($obj->authors);
		
		// post process name strings
		$n = count($obj->author);
		for ($i = 0; $i < $n; $i++)
		{
			// CSL PHP needs atomised names :(
			if (!isset($obj->author[$i]->family))
			{
				// We need to handle author names where there has been a clumsy attempt
				// (mostly by me) to include multiple language strings
			
				// 大橋広好(Hiroyoshi Ohashi)
				// 韦毅刚/WEI Yi-Gang
				if (preg_match('/^(.*)\s*[\/|\(]([^\)]+)/', $obj->author[$i]->literal, $m))
				{
					// print_r($m);
					
					if (preg_match('/\p{Han}+/u', $m[1]))
					{
						$obj->author[$i]->literal = $m[2];									
					}
					if (preg_match('/\p{Han}+/u', $m[2]))
					{
						$obj->author[$i]->literal = $m[1];									
					}
					
				}							
			
				$parts = preg_split('/,\s+/', $obj->author[$i]->literal);
				
				if (count($parts) == 2)
				{
					$obj->author[$i]->family = $parts[0];
					$obj->author[$i]->given = $parts[1];
				}
				else
				{
					$parts = preg_split('/\s+/', $obj->author[$i]->literal);
					
					if (count($parts) > 1)
					{
						$obj->author[$i]->family = array_pop($parts);
						$obj->author[$i]->given = join(' ', $parts);
					}
					
				}
			
			}
		}
		
	}
	
	return $obj;

}

// test

if (0)
{
	$id = 'Q105118008';
	$id = 'Q104735653';
	$csl = wikidata_to_csl($id);
	
	echo json_encode($csl, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

}

?>