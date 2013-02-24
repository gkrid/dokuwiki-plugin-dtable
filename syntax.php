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
class syntax_plugin_dtable extends DokuWiki_Syntax_Plugin {

    function getInfo() {
        return array('author' => 'Szymon Olewniczak',
                     'email'  => 'szymon.olewniczak@rid.pl',
                     'date'   => '2012-07-29',
                     'name'   => 'DTable Plugin',
                     'desc'   => 'Add to your page dynamic table which you can manage by simple GUI',
                     'url'    => 'http://www.dokuwiki.org/plugin:dtable');
    }
    function getPType(){
       return 'block';
    }

    function getType() { return 'substition'; }
    function getSort() { return 32; }


    function connectTo($mode) {
	$this->Lexer->addSpecialPattern('\[dtable.*?\]',$mode,'plugin_dtable');
    }

    function handle($match, $state, $pos, &$handler) {
	$exploded = explode(' ', $match);
	$file = $exploded[1];
	preg_match('/"(.*?)"/', $match, $res);
	$fileds = array();
	preg_match_all('/[[:alnum:]]*\(.*?\)/', $res[1], $fileds_raw);
	foreach($fileds_raw[0] as $filed)
	{
	    preg_match('/(.*?)\((.*?)\)/', $filed, $res2);
	    $fileds[$res2[1]][] = $res2[2];
	    $fileds['all'][] = $res2[2];
	}
	return array('file' => $file, 'fileds' => $fileds);
    }

    function render($mode, &$renderer, $data) {
        if($mode == 'xhtml'){

	    $BUTTONS = $this->getConf('buttons');
	    $MAX_TABLE_WIDTH = $this->getConf('max_table_width');

	    $NAZWA_BAZY = $data['file'];
	    $NAGLOWKI = $data['fileds']['all'];
	    $KOLUMNY_Z_PLIKAMI = $data['fileds']['file'];
	    $KOLUMNY_Z_DATAMI = $data['fileds']['date'];
	    $SUBMIT_WIDTH = 60;
	    $INPUT_WIDTH = floor(($MAX_TABLE_WIDTH-$SUBMIT_WIDTH)/count($NAGLOWKI))-5;//border około 5px;

	    $dtable =& plugin_load('helper', 'dtable');


	$baza = $dtable->db_path($NAZWA_BAZY);
	$baza_meta = $dtable->db_meta_path($NAZWA_BAZY);


	//creata base
	if(!file_exists($baza)) {
	    $handle = fopen($baza, 'w+');
	    fclose($handle);
	} 

	//this data should be cached
	$handle = fopen($baza_meta, 'w');
	$naglowki_md5 = $dtable->md5_array($NAGLOWKI);
	$files_md5 = $dtable->md5_array($KOLUMNY_Z_PLIKAMI);
	$data_md5 = $dtable->md5_array($KOLUMNY_Z_DATAMI);
	fwrite($handle, json_encode(array($naglowki_md5, $files_md5, $data_md5)));
	fclose($handle);


	if (auth_quickaclcheck($ID) >= AUTH_WRITE) 
	{
	    $renderer->doc .= '
		<ul id="dtable_context_menu" class="contextMenu">
		    <li class="insert_before">
			<a href="#insert_before">Wstaw przed</a>
		    </li>
		    <li class="insert_after">
			<a href="#insert_after">Wstaw za</a>
		    </li>
		    <li class="edit separator">
			<a href="#edit">Edytuj</a>
		    </li>
		    <li class="remove">
			<a href="#remove">Usuń</a>
		    </li>
		</ul>
		';
	}

	    $renderer->doc .= '<form id="dtable_form" action="'.$DOKU_BASE.'lib/exe/ajax.php" method="post">';

	    $renderer->doc .= '<input type="hidden" name="table" value="'.$NAZWA_BAZY.'" >';
	    $renderer->doc .= '<input type="hidden" name="call" value="dtable" >';
	    $renderer->doc .= '<input type="hidden" id="dtable_action" name="add" value="-1" >';

	    $renderer->doc .= '<table id="dtable_'.$NAZWA_BAZY.'"><tr>';
	    foreach($NAGLOWKI as $v)
	    {
	      $renderer->doc .= "<th>$v</th>";
	    }
	    $renderer->doc .= '</tr>';

	    $renderer->doc .= '<tr class="form" style="';
	    if(count(file($baza)) != 0)
		$renderer->doc .='display:none;';
	    $renderer->doc .= '">';
		foreach($NAGLOWKI as $v)
		{
		  if(is_array($KOLUMNY_Z_PLIKAMI) && in_array($v, $KOLUMNY_Z_PLIKAMI))
		    $renderer->doc .= '<td><span id="aFileName"></span><input type="text" name="'.md5($v).'" id="wiki__text"><a href="#" id="wstaw_plik">'.$this->getLang('upload_file').'</a></td>';
		  elseif(is_array($KOLUMNY_Z_DATAMI) && in_array($v, $KOLUMNY_Z_DATAMI))
		    $renderer->doc .= '<td><input type="date" name="'.md5($v).'" style="width: '.$INPUT_WIDTH.'px" /></td>';
		  else
		    $renderer->doc .= '<td><textarea name="'.md5($v).'" style="width: '.$INPUT_WIDTH.'px"></textarea></td>';
		}
		if($BUTTONS == '1')
		    $renderer->doc .= '<td><input type="submit" style="width: '.$SUBMIT_WIDTH.'px" value="'.$this->getLang('add').'"></td>';

		$renderer->doc .= '</tr>';
	    	$CON_TO_PRA = '<html>';//content to dokuwkiki parser
		$handle = fopen($baza, 'r');
	      
	    if (!$handle) 
	      exit($this->getLang('db_error'));

		while (($bufor = fgets($handle)) !== false) {
		    $dane = explode($dtable->separator(), $bufor);
			$CON_TO_PRA .= '<tr id="'.$dane[0].'" class="tr_hover"></html>';
			for($i=1;$i<sizeof($dane);$i++)
			{
			    $CON_TO_PRA .= '<html><td></html>'.$dane[$i].'<html></td></html>';
			}
			$CON_TO_PRA .= '<html></tr>';
		}
		if (!feof($handle)) {
		   $CON_TO_PRA .= $this->getLang('db_error');
		}
		fclose($handle);

	    $CON_TO_PRA .= '</table></html>';

	    $renderer->doc .= $dtable->parse(str_replace('<br>', "\n", str_replace($dtable->separator_en(), $dtable->separator(), $CON_TO_PRA)));
	    $renderer->doc .= '</form>';


            return true;
        }
        return false;
    }
}
