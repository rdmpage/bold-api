<?php

// $Id: //

/**
 * @file config.php
 *
 * Global configuration variables (may be added to by other modules).
 *
 */

global $config;

// Date timezone
date_default_timezone_set('Europe/London');

// Proxy settings for connecting to the web---------------------------------------------------------

// Set these if you access the web through a proxy server
$config['proxy_name'] 	= '';
$config['proxy_port'] 	= '';

//$config['proxy_name'] 	= 'wwwcache.gla.ac.uk';
//$config['proxy_port'] 	= '8080';


// Settings-----------------------------------------------------------------------------------------
$config['temp_dir'] = dirname(__FILE__) . '/tmp';

// Software-----------------------------------------------------------------------------------------
$config['clustalw2'] 	= '/usr/local/bin/clustalw2';
$config['paup'] 		= '/usr/local/bin/paup';
$config['zip'] 			= '/usr/bin/zip';
	
?>