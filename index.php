<?php

require_once (dirname(__FILE__) . '/search.php');

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
	<style> 
		mark { background-color:transparent; font-weight:bold; } 
		body { font-family:sans-serif;}
		a:link { color:#1a0dab; text-decoration:none; }
		a:visited { color:#660099; text-decoration:none; }
		a:hover { text-decoration:underline; }
	</style>
		<script type="text/javascript" src="//d1bxh8uas1mnw7.cloudfront.net/assets/embed.js"></script>

</head>
<body style="padding:20px;">

<div>
<a href =".">Home</a> <a href="match.html">Match</a>
</div>

<p/>

<div>
<form action=".">
<input style="font-size:1.5em;width:80%" type="text" id="q" name="q" value="<?php echo $q; ?>" placeholder="Search">
<button type="submit" style="font-size:1.5em;">Search</button>
</form>
</div>

<div>

<?php

 $locale = 'en';

 if ($result)
 {
	foreach ($result->{'@graph'}[0]->dataFeedElement as $item)
	{
		echo '<div>';

		/*
		echo '<div style="float:right;padding:10px;">';
		if (isset($item->doi))
		{
			echo '<div data-badge-type="donut" data-doi="' . $item->doi . '" data-hide-no-mentions="true" data-badge-popover="right" class="altmetric-embed"></div>';

		}
		echo '</div>';
		*/
		
		
		echo '<div style="padding:4px;margin-bottom:1em;width:70%;">';
		
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
		// echo '<div style=font-size:0.8em;color:#007600;margin:4px;">' . $item->bibliographicCitation[0] . '</div>';

		
		// highlights
		echo '<div style="font-size:0.8em;color:#222;margin:4px;">';
		echo $item->description;
		echo '</div>';
		
		if (isset($item->doi))
		{
			echo '<div style="padding:4px;display:inline;width:auto;color:white;background-color:blue;font-size:0.8em;margin:4px;">';
			echo $item->doi;
			echo '</div>';
		}
		
		
		// identifiers / pdf
		echo '<div style="font-size:0.8em;margin:4px;">';
		
		if (isset($item->contentUrl))
		{
			echo '<a href="' . $item->contentUrl[0] . '">PDF</a>';
		}
		
		echo '</div>';
	
		echo '</div>';
		
		echo '</div>';
	}
  }
?>


</div>
</body>
</html>
