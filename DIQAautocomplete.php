<?php

/**
 * Enhanced autocomplete for [http://www.mediawiki.org/wiki/Extension:SemanticForms Semantic Forms].
 *
 * @defgroup DIQA autocomplete
 *
 * @author Kai Kühn
 *
 * @version 0.1
 */

/**
 * The main file of the DIQA autocomplete extension
 *
 * @file
 * @ingroup DIQA
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is part of a MediaWiki extension, it is not a valid entry point.' );
}

define( 'DIQA_AUTOCOMPLETE_VERSION', '0.1' );

global $wgVersion;
global $wgExtensionCredits;
global $wgExtensionMessagesFiles;
global $wgHooks;
global $wgResourceModules;

// register extension
$wgExtensionCredits[ 'odb' ][] = array(
	'path' => __FILE__,
	'name' => 'Autocomplete',
	'author' => array( 'DIQA Projektmanagement GmbH' ),
	'license-name' => 'GPL-2.0+',
	'url' => 'http://www.diqa-pm.com',
	'descriptionmsg' => 'diqa-autocomplete-desc',
	'version' => DIQA_AUTOCOMPLETE_VERSION,
);

$dir = dirname( __FILE__ );

$wgExtensionMessagesFiles['DIQAautocomplete'] = $dir . '/DIQAautocomplete.i18n.php';
$wgHooks['ParserFirstCallInit'][] = 'wfDIQAautocompleteSetup';

$wgResourceModules['ext.autocomplete.core'] = array(
		'localBasePath' => $dir,
		'remoteExtPath' => 'DIQAautocomplete',
		'scripts' => array('scripts/ac-extensions.js'),
		'dependencies' => array( 'ext.pageforms.main' )
);
$GLOBALS['wgAPIModules']['diqa_autocomplete'] = 'DIQA\Autocomplete\AutocompleteAjaxAPI';
$wgHooks['pf_autocomplete_external'][] = 'DIQA\Autocomplete\AutocompleteAjaxAPI::handlePFAutoCompleteHook';
$wgHooks['UserLogout'][] = 'wfDIQAautocompleteLogout';

/**
 * Initializations for DIQAautocomplete
 */
function wfDIQAautocompleteSetup() {
	global $wgOut;
	$wgOut->addModules('ext.autocomplete.core');
	return true;
};

/**
 * Ends direct auto-complete session
 * @return 
 */
function wfDIQAautocompleteLogout() {
	global $wgServerHTTP, $wgScriptPath, $wgDBname;

	$http = function ($url, $cookies) {
		$res = "";
		$header = "";

		// Create a curl handle to a non-existing location
		$ch = curl_init($url);

		$cookieArray = [];
		foreach($cookies as $key => $value) {
			$cookieArray[] = "$key=$value";
		}


		// Execute
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookieArray));
		$res = curl_exec($ch);

		$status = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		curl_close($ch);

		$bodyBegin = strpos($res, "\r\n\r\n");
		list($header, $res) = $bodyBegin !== false ? array(substr($res, 0, $bodyBegin), substr($res, $bodyBegin+4)) : array($res, "");
		return array($header, $status, str_replace("%0A%0D%0A%0D", "\r\n\r\n", $res));
	};

	$userid = isset($_COOKIE[$wgDBname.'UserID']) ? $_COOKIE[$wgDBname.'UserID'] : '';
	$userName = isset($_COOKIE[$wgDBname.'UserName']) ? $_COOKIE[$wgDBname.'UserName'] : '';
	$sessionId = isset($_COOKIE[$wgDBname.'_session']) ? $_COOKIE[$wgDBname.'_session'] : '';

	$PHPSESSID = isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : '';

	$cookies = [
	$wgDBname.'UserID' => $userid,
	$wgDBname.'UserName' => $userName,
	$wgDBname.'_session' => $sessionId,
	'PHPSESSID' => $PHPSESSID,
	];

	$res = $http($wgServerHTTP . $wgScriptPath . "/api.php?action=pfautocomplete&format=json&logout=true", $cookies);
};
