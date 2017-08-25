<?php

global $wgDBname, $wgDBuser, $wgDBpassword;
$wgDBname='wima_wiki';
$wgDBuser = 'root';
$wgDBpassword = 'vagrant';

global $wgServerHTTP, $wgScriptPath;
$wgServerHTTP = 'http://wimawiki.local';
$wgScriptPath='/mediawiki';

global $wgPageFormsAutocompletionURLs;
$wgPageFormsAutocompletionURLs['autocomplete-bezugselement']
 = $wgServerHTTP . $wgScriptPath . '/api.php?action=diqa_autocomplete&format=json&substr=<substr>&property=Titel&category=Wissenselement'; //Der Name des internen Properties was die Kleinschreibung enth�lt ist wie im property-Parameter mit dem suffix "_lowercase", hier also "Titel_lowercase"

global $wgFormattedNamespaces;
$wgFormattedNamespaces[0] = '';
$wgFormattedNamespaces[10] = 'Vorlage';
$wgFormattedNamespaces[14] = 'Kategorie';
$wgFormattedNamespaces[102] = 'Atttribut';
