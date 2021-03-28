<?php
// must be run within DokuWiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'syntax.php';
require_once DOKU_INC.'inc/parser/parser.php';

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
		$line_nr = 0;
		if (!is_array(self::$line_nr_c[$file_path])) {
			self::$line_nr_c[$file_path] = array();
			$start_pos = 0;
			$line_nr = 0;
		} else {
			$start_pos = count(self::$line_nr_c[$file_path]) - 1;
			$line_nr = self::$line_nr_c[$file_path][count(self::$line_nr_c[$file_path]) - 1];
		}

		if ($start_line > 0) {
			//find last pos on current line
			if (($find = array_search($start_line, self::$line_nr_c[$file_path])) !== false) {
				//the new line charter from last line -> it's nessesery in order to corect work of my handler
				$start_pos = $find;
				$pos += $find;
			} else {
				if (self::$file_cont == NULL)
					self::$file_cont = io_readFile($file_path);	

				for($i = $start_pos; $i < strlen(self::$file_cont) && $line_nr < $start_line; $i++) {
					self::$line_nr_c[$file_path][$i] = $line_nr;
					if(self::$file_cont[$i] == "\n")
						$line_nr++;
				}
				self::$line_nr_c[$file_path][$i] = $line_nr;

				$pos += $i;
				$start_pos  = $i;
			}
			$line_nr = $start_line;

		}
		if ($start_pos >= $pos) {
			/*dbglog("TYRANOZAUR");
			dbglog(self::$line_nr_c);*/
			return self::$line_nr_c[$file_path][$pos];
		} else {
			if (self::$file_cont == NULL)
				self::$file_cont = io_readFile($file_path);	

			for($i=$start_pos;$i <= $pos; $i++)
			{
				self::$line_nr_c[$file_path][$i] = $line_nr;
				if(self::$file_cont[$i] == "\n")
					$line_nr++;
			}
			return self::$line_nr_c[$file_path][$pos];
		}
    }
	function rows($row, $page_id, $start_line)
	{
		$Parser = new Doku_Parser();

		$Parser->Handler = new helper_plugin_dtable_handler($page_id, $start_line);

        //add modes to parser
		$modes = p_get_parsermodes();
        foreach($modes as $mode) {
            $Parser->addMode($mode['mode'], $mode['obj']);
		}

		return $Parser->parse($row);
		
	}
	function get_spans($start_line, $page_lines, $page_id) {
		$table = '';
		for ($i = $start_line; trim($page_lines[$i]) != '</dtable>' && $i < count($page_lines); $i++) {
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
	public $type;
	public $file_path;
	public $start_line;
	
	public function __construct($page_id, $start_line) {
		$this->file_path = wikiFN($page_id);
		$this->start_line = $start_line;
	}

    function table($match, $state, $pos) {
        switch ( $state ) {

            case DOKU_LEXER_ENTER:

				$type = trim($match);

				$this->calls = array();

				$line = helper_plugin_dtable::line_nr($pos, $this->file_path, $this->start_line);

				$this->calls[$this->row][0][$this->cell] = array(1, 1, $type, '');
				$this->calls[$this->row][1][0] = $line;

            break;

            case DOKU_LEXER_EXIT:
				$line = helper_plugin_dtable::line_nr($pos, $this->file_path, $this->start_line);
				$this->calls[$this->row][1][1] = $line - 1;


            break;

            case DOKU_LEXER_UNMATCHED:
				if (is_array($this->calls)) {
					$this->calls[$this->row][0][$this->cell][3] .= $match;
				}
            break;

            case DOKU_LEXER_MATCHED:
                if ( preg_match('/:::/',$match) ) {
					$this->calls[$this->row][0][$this->cell][3] .= $match;
				} else if ( trim($match) == '' ){
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
				}
            break;
        }
        return true;
    }
	/** Change // into \n during editing in textbox, can be turn off if not needed. */
    public function linebreak($match, $state, $pos) {
		$this->calls[$this->row][0][$this->cell][3] .= "\n";
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
		$this->calls[$this->row][0][$this->cell][3] .= $params[0];
		return true;
    }
	public function _finalize() {
		//remove last cell and -- the celsapn it doesn't exist
		array_pop($this->calls[$this->row][0]);
		$this->row = 0;
		$this->cell = 0;
	}
}

