<?php

// utils

require_once(dirname(__FILE__) . '/lcs.php');

//----------------------------------------------------------------------------------------
// https://developer.wordpress.org/reference/functions/force_balance_tags/
function force_balance_tags( $text ) {
    $tagstack  = array();
    $stacksize = 0;
    $tagqueue  = '';
    $newtext   = '';
    // Known single-entity/self-closing tags.
    $single_tags = array( 'area', 'base', 'basefont', 'br', 'col', 'command', 'embed', 'frame', 'hr', 'img', 'input', 'isindex', 'link', 'meta', 'param', 'source' );
    // Tags that can be immediately nested within themselves.
    $nestable_tags = array( 'blockquote', 'div', 'object', 'q', 'span' );
 
    // WP bug fix for comments - in case you REALLY meant to type '< !--'.
    $text = str_replace( '< !--', '<    !--', $text );
    // WP bug fix for LOVE <3 (and other situations with '<' before a number).
    $text = preg_replace( '#<([0-9]{1})#', '&lt;$1', $text );
 
    /**
     * Matches supported tags.
     *
     * To get the pattern as a string without the comments paste into a PHP
     * REPL like `php -a`.
     *
     * @see https://html.spec.whatwg.org/#elements-2
     * @see https://w3c.github.io/webcomponents/spec/custom/#valid-custom-element-name
     *
     * @example
     * ~# php -a
     * php > $s = [paste copied contents of expression below including parentheses];
     * php > echo $s;
     */
    $tag_pattern = (
        '#<' . // Start with an opening bracket.
        '(/?)' . // Group 1 - If it's a closing tag it'll have a leading slash.
        '(' . // Group 2 - Tag name.
            // Custom element tags have more lenient rules than HTML tag names.
            '(?:[a-z](?:[a-z0-9._]*)-(?:[a-z0-9._-]+)+)' .
                '|' .
            // Traditional tag rules approximate HTML tag names.
            '(?:[\w:]+)' .
        ')' .
        '(?:' .
            // We either immediately close the tag with its '>' and have nothing here.
            '\s*' .
            '(/?)' . // Group 3 - "attributes" for empty tag.
                '|' .
            // Or we must start with space characters to separate the tag name from the attributes (or whitespace).
            '(\s+)' . // Group 4 - Pre-attribute whitespace.
            '([^>]*)' . // Group 5 - Attributes.
        ')' .
        '>#' // End with a closing bracket.
    );
 
    while ( preg_match( $tag_pattern, $text, $regex ) ) {
        $full_match        = $regex[0];
        $has_leading_slash = ! empty( $regex[1] );
        $tag_name          = $regex[2];
        $tag               = strtolower( $tag_name );
        $is_single_tag     = in_array( $tag, $single_tags, true );
        $pre_attribute_ws  = isset( $regex[4] ) ? $regex[4] : '';
        $attributes        = trim( isset( $regex[5] ) ? $regex[5] : $regex[3] );
        $has_self_closer   = '/' === substr( $attributes, -1 );
 
        $newtext .= $tagqueue;
 
        $i = strpos( $text, $full_match );
        $l = strlen( $full_match );
 
        // Clear the shifter.
        $tagqueue = '';
        if ( $has_leading_slash ) { // End tag.
            // If too many closing tags.
            if ( $stacksize <= 0 ) {
                $tag = '';
                // Or close to be safe $tag = '/' . $tag.
 
                // If stacktop value = tag close value, then pop.
            } elseif ( $tagstack[ $stacksize - 1 ] === $tag ) { // Found closing tag.
                $tag = '</' . $tag . '>'; // Close tag.
                array_pop( $tagstack );
                $stacksize--;
            } else { // Closing tag not at top, search for it.
                for ( $j = $stacksize - 1; $j >= 0; $j-- ) {
                    if ( $tagstack[ $j ] === $tag ) {
                        // Add tag to tagqueue.
                        for ( $k = $stacksize - 1; $k >= $j; $k-- ) {
                            $tagqueue .= '</' . array_pop( $tagstack ) . '>';
                            $stacksize--;
                        }
                        break;
                    }
                }
                $tag = '';
            }
        } else { // Begin tag.
            if ( $has_self_closer ) { // If it presents itself as a self-closing tag...
                // ...but it isn't a known single-entity self-closing tag, then don't let it be treated as such
                // and immediately close it with a closing tag (the tag will encapsulate no text as a result).
                if ( ! $is_single_tag ) {
                    $attributes = trim( substr( $attributes, 0, -1 ) ) . "></$tag";
                }
            } elseif ( $is_single_tag ) { // Else if it's a known single-entity tag but it doesn't close itself, do so.
                $pre_attribute_ws = ' ';
                $attributes      .= '/';
            } else { // It's not a single-entity tag.
                // If the top of the stack is the same as the tag we want to push, close previous tag.
                if ( $stacksize > 0 && ! in_array( $tag, $nestable_tags, true ) && $tagstack[ $stacksize - 1 ] === $tag ) {
                    $tagqueue = '</' . array_pop( $tagstack ) . '>';
                    $stacksize--;
                }
                $stacksize = array_push( $tagstack, $tag );
            }
 
            // Attributes.
            if ( $has_self_closer && $is_single_tag ) {
                // We need some space - avoid <br/> and prefer <br />.
                $pre_attribute_ws = ' ';
            }
 
            $tag = '<' . $tag . $pre_attribute_ws . $attributes . '>';
            // If already queuing a close tag, then put this tag on too.
            if ( ! empty( $tagqueue ) ) {
                $tagqueue .= $tag;
                $tag       = '';
            }
        }
        $newtext .= substr( $text, 0, $i ) . $tag;
        $text     = substr( $text, $i + $l );
    }
 
    // Clear tag queue.
    $newtext .= $tagqueue;
 
    // Add remaining text.
    $newtext .= $text;
 
    while ( $x = array_pop( $tagstack ) ) {
        $newtext .= '</' . $x . '>'; // Add remaining tags to close.
    }
 
    // WP fix for the bug with HTML comments.
    $newtext = str_replace( '< !--', '<!--', $newtext );
    $newtext = str_replace( '<    !--', '< !--', $newtext );
 
    return $newtext;
}


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
	
	// try and avoid spurious matches
	if (strlen($result->str1) < 50)
	{
		$result->p = 0;
	}
	
	// try and avoid spurious matches
	if (strlen($result->str2) < 50)
	{
		$result->p = 0;
	}
	

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
