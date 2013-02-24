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
class action_plugin_dtable extends DokuWiki_Action_Plugin {

    function register(&$controller) {
	    $controller->register_hook('DOKUWIKI_STARTED', 'AFTER',  $this, 'add_php_data');
	    $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE',  $this, 'handle_ajax');
    }
    
    function add_php_data(&$event, $param) {
	global $JSINFO;

	if (auth_quickaclcheck($ID) >= AUTH_WRITE) 
	    $JSINFO['write'] = 1;
	else
	    $JSINFO['write'] = 0;
    }
    function handle_ajax(&$event, $param)
    {
	if($event->data != 'dtable') return;
	$event->preventDefault();
	$event->stopPropagation();

	$dtable =& plugin_load('helper', 'dtable');


	if (auth_quickaclcheck($ID) >= AUTH_WRITE) 
	{

	    $baza = $dtable->db_path($_POST['table']);
	    $baza_meta = $dtable->db_meta_path($_POST['table']);

	    if(isset($_POST['add']))
	    {
		$after_id = $_POST['add'];
		$after_line = -1;

		$max_id = 0;
		$handle = fopen($baza, 'r');
		if (!$handle) 
		    exit($dtable->error('db_error', true));

		$i=0;
		while (($bufor = fgets($handle)) !== false) {
		    $dane = explode($dtable->separator(), $bufor);
		    if($max_id < (int)$dane[0])
		    {
		      $max_id = (int)$dane[0];
		    }
		    if($after_id == $dane[0])
			$after_line = $i;
		    $i++;
		}
		if (!feof($handle)) 
		    exit($dtable->error('db_error', true));

		fclose($handle);

		$lines = file($baza);
		if($lines) 
		    $max_id++;
		else
		    $max_id=1;

		$line .= $max_id.$dtable->separator();
		$handle = fopen($baza, 'w+');
		if (!$handle) 
		    exit($dtable->error('db_error', true));

		    $conf_file = file($baza_meta);

		    if (!$conf_file) 
			exit($dtable->error('db_error', true));

		    $conf = json_decode($conf_file[0]);
		    $heads = $conf[0];

		    $in_fileds = array();


		    foreach($heads as $v)
		    {  
			$value = str_replace($dtable->separator(), 
					     $dtable->separator_en(),
					     str_replace("\n", '<br>', $_POST[$v])
					    );
			$in_fileds[] = $dtable->parse($value);

		       $line .= $value.$dtable->separator();
		    }
		    $line = substr($line, 0, -1);
		    $line .= "\n";
		    if($after_line == -1)
			array_unshift($lines, $line);
		     
		    foreach ($lines as $k => $file_line) { 
			fwrite( $handle, "$file_line"); 
			if($k == $after_line)
			    fwrite( $handle, "$line"); 
		    }

		    fclose($handle);

		    echo json_encode(array('type' => 'success', 'id' => $max_id, 'fileds' => $in_fileds));

	    } elseif(isset($_POST['get']))
	    {
		$id = (int)$_POST['get'];
		$lines = file($baza);

		if(!$lines) 
		    exit($dtable->error('db_error', true));

	        foreach ($lines as $file_line) { 
		    $dane = explode($dtable->separator(), $file_line);
		    if($dane[0] == $id)
		    {
			array_shift($dane);
			foreach($dane as $k => $d)
			{
			    $dane[$k] = str_replace('<br>', "\n", $d);
			}
			echo json_encode($dane);
			break;
		    }
		}
	    } elseif(isset($_POST['edit']))
	    {
	    $id = (int)$_POST['edit'];
	    $lines = file($baza);

	    if(!$lines) 
		exit($dtable->error('db_error', true));

	    $line .= $id.$dtable->separator();
	    
	    $conf_file = file($baza_meta);

	    if (!$conf_file) 
		exit($dtable->error('db_error', true));

	    $conf = json_decode($conf_file[0]);
	    $heads = $conf[0];

	    $in_fileds = array();
	    foreach($heads as $v)
	    {  
		$value = str_replace($dtable->separator(), $dtable->separator_en(), 
					 str_replace("\n", '<br>', $_POST[$v]));
	        $line .= $value.$dtable->separator();

		$in_fileds[] = $dtable->parse($value);
	    }
	    $line = substr($line, 0, -1);
	    $line .= "\n";

	    $handle = fopen($baza, 'w+');
	    if (!$handle) 
		exit($dtable->error('db_error', true));

	      foreach ($lines as $file_line) { 
		$dane = explode($dtable->separator(), $file_line);
		if($dane[0] != $id)
		{
		  fwrite( $handle, "$file_line");
		} else
		{
		  fwrite($handle, "$line");
		}
	      }
	      fclose($handle);
	      echo json_encode(array('type' => 'success', 'id' => $id, 'fileds' => $in_fileds));

	    } elseif(isset($_POST['remove']))
	    {
		$id = $_POST['remove'];
		$lines = file($baza);
		
		if(!$lines) 
		  exit($dtable->error('db_error', true));

		$handle = fopen($baza, 'w+');
		if (!$handle) 
		    exit($dtable->error('db_error', true));

		  foreach ($lines as $file_line) { 
		    $dane = explode($dtable->separator(), $file_line);
		    if($dane[0] != $id)
		    {
			fwrite( $handle, "$file_line");
		    }
		  }
		  fclose($handle);
		  echo json_encode(array('type' => 'success'));

	    }
	}
    }
}
