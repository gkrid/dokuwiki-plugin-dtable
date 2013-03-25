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
	'name'   => 'file_path',
	'desc'   => 'returns db path',
	'params' => array('name' => 'string'),
	'return' => array('path' => 'string'),
      );
      $result[] = array(
	'name'   => 'db_path',
	'desc'   => 'returns full db path',
	'params' => array('name' => 'string'),
      );
      $result[] = array(
	'name'   => 'db_meta_path',
	'desc'   => 'returns full db path',
	'params' => array('name' => 'string'),
	'return' => array('path' => 'string'),
      );
      $result[] = array(
	'name'   => 'separator',
	'desc'   => 'csv separator',
	'params' => array(),
	'return' => array('separator' => 'string'),
      );
      $result[] = array(
	'name'   => 'separator_en',
	'desc'   => 'csv separator - utf code',
	'params' => array(),
	'return' => array('separator_en' => 'string'),
      );
      $result[] = array(
	'name'   => 'error',
	'desc'   => 'handle error',
	'params' => array('code' => 'string', 'json' => 'boolen'),
	'return' => array('msg' => 'string'),
      );
      $result[] = array(
	'name'   => 'parse',
	'desc'   => 'change dokuwiki syntax to html',
	'params' => array('string' => 'string'),
	'return' => array('content' => 'string'),
      );
      return $result;
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
    function file_path($name='')
    {
	$base_dir = $this->getConf('bases_dir'); 
	return ($base_dir[0] != '/' ? DOKU_INC : '').$base_dir.'/'.$name;
    }
    
    function db_path($name)
    {
	return $this->file_path($name.'.txt');
    }
    function db_meta_path($name)
    {
	return $this->file_path('meta.'.$name.'.txt');
    }
    function separator()
    {
	return '\\';
    }
    function separator_en()
    {
	return '&#92;';
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
    function parse($string)
    {
	$info = array();
	$r_str = str_replace('<br>', "\n", str_replace($this->separator_en(), $this->separator(), $string));
	return p_render('xhtml',p_get_instructions($r_str),$info);
    }
/*    function getMethods(){
      $result = array();
      $result[] = array(
	'name'   => 'get_acl',
	'desc'   => 'returns acl of the current user',
	'params' => array(),
	'return' => array('acl' => 'integer'),
      );
      return $result;
    }
    function get_acl()
    {
	$sesja = reset($_SESSION);
	$grupy = $sesja['auth']['info']['grps'];
	$user = $sesja['auth']['user'];

	$acl = 0;
	if(isset($grupy))
	   $acl = auth_aclcheck($_GET['id'], $user, $grupy);
	return $acl;
    }
    /*
	    if(!function_exists('selfURL'))
	    {
		function selfURL($get_to_remove='') {
		    $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
		    $protocol = strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
		    $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
		    if($get_to_remove == '')
		    return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI'];
		    else
		    return $protocol."://".$_SERVER['SERVER_NAME'].$port.preg_replace('/&'.$get_to_remove.'=[^&]*//*', '', $_SERVER['REQUEST_URI']);
		}
	    }
	    if(!function_exists('wiki_url'))
	    {
		function wiki_url()
		{
		    $self = selfURL();
		    $ex = explode('/', $self);
		    array_pop($ex);
		    return implode('/', $ex);
		}
	    }
	    if(!function_exists('strleft'))
	    {
		function strleft($s1, $s2) {
		    return substr($s1, 0, strpos($s1, $s2));
		}
	    }

     */
}

