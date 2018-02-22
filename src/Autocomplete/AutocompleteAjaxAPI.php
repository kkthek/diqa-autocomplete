<?php

namespace DIQA\Autocomplete;

use DIQA\Util\QueryUtils;

/**
 * Provides an autocompletion endpoint.
 *
 * Takes property and category contraint and returns all property values
 * of the given property which occur on pages with the given category.
 *
 * Called via:
 *
 * /api.php?action=diqa_autocomplete&format=json&substr=obj&property=Titel&category=Bau
 *
 * @author Kai
 *        
 */
class AutocompleteAjaxAPI extends \ApiBase {

	public function __construct($query, $moduleName) {
		parent::__construct ( $query, $moduleName );
	}

	public function isReadMode() {
		return false;
	}

	public function execute() {
		$params = $this->extractRequestParams ();
		
		try {
		    
		  $values = self::getACResults ( $params );
		  
		} catch(\Exception $e) {
		    $this->dieUsage ( $e->getMessage() );
		}
		
		// If we got back an error message, exit with that message.
		if (! is_array ( $values )) {
			$this->dieUsage ( $values );
		}
		
		// Set top-level elements.
		$result = $this->getResult ();
		$result->setIndexedTagName ( $values, 'p' );
		$result->addValue ( null, 'pfautocomplete', $values );
	}

	protected function getAllowedParams() {
		return array (
				
				'substr' => null,
				'property' => null,
				'category' => null,
				'concept' => null,
				'schema' => null,
		        'query' => null,
				'_' => null 
		);
	}

	protected function getParamDescription() {
		return array (
				
				'substr' => 'Search substring',
				'property' => 'Semantic property for which to search values',
				'category' => 'Categories for which to search values (comma-separated)',
				'concept' => 'Concept for which to search values',
		        'query' => 'Query constraint',
				'schema' => 'Return schema elements',
				'_' => '' 
		);
	}

	protected function getDescription() {
		return 'Autocompletion call used by SFI-Extensions (DIQA-PM.COM)';
	}

	protected function getExamples() {
		return array (
				'api.php?action=diqa_autocomplete&format=json&substr=obj&property=Titel&category=Bau' 
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	public static function handlePFAutoCompleteHook($url, & $pageContent) {
		
		// handle only diqa_autocomplete URLs
		if (strpos ( $url, 'diqa_autocomplete' ) === false) {
			return;
		}
		
		// parse autocomplete URL
		$parts = parse_url ( $url );
		$query = $parts ['query'];
		$keyValues = explode ( "&", $query );
		$params = [ ];
		$params ['substr'] = '';
		$params ['property'] = '';
		$params ['query'] = '';
		$params ['category'] = '';
		$params ['concept'] = '';
		$params ['schema'] = '';
		foreach ( $keyValues as $keyValue ) {
			list ( $key, $value ) = explode ( "=", $keyValue );
			$params [$key] = urldecode ( $value );
		}
		
		// get results
	    try {
	        
		  $values = self::getACResults ( $params );
		  
		} catch(\Exception $e) {
		    http_response_code(404);
		    $o = new \stdClass ();
		    $o->pfautocomplete = $values;
		    $pageContent = $e->getMessage();
		    return;
		}
		
		// serialize to JSON
		$o = new \stdClass ();
		$o->pfautocomplete = $values;
		$pageContent = json_encode ( $o );
	}

	private static function getACResults($params) {
		$substr = $params ['substr'];
		$property = $params ['property'];
		$category = $params ['category'];
		$concept = $params ['concept'];
		$query = $params ['query'];
		$schema = $params ['schema'];
		
		if (strlen ( $substr ) == 0) {
			throw new \Exception( 'The param "substr" must be specified');
		}
		
		if (strlen ( $property ) == 0) {
		    throw new \Exception( 'The param "property" must be specified');
		}
		
		if (is_null ( $substr )) {
			return;
		}
		
		if ($substr == '*') {
			$substr = '';
		}
		
		if (strlen ( $category ) != 0) {
			$categories = explode ( ",", $category );
			$queries = array_map ( function ($c) {
				$c = trim ( $c );
				return "[[Category:$c]]";
			}, $categories );
			$values = self::getTitleBy ( implode ( ' OR ', $queries ) . $query, $property, $substr );
		} else if (strlen ( $concept ) != 0) {
			$values = self::getTitleBy ( "[[Concept:$concept]]" . $query, $property, $substr );
		} else if (strlen ( $schema ) != 0) {
			$list = explode ( ",", $schema );
			$query = array_map ( function ($e) {
				return "$e:+";
			}, $list );
			$values = self::getTitleBy ( "[[" . implode ( '||', $query ) . "]]" . $query, $property, $substr );
		} else {
			$values = self::getTitleBy ( "$query", $property, $substr );
		}
		return $values;
	}
	

	private static function getTitleBy($query, $property, $substr) {
		$printout = new \SMWPrintRequest ( \SMWPrintRequest::PRINT_PROP, "$property", \SMWPropertyValue::makeUserProperty ( $property ) );
		$printout_cat = new \SMWPrintRequest ( \SMWPrintRequest::PRINT_CATS, '' );
		$query_result = QueryUtils::executeBasicQuery ( sprintf ( "{$query}[[{$property}_lowercase::~*%s*]]", strtolower ( $substr ) ), [ 
				$printout,
				$printout_cat 
		], [ 
				'limit' => '50' 
		] );
		
		$results = [ ];
		while ( $res = $query_result->getNext () ) {
			$pageID = $res [0]->getNextText ( SMW_OUTPUT_WIKI );
			$pageTitle = $res [1]->getNextText ( SMW_OUTPUT_WIKI );
			$catTitle = $res [2]->getNextText ( SMW_OUTPUT_WIKI );
			
			$mwTitle = \Title::newFromText ( $pageID );
			
			$extension = null;
			$href = null;
			if ($mwTitle->getNamespace () == NS_FILE) {
				$file = wfLocalFile ( $mwTitle );
				$extension = $file->getExtension ();
				$href = $file->getFullUrl ();
			}
			
			$results [] = [ 
					'title' => $pageTitle,
					'data' => [ 
							'category' => $catTitle,
							'fullTitle' => $mwTitle->getPrefixedText (),
							'file' => [ 
									'extension' => $extension,
									'href' => $href 
							] 
					],
					'id' => $mwTitle->getText (),
					'ns' => $mwTitle->getNamespace () 
			];
		}
		
		return $results;
	}
}