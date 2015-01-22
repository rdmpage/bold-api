<?php

// Client for BOLD tree service

require_once(dirname(dirname(__FILE__)) . '/service/lib.php');

$base_url = 'http://localhost/~rpage/bold-api/service';

$process_id = 'ANGBF9045-12';


// Step 1: get similar sequences to this barcode
$url = $base_url . '/api.php?process_id=' . $process_id;

echo $url . "\n";

$json = get($url);

$response = json_decode($json);

print_r($response);

if ($response->status = 200)
{
	// Step 2 build tree
	$url = $base_url . '/api.php?process_id=' . $process_id . '&tree';
	$json = get($url);
	$tree_response = json_decode($json);
	
	print_r($tree_response);
	
	if ($tree_response->status = 200)
	{
		$nexus = $tree_response->tree;
				
		$nexus .= 'begin characters;
	dimensions nchar=2;
	format datatype = geographic;
	charstatelabels 
		1 latitude,
		2 longitude
		; 
matrix
';

		$bins = array();
		foreach ($response->hits as $hit)
		{
			$id = $hit->id;
			$id = str_replace('-', '_', $id);
			
			if (isset($hit->bin_uri))
			{
				$bins[$id] = $hit->bin_uri;
			}
			else
			{
				$bins[$id] = $id;
			}
			
			if (isset($hit->latitude))
			{
				$nexus .= $id . ' ' . $hit->latitude . ' ' . $hit->longitude . "\n";
			}
		}
		$nexus .= ";
end;";

		$nexus .= "\nbegin notes;
	alttaxnames tax_id = \n";
	
		foreach ($bins as $id => $bin)
		{
			$nexus .= "\t[" . $id . "] " . $bin . "\n";
		}
		$nexus .= ";\nend;\n";
	
	echo $nexus;		
		
	}


}

?>