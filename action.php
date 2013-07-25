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
	    $controller->register_hook('PARSER_WIKITEXT_PREPROCESS', 'AFTER',  $this, 'parser_preprocess_handler');

	    $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER',  $this, 'load_lexer_rules');
    }
    function parser_preprocess_handler(&$event, $parm)
    {
	global $ID;
	$lines = explode("\n", $event->data);
	$new_lines = array();
	//determine dtable page

	foreach($lines as $line)
	{
	    if(strpos($line, '<dtable>') === 0)
	    {
		$new_line = '<dtable id="'.$ID.'">';
	    }
	    else
	    	$new_line = $line;

	    $new_lines[] = $new_line;

	}

	//mark dtables
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
    function load_lexer_rules(&$event, $param)
    {
	//files contianging lexer commands
//	$files = array(DOKU_INC.'inc/parser/parser.php');
	$files = array();

	$type = 'syntax';
	//$pluginlist = plugin_list($type);
	//$plugin_files = array();
	//get from plugincontroller.class.php 
	
	$doku_plugin_dir = opendir(DOKU_PLUGIN);
	while (($plugin_dir = readdir($doku_plugin_dir)) !== false)
	{
	    $plugin = $plugin_dir;

	    if($plugin_dir == '.' || $plugin_dir == '..' || ! is_dir(DOKU_PLUGIN.$plugin_dir) || plugin_isdisabled($plugin) )
		continue;

	    $dir = $plugin;

            if (@file_exists(DOKU_PLUGIN."$dir/$type.php")){
                $files [] = DOKU_PLUGIN."$plugin/$type.php";
            } else {
                if ($dp = @opendir(DOKU_PLUGIN."$dir/$type/")) {
                    while (false !== ($component = readdir($dp))) {
                        if (substr($component,0,1) == '.' || strtolower(substr($component, -4)) != ".php") continue;
                        if (is_file(DOKU_PLUGIN."$dir/$type/$component")) {
                            $files[] = DOKU_PLUGIN."$dir/$type/".$component;
                        }
                    }
                    closedir($dp);
                }
            }
        }

	//Lexer rules
	$lexer_rules = array('addEntryPattern' => array(), 'addPattern' => array(), 'addExitPattern' => array(), 'addSpecialPattern' => array() );

	$modes = array('table', 'copy');
	foreach($modes as $mode)
	{
	    //basic dokuwiki syntax:
	    //strong
	    $lexer_rules['addEntryPattern'][] = array('\*\*(?=.*\*\*)', $mode, 'copy');
	    $lexer_rules['addExitPattern'][] = array('\*\*', 'copy');

	    //emphasis
	    $lexer_rules['addEntryPattern'][] = array('//(?=.//)', $mode, 'copy');//without hack for bugs #384 #763 #1468
	    $lexer_rules['addExitPattern'][] = array('//', 'copy');

	    //underline
	    $lexer_rules['addEntryPattern'][] = array('__(?=.*__)', $mode, 'copy');
	    $lexer_rules['addExitPattern'][] = array('__', 'copy');

	    //monospace
	    $lexer_rules['addEntryPattern'][] = array('\x27\x27(?=.*\x27\x27)', $mode, 'copy');
	    $lexer_rules['addExitPattern'][] = array('\x27\x27', 'copy');

	    //subscript
	    $lexer_rules['addEntryPattern'][] = array('<sub>(?=.*</sub>)', $mode, 'copy');
	    $lexer_rules['addExitPattern'][] = array('</sub>', 'copy');

	    //superscript
	    $lexer_rules['addEntryPattern'][] = array('<sup>(?=.*</sup>)', $mode, 'copy');
	    $lexer_rules['addExitPattern'][] = array('</sup>', 'copy');

	    //deleted
	    $lexer_rules['addEntryPattern'][] = array('<del>(?=.*</del>)', $mode, 'copy');
	    $lexer_rules['addExitPattern'][] = array('</del>', 'copy');

	    $lexer_rules['addEntryPattern'][] = array('<nowiki>(?=.*</nowiki>)',$mode,'copy');
	    $lexer_rules['addEntryPattern'][] = array('%%(?=.*%%)',$mode,'copy');
	    $lexer_rules['addExitPattern'][] = array('</nowiki>','unformatted');
	    $lexer_rules['addExitPattern'][] = array('%%','unformattedalt');
	    $lexer_rules['mapHandler'][] = array('unformattedalt','unformatted');
	    $lexer_rules['addEntryPattern'][] = array('\x28\x28(?=.*\x29\x29)',$mode,'copy');
	    $lexer_rules['addExitPattern'][] = array('\x29\x29','footnote');
	    $lexer_rules['addEntryPattern'][] = array('<php>(?=.*</php>)',$mode,'copy');
	    $lexer_rules['addEntryPattern'][] = array('<PHP>(?=.*</PHP>)',$mode,'copy');
	    $lexer_rules['addExitPattern'][] = array('</php>','php');
	    $lexer_rules['addExitPattern'][] = array('</PHP>','phpblock');
	    $lexer_rules['addEntryPattern'][] = array('<html>(?=.*</html>)',$mode,'copy');
	    $lexer_rules['addEntryPattern'][] = array('<HTML>(?=.*</HTML>)',$mode,'copy');
	    $lexer_rules['addExitPattern'][] = array('</html>','html');
	    $lexer_rules['addExitPattern'][] = array('</HTML>','htmlblock');
	    $lexer_rules['addEntryPattern'][] = array('\n  (?![\*\-])',$mode,'copy');
	    $lexer_rules['addEntryPattern'][] = array('\n\t(?![\*\-])',$mode,'copy');
	    $lexer_rules['addPattern'][] = array('\n  ','preformatted');
	    $lexer_rules['addPattern'][] = array('\n\t','preformatted');
	    $lexer_rules['addExitPattern'][] = array('\n','preformatted');
	    $lexer_rules['addEntryPattern'][] = array('<code(?=.*</code>)',$mode,'copy');
	    $lexer_rules['addExitPattern'][] = array('</code>','code');
	    $lexer_rules['addEntryPattern'][] = array('<file(?=.*</file>)',$mode,'copy');
	    $lexer_rules['addExitPattern'][] = array('</file>','file');
	    $lexer_rules['addEntryPattern'][] = array('\n>{1,}',$mode,'copy');
	    $lexer_rules['addPattern'][] = array('\n>{1,}','quote');
	    $lexer_rules['addExitPattern'][] = array('\n','quote');


	    $ws   =  '\s/\#~:+=&%@\-\x28\x29\]\[{}><"\'';   // whitespace
	    $punc =  ';,\.?!';

	    if($conf['typography'] == 2){
		$this->Lexer->addSpecialPattern(
			    "(?<=^|[$ws])'(?=[^$ws$punc])",$mode,'singlequoteopening'
			);
		$this->Lexer->addSpecialPattern(
			    "(?<=^|[^$ws]|[$punc])'(?=$|[$ws$punc])",$mode,'singlequoteclosing'
			);
		$this->Lexer->addSpecialPattern(
			    "(?<=^|[^$ws$punc])'(?=$|[^$ws$punc])",$mode,'apostrophe'
			);
	    }

	    $lexer_rules['addSpecialPattern'][] = array(
			"(?<=^|[$ws])\"(?=[^$ws$punc])",$mode,'doublequoteopening'
		    );
	    $lexer_rules['addSpecialPattern'][] = array(
			"\"",$mode,'doublequoteclosing'
		    );


	    //media
	    $lexer_rules['addSpecialPattern'][] = array("\{\{[^\}]+\}\}",$mode,'copy');
	    //link
	    $lexer_rules['addSpecialPattern'][] = array("\[\[(?:(?:[^[\]]*?\[.*?\])|.*?)\]\]", $mode, 'copy');
	}

	//Try to read plugins
	foreach( $files as $file )
	{
	    $handle = @fopen($file, "r");
	    if ($handle) {
		while (($buffer = fgets($handle)) !== false) {
		    if( strpos( $buffer, '$this->Lexer') !== false )
		    {
			//replace \\ by single \
			$buffer = str_replace('\\\\', '\\', $buffer);
			$php_strs = array();
			$php_strs_rep = array();
			$php_strs_i = 0;
			$php_str = '';
			$escape_buffer = '';
			$in_php_str = 0;
			for( $i = 0; $i < strlen($buffer); $i++ )
			{
			    if( $buffer[ $i ] == '\\' )
			    {
				if( $in_php_str == 1)
				{
				    $php_str .= $buffer[ $i ];
				    $php_str .= $buffer[ $i+1 ];
				} else
				{
				    $escape_buffer .= $buffer[ $i ];
				    $escape_buffer .= $buffer[ $i+1 ];
				}
				$i++;
				continue;
			    }

			    if( $buffer[ $i ] == "'" || $buffer[ $i ] == '"' )
			    {
				if($in_php_str == 1)
			        {
				    $in_php_str = 0;
				    $php_strs[ $php_strs_i ] = $php_str;
				    $php_strs_rep[ $php_strs_i ] = '%'.$php_strs_i;
				    $php_str = '';
				    $escape_buffer .= '%'.$php_strs_i;
				    $php_strs_i++;
				} else
				{
				    $in_php_str = 1;
				    $i++;
				    $php_str .= $buffer[ $i ];
				}
			    } else
			    {
				if( $in_php_str == 1)
				{
				    $php_str .= $buffer[ $i ];
				} else
				{
				    $escape_buffer .= $buffer[ $i ];
				}
			    }
			}

			$instructions = explode(';', $escape_buffer);
			foreach($instructions as $instr)
			{
			    if( preg_match('/\$this->Lexer->([^(]*)\((.*)\)/', $instr, $matches)  )
			    {
				$function = trim($matches[1]);
				if( array_key_exists($function, $lexer_rules) )
				{
				    $args_dirt = explode(',', $matches[2]);
				    $args = array();
				    foreach($args_dirt as $arg)
				    {
					$arg = str_replace($php_strs_rep, $php_strs, $arg);
					$arg = trim($arg);
					$args[] = $arg;
				    }
				    $lexer_rules[ $function ][] = $args;
				}
			    }
			}
			dbglog($lexer_rules);
		    }
		}
		fclose($handle);
	    }
	}

	$event->data['current']['plugin_dtable_lexer_rules'] = $lexer_rules;
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



	    $json = new JSON();

	    list($dtable_start_line, $dtable_page_id) = explode('_', $_POST['table'], 2);
	    $file = wikiFN( $dtable_page_id );
	    if( ! @file_exists( $file  ) )
	    {
		echo $json->encode( array('type' => 'error', 'msg' => 'This page does not exist.') );
		exit(0);
	    }

	    $dtable =& plugin_load('helper', 'dtable');

	    //$dtable::$lexer_rules = p_get_metadata($dtable_page_id, 'plugin_dtable_lexer_rules');

	    $page_lines = explode( "\n", io_readFile( $file ) );

	    if(isset($_POST['remove']))
	    {
		$table_line = (int) $_POST['remove'];

		$line_to_remove = $dtable_start_line + $table_line;

		$removed_line = $page_lines[ $line_to_remove ]; 

		unset( $page_lines[ $line_to_remove ] );

		$new_cont = implode( "\n", $page_lines );

		saveWikiText($dtable_page_id, $new_cont, $this->getLang('summary_remove').' '.$removed_line);


		echo $json->encode( array('type' => 'alternate_success', 'rowspans' =>  $dtable->get_rowspans($removed_line, $table_line, $dtable_start_line, $page_lines, $dtable_page_id) ) );

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

		echo $json->encode( array('type' => 'alternate_success', 'new_row' => $dtable->parse_line($formated_line, $dtable_page_id), 'rowspans' =>  $dtable->get_rowspans($formated_line, $table_line, $dtable_start_line, $page_lines, $dtable_page_id) ) );
	    } elseif( isset( $_POST['get'] ) )
	    {
		$table_line = (int) $_POST['get'];
		$line_to_get = $dtable_start_line + $table_line;

		//0 - rows 1 - rowspan and colspans
		$rows = $dtable->rows( $page_lines[ $line_to_get ], $dtable_page_id, true );

		echo $json->encode( $rows  );

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

		echo $json->encode( array('type' => 'alternate_success', 'new_row' => $dtable->parse_line($new_line, $dtable_page_id), 'rowspans' =>  $dtable->get_rowspans($new_line, $table_line, $dtable_start_line, $page_lines, $dtable_page_id) ) );
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
