<?php
namespace DIQA\Autocomplete;

use DIQA\Util\Configuration\ConfigLoader as UtilConfigLoader;

/**
 * Loads the Autocomplete configuration files.
 */
class ConfigLoader {
        
    public static function loadConfig() {
        require_once __DIR__ . '/../../../../Util/src/Util/Configuration/ConfigLoader.php';
        
        $mwPath = __DIR__ . '/../../../../..';
        $ds = __DIR__ . '/../../../DefaultSettings.php';
        $configVariables = [
            'wgDBname', 'wgDBuser', 'wgDBpassword', 'wgServerHTTP',
            'wgScriptPath',
            'wgPageFormsAutocompletionURLs', 'wgFormattedNamespaces'
        ];

		$loader = new UtilConfigLoader($mwPath, $ds, $configVariables);
        $loader->loadConfig();
    }
    
}
