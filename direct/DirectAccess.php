<?php
use DIQA\Autocomplete\Autocomplete;

error_reporting(E_ALL);
ini_set( 'display_errors', 1 );

function checkIfConfigured($var) {
	if (!isset($GLOBALS[$var])) {
		trigger_error("'$var' is not configured in env.php.");
		die();
	}
}

if (file_exists(__DIR__ . '/../../../env.php')) {

	// read env.php file	
	require_once(__DIR__ . '/../../../env.php');
	checkIfConfigured('wgDBname');
	checkIfConfigured('wgDBuser');
	checkIfConfigured('wgDBpassword');
	checkIfConfigured('wgServerHTTP');
	checkIfConfigured('wgScriptPath');
	checkIfConfigured('wgPageFormsAutocompletionURLs');
	checkIfConfigured('wgFormattedNamespaces');
	
} else if (file_exists(__DIR__ . '/env-sample.php')) {
	
	// assume local config in VM, so read sample file
	require_once(__DIR__ . '/env-sample.php');
	
} else {
	
	// abort
	trigger_error("env.php is not configured.");
	die();
	
}

Autocomplete::init();

