<?php

// Generate schema.org-style bibliographic record from CSL

error_reporting(E_ALL);

require_once('vendor/autoload.php');

function csl_to_rdf($csl, $format_string = 'ntriples')
{
	$triples = array();

	// @id
	$subject_id = 'http://www.wikidata.org/entity/' . $csl->id;

	// @type
	if (isset($csl->type))
	{
		$type = '';
	
		switch ($csl->type)
		{
			case 'book':
				$type = 'http://schema.org/Book';
				break;
			
			case 'chapter':
				$type = 'http://schema.org/Chapter';
				break;
			
			case 'article-journal':
			default:
				$type = 'http://schema.org/ScholarlyArticle';
				break;
	
		}

		$triples[] =  '<' . $subject_id . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <' . $type .'>  . ';		
	}
	
	// name
	
	$name_done = false;
	
	if (isset($csl->multi))
	{
		if (isset($csl->multi->_key->title))
		{
			foreach ($csl->multi->_key->title as $language => $value)
			{
				$triples[] =  '<' . $subject_id . '> <http://schema.org/name> "' . addcslashes($value, '"') . '"@' . $language . '  . ';		
			
			}
		
			$name_done = true;
			
		}
	}
	
	if (!$name_done)
	{
		if (isset($csl->title))
		{
				$triples[] =  '<' . $subject_id . '> <http://schema.org/name> "' . addcslashes($csl->title, '"') . '" . ';				
		}
	}

	// date
	if (isset($csl->issued))
	{
		$date = '';
		$d = $csl->issued->{'date-parts'}[0];
		
		// sanity check
		if (is_numeric($d[0]))
		{
			if ( count($d) > 0 ) $year = $d[0] ;
			if ( count($d) > 1 ) $month = preg_replace ( '/^0+(..)$/' , '$1' , '00'.$d[1] ) ;
			if ( count($d) > 2 ) $day = preg_replace ( '/^0+(..)$/' , '$1' , '00'.$d[2] ) ;
			if ( isset($month) and isset($day) ) $date = "$year-$month-$day";
			else if ( isset($month) ) $date = "$year-$month-00";
			else if ( isset($year) ) $date = "$year-00-00";
			
			$triples[] =  '<' . $subject_id . '> <http://schema.org/datePublished> "' . addcslashes($date, '"') .'"  . ';		
		}				
				
	}
	
	// container
	if (isset($csl->{'container-title'}))
	{
		$container_id = $subject_id . '#container';
		
		$triples[] =  '<' . $subject_id . '> <http://schema.org/isPartOf> <' . $container_id .'>  . ';	
		
		$name_done = false;
	
		if (isset($csl->multi))
		{
			if (isset($csl->multi->_key->{'container-title'}))
			{
				foreach ($csl->multi->_key->{'container-title'} as $language => $value)
				{
					$triples[] =  '<' . $container_id . '> <http://schema.org/name> "' . addcslashes($value, '"') . '"@' . $language . '  . ';					
				}		
				$name_done = true;			
			}
		}
	
		if (!$name_done)
		{
			$triples[] =  '<' . $container_id . '> <http://schema.org/name> "' . addcslashes($csl->title, '"') . '" . ';				
		}
	
		if (isset($csl->ISSN))
		{
			foreach ($csl->ISSN as $issn)
			{
				$triples[] =  '<' . $container_id . '> <http://schema.org/issn> "' . $issn .'"  . ';					
			}
			
			$triples[] =  '<' . $container_id . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/Periodical>  . ';		
		}
		else
		{
			switch ($type)
			{
				case 'http://schema.org/ScholarlyArticle':
					$triples[] =  '<' . $container_id . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/Periodical>  . ';		
					break;
					
				case 'http://schema.org/Chapter':
					$triples[] =  '<' . $container_id . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/Book>  . ';		
					break;
					
				default:
					break;
					
			}		
		}
	}
	
	// these could really be "partOf" relationships
	if (isset($csl->volume))
	{
		$triples[] =  '<' . $subject_id . '> <http://schema.org/volumeNumber> "' . addcslashes($csl->volume, '"') .'"  . ';				
	}
	if (isset($csl->issue))
	{
		$triples[] =  '<' . $subject_id . '> <http://schema.org/issueNumber> "' . addcslashes($csl->volume, '"') .'"  . ';				
	}
	
	if (isset($csl->page))
	{
		$triples[] =  '<' . $subject_id . '> <http://schema.org/pagination> "' . addcslashes($csl->page, '"') .'"  . ';				
	}	
	
	// identifiers
	if (isset($csl->DOI))
	{
		$identifier_id = $subject_id . '#doi';
	
		$triples[] = '<' . $subject_id . '> <http://schema.org/identifier> <' . $identifier_id .'>  . ';	
		$triples[] = '<' . $identifier_id . '> <http://schema.org/propertyID> "doi"  . ';	
		$triples[] = '<' . $identifier_id . '> <http://schema.org/value> ' . '"' . addcslashes(strtolower($csl->DOI), '"') . '"' . '.';
		
		$triples[] =  '<' . $subject_id . '> <http://schema.org/sameAs> <https://doi.org/' . strtolower($csl->DOI) .'>  . ';	
	}
	
	if (isset($csl->JSTOR))
	{
		$identifier_id = $subject_id . '#jstor';
	
		$triples[] = '<' . $subject_id . '> <http://schema.org/identifier> <' . $identifier_id .'>  . ';	
		$triples[] = '<' . $identifier_id . '> <http://schema.org/propertyID> "jstor"  . ';	
		$triples[] = '<' . $identifier_id . '> <http://schema.org/value> ' . '"' . addcslashes(strtolower($csl->JSTOR), '"') . '"' . '.';
		
		$triples[] =  '<' . $subject_id . '> <http://schema.org/sameAs> <https://www.jstor.org/stable/' . strtolower($csl->JSTOR) .'>  . ';	
	}
	
	
	// full text
/*
  "link": [
    {
      "URL": "https://archive.org/download/acta-zoologica-lilloana-35-002/acta-zoologica-lilloana-35-002.pdf",
      "content-type": "application/pdf",
      "thumbnailUrl": "https://archive.org/download/acta-zoologica-lilloana-35-002/page/cover_thumb.jpg"
    }
  ],
*/

	if (isset($csl->link))	
	{
		$count = 1;
		foreach ($csl->link as $link)
		{
			if (isset($link->{'content-type'}) && ($link->{'content-type'} == 'application/pdf'))
			{
				$encoding_id = $subject_id . '#encoding' . $count;
				
				$triples[] = '<' . $subject_id . '>  <http://schema.org/encoding> <' . $encoding_id . '> .';

				$triples[] = '<' . $encoding_id . '>  <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/MediaObject>  .';
				$triples[] = '<' . $encoding_id . '>  <http://schema.org/fileFormat> "application/pdf" .';
				$triples[] = '<' . $encoding_id . '>  <http://schema.org/contentUrl> <' . $link->URL  . '>  .';

				if (isset($link->thumbnailUrl))
				{
					$triples[] = '<' . $encoding_id . '>  <http://schema.org/thumbnailUrl> <' . $link->thumbnailUrl . '>  .';			
				}
								
				$count++;
			}
		}
	
	}
	
	// authors
	if (isset($csl->author))
	{
		$count = 1;
		
		foreach ($csl->author as $author)
		{
			$author_id = $subject_id . '#author' . $count;
			
			if (isset($author->WIKIDATA))
			{
				$author_id = 'http://www.wikidata.org/entity/' . $author->WIKIDATA;
			}
			
			$triples[] = '<' . $subject_id . '> <http://schema.org/author> <' . $author_id .'>  . ';	
						
			// name
			if (isset($author->literal))
			{
				$triples[] = '<' . $author_id . '> <http://schema.org/name> ' . '"' . addcslashes($author->literal, '"') . '"' . '.';			
			}
			if (isset($author->family))
			{
				$triples[] = '<' . $author_id . '> <http://schema.org/familyName> ' . '"' . addcslashes($author->family, '"') . '"' . '.';			
			}
			if (isset($author->given))
			{
				$triples[] = '<' . $author_id . '> <http://schema.org/givenName> ' . '"' . addcslashes($author->given, '"') . '"' . '.';			
			}
			
			// identifiers
			if (isset($author->ORCID))
			{
				$triples[] = '<' . $author_id . '> <http://schema.org/sameAs> ' . '<' . $author->ORCID . '>  .';			
			}
			if (isset($author->RESEARCHGATE))
			{
				$triples[] = '<' . $author_id . '> <http://schema.org/sameAs> ' . '<' . $author->RESEARCHGATE . '>  .';			
			}
			
			// image
			if (isset($author->thumbnailUrl))
			{
				$triples[] = '<' . $author_id . '> <http://schema.org/thumbnailUrl> ' . '<' . $author->thumbnailUrl . '>  .';			
			}
			
			
			$count++;
		
		}	
	}

	$graph = new \EasyRdf\Graph();
	$graph->parse(join("\n", $triples));
	
    $format = \EasyRdf\Format::getFormat($format_string);

    $serialiserClass  = $format->getSerialiserClass();
    $serialiser = new $serialiserClass();
    
    // if we are using GraphViz then we add some parameters 
    // to make the images nicer
    if(preg_match('/GraphViz/', $serialiserClass)){
        $serialiser->setAttribute('rankdir', 'LR');
    }
    
    $options = array();
    
    if ($format_string == 'jsonld')
    {
		$context = new stdclass;
		$context->{'@vocab'} = 'http://schema.org/';
	
		// publication
		$author = new stdclass;
		$author->{'@id'} = "author";
		$author->{'@container'} = "@set"; 

		$context->{'author'} = $author;
		
		// ISSN is always an array
		$issn = new stdclass;
		$issn->{'@id'} = "issn";
		$issn->{'@container'} = "@set";
	
		$context->{'issn'} = $issn;
	
		// encoding is an array
		$encoding = new stdclass;
		$encoding->{'@id'} = "encoding";
		$encoding->{'@container'} = "@set";
	
		$context->{'encoding'} = $encoding;
		
		// contentUrl
		$contentUrl = new stdclass;
		$contentUrl->{'@id'} = "contentUrl";
		$contentUrl->{'@type'} = "@id";
	
		$context->{'contentUrl'} = $contentUrl;	

		// thumbnailUrl
		$thumbnailUrl = new stdclass;
		$thumbnailUrl->{'@id'} = "thumbnailUrl";
		$thumbnailUrl->{'@type'} = "@id";
	
		$context->{'thumbnailUrl'} = $thumbnailUrl;	
	
		// sameAs
		$sameas = new stdclass;
		$sameas->{'@id'} = "sameAs";
		$sameas->{'@type'} = "@id";
		$sameas->{'@container'} = "@set";
	
		$context->{'sameAs'} = $sameas;
	
	
		// Frame document
		$frame = (object)array(
			'@context' => $context,
			'@type' => $type
		);	
	
		// Get simple JSON-LD
		$options = array();
		$options['context'] = $context;
		$options['compact'] = true;
		$options['frame']= $frame;	
	
	}
	
    $data = $serialiser->serialise($graph, $format_string, $options);
    
    return $data;
	
}

if (0)
{

	$json = '{
	  "id": "Q99669194",
	  "issue": "2",
	  "DOI": "10.2307/3223754",
	  "type": "article-journal",
	  "page": "177",
	  "multi": {
		"_key": {
		  "title": {
			"en": "Spiders of the New Genus Theridiotis (Araneae: Theridiidae)"
		  },
		  "container-title": {
			"en": "Transactions of the American Microscopical Society"
		  }
		}
	  },
	  "title": "Spiders of the New Genus Theridiotis (Araneae: Theridiidae)",
	  "volume": "73",
	  "ISSN": [
		"0003-0023",
		"2325-5145"
	  ],
	  "container-title": "Transactions of the American Microscopical Society",
	  "issued": {
		"date-parts": [
		  [
			1954,
			4
		  ]
		]
	  },
	  "author": [
		{
		  "literal": "Herbert W. Levi",
		  "family": "Levi",
		  "given": "Herbert W."
		}
	  ]
	}';

	$json = '{
	  "id": "Q101150098",
	  "DOI": "10.18942/bunruichiri.kj00001078622",
	  "multi": {
		"_key": {
		  "title": {
			"ja": "植物分類雑記19",
			"en": "Taxonomical Notes 19 (Continued from 36:182 of this Acta)"
		  },
		  "container-title": {
			"en": "Acta phytotaxonomica et geobotanica",
			"ja": "植物分類, 地理",
			"mul": "Shokubutsu bunrui, chiri"
		  }
		}
	  },
	  "title": "植物分類雑記19",
	  "issued": {
		"date-parts": [
		  [
			1988
		  ]
		]
	  },
	  "volume": "39",
	  "page": "147-149",
	  "container-title": "Acta phytotaxonomica et geobotanica",
	  "ISSN": [
		"0001-6799",
		"2189-7050"
	  ],
	  "type": "article-journal",
	  "author": [
		{
		  "literal": "源 村田",
		  "family": "村田",
		  "given": "源"
		}
	  ]
	}';

	$json = '{
	  "id": "Q107429332",
	  "multi": {
		"_key": {
		  "title": {
			"es": "Primeras páginas, portadilla y contraportada (Acta Zoológica Lilloana 35 (2))"
		  },
		  "container-title": {
			"es": "Acta Zoológica Lilloana"
		  }
		}
	  },
	  "title": "Primeras páginas, portadilla y contraportada (Acta Zoológica Lilloana 35 (2))",
	  "type": "article-journal",
	  "issued": {
		"date-parts": [
		  [
			1979
		  ]
		]
	  },
	  "ISSN": [
		"0065-1729",
		"1852-6098"
	  ],
	  "container-title": "Acta Zoológica Lilloana",
	  "volume": "35",
	  "issue": "2",
	  "link": [
		{
		  "URL": "https://archive.org/download/acta-zoologica-lilloana-35-002/acta-zoologica-lilloana-35-002.pdf",
		  "content-type": "application/pdf",
		  "thumbnailUrl": "https://archive.org/download/acta-zoologica-lilloana-35-002/page/cover_thumb.jpg"
		}
	  ],
	  "author": [
		{
		  "literal": "Del Editor",
		  "family": "Editor",
		  "given": "Del"
		}
	  ]
	}';
	

	$csl = json_decode($json);

	echo csl_to_rdf($csl, 'jsonld');
}


?>
