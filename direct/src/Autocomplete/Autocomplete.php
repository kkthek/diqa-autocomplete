<?php
namespace DIQA\Autocomplete;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Database\QueryException;


class Autocomplete {
	
	public static function init() {

		// check login
		$groups = Auth::session();
		if (count($groups) === 0) {
			// if not member of a group
			// return empty result
			$o = new \stdClass ();
			$o->pfautocomplete = [];
			echo json_encode ( $o );
			die();
		}
		
		// parse request
		$params = self::parseRequest($_SERVER['REQUEST_URI']);
		
		// request AC data
		self::initializeEloquent();
		$values = self::handleRequest($params);
		
		// serialize to JSON
		$o = new \stdClass ();
		$o->pfautocomplete = $values;
		echo json_encode ( $o );
	}
	
	/**
	 * Setups Eloquent ORM-Mapper
	 */
	private static function initializeEloquent() {
		$capsule = new Capsule ();
	
		global $wgDBname, $wgDBuser, $wgDBpassword;
		$capsule->addConnection ( [
				'driver' => 'mysql',
				'host' => 'localhost',
				'database' => $wgDBname,
				'username' => $wgDBuser,
				'password' => $wgDBpassword,
				'charset' => 'utf8',
				'collation' => 'utf8_unicode_ci',
				'prefix' => ''
				] );
	
	
		// Make this Capsule instance available globally via static methods... (optional)
		$capsule->setAsGlobal ();
		
		// Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
		$capsule->bootEloquent ();
	}
	
	private static function parseRequest($url) {
		
		// parse autocomplete URL from PageForms
		$parts = parse_url ( $url );
		$query = $parts ['query'];
		$keyValues = explode ( "&", $query );
		$params = [ ];
		$params ['external_url'] = '';
		$params ['substr'] = '';
		
		foreach ( $keyValues as $keyValue ) {
			list ( $key, $value ) = explode ( "=", $keyValue );
			$params [$key] = urldecode ( $value );
		}
		
		// get configured remote URL...
		global $wgPageFormsAutocompletionURLs;
		if (!array_key_exists($params ['external_url'], $wgPageFormsAutocompletionURLs)) {
			trigger_error("External URL unknown or empty: " . $params ['external_url']);
			return $params;
		}
		$url = $wgPageFormsAutocompletionURLs[$params ['external_url']];
		
		// ... and parse it
		$parts = parse_url ( $url );
		$query = $parts ['query'];
		$keyValues = explode ( "&", $query );
		
		$params ['property'] = '';
		$params ['category'] = '';
		
		foreach ( $keyValues as $keyValue ) {
			list ( $key, $value ) = explode ( "=", $keyValue );
			if ($key == 'substr') {
				continue;
			}
			$params [$key] = urldecode ( $value );
		}
		
		return $params;
	}
	
	private static function handleRequest($params) {
		
		global $wgFormattedNamespaces;
		
		$titleLowercasePropertyID = Capsule::table('smw_object_ids')
		->select('smw_id')
		->where('smw_title', $params['property'].'_lowercase')
		->where('smw_namespace', 102)
		->get()->first();
		
		$titlePropertyID = Capsule::table('smw_object_ids')
		->select('smw_id')
		->where('smw_title', $params['property'])
		->where('smw_namespace', 102)
		->get()->first();
		
		$substr = strtolower($params['substr']);
		
		// build SQL parameters
		$sqlParameters = [ 
					$titleLowercasePropertyID->smw_id,
					$titlePropertyID->smw_id 
		];
		$categories = explode('||', $params['category']);
		$categoryConstraint = [];
		foreach($categories as $c) {
			$sqlParameters[] = $c;
			$categoryConstraint[] = 'object.smw_title = ?';
		}
		$categoryConstraintSQL = implode(' OR ', $categoryConstraint);
		
		$sqlParameters[] = "%$substr%";
		$sqlParameters[] = "%$substr%";
		
		// run SQL query
		$pages = Capsule::select(
				'SELECT IF(title.o_blob IS NULL, title.o_hash, title.o_blob) AS title,
				 		subject.smw_title AS mw_title, 
						subject.smw_namespace AS mw_namespace,
						object.smw_title AS category_title
				 FROM smw_fpt_inst 
				 JOIN smw_object_ids subject ON subject.smw_id = s_id 
				 JOIN smw_object_ids object ON object.smw_id = o_id 
				 JOIN smw_di_blob title_filter ON subject.smw_id = title_filter.s_id AND title_filter.p_id = ?
				 JOIN smw_di_blob title ON subject.smw_id = title.s_id AND title.p_id = ?
				 WHERE ('.$categoryConstraintSQL.')
				 AND ( title_filter.o_hash LIKE ? OR title_filter.o_blob LIKE ?)', 
				 $sqlParameters);
		
		
		$results = [];
		foreach($pages as $row) {
			$results [] = [ 
					'title' => $row->title,
					'data' => [ 
							'category' => $row->category_title,
							'fullTitle' => $wgFormattedNamespaces[$row->mw_namespace] . ":" . $row->mw_title,
							//TODO: file
					],
					'id' => $row->mw_title,
					'ns' => $row->mw_namespace 
			];
		}
		
		return $results;
	}
}


