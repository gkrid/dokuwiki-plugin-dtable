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
class syntax_plugin_dtable_standalone extends DokuWiki_Syntax_Plugin {

    function getPType(){
       return 'block';
    }

    function getType() { return 'container'; }
    function getSort() { return 400; }
    function getAllowedTypes() {return array('container','formatting','substition');} 

    function connectTo($mode) { $this->Lexer->addEntryPattern('<dtable>(?=.*</dtable>)',$mode,'plugin_dtable_standalone'); }
    function postConnect() { $this->Lexer->addExitPattern('</dtable>','plugin_dtable_standalone'); }


    function handle($match, $state, $pos, &$handler) {
        switch ($state) {
          case DOKU_LEXER_ENTER :
                return array($state, '');
 
          case DOKU_LEXER_UNMATCHED :  return array($state, $match);
          case DOKU_LEXER_EXIT :       return array($state, '');
        }
        return array();
    }

    function render($mode, &$renderer, $data) {
	global $ID;
	if($mode == 'xhtml')
	{
	   list($state,$match) = $data;
	   switch ($state) {
	     case DOKU_LEXER_ENTER :     

		$MAX_TABLE_WIDTH = $this->getConf('max_table_width');

		$SUBMIT_WIDTH = 60;
		//$INPUT_WIDTH = floor(($MAX_TABLE_WIDTH-$SUBMIT_WIDTH)/count($NAGLOWKI))-5;//border około 5px;

		$dtable =& plugin_load('helper', 'dtable');


	    $naglowki_md5 = $dtable->md5_array($NAGLOWKI);

	    if (auth_quickaclcheck($ID) >= AUTH_EDIT) 
	    {
		$renderer->doc .= '
		    <ul id="dtable_context_menu" class="contextMenu">
			<li class="insert_before">
			    <a href="#insert_before">'.$this->getLang('insert_before').'</a>
			</li>
			<li class="insert_after">
			    <a href="#insert_after">'.$this->getLang('insert_after').'</a>
			</li>
			<li class="edit separator">
			    <a href="#edit">'.$this->getLang('edit').'</a>
			</li>
			<li class="remove">
			    <a href="#remove">'.$this->getLang('remove').'</a>
			</li>
		    </ul>
		    ';

		$renderer->doc .= '<form class="dtable_form" id="dtable_form_'.$NAZWA_BAZY.rand(1,1000000).'" action="'.$DOKU_BASE.'lib/exe/ajax.php" method="post">';

		$renderer->doc .= '<input type="hidden" name="table" value="'.$NAZWA_BAZY.'" >';
		$renderer->doc .= '<input type="hidden" name="call" value="dtable" >';
		$renderer->doc .= '<input type="hidden" class="dtable_action" name="add" value="-1" >';

		$renderer->doc .= '<input type="hidden" name="id" value="'.$ID.'">';
	    }
	    break;

	    /*$renderer->doc .= '<table id="dtable_'.$NAZWA_BAZY.'"><tr>';
	    foreach($NAGLOWKI as $v)
	    {
	      $renderer->doc .= "<th>$v</th>";
	    }
	    $renderer->doc .= '</tr>';

	    $renderer->doc .= '<tr class="form" style="';
	    if(count(file($baza)) != 0)
		$renderer->doc .='display:none;';
	    $renderer->doc .= '">';
	    $renderer->doc .= '<tr class="form" style="';
	    if(count(file($baza)) != 0)
		$renderer->doc .='display:none;';
	    $renderer->doc .= '">';
		foreach($NAGLOWKI as $v)
		{
		  if(is_array($KOLUMNY_Z_DATAMI) && in_array($v, $KOLUMNY_Z_DATAMI))
		    $renderer->doc .= '<td><input type="date" name="'.md5($v).'" style="width: '.$INPUT_WIDTH.'px" /></td>';
		  else
		    $renderer->doc .= '<td><textarea name="'.md5($v).'" style="width: '.$INPUT_WIDTH.'px"></textarea></td>';
		}

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

	     */
	     case DOKU_LEXER_UNMATCHED :  $renderer->doc .= $renderer->_xmlEntities($match); break;
	     case DOKU_LEXER_EXIT :     
		if (auth_quickaclcheck($ID) >= AUTH_EDIT) 
		    $renderer->doc .= "</form>"; 
		
		break;
	   }
	    return true;
	}
        return false;
    }
}
