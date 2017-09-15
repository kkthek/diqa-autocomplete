<?php

global $wgServerHTTP, $wgScriptPath;

global $wgPageFormsAutocompletionURLs;
$path = "$wgServerHTTP$wgScriptPath/api.php?action=diqa_autocomplete&format=json&substr=<substr>&property=Titel&category";
$wgPageFormsAutocompletionURLs['autocomplete-TEST'] =  "$path=TEST_CATEGORY";
 
global $wgFormattedNamespaces;
$wgFormattedNamespaces[0] = '';
$wgFormattedNamespaces[10] = 'Vorlage';
$wgFormattedNamespaces[14] = 'Kategorie';
$wgFormattedNamespaces[102] = 'Atttribut';
