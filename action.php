<?php
// must be run within DokuWiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'syntax.php';

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class action_plugin_dtable extends DokuWiki_Action_Plugin {

    function register(Doku_Event_Handler $controller) {
	$controller->register_hook('DOKUWIKI_STARTED', 'AFTER',  $this, 'add_php_data');
	$controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE',  $this, 'handle_ajax');
	$controller->register_hook('PARSER_WIKITEXT_PREPROCESS', 'AFTER',  $this, 'parser_preprocess_handler');
    	$controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_button', array ());

  }

  public function insert_button (Doku_Event $event, $param) {
      $event->data[] = array (
          'type' => 'format',
          'title' => $this->getLang('toolbar_insert_button'),
          'icon'  => '../../plugins/dtable/images/add_table.png',
          'open' => '<dtable>',
          'close' => '</dtable>',
          'sample' => "\n^   ^   ^\n|   |   |\n|   |   |\n|   |   |\n"
      );
  }
    function parser_preprocess_handler(&$event, $parm)
    {
		global $ID, $INFO;
		$lines = explode("\n", $event->data);
		$new_lines = array();
		//determine dtable page

		//only 100 dtables per page
		$i = 0;
		$dtable_pages = array();
		foreach($lines as $line)
		{
			if(strpos($line, '<dtable>') === 0) {
				$new_lines[] = '<dtab'.( $i < 10 ? '0'.$i : $i ).'>';
				$dtable_pages[$i] = $ID;
				$i++;
			} else
			{
				$new_lines[] = $line;
			}
		}

		//it will make include plugin behaves correctly
		p_set_metadata($INFO['id'], array('dtable_pages' => $dtable_pages), false, false);

		//mark dtables
		//it will not work becouse section editing in dokuwiki needs no modified content.
		if($this->getConf('all_tables'))
		{
			$new_lines = array();

			$in_tab = 0;
			$in_dtable_tag = 0;

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

	$JSINFO['disabled'] = explode(',', $this->getConf('disabled'));


	$JSINFO['lang']['insert_before'] = $this->getLang('insert_before');
	$JSINFO['lang']['insert_after'] = $this->getLang('insert_after');
	$JSINFO['lang']['edit'] = $this->getLang('edit');
	$JSINFO['lang']['remove'] = $this->getLang('remove');
	$JSINFO['lang']['insert_col_left'] = $this->getLang('insert_col_left');
	$JSINFO['lang']['insert_col_right'] = $this->getLang('insert_col_right');
	$JSINFO['lang']['mark_row_as_header'] = $this->getLang('mark_row_as_header');
	$JSINFO['lang']['mark_col_as_header'] = $this->getLang('mark_col_as_header');
	$JSINFO['lang']['mark_cell_as_header'] = $this->getLang('mark_cell_as_header');

	$JSINFO['lang']['mark_row_as_cell'] = $this->getLang('mark_row_as_cell');
	$JSINFO['lang']['mark_col_as_cell'] = $this->getLang('mark_col_as_cell');
	$JSINFO['lang']['mark_cell_as_cell'] = $this->getLang('mark_cell_as_cell');

	$JSINFO['lang']['show_merged_rows'] = $this->getLang('show_merged_rows');

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



	    $json = new JSON();

	    list($dtable_start_line, $dtable_page_id) = explode('_', $_POST['table'], 2);
	    $file = wikiFN( $dtable_page_id );
	    if( ! @file_exists( $file  ) )
	    {
			echo $json->encode( array('type' => 'error', 'msg' => 'This page does not exist.') );
			exit(0);
	    }

	    $dtable =& plugin_load('helper', 'dtable');

	    $page_lines = explode( "\n", io_readFile( $file ) );

	    if(isset($_POST['remove']))
	    {
			$scope = $json->decode($_POST['remove']);

			$lines_to_remove = array();
			for ($i = $scope[0]; $i <= $scope[1]; $i++)
				$lines_to_remove[] = $i;

			$removed_line = '';
			foreach ($lines_to_remove as $line) {
				$removed_line .= $page_lines[ $line ]." "; 
			}

			array_splice($page_lines, $scope[0], $scope[1] - $scope[0] + 1);

			$new_cont = implode( "\n", $page_lines );

			saveWikiText($dtable_page_id, $new_cont, $this->getLang('summary_remove').' '.$removed_line);



			echo $json->encode( array('type' => 'success', 'spans' =>  
						$dtable->get_spans($dtable_start_line, $page_lines, $dtable_page_id) ) );

		} else
		{
			$cols = array();
			$new_table_line = array();
			foreach( $_POST as $k => $v )
			{
				if( strpos( $k, 'col' ) === 0)
				{
					//remove col from col12, col1 etc. to be 12 1
					$cols[(int)substr($k, 3)] = $json->decode($v);
				}
			}
			ksort($cols);

			//reset index
			$cols = array_values($cols);

			$j = 0;
			for($i = 0; $i < count($cols); $i++) {
					$class = $cols[$i][0];
					$value = $cols[$i][1];

					if ($value == '' && $j >= 1)
						$new_table_line[$j-1][1]++;
					else {
						$type = $class == 'tablecell_open' ? '|' : '^';
						$new_table_line[$j] = array(1, 1, $type, $value);
						$j++;
					}
			}
			$new_line = $dtable->format_row($cols);

			if( isset( $_POST['add'] ) )
			{
				$action = 'add';

				/*$table_line = (int) $_POST['add'] + 1;
				$line_to_add = $dtable_start_line + $table_line;*/
				$line_to_add = (int) $_POST['add'] + 1;

				array_splice($page_lines, $line_to_add, 0, $new_line );
				$line_nr = $line_to_add;

				$info = $this->getLang('summary_add').' '.$new_line;

			} elseif( isset( $_POST['edit'] ) )
			{
				$action = 'edit';

				$scope = $json->decode($_POST['edit']);

				$lines_to_change = array();
				for ($i = $scope[0]; $i <= $scope[1]; $i++)
					$lines_to_change[] = $i;

				$old_line= '';
				foreach ($lines_to_change as $line) {
					$old_line .= $page_lines[ $line ]." "; 
				}

				//$old_line = $page_lines[ $line_to_change ];

				array_splice($page_lines, $scope[0], $scope[1] - $scope[0] + 1, $new_line);
				$line_nr = $scope[0];

				$new_cont = implode( "\n", $page_lines );

				$info = str_replace( array('%o', '%n'), array($old_line, $new_line), $this->getLang('summary_edit') );
			}

			$new_cont = implode( "\n", $page_lines );
			saveWikiText($dtable_page_id, $new_cont, $info);

			echo $json->encode( array('type' => 'success', 'action' => $action, 'new_row' => $dtable->parse_line($new_line, $dtable_page_id), 'raw_row' => array($new_table_line, array($line_nr, $line_nr)), 'spans' =>  $dtable->get_spans($dtable_start_line, $page_lines, $dtable_page_id) ) );

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
