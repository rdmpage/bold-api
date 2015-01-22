<?php

require_once (dirname(__FILE__) . '/lib.php');
require_once (dirname(__FILE__) . '/api_utils.php');
require_once (dirname(__FILE__) . '/bold.php');

//--------------------------------------------------------------------------------------------------
function default_display()
{
	echo "hi";
}

//--------------------------------------------------------------------------------------------------
// Get similar sequences to this barcode
function display_submit_process_id ($process_id, $callback)
{
	$obj = get_similar_sequences($process_id);
	
	api_output($obj, $callback);
}

//--------------------------------------------------------------------------------------------------
// Make tree
function display_tree ($process_id, $callback)
{
	$obj = make_tree($process_id);
	
	api_output($obj, $callback);
}


//--------------------------------------------------------------------------------------------------
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
	
	// Submit job
	if (!$handled)
	{
		if (isset($_GET['process_id']))
		{	
			$process_id = $_GET['process_id'];
		

			if (isset($_GET['tree']))
			{
				display_tree($process_id, $callback);
				$handled = true;
			}
				
			if (!$handled)
			{

				// submit job
				display_submit_process_id($process_id, $callback);
				$handled = true;
			}
		}
	}
		
	
}


main();

?>