(function($){
    jQuery.fn.getStyleObject = function(styles){
		var ret_style = {};
		for (var i = 0; i < styles.length; i++)
		{
			ret_style[styles[i]] = jQuery(this).css(styles[i]);
		}
		return ret_style;
	}
})(jQuery);

dtable = {};

//acl >= 2 - użytkownik może modyfikować tabelkę
//dtable.init = function(acl, self_url, wiki_url, page_id)

dtable.toolbar_id = "dtable_tool__bar";
//I need it to use dokuwiki toolbar
dtable.textarea_id = "dtable_wiki__text";
//Set it to true if we are waiting fro form to send
dtable.form_processing = false;
//Store informatino about actual clicked row
dtable.row = {};
//Id of processed dtable
dtable.id = "";
//if page locked
dtable.page_locked = 0;

//state of lock
//0 -> we don't know anything
//1 -> someone lock the page and we waiting until we could refresh it 
//2 -> we can edit page for some time but we left browser alone and our lock expires and someone else came end start to edit page, so we need to lock our page and optionally send the form.
dtable.lock_state = 0;

//use to determine if user doing something
dtable.pageX = 0;
dtable.pageY = 0;
dtable.prev_pageX = 0;
dtable.prev_pageY = 0;

//check if forms in dtable are changed
dtable.prev_val = '';

//When my or someones else lock expires
dtable.lock_expires = -1;

//All intervals
dtable.intervals = [];

dtable.lock_seeker_timeout = 5*1000;

dtable.error = function(msg)
{
    alert(msg);
};
dtable.show_form = function($table)
{
    var $form = $table.find(".form_row"); 
    var $toolbar = jQuery("#"+dtable.toolbar_id);

    //display fix jquery 1.6 bug
    $form.css("display", "table-row");

	var rowspan_text_height = -1;

	$form.find("textarea.dtable_field").each(function() {

		//this is merged cell
		var button = jQuery(this).closest('td').find('button'); 
		if (button.length > 0)
		{
			var button_width = 31;
			var text_off = jQuery(this).offset();
			var scroller_width = 20;

			var button_off = button.offset();
			button.css({'position': 'absolute', 'top': text_off.top , 'left': button_off.left + jQuery(this).width() - button_width - scroller_width}).appendTo("body");
		}
	});

	//calculate texarea.rowspans positions
	var textarea_offset = $form.find("textarea.dtable_field").first().offset();

	$table.find("textarea.dtable_field:not(.form_row textarea.dtable_field)").each(function() {
		var this_texta_offset = jQuery(this).offset();
		jQuery(this).css('top', textarea_offset.top - this_texta_offset.top);
	});

	

    var offset = $form.offset();
    $toolbar.css({
	"left": offset.left, 
	"top": offset.top-$toolbar.height()
    });
    $toolbar.show();
};
dtable.hide_form = function($table)
{
    var $form = $table.find(".form_row"); 
	//remove form
	$form.remove();
	//remove textareas in rowspans
	$table.find("textarea.dtable_field").remove();

	jQuery(".dtable_unmerge").remove();

    var $toolbar = jQuery("#"+dtable.toolbar_id);
    $form.hide();
    $toolbar.hide();
};
dtable.get_data_rows = function($table)
{
	//.not(".form_row") is nesssesery
    return $table.find("tr").not(".form_row");//.not(":hidden");
};
dtable.get_row_id = function($table, $row)
{
    return dtable.get_data_rows($table).index($row);
};
dtable.get_call = function($form)
{
	return $form.find("input.dtable_field[name=call]").val();
};
//Lock actuall page
dtable.lock = function()
{
  jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', 
  {
      'call': 'dtable_page_lock',
      'page': JSINFO['id'],
  },function() { dtable.page_locked = 1 });
};
dtable.unlock = function()
{
  if(dtable.page_locked == 1)
  {
      jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', 
      {
	  'call': 'dtable_page_unlock',
	  'page': JSINFO['id'],
      },function() { dtable.page_locked = 0 });
  }
};
dtable.panlock_switch = function(state)
{
    if(state == undefined)
	state = 'hide';


    if(state == 'panlock')
    {
	jQuery(".dtable .panunlock").hide();
	jQuery(".dtable .panlock").show();
    } else if(state == 'panunlock')
    {
	jQuery(".dtable .panlock").hide();
	jQuery(".dtable .panunlock").show();
    } else
    {
	jQuery(".dtable .panlock").hide();
	jQuery(".dtable .panunlock").hide();
    }
};

dtable.lock_seeker = function(nolock, lock)
{
  jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', 
  {
      'call': 'dtable_is_page_locked',
      'page': JSINFO['id'],
  }, function(data)
     {
	 var res = jQuery.parseJSON(data);

	 dtable.lock_expires = res.time_left;

	 if(res.locked === 1)
	 {
	     if(dtable.lock_state == 2)
      		lock();

	    jQuery(".dtable .panlock .who").text(res.who);
	    dtable.update_lock_timer(dtable.lock_expires);
	    dtable.panlock_switch('panlock');


	    dtable.lock_state = 1;

	 } else
	 {
	    dtable.panlock_switch('hide');
	    if(dtable.lock_state === 0)
		nolock();
	    else if(dtable.lock_state === 1)
	    {
		dtable.panlock_switch('panunlock');
		dtable.clear_all_intervals();
	    }

	    dtable.lock_state = 2;


	    //refresh lock if user do something
	    var form_val_str = '';
	    jQuery('.dtable .form_row').find('textarea.dtable_field').each(function() {
		form_val_str += jQuery(this).val();
	    });
	    if(dtable.pageX != dtable.prev_pageX || dtable.pageY != dtable.prev_pageY || dtable.prev_val != form_val_str)
	    {
		dtable.prev_pageX = dtable.pageX;
		dtable.prev_pageY = dtable.pageY;
		dtable.prev_val = form_val_str;
		dtable.lock();
	    }
	 }

     });
};
dtable.update_lock_timer = function(seconds)
{
    var date = new Date();
    date.setSeconds(date.getSeconds()+seconds);
    jQuery(".dtable .panlock .time_left").text(date.toLocaleString());
};
dtable.unlock_dtable = function()
{

    var $row = dtable.get_data_rows(jQuery(".dtable"));
    var $context_menu = jQuery("#dtable_context_menu");

    dtable.lock();

   //track mouse in order to check if user do somenhing
   jQuery(document).bind('mousemove', function(e){
       dtable.pageX = e.pageX;
       dtable.pageY = e.pageY;
   }); 



    $row.find("td, th").bind("contextmenu", dtable.row_mousedown);

    jQuery(document).bind("mouseup", function(e) {
       if (e.which == 1) { $context_menu.hide(); }
    });

	//prevent unmerge button from sending form
    jQuery("body").delegate(".dtable_unmerge", "dblclick", function(e) {
		e.stopPropagation();
    });

	//prevent outer texarea from sending form
    jQuery(".dtable").delegate("textarea.dtable_field", "dblclick", function(e) {
		e.stopPropagation();
    });

    //This was previously at the bottom of init function
    jQuery(".dtable").delegate(".form_row", "dblclick", function(e) {
		e.stopPropagation();
    });

    jQuery("body").delegate("#"+dtable.toolbar_id, "dblclick", function(e) {
		e.stopPropagation();
    });

    jQuery(document).dblclick(function(e){
	    //sent form only once
	    if(dtable.form_processing == false) {
			dtable.form_processing = true;
			//$context_menu.hide();
			if(jQuery(".dtable .form_row").find(":visible").length > 0)
				jQuery(".dtable").submit();
	    }
    });
};
dtable.lock_dtable = function()
{
    var $row = dtable.get_data_rows(jQuery(".dtable"));

    jQuery(document).unbind('mousemove');
    $row.find("td, th").unbind('contextmenu');
    
    jQuery("#dtable_context_menu").hide();
};

dtable.row_mousedown = function(e) {
    var $this_cell = jQuery(this);
    var $this_row = $this_cell.closest("tr");

    var $context_menu = jQuery("#dtable_context_menu");
	
    $context_menu.html('');
	switch(this.nodeName.toLowerCase())
	{
		case 'td':
			//create contextMenu
			var context_menus = ['insert_before', 'insert_after', 'edit', 'remove'];
				//'insert_col_left', 'insert_col_right', 'mark_row_as_header', 'mark_col_as_header', 'mark_cell_as_header'];
		break;
		case 'th':
			var context_menus = ['insert_before', 'insert_after', 'edit', 'remove'];
				//'insert_col_left', 'insert_col_right', 'mark_row_as_cell', 'mark_col_as_cell', 'mark_cell_as_cell'];
		break;
	}
	//remove disabled actions
	context_menus = jQuery(context_menus).not(JSINFO['disabled']).get();

	var colspan = $this_cell.attr("colspan");
	var rowspan = $this_cell.attr("rowspan");


	for(item_index in context_menus)
	{
		var item = context_menus[item_index];
		jQuery('<li class="'+item+'">').html('<a href="#'+item+'">'+JSINFO['lang'][item]).appendTo($context_menu);
	}
	$context_menu.find("li.edit").addClass("separator");
	$context_menu.find("li.insert_col_left").addClass("separator");
	$context_menu.find("li.mark_row_as_header").addClass("separator");

	
    var offsetX = e.pageX + 1;
    var offsetY = e.pageY + 1;


    $context_menu.show();
    $context_menu.css('top',offsetY);
    $context_menu.css('left',offsetX);

    dtable.row = $this_row;
    e.preventDefault();

};
dtable.clear_all_intervals = function()
{
    for( i in dtable.intervals)
    {
	clearInterval(dtable.intervals[i]);
    }
};

dtable.change_rows = function($table, spans)
{
	$table.find("tr").each(function(index) {
		jQuery(this).find("td, th").each(function(td_ind) {
			if (spans[index][td_ind][0] !== 1) {
				jQuery(this).attr("colspan", spans[index][td_ind][0]);
			} else {
				jQuery(this).removeAttr("colspan");
			}

			if (spans[index][td_ind][1] !== 1) {
				jQuery(this).attr("rowspan", spans[index][td_ind][1]);
			} else {
				jQuery(this).removeAttr("rowspan");
			}
		});
	});
	
};

dtable.get_table_id = function($form)
{
    var table = $form.attr("id");
    return table.replace(/^dtable_/, '');
};

dtable.new_build_form = function($form, $row, action, value, row_data, colspan_callback, mod_cell_callback)
{
	$form_row = jQuery('<tr class="form_row">').hide().appendTo( $form.find("table") );

	if ($form.find("input.dtable_field.dtable_action").length > 0)
   	{
		jQuery($form).find("input.dtable_field.dtable_action").attr("name", action).val(JSON.stringify(value));
		jQuery($form).find("input.dtable_field[name=table]").val(dtable.get_table_id($form));
						
	} else
   	{
		//append dtable_action
		jQuery($form).append('<input type="hidden" class="dtable_action dtable_field" name="'+action+'" value="'+JSON.stringify(value)+'">');
		//append table name
		jQuery($form).append('<input type="hidden" class="dtable_field" name="table" value="'+ dtable.get_table_id($form) +'">');
	}


	var rowspans = [];
	var rowspans_keys = [];
	var rows_after = 0;
	//found rowspans mother cells
	$this_row.next().prevAll().each(
		function() {
			jQuery(this).find("td, th").each(function()
				{
					var rowspan = jQuery(this).attr("rowspan");
					if (typeof rowspan !== 'undefined' && rowspan !== false && parseInt(rowspan) > rows_after) {
						var ind = jQuery(this).index();
						rowspans[ind] = jQuery(this);
						rowspans_keys.push(ind);
					}
				});
				rows_after++;
		});
	rowspans_keys.sort();


	var td_index = 0;
	var col = 0;
	var rowsp_cell_ind = 0;


	var cells = row_data[0];

	for(var i = 0; i < cells.length; i++)
	{
		switch (cells[i][2]) {
			case '^':
				var tclass = 'tableheader_open';
				break;
			default:
				var tclass = 'tablecell_open';
				break;
		}
		var colspan = cells[i][0]; 
		var rowspan = cells[i][1]; 
		var content = cells[i][3]; 

		var $father_cell = $row.find("td, th").eq(td_index);
		//var rowspan = $father_cell.attr('rowspan');

		if (mod_cell_callback !== undefined) {
			var mod = mod_cell_callback.call(this, tclass, rowspan, colspan, content);

			tclass = mod[0];
			rowspan = mod[1];
			colspan = mod[2];
			content = mod[3];
		}

		if (jQuery.trim(content) == ':::')
		{
			var $mother_cell = rowspans[rowspans_keys[rowsp_cell_ind]];
			var width = $mother_cell.width();
			if (width < 20)
				width = 20;
			rowsp_cell_ind++;
			jQuery('<textarea class="'+tclass+' rowspans dtable_field" name="col' + col +'">').val(content).width(width).css({'position': 'relative', 'display': 'block'}).appendTo($mother_cell);
			col++;
			if ($mother_cell.get(0) === $father_cell.get(0))
				td_index++;
		} else
		{

			if (action === "edit") {
				var width = $father_cell.width();// + 10;
				var height = $father_cell.height(); //+ 5;
				if (height < 40)
					height = 40;
				if (width < 80)
					width = 80;
			} else
			{
				width = $father_cell.width();
				height = 50;
			}

			if (colspan > 1)
			{
				col = colspan_callback.call(this, $form_row, colspan,  width, height, tclass, col, content);
				td_index++;

			} else
			{

				var $form_cell = jQuery('<td>').attr({rowspan: rowspan});
				$textarea = jQuery('<textarea class="'+tclass+' dtable_field" name="col' + col +'">').val(content).width(width).height(height).appendTo($form_cell);

				td_index++;
				col++;

				$form_row.append($form_cell);
			}
		}

	}
	$form.find("textarea.dtable_field").first().attr("id", dtable.textarea_id);

    var $toolbar = jQuery("#"+dtable.toolbar_id);
	if ($toolbar.is(':empty')) {
		initToolbar(dtable.toolbar_id, dtable.textarea_id, toolbar);
	}
};

dtable.get_lines = function ($form, id) {
	var rows_data = $form.data("table");
	return JSON.stringify(rows_data[id][1]);
};

dtable.remove = function($this_row) {
	$form = $this_row.closest("form");
	$table = $form.find("table");

	var id = dtable.get_row_id($table, $this_row);
	  jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', 
	  {
	      'call': dtable.get_call($form),
	      'table': dtable.get_table_id($form),
		  'remove': dtable.get_lines($form, id)
	  },
	  function(data)
	  {
	      var res = jQuery.parseJSON(data);
	      if(res.type == 'success')
	      {
			var rows_data = $form.data("table");
			var length = rows_data[id][1][1] - rows_data[id][1][0] + 1;
			rows_data.splice(id, 1);

			$form.data("table", rows_data);

			$this_row.remove();


			for (var i = id; i < rows_data.length; i++) {
				  rows_data[i][1][0] -= length;
				  rows_data[i][1][1] -= length;
			}
		    $form.data('table', rows_data);

			//change rows in case of rowspan
			dtable.change_rows($table, res.spans);


	      } else
	      {
			  dtable.error(res.msg);
	      }
	  });
};

dtable.contex_handler = function(e) {
    e.preventDefault();

	var insert_colspan_callback = 
				function($form_row, colspan,  width, height, tclass, col) {
					width /= colspan;
					for (var j = 0; j < colspan; j++)
					{
						jQuery('<textarea class="'+tclass+' dtable_field" name="col' + col +'">').val('').width(width).height(height).appendTo(jQuery('<td>').appendTo($form_row));
						col++;
					}
					return col;
				}; 

    $this_row = dtable.row;
    dtable.id = $this_row.closest(".dtable").attr("id");

    var row_id = $this_row.attr("id");
    var $table = $this_row.closest("table");
    var $form = $this_row.closest("form");


    var table = dtable.get_table_id($form);

    //hide current form
    var ev = jQuery(this).attr("href");

	//check any form in any table
	jQuery(".form_row").each(function() {
		$this_table = jQuery(this).closest("table");
		$this_table.find("tr:hidden").show();
		dtable.hide_form($this_table);
	});

    switch(ev)
    {
	case '#remove':
		dtable.remove($this_row);
	break;
	case '#edit':

		var row_id = dtable.get_row_id($table, $this_row);
		var rows_data = $form.data("table");
		var rows = rows_data[row_id];

		dtable.new_build_form($form, $this_row, "edit", rows[1], rows,
				function($form_row, colspan,  width, height, tclass, col, content) {
					$form_cell = jQuery('<td>').attr({'colspan': colspan});

					var $button = jQuery('<button class="toolbutton dtable_unmerge" title="'+((JSINFO['lang']['show_merged_rows']).replace("%d", colspan-1))+'"><img width="16" height="16" src="lib/plugins/dtable/images/unmerge.png"></button>').appendTo($form_cell);

					$form_row.append($form_cell);

					var textareas = [];
					jQuery('<textarea class="'+tclass+' dtable_field" name="col' + col +'">').val(content).width(width).height(height).appendTo($form_cell);
					textareas.push(col);
					col++;
					for (var j = 1; j < colspan; j++)
					{
						jQuery('<textarea class="'+tclass+' dtable_field" name="col' + col +'">').val('').width(width).height(height).appendTo(jQuery('<td>').hide().appendTo($form_row));
						textareas.push(col);
						col++;
					}

					$button.data('textareas', textareas);
					$button.data('colspan', colspan);

					$button.click(function() {
						var textareas = jQuery(this).data('textareas');
						var colspan = jQuery(this).data('colspan');

						var $mother = $form.find("textarea.dtable_field[name=col"+textareas[0]+"]");
						$mother.closest('td').attr('colspan', 0);
						var width = $mother.width() / colspan;
						var tdwidth = $mother.closest('td').width() / colspan;
						var height = $mother.height();
						for(var i = 0; i < textareas.length; i++)
						{
							var $elm = $form.find("textarea.dtable_field[name=col"+textareas[i]+"]");
							$elm.closest('td').show();
							$elm.width(width).height(height);
						}
						jQuery(this).remove();
					});
					return col;
				});
		$this_row.after($table.find(".form_row"));

		$this_row.hide();
		dtable.show_form($table);  

	break;
	case '#insert_after':

		var row_id = dtable.get_row_id($table, $this_row);
		var rows_data = $form.data("table");
		var rows = rows_data[row_id];

		dtable.new_build_form($form, $this_row, "add", rows[1][1], rows, 
				insert_colspan_callback,
				function(cclass, rowspan, colspan, value)
			    {
					if (jQuery.trim(value) !== ':::')
						value = '';
					if (typeof rowspan !== 'undefined' && rowspan !== false && rowspan > 1) {
						rowspan = 1;
						value = ':::'
					}

					cclass = 'tablecell_open';
					return [cclass, rowspan, colspan, value];
			    });

		var $form_row = $table.find(".form_row");

		$this_row.after($form_row);
		dtable.show_form($table);  
	break;
	case '#insert_before':

		var $form_row = $table.find(".form_row");

		var rows_data = $form.data("table");

		var $before_elm = $this_row.prev();


		if($before_elm.length != 0) {
			var bef_row_id = dtable.get_row_id($table, $before_elm);
		    var add = rows_data[bef_row_id][1][1];
			var first_elm = false;
		} else {
			var add = rows_data[0][1][1];
			var first_elm = true;
		}

		var rows = rows_data[dtable.get_row_id($table, $this_row)];

		if (first_elm == true) {
			var mod_row_call = 
				function(cclass, rowspan, colspan, value)
			    {
					if (jQuery.trim(value) !== ':::')
						value = '';
					if (typeof rowspan !== 'undefined' && rowspan !== false && rowspan > 1) {
						rowspan = 1;
						value = ''
					}

					cclass = 'tablecell_open';
					return [cclass, rowspan, colspan, value];
			    };
		} else {
			var mod_row_call = 
				function(cclass, rowspan, colspan, value)
			    {
					if (jQuery.trim(value) !== ':::')
						value = '';
					if (typeof rowspan !== 'undefined' && rowspan !== false && rowspan > 1) {
						rowspan = 1;
						value = ':::'
					}

					cclass = 'tablecell_open';
					return [cclass, rowspan, colspan, value];
			    };
		}

		dtable.new_build_form($form, $this_row, "add", add, rows, 
				insert_colspan_callback, mod_row_call);

		$this_row.before($table.find(".form_row"));
		dtable.show_form($table);  
	break;
    }
    jQuery(this).closest("#dtable_context_menu").hide();
};

dtable.init = function()
{
//create panlock elm
jQuery('<div class="panlock notify">').html(JSINFO['lang']['lock_notify']).hide().prependTo(".dtable");

//create panunlock elm
jQuery('<div class="panunlock notify">').html(JSINFO['lang']['unlock_notify']).hide().prependTo(".dtable");


//update lock expires
dtable.intervals.push(setInterval(function()
{
    dtable.lock_expires -= 1;
    if(dtable.lock_expires <= -1)
	return;
    
    if(dtable.lock_expires === 0)
    {
	//we had own lock
	if(dtable.page_locked == 1)
	{
	    //clear all intervals
	    dtable.clear_all_intervals();

	    //page is locked
	    dtable.page_locked = 0;

	    var $forms = jQuery('.dtable .form_row:visible').closest('form');
	    $forms.submit();

	    //after submitting form
	    dtable.lock_dtable();
	    dtable.panlock_switch('panunlock');
	} else 
	{
	    //unblock us if someones lock expires
	    dtable.lock_seeker();
	}
    }
    dtable.update_lock_timer(dtable.lock_expires);
}, 1000));


$toolbar = jQuery("body").append('<div id="'+dtable.toolbar_id+'" style="position:absolute;display:none;z-index:999"></div>');

jQuery.ui.dialog.prototype._oldcreate = jQuery.ui.dialog.prototype._create;
jQuery.extend(jQuery.ui.dialog.prototype, {
    _init: function( )
    {
	//This must be done to have correct z-index bahaviour in monobook template
	var lin_wiz = jQuery("#link__wiz");
	lin_wiz.appendTo("body");
	 this._oldcreate();
    }
});

//This is the place where was old init Toolbar code


//create empty context menu - it will be filled with context before displaying
var $context_menu = jQuery('<ul id="dtable_context_menu">').prependTo("body");


$context_menu.delegate("a", "click", dtable.contex_handler);


var $row = dtable.get_data_rows(jQuery(".dtable"));



dtable.lock_seeker(dtable.unlock_dtable, dtable.lock_dtable);

dtable.intervals.push(setInterval(function() {
    dtable.lock_seeker(dtable.unlock_dtable, dtable.lock_dtable);
}, dtable.lock_seeker_timeout));


//Add is set on id of element after we want to add new element if set to -1 we adding element at the top of the table
jQuery(".dtable").submit(
	function()
	{
	    var $form = jQuery(this);
	    if($form.attr("id") == dtable.id) {
			/*dtable.form_processing = true;*///Now form_processing is in dblclick func
			var data = {};
			var action = jQuery(this).find("input.dtable_field.dtable_action").attr("name");
			var any_data = false;
			jQuery(this).find("textarea.dtable_field, input.dtable_field").each(
				function()
				{
					//if row is empty it isn't submited during adding and it's deleted during editing
					if (jQuery(this).attr("class") != null && jQuery(this).attr("name").indexOf("col") == 0) {
						if (jQuery(this).val() != "" && jQuery.trim(jQuery(this).val()) != ':::')
							any_data = true;
						data[jQuery(this).attr("name")] = JSON.stringify([jQuery(this).hasClass("tableheader_open") ? "tableheader_open" : "tablecell_open", jQuery(this).val()]);
					} else
						data[jQuery(this).attr("name")] = jQuery(this).val();
				});

			if (any_data == true) {
				jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', 
						data,
				function(data)
				{
					  var res = jQuery.parseJSON(data);
					  if(res.type == 'success')
					  {
						  
						  if( res.new_row !== undefined )
						  {
							  //remove old element
						      if (action == "edit")
								  $form.find(".form_row").prev().remove();

							  var $table = $form.find("table");

							  $new_elm = jQuery('<tr>');
							  $new_elm.html( res.new_row );

							  $form.find(".form_row").after($new_elm);
							  if(dtable.page_locked == 1)
								  $new_elm.find("td, th").bind("contextmenu", dtable.row_mousedown);

							  var index = dtable.get_row_id($table, $new_elm);
							  
							  var raw_rows = $form.data('table');

							  if (res.action == 'edit') {
								  old_row = raw_rows[index];
								  raw_rows[index] = res.raw_row;
								  diff = old_row[1][1] - old_row[1][0];
								  for (var i = index+1; i < raw_rows.length; i++) {
									  raw_rows[i][1][0] -= diff;
									  raw_rows[i][1][1] -= diff;
								  }
							  } else {
								  raw_rows.splice(index, 0, res.raw_row);
								  diff = res.raw_row[1][1] - res.raw_row[1][0] + 1;
								  for (var i = index+1; i < raw_rows.length; i++) {
									  raw_rows[i][1][0] += diff;
									  raw_rows[i][1][1] += diff;
								  }
							  }
							  $form.data('table', raw_rows);
						  }

						  dtable.hide_form($form);

						  var $table = $form.find("table");
						  dtable.change_rows($table, res.spans);

					  } else
					  {
						  dtable.error(res.msg);
					  }
					  dtable.form_processing = false;
				   });
			} else {
				if (action == "edit") {
					$this_row = $form.find(".form_row").prev();
					dtable.remove($this_row);
					$this_row.remove();
				}
				dtable.hide_form($form);
				dtable.form_processing = false;
			}
	    }
	   return false;
	});
jQuery(".dtable").delegate('textarea.dtable_field', 'focus', function(e) {

    dtable.id = jQuery(this).closest(".dtable").attr("id");

    if(jQuery(this).attr("id") != dtable.textarea_id)
    {
	$marked_textarea = jQuery("#"+dtable.textarea_id);

	$marked_parent = $marked_textarea.parent();
	$this_parent = jQuery(this).parent();
	
	this_val = jQuery(this).val();
	marked_val = $marked_textarea.val();
	jQuery(this).val(marked_val);
	$marked_textarea.val(this_val);

	this_name = jQuery(this).attr("name");
	marked_name = $marked_textarea.attr("name");
	jQuery(this).attr("name", marked_name);
	$marked_textarea.attr("name", this_name);

	this_class = jQuery(this).attr("class");
	marked_class = $marked_textarea.attr("class");
	jQuery(this).attr("class", marked_class);
	$marked_textarea.attr("class", this_class);

	this_width = jQuery(this).width();
	marked_width = $marked_textarea.width();

	this_height = jQuery(this).height();
	marked_height = $marked_textarea.height();
	
	//get styles
	var this_style = jQuery(this).getStyleObject(['position', 'top', 'left', 'display']);
	var marked_style = $marked_textarea.getStyleObject(['position', 'top', 'left', 'display']);
	jQuery(this).css(marked_style);
	$marked_textarea.css(this_style);
	

	//correct width and height
	jQuery(this).width(marked_width);
	$marked_textarea.width(this_width);

	jQuery(this).height(marked_height);
	$marked_textarea.height(this_height);
	

	$marked_parent.append(jQuery(this));
	$this_parent.append($marked_textarea);

	$marked_textarea.show();

	jQuery("#"+dtable.textarea_id).focus();

    }

});



};

jQuery(document).ready(function()
{
	//load images
	new Image('lib/plugins/dtable/images/unmerge.png');
    //check permission and if any dtable exists
    if(JSINFO['write'] === true && jQuery(".dtable").length > 0)
		dtable.init();
});
jQuery(window).on('unload', function () { dtable.unlock(); } );
