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
	static $line_nr_c = array();
	static $file_cont = NULL;

    function error($code, $json=false)
    {
		if($json == true) {
			$json = new JSON();
			return $json->encode(array('type' => 'error', 'msg' => $this->getLang($code)));
		} else {
			return $this->getLang($code);
		}
    }

    function line_nr($pos, $file_path, $start_line = 0) {
		////dbglog("start line:".$start_line);
		////dbglog("Start line: $start_line");*/
		$line_nr = 0;
		if (!is_array(self::$line_nr_c[$file_path])) {
			self::$line_nr_c[$file_path] = array();
			$start_pos = 0;
			$line_nr = 0;
		} else {
			$start_pos = count(self::$line_nr_c[$file_path]) - 1;
			$line_nr = self::$line_nr_c[$file_path][count(self::$line_nr_c[$file_path]) - 1];
		}
		/*dbglog("Cache:");
		dbglog(self::$line_nr_c);*/

		if ($start_line > 0) {
			////dbglog("Arary reverse: ", array_reverse(self::$line_nr_c[$file_path]));
			//find last pos on current line
			if (($find = array_search($start_line, self::$line_nr_c[$file_path])) !== false) {
				//$find = array_search($start_line -1, array_reverse(self::$line_nr_c[$file_path], true)); 
				//dbglog("start line found");
				//the new line charter from last line -> it's nessesery in order to corect work of my handler
				$start_pos = $find;
				$pos += $find;
			} else {
				////dbglog("start line NOT found, sart_pos $start_pos start line $line_nr < $start_line ".strlen(self::$file_cont));
				if (self::$file_cont == NULL)
					self::$file_cont = io_readFile($file_path);	

				for($i = $start_pos; $i < strlen(self::$file_cont) && $line_nr < $start_line; $i++) {
					////dbglog("COUNTER:".self::$file_cont[$i]." $i");
					self::$line_nr_c[$file_path][$i] = $line_nr;
					if(self::$file_cont[$i] == "\n")
						$line_nr++;
				}
				self::$line_nr_c[$file_path][$i] = $line_nr;

				$pos += $i;
				$start_pos  = $i;
			}
			$line_nr = $start_line;
			/*dbglog("$pos, $line_nr, $start_pos, $line_nr");
			dbglog(self::$line_nr_c);*/

		}
		if ($start_pos >= $pos) {
			/*dbglog("TYRANOZAUR");
			dbglog(self::$line_nr_c);*/
			return self::$line_nr_c[$file_path][$pos];
		} else {
			if (self::$file_cont == NULL)
				self::$file_cont = io_readFile($file_path);	

			////dbglog("Rozpocznij pętlę pierwotną");
			////dbglog(self::$line_nr_c);

			for($i=$start_pos;$i <= $pos; $i++)
			{
				self::$line_nr_c[$file_path][$i] = $line_nr;
				if(self::$file_cont[$i] == "\n")
					$line_nr++;
			}
			////dbglog(self::$line_nr_c);
			//dbglog("pos: $i");

			return self::$line_nr_c[$file_path][$pos];
		}
    }
	function get_rows_instructions($row, $page_id, $start_line)
	{
		/*$lexer_rules = p_get_metadata($page_id, 'plugin_dtable_lexer_rules');

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

		return $Parser->parse($row);*/

		$Parser = new Doku_Parser();

		$Parser->Handler = new helper_plugin_dtable_handler($page_id, $start_line);

        //add modes to parser
		$modes = p_get_parsermodes();
        foreach($modes as $mode) {
            $Parser->addMode($mode['mode'], $mode['obj']);
		}
		//$Parser->addMode('table', new Doku_Parser_Mode_table());

		////dbglog("Porser begin");
		dbglog(var_export($Parser->parse($row), true));
		////dbglog("Porser end");

		return $Parser->parse($row);
		
	}
	function rows($row, $page_id, $start_line)
	{
		$instr = self::get_rows_instructions($row, $page_id, $start_line);
		return $instr;

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
	function get_spans($start_line, $page_lines, $page_id) {
		$table = '';
		for ($i = $start_line; trim($page_lines[$i]) != '</dtable>'; $i++) {
			$table .= $page_lines[$i]."\n";
		}

		$spans = array();
		$rows = self::rows($table, $page_id, $start_line);
		for ($i = 0; $i < count($rows); $i++) {
			for ($j = 0; $j < count($rows[$i][0]); $j++) {
				$spans[$i][$j][0] = $rows[$i][0][$j][0];
				$spans[$i][$j][1] = $rows[$i][0][$j][1];
			}
		}
		return $spans;
	}
	/*function get_rowspans($start_line, $page_lines, $page_id)
	{
		$len = 1;
		while (strpos($page_lines[$start_line + $len], '|') === 0 || strpos($page_lines[$start_line + $len], '^') === 0)
			$len++;
		$table_lines = array_splice($page_lines, $start_line, $len);

		$table = implode("\n", $table_lines);

		$instr = self::get_rows_instructions($table, $page_id);

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

	}*/
    function format_row($array_line) {
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

class helper_plugin_dtable_handler {
	public $calls = NULL;
	public $row = 0;
	public $cell = 0;
	//public $cell_start = false;
	public $type;
	public $file_path;
	public $start_line;
	
	public function __construct($page_id, $start_line) {
		$this->file_path = wikiFN($page_id);
		$this->start_line = $start_line;
	}

    function table($match, $state, $pos) {
		//dbglog("ALA I AS");
		//dbglog($match);
		////dbglog($state);
		//dbglog($pos);
		//dbglog("ALA I AS END");
        switch ( $state ) {

            case DOKU_LEXER_ENTER:

/*                $ReWriter = new Doku_Handler_Table($this->CallWriter);
                $this->CallWriter = & $ReWriter;

                $this->_addCall('table_start', array($pos + 1), $pos);
                if ( trim($match) == '^' ) {
                    $this->_addCall('tableheader', array(), $pos);
                } else {
                    $this->_addCall('tablecell', array(), $pos);
				}*/
                /*$this->_addCall('table_start', array($pos + 1), $pos);
                if ( trim($match) == '^' ) {
                    $this->_addCall('tableheader', array(), $pos);
                } else {
                    $this->_addCall('tablecell', array(), $pos);
				}*/
				$type = trim($match);

				$this->calls = array();

				$line = helper_plugin_dtable::line_nr($pos, $this->file_path, $this->start_line);

				$this->calls[$this->row][0][$this->cell] = array(1, 1, $type, '');
				$this->calls[$this->row][1][0] = $line;
				/*dbglog("MONGO2");
				//dbglog($pos);
				//dbglog($this->start_line);
				dbglog($line);
				exit(0);
				dbglog("EEND MONGO2");*/

            break;

            case DOKU_LEXER_EXIT:
                //$this->_addCall('table_end', array($pos), $pos);
                //$this->CallWriter->process();
                //$ReWriter = & $this->CallWriter;
				//$this->CallWriter = & $ReWriter->CallWriter;
				/*if (is_array($this->calls)) {
					$this->calls[$this->row][1] = array_unique($this->calls[$this->row][1]);
				}*/
				$line = helper_plugin_dtable::line_nr($pos, $this->file_path, $this->start_line);
				$this->calls[$this->row][1][1] = $line - 1;


            break;

            case DOKU_LEXER_UNMATCHED:
                /*if ( trim($match) != '' ) {
                    $this->_addCall('cdata',array($match), $pos);
				}*/
				if (is_array($this->calls)) {
					if ( trim($match) != '' ) {

						$this->calls[$this->row][0][$this->cell][3] .= $match;
							//$this->calls[$this->row][1][] = helper_plugin_dtable::line_nr($pos, $this->file_path, $this->start_line);
							//$this->calls[$this->row][0][$this->cell][3 = array(0, 0, $this->type, $match);

					} /*else {
						//colspan
						$this->calls[$this->row][0][$this->cell][0]++;
					}*/
				}
            break;

            case DOKU_LEXER_MATCHED:
                /*if ( $match == ' ' ){
                    $this->_addCall('cdata', array($match), $pos);
                } else if ( preg_match('/:::/',$match) ) {
                    $this->_addCall('rowspan', array($match), $pos);
                } else if ( preg_match('/\t+/',$match) ) {
                    $this->_addCall('table_align', array($match), $pos);
                } else if ( preg_match('/ {2,}/',$match) ) {
                    $this->_addCall('table_align', array($match), $pos);
                } else if ( $match == "\n|" ) {
                    $this->_addCall('table_row', array(), $pos);
                    $this->_addCall('tablecell', array(), $pos);
                } else if ( $match == "\n^" ) {
                    $this->_addCall('table_row', array(), $pos);
                    $this->_addCall('tableheader', array(), $pos);
                } else if ( $match == '|' ) {
                    $this->_addCall('tablecell', array(), $pos);
                } else if ( $match == '^' ) {
                    $this->_addCall('tableheader', array(), $pos);
				}
				 */
				/*////dbglog(var_export("MONGO", true));
				////dbglog(var_export($this->calls, true));
				////dbglog(var_export($match, true));*/
                if ( preg_match('/:::/',$match) ) {
					$this->calls[$this->row][0][$this->cell][3] .= $match;
				} else if ( $match == ' ' ){
					/*$this->cell++;
					$this->calls[$this->row][0][$this->cell] = array(0, 0, $this->type, $match);
					$this->calls[$this->row][1][] = helper_plugin_dtable::line_nr($pos, $this->file_path, $this->start_line);*/
					$this->calls[$this->row][0][$this->cell][3] .= $match;
				} else {
					$row = $this->row;
					while (preg_match('/^\s*:::\s*$/', $this->calls[$row][0][$this->cell][3]) && $row > 0) {
						$row--;
					}
					if ($row != $this->row)
						$this->calls[$row][0][$this->cell][1]++;

					if ( $match[0] == "\n") {
						$line = helper_plugin_dtable::line_nr($pos, $this->file_path, $this->start_line);
						$this->calls[$this->row][1][1] = $line - 1;

						//remove last cell and -- the celsapn it doesn't exist
						array_pop($this->calls[$this->row][0]);

						$this->row++;
						$this->calls[$this->row] = array(array(), array());

						$this->cell = 0;
						$type = $match[1];

						$this->calls[$this->row][1][0] = $line;

						$this->calls[$this->row][0][$this->cell] = array(1, 1, $type, '');
					} else {
						if ($this->calls[$this->row][0][$this->cell][3] == '' && $this->cell > 0) {
							$this->calls[$this->row][0][$this->cell - 1][0]++;
							array_pop($this->calls[$this->row][0]);
						} else {
							$this->cell++;
						}
						$type = $match[0];
						$this->calls[$this->row][0][$this->cell] = array(1, 1, $type, '');

					}
					//$this->calls[$this->row][1][] = helper_plugin_dtable::line_nr($pos, $this->file_path, $this->start_line) - 1;
				}
            break;
        }
        return true;
    }
    /**
	* Catchall handler for the remaining syntax
	*
	* @param string $name Function name that was called
	* @param array $params Original parameters
	* @return bool If parsing should be continue
	*/
    public function __call($name, $params) {
        if (count($params) == 3) {
			/*//dbglog($name);
			//dbglog('start line:,'.helper_plugin_dtable::line_nr($params[2], $this->file_path, $this->start_line));
			//dbglog($params[2]);*/
			$this->calls[$this->row][0][$this->cell][3] .= $params[0];
			//$this->calls[$this->row][1][] = helper_plugin_dtable::line_nr($params[2], $this->file_path, $this->start_line);
            return true;
        } else {
            trigger_error('Error, handler function '.hsc($name).' with '.count($params).' parameters called which isn\'t implemented', E_USER_ERROR);
            return false;
        }
    }
	public function _finalize() {
		// remove padding that is added by the parser in parse()
        //$this->calls = substr($this->calls, 1, -1);
	
		//Unique lines of rows
		//var_dump($this->calls, $this->row);
		
		/*for ($i = 0; $i <= $this->row; $i++)
		$this->calls[$i][1] = array_unique($this->calls[$i][1]);*/

		//remove last cell and -- the celsapn it doesn't exist
		array_pop($this->calls[$this->row][0]);

		$this->row = 0;
		$this->cell = 0;
	}
}

