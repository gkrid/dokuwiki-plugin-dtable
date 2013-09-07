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
      $result[] = array(
	'name'   => 'get_rows_instructions',
	'desc'   => 'Parse table rows by copy handler.',
	'params' => array('row' => 'string', 'pos' => 'int'),
	'return' => array('output' => 'array'),
      );
      $result[] = array(
	'name'   => 'rows',
	'desc'   => 'Parse single table row using copy handler.',
	'params' => array('row' => 'string', 'page_id' => 'string'),
	'return' => array('table_cells' => 'array'),
      );
      $result[] = array(
	'name'   => 'get_rowspans',
	'desc'   => 'Get rowspans attributes of dokuwiki tables cells.',
	'params' => array('start_line' => 'int', 'page_lines' => 'array', 'page_id' => 'string'),
	'return' => array('table_rowspans' => 'array'),
      );
      $result[] = array(
	'name'   => 'format_row',
	'desc'   => 'Build dokuwiki raw row from array',
	'params' => array('array_line' => 'array'),
	'return' => array('line' => 'array'),
      );
      $result[] = array(
	'name'   => 'parse_line',
	'desc'   => 'Parse dokuwiki table line into html',
	'params' => array('line' => 'string', 'page_id' => 'string'),
	'return' => array('output' => 'string'),
      );
	  return $result;
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
	function get_rows_instructions($row, $page_id)
	{
		$lexer_rules = p_get_metadata($page_id, 'plugin_dtable_lexer_rules');

		$Parser = new Doku_Parser();

		require_once 'dtable_handler.php';

		$Parser->Handler = new Dtable_Doku_Handler();

		$Parser->Lexer = new Doku_Lexer( $Parser->Handler, 'base', TRUE );



		foreach( $lexer_rules['addEntryPattern'] as $pattern )
		{
			$Parser->Lexer->addEntryPattern($pattern[0], 'table', 'copy');
		}
		foreach( $lexer_rules['addPattern'] as $pattern )
		{
			$Parser->Lexer->addPattern($pattern[0], 'copy');
		}
		foreach( $lexer_rules['addExitPattern'] as $pattern )
		{
			$Parser->Lexer->addExitPattern($pattern[0], 'copy');
		}
		foreach( $lexer_rules['addSpecialPattern'] as $pattern )
		{
			$Parser->Lexer->addSpecialPattern($pattern[0], 'table', 'copy');
		}

		$Parser->addMode('table', new Doku_Parser_Mode_table());

		return $Parser->parse($row);
	}
	function rows($row, $page_id)
	{
		$instr = helper_plugin_dtable::get_rows_instructions($row, $page_id);

		$table_cells = array();

		//first table cell is 3 next 6 ...
		$i = 3;
		while( isset( $instr[$i] ) && ($instr[$i][0] == 'tablecell_open' || $instr[$i][0] == 'tableheader_open'))
		{
			$cell = $instr[$i+1][1][0];
			$cell = str_replace('\\\\ ', "\n", $cell);
			// tablecell/tableheader, colspan, value
			$table_cells[] = array($instr[$i][0],$instr[$i][1][0], $cell);
			$i += 3;
		}

		return $table_cells;
		}
	function get_rowspans($start_line, $page_lines, $page_id)
	{
		$len = 1;
		while (strpos($page_lines[$start_line + $len], '|') === 0 || strpos($page_lines[$start_line + $len], '^') === 0)
			$len++;
		$table_lines = array_splice($page_lines, $start_line, $len);

		$table = implode("\n", $table_lines);

		$instr = helper_plugin_dtable::get_rows_instructions($table, $page_id);

		$table_rowspans = array();

		$row = 0;
		$cell = 0;
		for($i = 2; $i < count($instr) - 2; $i++)
		{
			if ($instr[$i][0] == 'tablecell_open' || $instr[$i][0] == 'tableheader_open')
			{
				$rowspan = $instr[$i][1][2];
				$table_rowspans[$row][$cell] = $rowspan;
				$cell++;
			} elseif($instr[$i][0] == 'tablerow_open') {
				$table_rowspans[$row] = array();
				$cell = 0;
			} elseif($instr[$i][0] == 'tablerow_close') {
				$row++;
			}
		}

		return $table_rowspans;

	}
    function format_row($array_line)
    {
		foreach ($array_line as $cell)
		{
			if ($cell[0] == 'tableheader_open')
			{
				$line .= '^'.$cell[1];
			} else
			{
				$line .= '|'.$cell[1];
			}
		}
		if ($array_line[count($array_line) - 1][0] == 'tableheader_open')
		{
			$line .= '^';
		} else
		{
			$line .= '|';
		}
		$line = str_replace("\n", '\\\\ ', $line);

		return $line;
    }
    function parse_line($line, $page_id)
    {
		$line = preg_replace('/\s*:::\s*\|/', '', $line);


		$info = array();
		$html = p_render('xhtml',p_get_instructions($line),$info);

		$maches = array();

		preg_match('/<tr.*?>(.*?)<\/tr>/si', $html, $maches);

		return trim($maches[1]);
    }
}

