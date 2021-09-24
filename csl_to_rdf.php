<?php

// Generate schema.org-style bibliographic record from CSL

error_reporting(E_ALL);


require_once('vendor/autoload.php');

use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;

$use_bnodes = true;
$use_bnodes = false;



//----------------------------------------------------------------------------------------
// Eventually it becomes clear we can't use b-nodes without causing triples tores to replicate
// lots of triples, so create arbitrary URIs using the graph URI as the base.
function create_bnode($graph, $type = "")
{
	global $use_bnodes;
	
	$bnode = null;
	
	if ($use_bnodes)
	{
		if ($type != "")
		{
			$bnode = $graph->newBNode($type);
		}
		else
		{
			$bnode = $graph->newBNode();
		}		
	}
	else
	{
		$bytes = random_bytes(5);
		$node_id = bin2hex($bytes);
		
		// if we use fragment identifiers the rdf:list trick for JSON-LD fails :(
		if (1)
		{
			$uri = '_:' . $node_id;
		}
		else
		{
			$uri = $graph->getUri() . '#' . $node_id;
		}

		if ($type != "")
		{
			$bnode = $graph->resource($uri, $type);
		}
		else
		{
			$bnode = $graph->resource($uri);
		}	
	
	}

	return $bnode;
}

//----------------------------------------------------------------------------------------
// Make a URI play nice with triple store
function nice_uri($uri)
{
	$uri = str_replace('[', urlencode('['), $uri);
	$uri = str_replace(']', urlencode(']'), $uri);
	$uri = str_replace('<', urlencode('<'), $uri);
	$uri = str_replace('>', urlencode('>'), $uri);

	return $uri;
}



//----------------------------------------------------------------------------------------
// From easyrdf/lib/parser/ntriples
function unescapeString($str)
    {
        if (strpos($str, '\\') === false) {
            return $str;
        }

        $mappings = array(
            't' => chr(0x09),
            'b' => chr(0x08),
            'n' => chr(0x0A),
            'r' => chr(0x0D),
            'f' => chr(0x0C),
            '\"' => chr(0x22),
            '\'' => chr(0x27)
        );
        foreach ($mappings as $in => $out) {
            $str = preg_replace('/\x5c([' . $in . '])/', $out, $str);
        }

        if (stripos($str, '\u') === false) {
            return $str;
        }

        while (preg_match('/\\\(U)([0-9A-F]{8})/', $str, $matches) ||
               preg_match('/\\\(u)([0-9A-F]{4})/', $str, $matches)) {
            $no = hexdec($matches[2]);
            if ($no < 128) {                // 0x80
                $char = chr($no);
            } elseif ($no < 2048) {         // 0x800
                $char = chr(($no >> 6) + 192) .
                        chr(($no & 63) + 128);
            } elseif ($no < 65536) {        // 0x10000
                $char = chr(($no >> 12) + 224) .
                        chr((($no >> 6) & 63) + 128) .
                        chr(($no & 63) + 128);
            } elseif ($no < 2097152) {      // 0x200000
                $char = chr(($no >> 18) + 240) .
                        chr((($no >> 12) & 63) + 128) .
                        chr((($no >> 6) & 63) + 128) .
                        chr(($no & 63) + 128);
            } else {
                # FIXME: throw an exception instead?
                $char = '';
            }
            $str = str_replace('\\' . $matches[1] . $matches[2], $char, $str);
        }
        return $str;
    }




//----------------------------------------------------------------------------------------
// Generic CSL to RDF, flexible as possible

function csl_to_rdf($csl, $format_string = 'ntriples')
{	
	$graph = new \EasyRdf\Graph();
	
	$type = 'schema:CreativeWork';
	
	if (isset($csl->type))
	{
		switch ($csl->type)
		{
			case 'book':
				$type = 'schema:Book';
				break;
			
			case 'chapter':
				$type = 'schema:Chapter';
				break;
			
			case 'article-journal':
			default:
				$type = 'schema:ScholarlyArticle';
				break;	
		}
	}	

	$work = $graph->resource('http://www.wikidata.org/entity/' . $csl->id, $type);	
	
	$name_done = false;
	
	if (isset($csl->multi))
	{
		if (isset($csl->multi->_key->title))
		{
			foreach ($csl->multi->_key->title as $language => $value)
			{
				$work->addLiteral('schema:name', strip_tags($value), $language);
			}		
			$name_done = true;			
		}
	}
	
	if (!$name_done)
	{
		if (isset($csl->title))
		{
			$work->add('schema:name', strip_tags($csl->title));
		}
	}

	// simple literals ---------------------------------------------------
	if (isset($csl->volume))
	{
		$work->add('schema:volumeNumber', $csl->volume);
	}
	if (isset($csl->issue))
	{
		$work->add('schema:issueNumber', $csl->issue);
	}
	if (isset($csl->page))
	{
		$work->add('schema:pagination', $csl->page);
	}

	// date --------------------------------------------------------------
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

			if (0)
			{
				// proper RDF
				$work->add('schema:datePublished', new \EasyRdf\Literal\Date($date));
			}
			else
			{
				// simple literal
				$work->add('schema:datePublished', $date);					
			}
		}				
	}		

	// authors (how do we handle order?) ---------------------------------
	if (isset($csl->author))
	{

		if (1)
		{
			$authors_in_order = array();
		
			foreach ($csl->author as $creator)
			{
				$creator_id = '';
				
				if ($creator_id == '')
				{
					if (isset($creator->WIKIDATA))
					{
						$creator_id = 'http://www.wikidata.org/entity/' . $creator->WIKIDATA;
					}
				}

				if ($creator_id == '')
				{
					if (isset($creator->ORCID))
					{
						$creator_id = $creator->ORCID;
					}
				}
				
				
				// If we have a URI then create a node, otherwise it's a bNode
				if ($creator_id == '')
				{
					$author = create_bnode($graph, 'schema:Person');
				}
				else
				{
					$author = $graph->resource($creator_id, 'schema:Person');	
				}
				
				if (isset($creator->ORCID) && ($creator_id != $creator->ORCID))
				{
					$author->addResource('schema:sameAs', $creator->ORCID);
				}

				if (isset($creator->literal))
				{
					$author->add('schema:name', $creator->literal);
				}
				if (isset($creator->given))
				{
					$author->add('schema:givenName', $creator->given);
				}
				if (isset($creator->family))
				{
					$author->add('schema:familyName', $creator->family);
				}


				$authors_in_order[] = $author;			
			}	
		
			$num_authors = count($authors_in_order);
			
			if ($num_authors > 0)
			{		
				$list = array();
		
				for ($k = 0; $k < $num_authors; $k++)
				{
					$list[$k] = create_bnode($graph, "");
			
					if ($k == 0)
					{
						$work->add('schema:author', $list[$k]);
					}
					else
					{
						$list[$k - 1]->add('rdf:rest', $list[$k]);
					}	
					$list[$k]->add('rdf:first', $authors_in_order[$k]);							
				}
				$list[$num_authors - 1]->addResource('rdf:rest', 'rdf:nil');
			}
		
		}
	
		
	}

	// container
	
	if (isset($csl->{'container-title'}))
	{
		$container = create_bnode($graph, "schema:Periodical");

		$name_done = false;
	
		if (isset($csl->multi))
		{
			if (isset($csl->multi->_key->{'container-title'}))
			{
				foreach ($csl->multi->_key->{'container-title'} as $language => $value)
				{
					$container->addLiteral('schema:name', strip_tags($value), $language);
				}		
				$name_done = true;			
			}
		}
	
		if (!$name_done)
		{
			if (isset($csl->{'container-title'}))
			{
				$container->add('schema:name', $csl->{'container-title'});
			}
		}

		if (isset($csl->ISSN))
		{
			foreach ($csl->ISSN as $issn)
			{
				$container->add('schema:issn', $issn);
			}
		}					
		$work->add('schema:isPartOf', $container);
	}

	// identifiers sameAs/seeAlso

	// BHL is seeAlso
	if (isset($csl->BHL))
	{
		$work->addResource('schema:seeAlso', 'https://www.biodiversitylibrary.org/page/' . $csl->BHL);
	}
	
	// DOI is sameAs if not the URI for the work
	if (isset($csl->DOI))
	{		
		if (!preg_match('/doi.org/', $work->getUri()))
		{
			$work->addResource('schema:sameAs', 'https://doi.org/' . strtolower($csl->DOI));
		}
	}	

	// JSTOR is sameAs
	if (isset($csl->JSTOR))
	{
		$work->addResource('schema:sameAs', 'https://www.jstor.org/stable/' . $csl->JSTOR);
	}

	// Identifiers as property-value pairs so that we can query by identifier value
	if (isset($csl->DOI))
	{
		// ORCID-style
		$identifier = create_bnode($graph, "schema:PropertyValue");		
		$identifier->add('schema:propertyID', 'doi');
		$identifier->add('schema:value', strtolower($csl->DOI));
		$work->add('schema:identifier', $identifier);
	}

	// URL(s)?
	if (isset($csl->URL))
	{
		$urls = array();
		if (!is_array($csl->URL))
		{
			$urls = array($csl->URL);
		}
		else
		{
			$urls = $csl->URL;
		}

		foreach ($urls as $url)
		{
			$work->addResource('schema:url', $url);
		}
	}

	// PDF?
	if (isset($csl->link))
	{
		$links = array(); // filter duplicates
	
		foreach ($csl->link as $link)
		{
			if (isset($link->{'content-type'}) && ($link->{'content-type'} == 'application/pdf'))
			{
				if (!in_array($link->URL, $links))
				{
					$links[] = $link->URL;
			
					$encoding = create_bnode($graph, "schema:MediaObject");
					$encoding->add('schema:fileFormat', $link->{'content-type'});
					$encoding->add('schema:contentUrl', $link->URL);
				
					if (isset($link->thumbnailUrl))
					{
						$encoding->add('schema:thumbnailUrl', $link->thumbnailUrl);
					}
				
					$work->add('schema:encoding', $encoding);
				}						
			}
		}
	}
	
	// export....
	// Triples 
	$format = \EasyRdf\Format::getFormat('ntriples');

	$serialiserClass  = $format->getSerialiserClass();
	$serialiser = new $serialiserClass();

	$triples = $serialiser->serialise($graph, 'ntriples');

	// Remove JSON-style encoding
	$told = explode("\n", $triples);
	$tnew = array();

	foreach ($told as $s)
	{
		$tnew[] = unescapeString($s);
	}

	$triples = join("\n", $tnew);
	
	//echo $triples . "\n";	
	
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
		
		// author is ordered list
		$author = new stdclass;
		$author->{'@id'} = "author";
		$author->{'@container'} = "@list";
		$context->author = $author;

	
		// Frame document
		$frame = (object)array(
			'@context' => $context,
			'@type' => str_replace('schema:', 'http://schema.org/', $type)
		);	
	
		// Get simple JSON-LD
		$options = array();
		$options['context'] = $context;
		$options['compact'] = true;
		$options['frame']= $frame;	

		// Use same libary as EasyRDF but access directly to output ordered list of authors
		$nquads = new NQuads();
		// And parse them again to a JSON-LD document
		$quads = $nquads->parse($triples);		
		$doc = JsonLD::fromRdf($quads);
		
		$obj = JsonLD::frame($doc, $frame);
		
		$data = json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	
	}
	else
	{
	
    	$data = $serialiser->serialise($graph, $format_string, $options);
    }

	return $data;	
	
	
}




if (0)
{
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
	
$json = '{
  "id": "Q96108337",
  "multi": {
    "_key": {
      "title": {
        "en": "TAXA NOVA LAGERSTROEMIAE E FLORA SINICA",
        "zh": "中国紫薇属新分类群"
      },
      "container-title": {
        "zh-cn": "植物研究",
        "en": "Bulletin of botanical research",
        "mul": "Zhiwu yanjiu"
      }
    }
  },
  "title": "TAXA NOVA LAGERSTROEMIAE E FLORA SINICA",
  "type": "article-journal",
  "issued": {
    "date-parts": [
      [1982]
    ]
  },
  "ISSN": ["1673-5102"],
  "container-title": "植物研究",
  "volume": "2",
  "issue": "1",
  "page": "143-150",
  "link": [{
    "URL": "https://archive.org/download/bulletin-botanical-research-harbin-2-143-150/bulletin-botanical-research-harbin-2-143-150.pdf",
    "content-type": "application/pdf",
    "thumbnailUrl": "https://archive.org/download/bulletin-botanical-research-harbin-2-143-150/page/cover_thumb.jpg"
  }],
  "author": [{
    "literal": "Lee Shu-kang",
    "family": "Shu-kang",
    "given": "Lee"
  }, {
    "literal": "Lau Lan-fang",
    "family": "Lan-fang",
    "given": "Lau"
  }]
}'	;

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
	
	$json = '{"id":"Q22002907","DOI":"10.3897/mycokeys.7.4508","type":"article-journal","multi":{"_key":{"title":{"en":"DNA barcode identification of lichen-forming fungal species in the Rhizoplaca melanophthalma species-complex (Lecanorales, Lecanoraceae), including five new species"},"container-title":{"en":"MycoKeys"}}},"title":"DNA barcode identification of lichen-forming fungal species in the Rhizoplaca melanophthalma species-complex (Lecanorales, Lecanoraceae), including five new species","page":"1-22","volume":"7","issued":{"date-parts":[[2013,5,9]]},"ISSN":["1314-4057","1314-4049"],"container-title":"MycoKeys","author":[{"literal":"Steven Leavitt","family":"Leavitt","given":"Steven"},{"literal":"Fernando Fernández-Mendoza","family":"Fernández-Mendoza","given":"Fernando"},{"WIKIDATA":"Q21337958","literal":"Sergio Pérez-Ortega","ORCID":"https://orcid.org/0000-0002-5411-3698","family":"Pérez-Ortega","given":"Sergio"},{"WIKIDATA":"Q21502730","literal":"Mohammad Sohrabi","family":"Sohrabi","given":"Mohammad"},{"literal":"Pradeep Divakar","family":"Divakar","given":"Pradeep"},{"WIKIDATA":"Q21339029","literal":"Helge Thorsten Lumbsch","ORCID":"https://orcid.org/0000-0003-1512-835X","family":"Lumbsch","given":"Helge Thorsten"},{"literal":"Larry St. Clair","family":"Clair","given":"Larry St."}]}';
	

	$csl = json_decode($json);

	echo csl_to_rdf($csl, 'jsonld');
}


?>
