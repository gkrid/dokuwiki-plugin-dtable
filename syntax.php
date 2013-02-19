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

	    $bazy_dir = $this->getConf('bases_dir');
	    $tr_hover_color = $this->getConf('tr_hover_backgroundcolor');
	    $BUTTONS = $this->getConf('buttons');
	    $MAX_TABLE_WIDTH = $this->getConf('max_table_width');

	    $NAZWA_BAZY = $data['file'];
	    $NAGLOWKI = $data['fileds']['all'];
	    $KOLUMNY_Z_PLIKAMI = $data['fileds']['file'];
	    $KOLUMNY_Z_DATAMI = $data['fileds']['date'];
	    $SUBMIT_WIDTH = 60;
	    $INPUT_WIDTH = floor(($MAX_TABLE_WIDTH-$SUBMIT_WIDTH)/count($NAGLOWKI))-5;//border około 5px;
	    $renderer->doc .= '
		    <div id="divContext" style="border: 1px solid #8CACBB; display: none; position: fixed">
			    <ul class="cmenu" style="margin: 0; padding: 0.3em; list-style: none !important; background-color: white;">
				    <li><a id="aDodaj" href="#">'.$this->getLang('add').'</a></li>
				<hr style="border: 0; border-bottom: 1px solid #8CACBB; margin: 3px 0px 3px 0px; width: 10em;" />
				<li><a id="aEdytuj" href="#">'.$this->getLang('edit').'</a></li>
				<li><a id="aUsun" href="#">'.$this->getLang('remove').'</a></li>
			</ul>
		</div>
	    ';
	    if(!function_exists('selfURL'))
	    {
		function selfURL($get_to_remove='') {
		    $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
		    $protocol = strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
		    $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
		    if($get_to_remove == '')
		    return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI'];
		    else
		    return $protocol."://".$_SERVER['SERVER_NAME'].$port.preg_replace('/&'.$get_to_remove.'=[^&]*/', '', $_SERVER['REQUEST_URI']);
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

	    $sesja = reset($_SESSION);
	    $grupy = $sesja['auth']['info']['grps'];
	    $user = $sesja['auth']['user'];

	    $id_of_page = explode(':', $_GET['id']);
	    $acl = 0;

	    if(isset($grupy))
	       $acl = auth_aclcheck($_GET['id'], $user, $grupy);

	    $baza = $bazy_dir.'/'.$NAZWA_BAZY.'.txt';
	    $rozdzielacz = '\\';
	    $rozdzielacz_encja = '&#92;';



	    if(isset($grupy) && in_array('user', $grupy))
	    {
		if(isset($_POST['dodaj']))
		{
		    $max_id = 0;
		    $handle = fopen($baza, 'r');
		    if ($handle) {
		    while (($bufor = fgets($handle)) !== false) {
			$dane = explode($rozdzielacz, $bufor);
			if($max_id < (int)$dane[0])
			{
			  $max_id = (int)$dane[0];
			}
		}
		if (!feof($handle)) {
		   $renderer->doc .= $this->getLang('db_error');
		}
		fclose($handle);
		} else
		{
		    $renderer->doc .= $this->getLang('db_error');
		}
		$lines = file($baza);
		if($lines) 
		    $max_id++;
		else
		    $max_id=1;

		$line .= $max_id.$rozdzielacz;
		$handle = fopen($baza, 'w+');
		if (!$handle) {
		    $renderer->doc .= $this->getLang('db_error');
		} else
		{
		    foreach($NAGLOWKI as $v)
		    {  
			$value = str_replace($rozdzielacz, 
				   	     $rozdzielacz_encja,
					     str_replace("\n", "<br>", trim($_POST[md5($v)]))
					    );

		       $line .= $value.$rozdzielacz;
		    }
		    $line = substr($line, 0, -1);
		    $line .= "\n";
		    array_unshift($lines, $line);
		    foreach ($lines as $file_line) { fwrite( $handle, "$file_line"); }
		    fclose($handle);
		    }

		} elseif(isset($_POST['popraw']))
		{
		$id = (int)$_POST['popraw'];
		$lines = file($baza);
		if($lines) 
		{
		$line .= $id.$rozdzielacz;
		foreach($NAGLOWKI as $v)
		{  
		   $value = str_replace($rozdzielacz, $rozdzielacz_encja, str_replace("\n", "<br>", trim($_POST[md5($v)])));
		   $line .= $value.$rozdzielacz;
		}
		$line = substr($line, 0, -1);
		$line .= "\n";

		$handle = fopen($baza, 'w+');
		if (!$handle) {
		  $renderer->doc .= $this->getLang('db_error');
		} else
		{
		  foreach ($lines as $file_line) { 
		    $dane = explode($rozdzielacz, $file_line);
		    if($dane[0] != $id)
		    {
		      fwrite( $handle, "$file_line");
		    } else
		    {
		      fwrite($handle, "$line");
		    }
		  }
		  fclose($handle);
		}
		} else
		{
		  $renderer->doc .= $this->getLang('db_error');
		}
		} elseif(isset($_GET['usun']))
		{
		$id = $_GET['usun'];
		$lines = file($baza);
		if($lines) 
		{
		$handle = fopen($baza, 'w+');
		if (!$handle) {
		  $renderer->doc .= $this->getLang('db_error');
		} else
		{
		  foreach ($lines as $file_line) { 
		    $dane = explode($rozdzielacz, $file_line);
		    if($dane[0] != $id)
		    {
		      fwrite( $handle, "$file_line");
		    }
		  }
		  fclose($handle);
		}
		} else
		{
		  $renderer->doc .= $this->getLang('db_error');
		}

		}
	    }
	    if(!file_exists($bazy_dir))
	    {
	    	mkdir($bazy_dir);
	    }
	    //creata base
	    if(!file_exists($baza)) {
	        $handle = fopen($baza, 'w+');
	        fclose($handle);
	    } 


	if($acl >= 2)
	{
	    $renderer->doc .= '
		<ul id="dtable_context_menu" class="contextMenu">
		    <li class="add_before">
			<a href="#wstaw_przed">Wstaw przed</a>
		    </li>
		    <li class="add_after">
			<a href="#wstaw_za">Wstaw za</a>
		    </li>
		    <li class="edit separator">
			<a href="#edit">Edytuj</a>
		    </li>
		    <li class="delete">
			<a href="#usun">Usuń</a>
		    </li>
		</ul>
		';
	}
	    if(!isset($_GET['edytuj']) && isset($grupy) && in_array('user', $grupy))
		$renderer->doc .= '<form action="'.selfURL().'" method="post">';
	    else
		$renderer->doc .= '<form action="'.selfURL('edytuj').'" method="post">';

	    $renderer->doc .= '<table class="inline" style="position:relative;" id="dtable"><tr>';
	    foreach($NAGLOWKI as $v)
	    {
	      $renderer->doc .= "<th>$v</th>";
	    }
	    $renderer->doc .= '</tr>';

	    if(!isset($_GET['edytuj']) && isset($grupy) && in_array('user', $grupy))
	    {
		$renderer->doc .= '<tr id="aform" style="';
		if(count(file($baza)) != 0)
		$renderer->doc .='display:none;';
		$renderer->doc .= '">';
		$renderer->doc .= '<input type="hidden" value="" name="dodaj">';
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
	    } 
	    	$CON_TO_PRA = '<html>';//content to dokuwkiki parser
		$handle = fopen($baza, 'r');
	      
	    if ($handle) {
		while (($bufor = fgets($handle)) !== false) {
		    $dane = explode($rozdzielacz, $bufor);
		    if(isset($_GET['edytuj']) && (int)$_GET['edytuj'] ==  $dane[0])
		    {
			$CON_TO_PRA .= '<tr id="'.$dane[0].'">';
			$CON_TO_PRA .= '<input type="hidden" value="'.$_GET['edytuj'].'" name="popraw">';
			$i=1;
			foreach($NAGLOWKI as $v)
			{

			  if(is_array($KOLUMNY_Z_PLIKAMI) && in_array($v, $KOLUMNY_Z_PLIKAMI))
			    $CON_TO_PRA .= '<td><span id="aFileName"></span><input type="text" name="'.md5($v).'" id="wiki__text" value="'.$dane[$i].'"><a href="#" id="wstaw_plik">wstaw     plik</a></td>';
			 elseif(is_array($KOLUMNY_Z_DATAMI) && in_array($v, $KOLUMNY_Z_DATAMI))
			    $CON_TO_PRA .= '<td><input type="date" name="'.md5($v).'" value="'.$dane[$i].'" style="width: '.$INPUT_WIDTH.'px"></td>';
			  else
			    $CON_TO_PRA .= '<td><textarea name="'.md5($v).'" style="width: '.$INPUT_WIDTH.'px">'.str_replace("<br>", "\n", $dane[$i]).'</textarea></td>';
			  $i++;
			}

			if($BUTTONS == '1')
			    $CON_TO_PRA .= '<td><input type="submit" style="width: '.$SUBMIT_WIDTH.'px" value="'.$this->getLang('correct').'"></td>';

			$CON_TO_PRA .= '</tr>';
		    } else
		    {
			$CON_TO_PRA .= '<tr id="'.$dane[0].'" class="tr_hover"></html>';
			for($i=1;$i<sizeof($dane);$i++)
			{
			    $CON_TO_PRA .= '<html><td class="con_menu"></html>'.$dane[$i].'<html></td></html>';
			}
			for($i=0;$i<sizeof($NAGLOWKI)-sizeof($dane)+1;$i++)
			{
			   $CON_TO_PRA .= '<html><td class="con_menu"></td></html>';
			}
			// $CON_TO_PRA .= '<td><a href="'.selfURL().'&usun='.$dane[0].'">usuń</a></td>';
			$CON_TO_PRA .= '<html></tr>';
		    }
		}
		if (!feof($handle)) {
		   //$CON_TO_PRA .= $this->getLang('db_error');
		}
		fclose($handle);
	    } else
	    {
	      //$CON_TO_PRA .= $this->getLang('db_error');
	    }

	    $CON_TO_PRA .= '</table></html>';
	    $info = array();
	    $renderer->doc .= p_render('xhtml',p_get_instructions(str_replace($rozdzielacz_encja, $rozdzielacz, $CON_TO_PRA)),$info);
	    $renderer->doc .= '</form>';
	    /*$renderer->doc .= '
	    <script type="text/javascript">
	    jQuery(document).ready( function() {
		dtable.init('.$acl.', "'.selfURL().'", "'.wiki_url().'", "'.$id_of_page[0].'");
	    });
	    </script>
		';*/

            return true;
        }
        return false;
    }
}
