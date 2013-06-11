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
	'name'   => 'error',
	'desc'   => 'handle error',
	'params' => array('code' => 'string', 'json' => 'boolen'),
	'return' => array('msg' => 'string'),
      );
      $result[] = array(
	'name'   => 'line_nr',
	'desc'   => 'Determine line nr in given file using $pos in input.',
	'params' => array('file_path' => 'string', 'pos' => 'int'),
	'return' => array('line_nr' => 'int'),
      );
    }
    function error($code, $json=false)
    {
	if($json == true)
	{
	    $json = new JSON();
	    return $json->encode(array('type' => 'error', 'msg' => $this->getLang($code)));
	} else
	{
	    return $this->getLang($code);
	}
    }
    function line_nr($file_path, $pos)
    {
	$file_cont = io_readFile($file_path);
	$line_nr = 0;
	for($i=0;$i<=$pos;$i++)
	{
	    if($file_cont[$i] == "\n")
		$line_nr++;
	}
	return $line_nr;
    }
}

