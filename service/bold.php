<?php

// BOLD API

require_once (dirname(__FILE__) . '/lib.php');

//--------------------------------------------------------------------------------------------------
// Make process id NEXUS-safe
function clean_id($id)
{
	$id = str_replace('-', '_', $id);
	return $id;
}

//--------------------------------------------------------------------------------------------------
function get_process_dir($process_id)
{
	global $config;
	
	$process_dir = $config['temp_dir'] . '/' . $process_id;
	
	if (!file_exists($process_dir))
	{
		$oldumask = umask(0); 
		mkdir($process_dir, 0777);
		umask($oldumask);
	}
	
	return $process_dir;
}

//--------------------------------------------------------------------------------------------------
// get a single BOLD record
function get_record($process_id)
{
	$obj = new stdclass;
	$obj->status = 404;
	$obj->process_id = $process_id;
	
	$url = 'http://www.boldsystems.org/index.php/API_Public/combined?ids=' . $process_id;
	$url .= '&format=xml';
	$xml = get($url);
	
	if ($xml != '')
	{				
		$obj->status = 200;
		
		$dom = new DOMDocument;
		$dom->loadXML($xml);
		$xpath = new DOMXPath($dom);
	
		$nodeCollection = $xpath->query ('//nucleotides');
		foreach($nodeCollection as $node)
		{
			$obj->nucleotides =  $node->firstChild->nodeValue;
		}
		$nodeCollection = $xpath->query ('//bin_uri');
		foreach($nodeCollection as $node)
		{
			$obj->bin_uri =  $node->firstChild->nodeValue;
		}
		
		$nodeCollection = $xpath->query ('//coordinates/lat');
		foreach($nodeCollection as $node)
		{
			$obj->lat =  $node->firstChild->nodeValue;
		}
		$nodeCollection = $xpath->query ('//coordinates/lon');
		foreach($nodeCollection as $node)
		{
			$obj->lon =  $node->firstChild->nodeValue;
		}
	}
	
	
	return $obj;
}

//--------------------------------------------------------------------------------------------------
// get similar sequences
function get_similar_sequences($process_id)
{
	global $config;
	
	$obj = new stdclass;
	$obj->status = 404;
	$obj->process_id = $process_id;
	
	$record = get_record($process_id);
	if ($record->status != 200)
	{
		return $obj;
	}
	
	$url = 'http://boldsystems.org/index.php/Ids_xml?db=COX1_SPECIES';
	$url .= '&sequence=' . $record->nucleotides;
	$url .= '&format=xml';
	
	$xml = get($url);
	
	//echo $xml;
	
	$fasta = '';
	
	if ($xml != '')
	{				
		$obj->status = 200;
		$obj->hits = array();
		
		$dom = new DOMDocument;
		$dom->loadXML($xml);
		$xpath = new DOMXPath($dom);
		
		$nodeCollection = $xpath->query ('//match');
		foreach($nodeCollection as $node)
		{
			$hit = new stdclass;
			
			$nc = $xpath->query('ID', $node);
			foreach($nc as $n)
			{
				$hit->id .= $n->firstChild->nodeValue;
			}
					
			$record = get_record($hit->id);
			
			if ($record)
			{
				$fasta .= ">" . clean_id($hit->id) . "\n";
				$fasta .= chunk_split($record->nucleotides, 60, "\n");
				$fasta .=  "\n";
				
				if (isset($record->bin_uri))
				{
					$hit->bin_uri = $record->bin_uri;
				}
				
				
				if (isset($record->lat))
				{
					$hit->latitude = $record->lat;
					$hit->longitude = $record->lon;
				}
				
				$obj->hits[] = $hit;
			}
		}
			
	}
	
	$obj->fastafile =  get_process_dir($process_id) . '/' . $process_id . '.fas';
	
	file_put_contents($obj->fastafile, $fasta);	

	$obj->jsonfile =  get_process_dir($process_id) . '/' . $process_id . '.json';

	file_put_contents($obj->jsonfile, json_encode($obj, JSON_PRETTY_PRINT));
	
	return $obj;
}

//--------------------------------------------------------------------------------------------------
function make_tree($process_id)
{
	global $config;

	$obj = new stdclass;
	$obj->status = 404;
	$obj->process_id = $process_id;

	// Align sequences
	$filename = get_process_dir($process_id) . '/' . $process_id . '.fas';
	$logfilename = get_process_dir($process_id) . '/' . $process_id . '_CLUSTALW.log';

	$basename = preg_replace('/\.fas$/', '', $filename);
	
	$command = $config['clustalw2'] . ' ' . '-INFILE=' . $filename . ' -QUICKTREE -OUTORDER=INPUT -OUTPUT=NEXUS' . ' 1>' . $logfilename;
	
	system($command);
	
	// Create NEXUS file for PAUP
	$nxs_filename = $basename . '.nxs';
	
	$nexus = file_get_contents($nxs_filename);
	
	$nexus .= "\n";
	$nexus .="[PAUP block]\n";
	$nexus .="begin paup;\n";
	$nexus .="   [root trees at midpoint]\n";
	$nexus .="   set rootmethod=midpoint;\n";
	$nexus .="   set outroot=monophyl;\n";
	$nexus .="   [construct tree using neighbour-joining]\n";
	$nexus .="   nj;\n";
	$nexus .="   [ensure branch lengths are output as substituions per nucleotide]\n";
	$nexus .="   set criterion=distance;\n";
	$nexus .="   [write rooted trees in Newick format with branch lengths]\n";
	$nexus .="   savetrees format=nexus root=yes brlen=yes replace=yes;\n";
	$nexus .="   quit;\n";
	$nexus .="end;\n";
	
	$nexus_filename = $basename . '.nex';
	file_put_contents($nexus_filename, $nexus);
	
	$logfilename = get_process_dir($process_id) . '/' . $process_id . '_PAUP.log';
	
	// Run PAUP
	$command = $config['paup'] . ' ' . $nexus_filename .  ' 1>' . $logfilename;
	
	system($command);
	
	$tree_filename = $basename . '.tre';
	$nexus = file_get_contents($tree_filename);
	
	// Get rest of data
	$json_filename = $basename . '.json';
	$json = file_get_contents($json_filename);
	
	$response = json_decode($json);
				
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
	$nexus .= ";\nend;";

	$nexus .= "\nbegin notes;\n\talttaxnames tax_id = \n";
	
	foreach ($bins as $id => $bin)
	{
		$nexus .= "\t[" . $id . "] '$bin'\n";
	}
	$nexus .= ";\nend;\n";
		
	
	if ($nexus != '')
	{
		$obj->treefile = $basename . '.tre';
		$obj->tree = $nexus;
		$obj->status = 200;
		
		// save
		file_put_contents($tree_filename, $nexus);
		
		// zip everything
		$zipfilename = $config['temp_dir'] . '/' . $process_id . '.zip';
		$logfilename = get_process_dir($process_id) . '/' . $process_id . '_ZIP.log';
		
		$command = $config['zip'] . ' ' . $zipfilename . ' -jr ' . get_process_dir($process_id) . ' 1>' . $logfilename;
		system($command);
		
		$obj->archive = $zipfilename;
		
		
	}
	
	return $obj;	
}


?>
