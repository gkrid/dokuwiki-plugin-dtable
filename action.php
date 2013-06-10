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
