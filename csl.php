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
				if (preg_match('/\+(?<year>[0-9]{4})-(?<month>[0-1][1-9])-00/', $value->time, $m))
				{
					$result[] = (Integer)$m['year'];
					$result[] = (Integer)$m['month'];
				}
				else
				{
					if (preg_match('/\+(?<year>[0-9]{4})-(?<month>[0-1][1-9])-(?<day>[0-3][0-9])/', $value->time, $m))
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
		'P304' => 'pages',
		'P356' => 'DOI',
		'P433' => 'issue',
		'P478' => 'volume',
		'P1476' => 'title',
	
		'P577' => 'issued',
	
		'P2093' => 'author',	

	);

	$json = get_one($id);
	$wd = json_decode($json);
	
	$obj = new stdclass;
	$obj->id = $id;

	foreach ($wd->entities->{$id}->claims as $k => $claim)
	{
		switch ($k)
		{
			// instance 
	
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
				$obj->{$wikidata_to_csl[$k]}->{'date-parts'}[] = $values = date_value($claim);
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
	}
	
	return $obj;

}

// test

if (1)
{
	$id = 'Q99952500';
	$csl = wikidata_to_csl($id);
	
	echo json_encode($csl, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

}

?>
