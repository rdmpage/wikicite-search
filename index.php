<?php

require_once (dirname(__FILE__) . '/search.php');
require_once (dirname(__FILE__) . '/utils.php');



$q = '';

if (isset($_GET['q']))
{
	$q = $_GET['q'];
}

$result = null;

if ($q != '')
{
	$result = do_search($q);
}


?>

<html>
<head>
	<head>
		<meta charset="utf-8" /> 
		
		<title>
			WikiCite Search
		</title>		
		
		<!--Import Google Icon Font-->
		<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
	
		  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.js"></script>
		  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.css">
		  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.js"></script>
	
	   <!--Let browser know website is optimized for mobile-->
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
	
		<script src="https://cdn.jsdelivr.net/npm/ejs@2.6.1/ejs.min.js" integrity="sha256-ZS2YSpipWLkQ1/no+uTJmGexwpda/op53QxO/UBJw4I=" crossorigin="anonymous"></script>	

		<script type="text/javascript" src="//d1bxh8uas1mnw7.cloudfront.net/assets/embed.js"></script>
		
		<script src="js/citation-0.4.0-9.js" type="text/javascript"></script>
		
		<script>
			const Cite = require('citation-js')
		</script>
		
		
		<style>
	/* body and main styles to give us a fixed footer, see https://materializecss.com/footer.html */	
body {
    display: flex;
    min-height: 100vh;
    flex-direction: column;
  }
  
    main {
    flex: 1 0 auto;
  }		
			
	.btn-small {
		margin-right:0.5em;
	}
		
		</style>
		
		<script>
		
			function show_cite(json) {
			
				var csl = decodeURIComponent(json);
					
				var data = new Cite(csl);

				var html = `<h5>Cite</h5>
					<table>
						<tr>
							<td style="vertical-align:top;font-weight:bold;">APA</td>
							<td>`;
							
				html +=  data.format('bibliography', {format: 'html', template: 'apa', lang: 'en' });
				html +=  	`</td>
						</tr>
						<tr>
							<td style="vertical-align:top;font-weight:bold;">BibTeX</td>
							<td>
								<div style="font-family:monospace;white-space:pre;">`;
								
				html +=  data.format('bibtex');
								
				html += `</div>
							</td>
						</tr>
						<tr>
							<td style="vertical-align:top;font-weight:bold;">RIS</td>
							<td>
								<div style="font-family:monospace;white-space:pre;">`;
								
				html +=  data.format('ris');

				html += `</div>
							</td>
						</tr>
					</table>										
					`;
			
				document.getElementById('modal-content').innerHTML = html;
				$('#modal').modal('open');
			}		
		
		</script>
		
		<script type="text/javascript">
			window.onload=function(){
			  
					$(document).ready(function() {
					  $('#modal').modal(); 	
					  $('.collapsible').collapsible();				 
					});
					
					
			   }
		</script>
		
		
				
</head>
<body>
	<header></header>
		<main>
			<div class="container">
	<!-- search box -->
				<div class="row">
					<form action="." class="col s12">					
						<div class="input-field col s12">
						<i class="material-icons prefix">
							search
						</i>
						<input style="font-size:2em;" type="text" id="q" name="q" placeholder="Search" value="<?php echo $q; ?>"> 
						</div>
					</form>
				</div>
	<!-- Modal popup -->
				<div id="modal" class="modal" style="z-index: 1003; display: none; opacity: 0; transform: scaleX(0.7); top: 4%;">
					<div class="modal-content">
						<div id="modal-content">
							Content
						</div>
					</div>
					<div class="modal-footer">
						<a class=" modal-action modal-close btn-flat">
							<i class="material-icons left">
								clear
							</i>
							Close
						</a>
					</div>
				</div>
				<div id="results">
					
<?php
$locale = 'en';

if ($result)
{
	$counter = 0;

	foreach ($result->{'@graph'}[0]->dataFeedElement as $item)
	{
		echo '<div class="row" style="margin-bottom:3em;">';
		
		echo '<div class="col s12 m2 hide-on-small-only" style="text-align:center;">';
		
		if (isset($item->contentUrl))
		{
			echo '<a href="' . $item->contentUrl[0] . '" target="_new">';
			
			if (isset($item->thumbnailUrl))
			{
				echo '<img class="z-depth-2" src="https://aipbvczbup.cloudimg.io/s/height/100/' . $item->thumbnailUrl . '" height="100" style="box-shadow:0 1px 3px rgba(0,0,0,0.5)">';					
			}
			else
			{
				echo '<img style="opacity:0.5" src="images/1126709.png" height="100">';						
			}
			echo '</a>';
		}

		echo '</div>';
		
		echo '<div class="col s12 m10">';

		// name
		$name = '';
		
		if (isset($item->name->{$locale}))
		{
			$name .= $item->name->{$locale};
		}
		else
		{
			// pick one
			if (isset($item->name))
			{
				$values = get_object_vars($item->name);
				reset($values);
				$first_key = key($values);
		
				$name .= $item->name->$first_key;
			}

					
		}
		
		echo '<div style="font-size:1.5em;font-weight:bold;">'; 
		echo strip_tags($name);
		echo '</div>';
		
		// highlights
		if (isset($item->description))
		{
			echo '<div>';
			echo force_balance_tags ($item->description);
			echo '</div>';
		}
		
		
		// citation
		if (isset($item->formattedCitation))
		{
			echo '<blockquote>' . $item->formattedCitation . '</blockquote>';
		}
		
		// ID
		echo '<div>';
		echo 'WIKIDATA: <a href="https://www.wikidata.org/wiki/' . $item->{'@id'} . '" target="_new">' . $item->{'@id'} . '</a>';
		echo '</div>';

		
		
		//actions
		echo '<div class="section" >';
		
		echo '<a class="btn-small" onclick="show_cite(\'' .  rawurlencode($item->csl) . '\')"><i class="material-icons">format_quote</i></a>';					
		
		if (isset($item->doi))
		{
			echo '<a class="btn-small" href="https://doi.org/' . $item->doi .'" target="_new">DOI:' . $item->doi . '</a>';
		}	
	
		if (isset($item->handle))
		{
			echo '<a class="btn-small" href="https://hdl.handle.net/' . $item->handle .'" target="_new">HDL:' . $item->handle . '</a>';
		}	

		if (isset($item->jstor))
		{
			echo '<a class="btn-small" href="https://www.jstor.org/stable/' . $item->jstor .'" target="_new">JSTOR:' . $item->jstor . '</a>';
		}	
		
		if (isset($item->contentUrl))
		{
			echo '<a class="btn-small" href="pdfproxy.php?url=' . urlencode($item->contentUrl[0]) . '" target="_new"><i class="material-icons">file_download</i></a>';
		}		
		
		echo '</div>';
		
		
		echo '</div>';
		
		echo '</div>';


	}
}
else
{
?>

<div>
<h1>Wikicite Search: a bibliographic search engine for Wikidata</h1>

<p>Wikicite Search is a search engine for scholarly articles and books in <a href="https://www.wikidata.org">Wikidata</a>.
At present it's focus is on the taxonomic literature, that is, papers that describe new species. 
It only has access to a small subset of literature added by the <a href="http://wikicite.org">WikiCite</a>
project, but this will grow over time. The initial goal is to provide tools to <a href="?q=Boomsma">search</a>
, <a href="./match.html">match</a>, and display articles in a variety of formats such as <a href="./api.php?id=Q96108337">CSL-JSON</a>
and <a href="./api.php?id=Q96108337&format=jsonld">JSON-LD</a>. Where possible access to PDFs for articles is provided by
the <a href="https://archive.org">Internet Archive</a> or the <a href="https://web.archive.org">Wayback Machine</a>.
</p>
</div>


<?php
}
?>				
					
				</div>
			</div>
		</main>
		
		<footer >
			<div class="container">
            	<div class="row">
            	<div class="divider"></div>
            		<a href=".">WikiCite Search</a> is a project by <a href="https://twitter.com/rdmpage">Rod Page</a> 
            		to provide a bibliographic search engine for <a href="https://www.wikidata.org/">Wikidata</a>. 
            		See also the the <a href="./match.html">Match references</a> reconciliation service.
            		
            	</div>
            </div>
		</footer>
		
	</body>
</html>

