<?php

error_reporting(E_ALL);

require_once('vendor/autoload.php');

use Seboettg\CiteProc\StyleSheet;
use Seboettg\CiteProc\CiteProc;

require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/csl_to_rdf.php');
require_once (dirname(__FILE__) . '/elastic.php');
require_once (dirname(__FILE__) . '/search.php');


//----------------------------------------------------------------------------------------
function default_display()
{
	echo "hi";
}

//----------------------------------------------------------------------------------------
// One record
function display_one ($id, $format= '', $callback = '')
{
	global $elastic;

	$mime = "text/plain";
	$output = null;
	
	$style = 'apa';

	if (preg_match('/^Q\d+/', $id))
	{
		$json = $elastic->send("GET", "_doc/" . $id);
		
		$obj = json_decode($json);
		
		if ($obj->found)
		{
			$csl = $obj->_source->search_display->csl;

			switch ($format)
			{
				case 'ntriples':
					$output = csl_to_rdf($csl, 'ntriples');
					break;
				
				case 'jsonld':			
					$output = csl_to_rdf($csl, 'jsonld');				
					$mime = "application/json";	
					break;
					
				case 'text':
				case 'html':				
					$style_sheet = StyleSheet::loadStyleSheet($style);
					$citeProc = new CiteProc($style_sheet);
					$html = $citeProc->render(array($csl), "bibliography");
				
					if ($format == 'html')
					{
						$output = $html;
						$mime = "text/html";				
					}
					else
					{				
						$text = strip_tags($html);
						$text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

						$output = $text;
						$mime = "text/plain";
					}
					break;
					
			
				case 'citeproc':
				case 'csl':
				default:
					$output = json_encode($csl , JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);				
					$mime = "application/json";			
					break;

			}
		}
	}
	
	header("Content-type: " . $mime);
		
	echo $output;

}

//----------------------------------------------------------------------------------------
function display_search($q, $callback = '')
{
	$obj = do_search($q);

	header("Content-type: text/plain");
	
	if ($callback != '')
	{
		echo $callback . '(';
	}
	
	echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	
	if ($callback != '')
	{
		echo ')';
	}

}

//----------------------------------------------------------------------------------------
function display_locate_page($container, $volume, $page, $callback = '')
{
	$obj = locate_page($container, $volume, $page);

	header("Content-type: text/plain");
	
	if ($callback != '')
	{
		echo $callback . '(';
	}
	
	echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	
	if ($callback != '')
	{
		echo ')';
	}

}


//----------------------------------------------------------------------------------------
// Count number of records
function display_count ($callback = '')
{
	global $elastic;

	$mime = "text/plain";
	$output = null;

	$json = $elastic->send("GET", "_count");
	
	$obj = json_decode($json);

	header("Content-type: " . $mime);
	
	if ($callback != '')
	{
		echo $callback . '(';
	}

	echo $json;
	
	if ($callback != '')
	{
		echo ')';
	}	

}

//----------------------------------------------------------------------------------------
function main()
{

	$callback = '';
	$handled = false;
	
	
	// If no query parameters 
	if (count($_GET) == 0)
	{
		default_display();
		exit(0);
	}
	
	if (isset($_GET['callback']))
	{	
		$callback = $_GET['callback'];
	}
	
	// get one item with optional formatting
	if (!$handled)
	{
		if (isset($_GET['id']))
		{	
			$id = $_GET['id'];
			
			$format = '';
			
			if (isset($_GET['format']))
			{
				$format = $_GET['format'];
			}			
			
			if (!$handled)
			{
				display_one($id, $format, $callback);
				$handled = true;
			}
			
		}
	}
	
	// get number of items in database
	if (!$handled)
	{
		if (isset($_GET['count']))
		{	
			display_count($callback);			
			$handled = true;
		}
			
	}		
	
	
	// locate item from [container, volume, page] tuple
	if (!$handled)
	{
		if (isset($_GET['container']) && isset($_GET['volume']) && isset($_GET['page']))
		{	
			$container 	= $_GET['container'];
			$volume		= $_GET['volume'];
			$page   	= $_GET['page'];

			display_locate_page($container, $volume, $page, $callback);
			
			$handled = true;
		}
			
	}		

		
	
	// do text search
	if (!$handled)
	{
		if (isset($_GET['q']))
		{	
			$q = $_GET['q'];
			display_search($q, $callback);
			
			$handled = true;
		}
			
	}		
	
	if (!$handled)
	{
		default_display();
	}	

}


main();


?>



