<?php

// utils

require_once(dirname(__FILE__) . '/lcs.php');


//----------------------------------------------------------------------------------------
function nice_strip_tags($str)
{
	$str = preg_replace('/</u', ' <', $str);
	$str = preg_replace('/>/u', '> ', $str);
	
	$str = strip_tags($str);
	
	$str = preg_replace('/\s\s+/u', ' ', $str);
	
	$str = preg_replace('/^\s+/u', '', $str);
	$str = preg_replace('/\s+$/u', '', $str);
	
	return $str;
	
}

//----------------------------------------------------------------------------------------
// Convert a GUID string to a clean, standardised version
function clean_guid($guid)
{
	$done = false;

	if (!$done)
	{
		// DOIs are lower case
		if (preg_match('/^10./', $guid))
		{
			$guid = strtolower($guid);
			$done = true;		
		}
	}
		
	if (!$done)
	{		
		// JSTOR is HTTPS
		if (preg_match('/jstor.org/', $guid))
		{
			if (preg_match('/jstor.org\/stable\/(?<id>.*)/', $guid, $m))
			{
				$guid = 'https://www.jstor.org/stable/' . strtolower($m['id']);	
				$done = true;		
			}
		}
	}
	
	if (!$done)
	{		
		// Make URLs lower case
		if (preg_match('/^http/', $guid))
		{
			$guid = strtolower($guid);		
			$done = true;			
		}	
	}
	
	return $guid;
}

//----------------------------------------------------------------------------------------
// https://stackoverflow.com/a/2759179
function Unaccent($string)
{
    return preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);~i', '$1', htmlentities($string, ENT_QUOTES, 'UTF-8'));
}

//----------------------------------------------------------------------------------------
function clean ($text)
{
	$text = preg_replace('/\./u', '', $text);
	$text = preg_replace('/-/u', ' ', $text);
	$text = preg_replace('/,/u', ' ', $text);
	$text = preg_replace('/\(/u', ' ', $text);
	$text = preg_replace('/\)/u', ' ', $text);
	$text = preg_replace('/[\?|:|\.]/u', ' ', $text);
	$text = preg_replace('/«/u', ' ', $text);
	$text = preg_replace('/»/u', ' ', $text);
		
	$text = preg_replace('/\s\s+/u', ' ', $text);
	
	//echo $text . "\n";

	$text = mb_convert_case($text, MB_CASE_LOWER);
	
	//echo $text . "\n";

	$text = Unaccent($text);

	//echo $text . "|\n";

	return $text;
}

//----------------------------------------------------------------------------------------
function compare($name1, $name2, $debug = false)
{
	
	$result = new stdclass;
	
	$result->str1 = $name1;
	$result->str2 = $name2;
	
	$result->str1 = clean($result->str1);
	$result->str2 = clean($result->str2);

	$lcs = new LongestCommonSequence($result->str1, $result->str2);
	
	$result->d = $lcs->score();
	
	$result->p = $result->d / min(strlen($result->str1), strlen($result->str2));
	
	$lcs->get_alignment();
			
	if ($debug)
	{
		$result->alignment = '';
		$result->alignment .= "\n";
		$result->alignment .= $lcs->left . "\n";
		$result->alignment .= $lcs->bars . "\n";
		$result->alignment .= $lcs->right . "\n";
		$result->alignment .= $result->d . " characters match\n";
		$result->alignment .= $result->p . " of shortest string matches\n";
	}	
	
	return $result;
}

//----------------------------------------------------------------------------------------

// make a standard citation for us to check matches against
function csl_to_citation_string($csl)
{
	$bibliographicCitation = array();

	$keys = array('author', 'issued', 'title', 'container-title', 'volume', 'issue', 'page');
	foreach ($keys as $k)
	{
		if (isset($csl->{$k}))
		{
			switch ($k)
			{
				case 'title':
					if (is_array($csl->{$k}))
					{
						$bibliographicCitation[] = ' ' . $csl->{$k}[0] . '.';
					}
					else
					{
						$bibliographicCitation[] = ' ' . $csl->{$k} . '.';						
					}
					break;

				case 'container-title':
					if (is_array($csl->{$k}))
					{
						$bibliographicCitation[] = ' ' . $csl->{$k}[0];
					}
					else
					{
						$bibliographicCitation[] = ' ' . $csl->{$k};						
					}
					break;

				case 'author':
					$authors = array();
					foreach ($csl->{$k} as $author)
					{
						$author_parts = [];
						if (isset($author->literal))
						{
							$author_parts[] = $author->literal;
						}
						else
						{
							if (isset($author->given))
							{
								$author_parts[] = $author->given;
							}
							if (isset($author->family))
							{
								$author_parts[] = $author->family;
							}
						}
						$authors[] = join(' ', $author_parts);						
					}
					$bibliographicCitation[] = join('; ', $authors);
					break;
				
				case 'issued':
					if (isset($csl->{$k}->{'date-parts'}))
					{
						$bibliographicCitation[] = ' (' . $csl->{$k}->{'date-parts'}[0][0] . ').';
					}					
					break;
				
				case 'page':
					$bibliographicCitation[] = ': ' . $csl->{$k};
					break;
				
				case 'issue':
					$bibliographicCitation[] = '(' . $csl->{$k} . ')';
					break;
		
				case 'volume':
					$bibliographicCitation[] = ', ' . $csl->{$k};
					break;

				default:
					$bibliographicCitation[] = $csl->{$k};
					break;
			}
	
		}

	}
		
	return trim(join('', $bibliographicCitation));
}

?>
