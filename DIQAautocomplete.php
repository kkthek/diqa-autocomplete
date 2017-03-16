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
	'name' => 'DIQA Autocomplete',
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
		'dependencies' => array( 'ext.semanticforms.main' )
);
$GLOBALS['wgAPIModules']['diqa_autocomplete'] = 'DIQA\Autocomplete\AutocompleteAjaxAPI';

/**
 * Initializations for DIQAautocomplete
 */
function wfDIQAautocompleteSetup() {
	global $wgOut;
	$wgOut->addModules('ext.autocomplete.core');
	return true;
}
