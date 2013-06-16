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
    function rows_count($line)
    {
	return substr_count($line, '|') - 1;
    }
    function rows($row)
    {
	if(strpos($row, '|') !== 0)
	    return false;

	$row = substr( $row, 0, -1 );
	$row = substr( $row, 1 );
	return explode('|', $row);
    }
    function has_triple_colon($row, $col_nr)
    {

	if( ($row_array = helper_plugin_dtable::rows($row) ) === false )
	    return false;

	if($row_array[$col_nr] == ':::')
	    return true;
	else
	    return false;
    }
    function get_rowspans($start_line_str, $table_line, $dtable_start_line, $page_lines)
    {
	$table_rows =  helper_plugin_dtable::rows_count($start_line_str);
	$rowspans = array();


	for( $j = 0; $j < $table_rows; $j++ )
	{
	    if( helper_plugin_dtable::has_triple_colon($start_line_str, $j) )
	    {
		$rowspan_val = 1;
		$i = $table_line - 1;
		$line = $page_lines[ $i + $dtable_start_line ];
		while( helper_plugin_dtable::has_triple_colon($line, $j) )
		{
		    $i--;
		    $rowspan_val++;

		    $line = $page_lines[ $i + $dtable_start_line ];
		}
		//eq can be negative becouse $dtable_start_line is 0 for first row that isn't th
		$eq = $i;

		$i = $table_line ;//+1
		$line = $page_lines[ $i + $dtable_start_line ];


		while( helper_plugin_dtable::has_triple_colon($line, $j) )
		{
		    $i++;
		    $rowspan_val++;
		    $line = $page_lines[ $i + $dtable_start_line ];
		}
		$rowspans[] = array('tr' => $eq, 'td' => $j, 'val' => $rowspan_val);

	    } else
	    {
		$next_line = $page_lines[ $table_line + 1 +$dtable_start_line ];
		if( helper_plugin_dtable::has_triple_colon($next_line, $j) )
		{
		    //$eq = $table_line - 1;
		    
		    $rowspan_val = 2;

		    $i = $table_line - 1;
		    $line = $page_lines[ $i + $dtable_start_line ];
		    while( helper_plugin_dtable::has_triple_colon($line, $j) )
		    {
			$i--;
			$rowspan_val++;

			$line = $page_lines[ $i + $dtable_start_line ];
		    }
		    //eq can be negative becouse $dtable_start_line is 0 for first row that isn't th
		    $eq = $i;

		    $i = $table_line + 2;

		    $line = $page_lines[ $i + $dtable_start_line ];
		    while( helper_plugin_dtable::has_triple_colon($line, $j) )
		    {
			$i++;
			$rowspan_val++;
			
			$line = $page_lines[ $i + $dtable_start_line ];
		    }
		    $rowspans[] = array('tr' => $eq, 'td' => $j, 'val' => $rowspan_val);
		}
	    }
	}

	for( $j = 0; $j < count( $rowspans ); $j++)
	{
	    if( $row = helper_plugin_dtable::rows($page_lines [ $rowspans[$j]['tr'] + $dtable_start_line ] ) )
	    {
		for( $i = 0; $i < $rowspans[$j]['td']; $i++ )
		{
		    if( $row[ $i ] == ':::' )
		    {
			$rowspans[$j]['td']--;
		    }
		}
	    }
	}
	return $rowspans;
    }
    function format_row($array_line)
    {
	$line = implode('|', $array_line);
	$line = '|'.$line.'|';

	return $line;
    }
    function parse_line($line)
    {
	$rows = helper_plugin_dtable::rows($line);

	$cells = array();
	foreach($rows as $row)
	{
	    if($row != ':::')
		$cells[] = $row;
	}

	$line = helper_plugin_dtable::format_row($cells);

	$info = array();
	$html = p_render('xhtml',p_get_instructions($line),$info);

	$maches = array();

	preg_match('/<tr.*?>\s*(.*?)\s*<\/tr>/', $html, $maches);

	return $maches[1];
    }
}

