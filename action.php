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
	    $controller->register_hook('PARSER_WIKITEXT_PREPROCESS', 'AFTER',  $this, 'mark_dtables');
    }
    function mark_dtables(&$event, $parm)
    {
	$lines = explode("\n", $event->data);
	$in_tab = 0;
	$in_dtable_tag = 0;

	$new_lines = $lines;
	if($this->getConf('all_tables'))
	{
	    $new_lines = array();
	    foreach($lines as $line)
	    {
		if(strpos($line, '<dtable>') === 0)
		    $in_dtable_tag = 1;
		if(strpos($line, '</dtable>') === 0)
		    $in_dtable_tag = 0;

		if(strpos($line, '|') !== 0 && $in_tab == 1 && $in_dtable_tag == 0)
		{
		    $new_lines[] = '</dtable>';
		    $in_tab = 0;
		}

		if(strpos($line, '^') === 0 && $in_tab == 0 && $in_dtable_tag == 0)
		{
		    $new_lines[] = '<dtable>';
		    $in_tab = 1;
		}

		$new_lines[] = $line;
	    }
	    $lines = $new_lines;
	}
	$event->data = implode("\n", $new_lines);
    }
    
    function add_php_data(&$event, $param) {
	global $JSINFO, $ID;

	if (auth_quickaclcheck($ID) >= AUTH_EDIT) 
	    $JSINFO['write'] = true;
	else
	    $JSINFO['write'] = false;

	$JSINFO['lang']['insert_before'] = $this->getLang('insert_before');
	$JSINFO['lang']['insert_after'] = $this->getLang('insert_after');
	$JSINFO['lang']['edit'] = $this->getLang('edit');
	$JSINFO['lang']['remove'] = $this->getLang('remove');

	$JSINFO['lang']['lock_notify'] = str_replace(
	    array('%u', '%t'), 
	    array('<span class="who"></span>', '<span class="time_left"></span>'), 
	    $this->getLang('lock_notify'));
	$JSINFO['lang']['unlock_notify'] = $this->getLang('unlock_notify');
    }
    function handle_ajax(&$event, $param)
    {
	global $conf;

	switch($event->data)
	{
	case 'dtable':
	    $event->preventDefault();
	    $event->stopPropagation();

	    $dtable =& plugin_load('helper', 'dtable');

	    $json = new JSON();

	    list($dtable_start_line, $dtable_page_id) = explode('_', $_POST['table'], 2);
	    $file = wikiFN( $dtable_page_id );
	    if( ! @file_exists( $file  ) )
	    {
		echo $json->encode( array('type' => 'error', 'msg' => 'This page does not exist.') );
		exit(0);
	    }
	    $page_lines = explode( "\n", io_readFile( $file ) );

	    if(isset($_POST['remove']))
	    {
		$table_line = (int) $_POST['remove'];

		$line_to_remove = $dtable_start_line + $table_line;

		$removed_line = $page_lines[ $line_to_remove ]; 

		unset( $page_lines[ $line_to_remove ] );

		$new_cont = implode( "\n", $page_lines );

		saveWikiText($dtable_page_id, $new_cont, $this->getLang('summary_remove').' '.$removed_line);


		echo $json->encode( array('type' => 'alternate_success', 'rowspans' =>  $dtable->get_rowspans($removed_line, $table_line, $dtable_start_line, $page_lines) ) );

	    } elseif( isset( $_POST['add'] ) )
	    {
		$table_line = (int) $_POST['add'] + 1;
		$line_to_add = $dtable_start_line + $table_line;

		$new_table_line = array();
		foreach( $_POST as $k => $v )
		{
		    if( strpos( $k, 'col' ) === 0)
		    {
			$new_table_line[$k] = $v;
		    }
		}
		ksort($new_table_line);

		$formated_line = $dtable->format_row($new_table_line);

		array_splice($page_lines, $line_to_add, 0, $formated_line );

		$new_cont = implode( "\n", $page_lines );
		saveWikiText($dtable_page_id, $new_cont, $this->getLang('summary_add').' '.$formated_line);

		echo $json->encode( array('type' => 'alternate_success', 'new_row' => $dtable->parse_line($formated_line), 'rowspans' =>  $dtable->get_rowspans($formated_line, $table_line, $dtable_start_line, $page_lines) ) );
	    } elseif( isset( $_POST['get'] ) )
	    {
		$table_line = (int) $_POST['get'];
		$line_to_get = $dtable_start_line + $table_line;

		echo $json->encode( $dtable->rows( $page_lines[ $line_to_get ] ) );

	    } elseif( isset( $_POST['edit'] ) )
	    {
		$table_line = (int) $_POST['edit'];
		$line_to_change = $dtable_start_line + $table_line;

		$new_table_line = array();
		foreach( $_POST as $k => $v )
		{
		    if( strpos( $k, 'col' ) === 0)
		    {
			$new_table_line[$k] = $v;
		    }
		}
		ksort($new_table_line);

		$new_line = $dtable->format_row($new_table_line);

		$old_line = $page_lines[ $line_to_change ];

		$page_lines[ $line_to_change ] = $new_line;

		$new_cont = implode( "\n", $page_lines );

		$info = str_replace( array('%o', '%n'), array($old_line, $new_line), $this->getLang('summary_edit') );
		saveWikiText($dtable_page_id, $new_cont, $info);

		echo $json->encode( array('type' => 'alternate_success', 'new_row' => $dtable->parse_line($new_line), 'rowspans' =>  $dtable->get_rowspans($new_line, $table_line, $dtable_start_line, $page_lines) ) );
	    }
	break;
	case 'dtable_page_lock':
	    $event->preventDefault();
	    $event->stopPropagation();

	    $ID = $_POST['page'];
	    lock($ID);
	break;
	case 'dtable_page_unlock':
	    $event->preventDefault();
	    $event->stopPropagation();

	    $ID = $_POST['page'];
	    unlock($ID);
	break;
	case 'dtable_is_page_locked':
	    $event->preventDefault();
	    $event->stopPropagation();

	    $ID = $_POST['page'];
	    $checklock = checklock($ID); 

	    //check when lock expire
	    $lock_file = wikiLockFN($ID);
	    if(file_exists($lock_file))
	    {
		$locktime = filemtime(wikiLockFN($ID));
		//dokuwiki uses dformat here but we will use raw unix timesamp
		$expire = $locktime + $conf['locktime'] - time();
	    } else
		$expire = $conf['locktime'];

	    $json = new JSON();

	    if($checklock === false)
	       	echo $json->encode(array('locked' => 0, 'time_left' => $expire));
	    else
	       	echo $json->encode(array('locked' => 1, 'who' => $checklock, 'time_left' => $expire));

	break;
	}
    }
}
