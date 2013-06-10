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

    function getPType(){
       return 'block';
    }

    function getType() { return 'container'; }
    function getSort() { return 400; }
    function getAllowedTypes() {return array('container','formatting','substition');} 

    function connectTo($mode) { $this->Lexer->addEntryPattern('<dtable>(?=.*</dtable>)',$mode,'plugin_dtable'); }
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

		//$INPUT_WIDTH = floor(($MAX_TABLE_WIDTH-$SUBMIT_WIDTH)/count($NAGLOWKI))-5;//border około 5px;

		$dtable =& plugin_load('helper', 'dtable');


	    $naglowki_md5 = $dtable->md5_array($NAGLOWKI);

	    if (auth_quickaclcheck($ID) >= AUTH_EDIT) 
	    {
		$renderer->doc .= '<form class="dtable" id="dtable_form_'.$NAZWA_BAZY.rand(1,1000000).'" action="'.$DOKU_BASE.'lib/exe/ajax.php" method="post">';

	    }
	    break;

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
