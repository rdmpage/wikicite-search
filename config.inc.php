<?php

error_reporting(E_ALL);

global $config;

// Date timezone--------------------------------------------------------------------------
date_default_timezone_set('UTC');

// Multibyte strings----------------------------------------------------------------------
mb_internal_encoding("UTF-8");

// Hosting--------------------------------------------------------------------------------

$site = 'local';
//$site = 'heroku';

switch ($site)
{
	case 'heroku':
		// Server-------------------------------------------------------------------------
		$config['web_server']	= 'https://wikicite-search.herokuapp.com'; 
		$config['site_name']	= 'Wikicite Search';

		// Files--------------------------------------------------------------------------
		$config['web_dir']		= dirname(__FILE__);
		$config['web_root']		= '/';		
		break;

	case 'local':
	default:
		// Server-------------------------------------------------------------------------
		$config['web_server']	= 'http://localhost'; 
		$config['site_name']	= 'Wikicite Search';

		// Files--------------------------------------------------------------------------
		$config['web_dir']		= dirname(__FILE__);
		$config['web_root']		= '/~rpage/wikicite-search/';
		break;
}

// Environment----------------------------------------------------------------------------
// In development this is a PHP file that is in .gitignore, when deployed these parameters
// will be set on the server
if (file_exists(dirname(__FILE__) . '/env.php'))
{
	include 'env.php';
}

// Cache----------------------------------------------------------------------------------
$config['cache'] = dirname(__FILE__) . '/cache';

// External hard drive
$config['cache'] = '/Volumes/Ultra Touch/wikicite-search/cache';

// Storage--------------------------------------------------------------------------------
$config['platform'] = 'local';
$config['platform'] = 'cloud';

if ($config['platform'] == 'local')
{
	// Local Docker Elasticsearch version 7.6.2 http://localhost:32772
	$config['elastic_options'] = array(
			'protocol' 	=> 'http',
			'index' 	=> 'wikicite',
			'protocol' 	=> 'http',
			'host' 		=> 'localhost',
			'port' 		=> 55001
			);
}

if ($config['platform'] == 'cloud')
{
	// Bitnami
	$config['elastic_options'] = array(
			'index' 	=> 'wikicite',
			'protocol' 	=> 'http',
			'host' 		=> '34.65.169.199',
			'port' 		=> 80,
			'user' 		=> getenv('ELASTIC_USERNAME'),
			'password' 	=> getenv('ELASTIC_PASSWORD'),
			);
}

?>
