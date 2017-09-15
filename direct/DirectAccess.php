<?php
use DIQA\Autocomplete\Autocomplete;
use DIQA\Autocomplete\ConfigLoader;

error_reporting(E_ALL);
ini_set( 'display_errors', 1 );

ConfigLoader::loadConfig();
Autocomplete::init();

