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

    function connectTo($mode) { $this->Lexer->addEntryPattern('<dtable[^>]*>(?=.*</dtable>)',$mode,'plugin_dtable'); }
    function postConnect() { $this->Lexer->addExitPattern('</dtable>','plugin_dtable'); }


    function handle($match, $state, $pos, &$handler) {
        switch ($state) {
          case DOKU_LEXER_ENTER :
	      try {
		$attrs = array();
	      	$xml = new SimpleXMLElement($match.'</dtable>');
		foreach($xml->attributes() as $k => $v )
		{
		    $attrs[$k] = (string)$v;
		}
                return array($state, array($pos, $attrs) );
	      } catch(Exception $e)
	      {
		  return array($state, false);
	      }
 
          case DOKU_LEXER_UNMATCHED :  return array($state, $match);
          case DOKU_LEXER_EXIT :       return array($state, '');
        }
        return array();
    }

    function render($mode, &$renderer, $data) {
	if($mode == 'xhtml')
	{
	   list($state,$match) = $data;
	   switch ($state) {
	     case DOKU_LEXER_ENTER :     

		if($match != false)
		{
		    if (auth_quickaclcheck($ID) >= AUTH_EDIT) 
		    {
			$dtable =& plugin_load('helper', 'dtable');

			$pos = $match[0];
			$attrs = $match[1];

			$id = $attrs['id'];
			$filepath = wikiFN( $id );

			//$match contains charter where dtable starts. 
			//<dtable> is first line
			$start_line = $dtable->line_nr($filepath, $pos) + 1;

			//search for first row 
			$file_cont = explode("\n", io_readWikiPage($filepath, $id));


			$header_line = -1;
			while( $start_line <  count($file_count) && strpos( $file_cont[ $start_line ], '|' ) !== 0 )
			{
			    if( strpos( $file_cont[ $start_line ], '^' ) !== 0 )
				$header_line = $start_line;

			    if( strpos( $file_cont[ $start_line ], '</dtable>' ) !== false )
			    {
				$start_line = $header_line;
				break;
			    }

			    $start_line++;
			}
			
			$start_line++;

			$renderer->doc .= '<form class="dtable dynamic_form" id="dtable_'.$start_line.'_'.$id.'" action="'.$DOKU_BASE.'lib/exe/ajax.php" method="post">';
			$renderer->doc .= '<input type="hidden" value="dtable" name="call">';

		    }
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
