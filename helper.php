<?php
/**
 * Plugin Now: Inserts a timestamp.
 * 
 * @license    GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Szymon Olewniczak <szymon.olewniczak@rid.pl>
 */

// must be run within DokuWiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'syntax.php';

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class helper_plugin_dtable extends dokuwiki_plugin
{
    function getMethods(){
      $result = array();
      $result[] = array(
	'name'   => 'md5_array',
	'desc'   => 'returns array with md5 of each value',
	'params' => array('array' => 'array'),
	'return' => array('md5_array' => 'array'),
      );
      $result[] = array(
	'name'   => 'error',
	'desc'   => 'handle error',
	'params' => array('code' => 'string', 'json' => 'boolen'),
	'return' => array('msg' => 'string'),
      );
    }
    function md5_array($array)
    {
	if(count($array) == 0)
	    return $array;

	$md5_array = array();
	foreach($array as $k => $v)
	{
	    $md5_array[$k] = md5($v);
	}
	return $md5_array;	
    }
    function error($code, $json=false)
    {
	if($json == true)
	{
	    return json_encode(array('type' => 'error', 'msg' => $this->getLang($code)));
	} else
	{
	    return $this->getLang($code);
	}
    }
}

