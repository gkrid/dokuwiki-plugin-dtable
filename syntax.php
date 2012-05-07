<?php
/**
 * Plugin Now: Inserts a timestamp.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>
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
        return array('author' => 'me',
                     'email'  => 'me@someplace.com',
                     'date'   => '2005-07-28',
                     'name'   => 'Now Plugin',
                     'desc'   => 'Include the current date and time',
                     'url'    => 'http://www.dokuwiki.org/devel:syntax_plugins');
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
	    //$renderer->doc .= var_export($data, true);

	    $NAZWA_BAZY = $data['file'];
	    $NAGLOWKI = $data['fileds']['all'];
	    $KOLUMNY_Z_PLIKAMI = $data['fileds']['file'];
	    $KOLUMNY_Z_DATAMI = $data['fileds']['date'];

	    $renderer->doc .= '
		    <div id="divContext" style="border: 1px solid #8CACBB; display: none; position: fixed">
			    <ul class="cmenu" style="margin: 0; padding: 0.3em; list-style: none !important; background-color: white;">
				    <li><a id="aDodaj" href="#">dodaj</a></li>
				<hr style="border: 0; border-bottom: 1px solid #8CACBB; margin: 3px 0px 3px 0px; width: 10em;" />
				<li><a id="aEdytuj" href="#">edytuj</a></li>
				<li><a id="aUsun" href="#">usuń</a></li>
			</ul>
		</div>
	    ';

	    function selfURL($get_to_remove='') {
	    $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
	    $protocol = strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
	    $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
	    if($get_to_remove == '')
	    return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI'];
	    else
	    return $protocol."://".$_SERVER['SERVER_NAME'].$port.preg_replace('/&'.$get_to_remove.'=[^&]*/', '', $_SERVER['REQUEST_URI']);
	    }
	    function strleft($s1, $s2) {
	    return substr($s1, 0, strpos($s1, $s2));
	    }


	    $sesja = reset($_SESSION);
	    $grupy = $sesja['auth']['info']['grps'];

	    $id_of_page = explode(':', $_GET['id']);
	    $renderer->doc .= '
	    <script type="text/javascript">
	    window.onload = function()
	    {';

	    if(isset($grupy) && in_array('user', $grupy)) { 
	    $renderer->doc .='
	    var add_file = document.getElementById("wstaw_plik");
	    add_file.onclick = function()
	    {
	    window.open("http://wiki.rid.pl/lib/exe/mediamanager.php?ns='.$id_of_page[0].'&edid=wiki__text", "pliki","width=800,height=600");
	    }

	    // comes from prototype.js; this is simply easier on the eyes and fingers
	    function id(id)
	    {
	    return document.getElementById(id);
	    }
	    function clear_tr_hover()
	    {
		var hover_tr = document.getElementsByClassName("tr_hover");
		for(i=0;i<hover_tr.length;i++)
		{
			hover_tr[i].style.backgroundColor="#fff";

		}
	    }
	    id("aDodaj").onclick = function()
	    {
	    id("aform").style.display = "table-row";
	    id("divContext").style.display = "none";
	    clear_tr_hover();
	    var td = id("aform").getElementsByTagName("td");
	    var td0 = td[0];
	    td0.firstChild.focus();
	    } 
	    document.ondblclick = function()
	    {
	    form = document.getElementsByTagName("form");
	    form = form[0];
	    var any_not_blank = false;
	    var inputs = form.getElementsByTagName("input");
	    for(var i=0;i<inputs.length;i++)
	    {
		console.log(inputs[i]);
		if(inputs[i].value != "")
		{
		  any_not_blank = true;
		  break;
		}
	    }
	    if(any_not_blank == false)
	    {
		var text = form.getElementsByTagName("textarea");
		for(var i=0;i<text.length;i++)
		{
		    if(text[i].textContent != "")
		    {
		      any_not_blank = true;
		      break;
		    }
		}
	    }
	    if(any_not_blank == true)
	    {
		form.submit();
	    } else
	    {
		id("aform").style.display = "none";
	    }
	    }
	    var add_events = function(nodes) {
	    for(var i=0;i<nodes.length;i++)
	    {
	    nodes[i].ondblclick = function(e) {
	     var event = e || window.event;

	     if (event.stopPropagation) {
	       event.stopPropagation();
	     } else {
	       event.cancelBubble = true;
	    } 
	    }
	    }
	    }
	    var inputs_elm = document.getElementsByTagName("input");
	    var textarea_elm = document.getElementsByTagName("textarea"); 
	    add_events(inputs_elm);
	    add_events(textarea_elm);

	    var _replaceContext = false;		// replace the system context menu?
	    var _mouseOverContext = false;		// is the mouse over the context menu?
	    var _divContext = id("divContext");	// makes my life easier

	    InitContext();

	    function InitContext()
	    {
		_divContext.onmouseover = function() { _mouseOverContext = true; };
		_divContext.onmouseout = function() { _mouseOverContext = false; };

		document.body.onmousedown = ContextMouseDown;
		document.body.oncontextmenu = ContextShow;
	    }

	    // call from the onMouseDown event, passing the event if standards compliant
	    function ContextMouseDown(event)
	    {
		if (_mouseOverContext)
			return;

		// IE is evil and doesnt pass the event object
		if (event == null)
			event = window.event;

		// we assume we have a standards compliant browser, but check if we have IE
		var target = event.target != null ? event.target : event.srcElement;

		nestedClass = false;

		elm = target;

		while(elm.parentNode != document)
		{
		    elm.hasClass = function(cl) {
		      var classes = this.className;
		      if (classes.indexOf(cl) != -1)
			return true;
		     return false; 
		    };
		    if(elm.hasClass("con_menu"))
		    {
			    nestedClass=true;
			    break;
		    }
		    elm = elm.parentNode;
		}

		// only show the context menu if the right mouse button is pressed
		//   and a hyperlink has been clicked (the code can be made more selective)

		if (event.button == 2 && nestedClass == true)
			_replaceContext = true;
		else if (!_mouseOverContext)
		{
			_divContext.style.display = "none";

			clear_tr_hover();

		}
	    }
	    function isNumber(n) {
		  return !isNaN(parseFloat(n)) && isFinite(n);
	    }
	    // call from the onContextMenu event, passing the event
	    // if this function returns false, the browsers context menu will not show up
	    function ContextShow(event)
	    {
		if (_mouseOverContext)
			return;

		// IE is evil and doesnt pass the event object
		if (event == null)
			event = window.event;

		// we assume we have a standards compliant browser, but check if we have IE
		var target = event.target != null ? event.target : event.srcElement;

		if (_replaceContext)
		{
			clear_tr_hover();
			target.parentNode.style.backgroundColor="#EFF3F6";
			
			var tr_id;
			
			elm = target.parentNode;

			while(elm.parentNode != document)
			{
			    if(isNumber(elm.id))
			    {
				    tr_id=elm.id;
				    break;
			    }
			    elm = elm.parentNode;
			}
			id("aUsun").href = "'.selfURL().'&usun=" + tr_id;
			id("aEdytuj").rel = tr_id;
			id("aEdytuj").href = "'.selfURL('edytuj').'&edytuj=" + tr_id;

			// hide the menu first to avoid an "up-then-over" visual effect
			_divContext.style.display = "none";

			_divContext.style.left = event.clientX  + "px";
			_divContext.style.top = event.clientY  + "px";
			_divContext.style.display = "block";

			_replaceContext = false;

			return false;
		}
	    }

	    var hover_tr = document.getElementsByClassName("tr_hover");

	    for(i=0;i<hover_tr.length;i++)
	    {
	    hover_tr[i].onmousemove = function() {
	     if(_divContext.style.display == "none") 
	     {
	       this.style.backgroundColor="#EFF3F6"; 
	     }
	    }   
	    hover_tr[i].onmouseout = function() {
	     if(_divContext.style.display == "none") 
	     {
	       this.style.backgroundColor="#fff";
	     }
	    }  
	    }';
	    } else
	    {
	    $renderer->doc .= '

	    var hover_tr = document.getElementsByClassName("tr_hover");

	    for(i=0;i<hover_tr.length;i++)
	    {
	    hover_tr[i].onmousemove = function() {
	    this.style.backgroundColor="#EFF3F6"; 
	    }   
	    hover_tr[i].onmouseout = function() {
	    this.style.backgroundColor="#fff";
	    }  
	    }';
	    }
	    $renderer->doc .= '}
	    </script>
	    ';
	    $bazy_dir = '/home/hafron/public_html/doku/data/pages/playground/bazy';
	    $baza = $bazy_dir.'/'.$NAZWA_BAZY.'.txt';
	    $rozdzielacz = '\\';
	    $rozdzielacz_encja = '&#92;';



	    if(isset($grupy) && in_array('user', $grupy)):
	    if(isset($_POST['dodaj']))
	    {
	    $max_id = 0;
	    if(!file_exists($baza)) {
	    $handle = fopen($baza, 'w+');
	    fclose($handle);
	    } else
	    {
	    $handle = fopen($baza, 'r');
	    }
	    if ($handle) {
	    while (($bufor = fgets($handle)) !== false) {
		$dane = explode($rozdzielacz, $bufor);
		if($max_id < (int)$dane[0])
		{
		  $max_id = (int)$dane[0];
		}
	    }
	    if (!feof($handle)) {
	       $renderer->doc .= "Błąd: niespodziewany błąd przy odczycie pliku.";
	    }
	    fclose($handle);
	    } else
	    {
	    die("Nie udało się otworzyć bazy danych.");
	    }
	    $lines = file($baza);
	    if($lines) 
	    $max_id++;
	    else
	    $max_id=1;

	    $line .= $max_id.$rozdzielacz;
	    $handle = fopen($baza, 'w+');
	    if (!$handle) {
	    $renderer->doc .="Nie udało się otworzyć bazy danych.";
	    } else
	    {
	    foreach($NAGLOWKI as $v)
	    {  
	       $value = str_replace($rozdzielacz, $rozdzielacz_encja, str_replace("\n", "<br>", trim($_POST[$v])));
	       $line .= $value.$rozdzielacz;
	    }
	    $line = substr($line, 0, -1);
	    $line .= "\n";
	    array_unshift($lines, $line);
	    foreach ($lines as $file_line) { fwrite( $handle, "$file_line"); }
	    fclose($handle);
	    }

	    } elseif(isset($_GET['usun']))
	    {
	    $id = $_GET['usun'];
	    $lines = file($baza);
	    if($lines) 
	    {
	    $handle = fopen($baza, 'w+');
	    if (!$handle) {
	      $renderer->doc .="Nie udało się otworzyć bazy danych.";
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
	      $renderer->doc .="Nie udało się otworzyć bazy danych.";
	    }

	    } elseif(isset($_POST['popraw']))
	    {


	    $id = $_POST['popraw'];
	    $lines = file($baza);
	    if($lines) 
	    {
	    $line .= $id.$rozdzielacz;
	    foreach($NAGLOWKI as $v)
	    {  
	       $value = str_replace($rozdzielacz, $rozdzielacz_encja, str_replace("\n", "<br>", trim($_POST[$v])));
	       $line .= $value.$rozdzielacz;
	    }
	    $line = substr($line, 0, -1);
	    $line .= "\n";

	    $handle = fopen($baza, 'w+');
	    if (!$handle) {
	      $renderer->doc .="Nie udało się otworzyć bazy danych.";
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
	      $renderer->doc .="Nie udało się otworzyć bazy danych.";
	    }
	    }
	    endif;


	    if(!file_exists($bazy_dir))
	    {
	    mkdir($bazy_dir);
	    }



	    if(!isset($_GET['edytuj']))
		$renderer->doc .= '<form action="'.selfURL().'" method="post"><form action="'.selfURL().'" method="post">';
	    else
		$renderer->doc .= '<form action="'.selfURL('edytuj').'" method="post">';

	    $renderer->doc .= '<table class="inline"><tr>';
	    foreach($NAGLOWKI as $v)
	    {
	      $renderer->doc .= "<th>$v</th>";
	    }
	    $renderer->doc .= '</tr>';
	    if(!isset($_GET['edytuj']))
	    {
	    $renderer->doc .= '<tr id="aform" style="';
	    if(count(file($baza)) != 0)
	    $renderer->doc .='display:none;';
	    $renderer->doc .= '">';
	    $renderer->doc .= '<input type="hidden" value="" name="dodaj">';
	    foreach($NAGLOWKI as $v)
	    {
	      if(in_array($v, $KOLUMNY_Z_PLIKAMI))
		$renderer->doc .= '<td><span id="aFileName"></span><input type="text" name="'.$v.'" id="wiki__text"><a href="#" id="wstaw_plik">wstaw     plik</a></td>';
		  elseif(in_array($v, $KOLUMNY_Z_DATAMI))
		    $renderer->doc .= '<td><input type="date" name="'.$v.'" /></td>';
		  else
		    $renderer->doc .= '<td><textarea name="'.$v.'"></textarea></td>';
		}
		//$renderer->doc .= '<td><input type="submit" value="Dodaj"></td>';

		$renderer->doc .= '</tr>';
	    }
	      if(!file_exists($baza)) {
	       $handle = fopen($baza, 'w+');
	       fclose($handle);
	       } else
	      {
		$handle = fopen($baza, 'r');
	      }
	      
	    if ($handle) {
		while (($bufor = fgets($handle)) !== false) {
		    $dane = explode($rozdzielacz, $bufor);
		    if(isset($_GET['edytuj']) && (int)$_GET['edytuj'] ==  $dane[0])
		    {
			$renderer->doc .= '<tr id="'.$dane[0].'">';
			$renderer->doc .= '<input type="hidden" value="'.$_GET['edytuj'].'" name="popraw">';
			$i=1;
			foreach($NAGLOWKI as $v)
			{

			  if(in_array($v, $KOLUMNY_Z_PLIKAMI))
			    $renderer->doc .= '<td><span id="aFileName"></span><input type="text" name="'.$v.'" id="wiki__text" value="'.$dane[$i].'"><a href="#" id="wstaw_plik">wstaw     plik</a></td>';
			 elseif(in_array($v, $KOLUMNY_Z_DATAMI))
			    $renderer->doc .= '<td><input type="date" name="'.$v.'" value="'.$dane[$i].'"></td>';
			  else
			    $renderer->doc .= '<td><textarea name="'.$v.'">'.str_replace("<br>", "\n", $dane[$i]).'</textarea></td>';
			  $i++;
			}
			//$renderer->doc .= '<td><input type="submit" value="Popraw"></td>';

			$renderer->doc .= '</tr>';
		    } else
		    {
			$renderer->doc .= '<tr id="'.$dane[0].'" class="tr_hover">';
			for($i=1;$i<sizeof($dane);$i++)
			{
			    $info = array();
			    $renderer->doc .= '<td class="con_menu">'.p_render('xhtml',p_get_instructions($dane[$i]),$info).'</td>';
			}
			for($i=0;$i<sizeof($NAGLOWKI)-sizeof($dane)+1;$i++)
			{
			   $renderer->doc .= '<td class="con_menu"></td>';
			}
			// $renderer->doc .= '<td><a href="'.selfURL().'&usun='.$dane[0].'">usuń</a></td>';
			$renderer->doc .= '</tr>';
		    }
		}
		if (!feof($handle)) {
		   $renderer->doc .= "Błąd: niespodziewany błąd przy odczycie pliku.";
		}
		fclose($handle);
	    } else
	    {
	      $renderer->doc .= "Nie udało się otworzyć bazy danych.";
	    }

	    $renderer->doc .= '</table>';
	    $renderer->doc .= '</form>';

            return true;
        }
        return false;
    }
}
