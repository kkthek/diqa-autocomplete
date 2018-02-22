<?php
namespace DIQA\Autocomplete;

use Illuminate\Database\Capsule\Manager as Capsule;

class Autocomplete {

    public static function init() {
        // check login
        if(!Auth::isLoggedIn()) {
            // if not logged in return empty result
            $o = new \stdClass();
            $o->pfautocomplete = [];
            echo json_encode($o);
            die();
        }
        
        // parse request
        $params = self::parseRequest($_SERVER['REQUEST_URI']);

        // request AC data
        self::initializeEloquent();
        $values = self::handleRequest($params);

        // serialize to JSON
        $o = new \stdClass();
        $o->pfautocomplete = $values;
        echo json_encode($o);
    }

    /**
     * Setup Eloquent ORM-Mapper
     */
    private static function initializeEloquent() {
        $capsule = new Capsule();
        
        global $wgDBserver, $wgDBname, $wgDBuser, $wgDBpassword, $wgDBprefix;
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => $wgDBserver,
            'database' => $wgDBname,
            'username' => $wgDBuser,
            'password' => $wgDBpassword,
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => $wgDBprefix
        ]);
        
        // Make this Capsule instance available globally via static methods... (optional)
        $capsule->setAsGlobal();
        
        // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
        $capsule->bootEloquent();
    }

    private static function parseRequest($url) {
        
        // parse autocomplete URL from PageForms
        $parts = parse_url($url);
        $query = $parts['query'];
        $keyValues = explode("&", $query);
        $params = [];
        $params['external_url'] = '';
        $params['substr'] = '';
        
        foreach ($keyValues as $keyValue) {
            list ($key, $value) = explode("=", $keyValue);
            $params[$key] = urldecode($value);
        }
        
        // get configured remote URL...
        global $wgPageFormsAutocompletionURLs;
        if (! array_key_exists($params['external_url'], $wgPageFormsAutocompletionURLs)) {
            trigger_error("External URL unknown or empty: " . $params['external_url']);
            echo json_encode($wgPageFormsAutocompletionURLs);
            return $params;
        }
        $url = $wgPageFormsAutocompletionURLs[$params['external_url']];
        
        // ... and parse it
        $parts = parse_url($url);
        $query = $parts['query'];
        $keyValues = explode("&", $query);
        
        $params['property'] = '';
        $params['category'] = '';
        $params['query'] = '';
        
        foreach ($keyValues as $keyValue) {
            list ($key, $value) = explode("=", $keyValue);
            if ($key == 'substr') {
                continue;
            }
            $params[$key] = urldecode($value);
        }
        
        return $params;
    }

    private static function handleRequest($params) {
        global $wgFormattedNamespaces;
        if (!isset($wgFormattedNamespaces)) {
            $wgFormattedNamespaces = [];
        }
        
        // set defaults
        $wgFormattedNamespaces[0] = '';
        $wgFormattedNamespaces[6] = 'Datei';
        $wgFormattedNamespaces[10] = 'Vorlage';
        $wgFormattedNamespaces[14] = 'Kategorie';
        $wgFormattedNamespaces[102] = 'Atttribut';
        
        $titleLowercasePropertyID = Capsule::table('smw_object_ids')->select('smw_id')
            ->where('smw_title', $params['property'] . '_lowercase')
            ->where('smw_namespace', 102)
            ->get()
            ->first();
        
        $titlePropertyID = Capsule::table('smw_object_ids')->select('smw_id')
            ->where('smw_title', $params['property'])
            ->where('smw_namespace', 102)
            ->get()
            ->first();
        
        $substr = strtolower($params['substr']);
        if ($substr == '*') {
            $substr = '';
        }
        
        // build SQL parameters
        $sqlParameters = [];
        
        $sqlParameters[] = $titleLowercasePropertyID->smw_id;
        $sqlParameters[] = $titlePropertyID->smw_id;
        
        list($joinsQueryParam, $whereQueryParam) = self::buildQuerySQLConstraint($params['query'], $sqlParameters);
        
        $categories = explode('||', $params['category']);
        $categoryConstraint = [];
        foreach ($categories as $c) {
            $sqlParameters[] = $c;
            $categoryConstraint[] = 'object.smw_title = ?';
        }
        $categoryConstraintSQL = implode(' OR ', $categoryConstraint);
        
        
        $sqlParameters[] = "%$substr%";
        $sqlParameters[] = "%$substr%";
        
        // run SQL query
        $sqlQuery = 'SELECT IF(title.o_blob IS NULL, title.o_hash, title.o_blob) AS title,
                        subject.smw_title AS mw_title,
                        subject.smw_namespace AS mw_namespace,
                        object.smw_title AS category_title
                 FROM smw_fpt_inst
                 JOIN smw_object_ids subject ON subject.smw_id = s_id
                 JOIN smw_object_ids object ON object.smw_id = o_id
                 JOIN smw_di_blob title_filter ON subject.smw_id = title_filter.s_id AND title_filter.p_id = ?
                 JOIN smw_di_blob title ON subject.smw_id = title.s_id AND title.p_id = ?
                 '.join(' ', $joinsQueryParam).'
                 WHERE (' . join(' AND ', $whereQueryParam) . ' AND ' . $categoryConstraintSQL .')
                 AND ( title_filter.o_hash LIKE ? OR title_filter.o_blob LIKE ?)';
       
        $pages = Capsule::select($sqlQuery, $sqlParameters);
      
        $results = [];
        foreach ($pages as $row) {
            $results[] = [
                'title' => $row->title,
                'data' => [
                    'category' => $row->category_title,
                    'fullTitle' => $wgFormattedNamespaces[$row->mw_namespace] . ":" . $row->mw_title
                ]
                // TODO: file
                ,
                'id' => $row->mw_title,
                'ns' => $row->mw_namespace
            ];
        }
        
        return $results;
    }
    
    /**
     * Parses a SMW-like query [[Property::Constraint]]
     * 
     * Supports only Wikipage and String properties!
     * 
     * @param string $query SMW query
     * @param array $sqlParameters
     * @return string[][]
     */
    private static function buildQuerySQLConstraint($query, & $sqlParameters) {
        $num = preg_match_all('/([^:[]*)::([^]]*)/',$query, $matches);
        $where = ['true'];
        $joins = [];
        
        $whereParams = [];
        $joinParams = [];
        
        if ($num > 0) {
            for($i = 0; $i < $num; $i++) {
               $property = trim($matches[1][$i]);
               $value = trim($matches[2][$i]);
               $valueID = null;
               
               $propertyID = Capsule::table('smw_object_ids')->select('smw_id')
               ->where('smw_title', str_replace(' ','_', $property))
               ->where('smw_namespace', 102)
               ->get()
               ->first();
               
               if (is_null($propertyID)) {
                   $propertyID = -1;
               } else {
                   $propertyID = $propertyID->smw_id;
               }
               
               $mwPageID = Capsule::table('page_props')->select('pp_page')
               ->where('pp_propname', 'displaytitle')->where('pp_value', $value)
                ->get()
               ->first();
               
               if (!is_null($mwPageID)) {
               
                   $mwPageTitle = Capsule::table('page')->select('page_title', 'page_namespace')
                   ->where('page_id', $mwPageID->pp_page)
                    ->get()
                   ->first();
                   
                   $valueID = Capsule::table('smw_object_ids')->select('smw_id')
                   ->where('smw_title', $mwPageTitle->page_title)
                   ->where('smw_namespace', $mwPageTitle->page_namespace)
                   ->get()
                   ->first();
               }
               
               if (!is_null($valueID)) {
                   $valueID = $valueID->smw_id;
               }
               
               $joinParams[] = $propertyID;
               $joinParams[] = $propertyID;
               $whereParams[] = $value;
               $whereParams[] = $value;
               $whereParams[] = $valueID;
               $joins[] = "LEFT JOIN smw_di_blob prop_constraint$i ON subject.smw_id = prop_constraint$i.s_id AND prop_constraint$i.p_id = ?";
               $joins[] = "LEFT JOIN smw_di_wikipage prop_constraint_wikipage$i ON subject.smw_id = prop_constraint_wikipage$i.s_id AND prop_constraint_wikipage$i.p_id = ?";
               $where[] = $value == '+' ? "" : " (prop_constraint$i.o_hash = ? OR prop_constraint$i.o_blob = ? OR prop_constraint_wikipage$i.o_id = ?)";
            }
        }
        
        foreach($joinParams as $p) {
            $sqlParameters[] = $p;
        }
        foreach($whereParams as $p) {
            $sqlParameters[] = $p;
        }
        return [ $joins, $where ];
    }
}
