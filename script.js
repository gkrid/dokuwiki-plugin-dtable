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
dtable.show_form = function($parent)
{
    var $form = $parent.find(".form_row"); 
    var $toolbar = jQuery("#"+dtable.toolbar_id);

    //backwards contability with 
    if($form.closest('form').hasClass("dynamic_form"))
    {
	//there will be code which will handle rowspan in the futhure
    }

    //display fix jquery 1.6 bug
    $form.css("display", "table-row");

    var offset = $form.offset();
    $toolbar.css({
	"left": offset.left, 
	"top": offset.top-$toolbar.height()
    });
    $toolbar.show();
};
dtable.hide_form = function($parent)
{
    var $form = $parent.find(".form_row"); 
    var $toolbar = jQuery("#"+dtable.toolbar_id);
    $form.hide();
    $toolbar.hide();
};
dtable.get_data_rows = function($table)
{
    return $table.find("tr").not(".form_row").not(":has(th)");
};
dtable.get_row_id = function($table, $row)
{
    return dtable.get_data_rows($table).index($row);
};
dtable.get_call = function($form)
{
	return $form.find("input[name=call]").val();
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
	    jQuery('.dtable .form_row').find('textarea, input').each(function() {
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

    jQuery("#dtable_context_menu a").live("click", contex_handler);


    $row.bind("contextmenu", dtable.row_mousedown);

    jQuery(document).bind("mouseup", function(e) {
       if (e.which == 1) { $context_menu.hide(); }
    });


    //This was previously at the bottom of init function
    jQuery(".dtable .form_row").dblclick(function(e) {
	e.stopPropagation();
    });

    jQuery("#"+dtable.toolbar_id).dblclick(function(e) {
	e.stopPropagation();
    });

    jQuery(document).dblclick(function(e){
	    //sent form only once
	    if(dtable.form_processing == false)
	    {
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
    $row.unbind('contextmenu');
    
    jQuery("#dtable_context_menu").hide();
};

dtable.row_mousedown = function(e) {
    var $this_row = jQuery(this);
    var $context_menu = jQuery("#dtable_context_menu");

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

dtable.change_rows = function($table, rowspans)
{
      for( row in rowspans )
      {
	  var rowspan = rowspans[ row ];

	  var $cell = $table.find('tr').eq(parseInt( rowspan.tr ) + 1)
			    .find('td, th').eq( parseInt( rowspan.td) );

	      
	  $cell.attr("rowspan", rowspan.val);


      }
};

dtable.get_table_id = function($form)
{
    var table = $form.attr("id");
    return table.replace(/^dtable_/, '');
};

dtable.init = function()
{
//create panlock elm
jQuery('<div class="panlock notify">').html(JSINFO['lang']['lock_notify']).hide().prependTo(".dtable");

//create panunlock elm
jQuery('<div class="panunlock notify">').html(JSINFO['lang']['unlock_notify']).hide().prependTo(".dtable");

//create form
jQuery(".dtable.dynamic_form").each(function()
{
    //append dtable_action
    jQuery(this).append('<input type="hidden" class="dtable_action" name="add" value="-1">');
    //append table name
    jQuery(this).append('<input type="hidden" name="table" value="'+ dtable.get_table_id( jQuery(this) ) +'">');
    
    var td_len = jQuery( this ).find("tr:first").find("td, th").length;

    $form_row = jQuery('<tr class="form_row">').hide().appendTo( jQuery( this ).find("table") );
    
    for(var i = 0; i < td_len; i++ )
    {
	$form_row.append( jQuery( '<td>' ).append('<textarea name="col' + i +'">') );
    }
});

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
	//This must be done to have correct z-index bahaviour
	jQuery("#link__wiz").appendTo("body");
	 this._oldcreate();
    }
});

//If I won't do it, initToolbar will not work.
jQuery("<textarea>").hide().attr("id", dtable.textarea_id).appendTo("body");

//jQuery(".dtable textarea").first().attr("id", dtable.textarea_id);

initToolbar(dtable.toolbar_id,dtable.textarea_id,toolbar);

//create contextMenu

var context_menus = ['insert_before', 'insert_after', 'edit', 'remove'];

var $context_menu = jQuery('<ul id="dtable_context_menu">').prependTo("body");


for(item_index in context_menus)
{
    var item = context_menus[item_index];
    jQuery('<li class="'+item+'">').html('<a href="#'+item+'">'+JSINFO['lang'][item]).appendTo($context_menu);
}
$context_menu.find("li.edit").addClass("separator");



var $row = dtable.get_data_rows(jQuery(".dtable"));


contex_handler = function(e) {
    e.preventDefault();

    $this_row = dtable.row;
    dtable.id = $this_row.closest(".dtable").attr("id");

    var row_id = $this_row.attr("id");
    var $table = $this_row.closest("table");
    var $form = $this_row.closest("form");


    var table = dtable.get_table_id($form);

    //hide current form
    var ev = jQuery(e.currentTarget).attr("href");
    
    switch(ev)
    {
	case '#remove':
	  jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', 
	  {
	      'call': dtable.get_call($form),
	      'table': table,
	      'remove': dtable.get_row_id($table, $this_row)
	  },
	  function(data)
	  {
	      var res = jQuery.parseJSON(data);
	      if(res.type == 'success')
	      {
		  $this_row.remove();
		  if($table.find("tr").length <= 2 )
		  {
		    dtable.show_form($table);  
		  }
	      } else if(res.type == 'alternate_success')
	      {
		  $this_row.remove();

		  //change rows in case of rowspan
		  dtable.change_rows($table, res.rowspans);


		  if($table.find("tr").length <= 2 )
		  {
		    dtable.show_form($table);  
		  }
	      } else
	      {
		  dtable.error(res.msg);
	      }
	  });
	break;
	case '#edit':
		$this_row.after($table.find(".form_row"));

		$form.find(".dtable_action").attr("name", "edit")
					.attr("value", dtable.get_row_id($table, $this_row));

	      jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', 
	      {
		  'call': dtable.get_call($form),
		  'table': table,
		  'get': dtable.get_row_id($table, $this_row)
	      },
	      function(data)
	      {
		  var res = jQuery.parseJSON(data);
		  //!!!
		  //var $form_elm = jQuery("#"+dtable.id+" .form td").find("input, textarea");
		  var $form_elm = $table.find(".form_row").find("input, textarea");
		  var i = 0;
		  for(elm in res)
		  {
		      $form_elm.eq(i).val(res[elm]);
		      i++;
		  }
		  $this_row.hide();
		  dtable.show_form($table);  
	      });
		

		$edit_link = jQuery("#dtable_context_menu a");

		
		var old_row = $this_row;
		$edit_link.die();
		$edit_link.live('click', 
			function(e)
			{
			    dtable.hide_form($table);  

			    $table.find(".form_row").find("input, textarea").val('');
			    $form.find(".dtable_action").attr("name", "add").attr("value", "-1");
			
			    old_row.show();
			    contex_handler(e);
			});
	break;
	case '#insert_after':

		var $form_row = $table.find(".form_row");

		$this_row.after($form_row);
		dtable.show_form($table);  
		$form.find(".dtable_action").val(dtable.get_row_id($table, $this_row));
	break;
	case '#insert_before':

		var $form_row = $table.find(".form_row");

		var $before_elm = $this_row.prev();
		var add = -1;
		if($before_elm.length != 0)
		    add = dtable.get_row_id($table, $before_elm);

		$form.find(".dtable_action").val(add);
		$this_row.before($table.find(".form_row"));
		dtable.show_form($table);  
	break;
    }
    $context_menu.hide();
};

dtable.lock_seeker(dtable.unlock_dtable, dtable.lock_dtable);

dtable.intervals.push(setInterval(function() {
    dtable.lock_seeker(dtable.unlock_dtable, dtable.lock_dtable);
}, dtable.lock_seeker_timeout));


//Add is set on id of element after we want to add new element if set to -1 we adding element at the top of the table
jQuery(".dtable").submit(
	function()
	{
	    var $form = jQuery(this);
	    if($form.attr("id") == dtable.id)
	    {
		dtable.form_processing = true;
		var data = {};
		jQuery(this).find("input, textarea").each(
		    function()
		    {
			data[jQuery(this).attr("name")] = jQuery(this).val();
		    });
		jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', 
			    data,
		function(data)
		{
		      var res = jQuery.parseJSON(data);
		      //left for comtability with dtableremote
		      if(res.type == 'success')
		      {
			  
			  $new_elm = jQuery('<tr>');
			  $form.find(".form_row").after($new_elm);

			  for(f in res.fileds)
			  {
			      $new_elm.append("<td>"+res.fileds[f]+"</td>");
			  }

			  if(dtable.page_locked == 1)
			      $new_elm.bind("contextmenu", dtable.row_mousedown);

			  //remove old element
			  $form.find("tr:hidden").remove();

			  dtable.hide_form($form);
			  $form.find(".form_row input, textarea").val('');

			//$edit_link = jQuery("#dtable_context_menu a");
			
			//$edit_link.die();
			//$edit_link.live('click', contex_handler);

		      } else if(res.type == 'alternate_success')
		      {
			  console.log(res);
			  if( res.new_row !== undefined )
			  {
			      $new_elm = jQuery('<tr>');
			      $new_elm.html( res.new_row );

			      $form.find(".form_row").after($new_elm);
			      if(dtable.page_locked == 1)
				  $new_elm.bind("contextmenu", dtable.row_mousedown);
			  }

			  //remove old element
			  $form.find("tr:hidden").remove();
			  dtable.hide_form($form);
			  $form.find(".form_row input, textarea").val('');
			  
			  var $table = $form.find("table");
			  dtable.change_rows($table, res.rowspans);

		      } else
		      {
			  dtable.error(res.msg);
		      }
		      $form.find(".dtable_action").attr("name", "add").attr("value", "-1");
		      dtable.form_processing = false;
	       });
	    }
	   return false;
	});
jQuery(".dtable textarea").bind('focus', function(e) {

    dtable.id = jQuery(this).closest(".dtable").attr("id");

    //If I won't do it, initToolbar will not work.
    //jQuery("#dtable_form textarea:first-child").attr("id", "");
    //jQuery(this).attr("id", dtable.textarea_id);
    
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

	this_width = jQuery(this).width();
	marked_width = $marked_textarea.width();
	jQuery(this).width(marked_width);
	$marked_textarea.width(this_width);

	this_height = jQuery(this).height();
	marked_height = $marked_textarea.height();
	jQuery(this).height(marked_height);
	$marked_textarea.height(this_height);

	$marked_parent.append(jQuery(this));
	$this_parent.append($marked_textarea);

	$marked_textarea.show();

	jQuery("#"+dtable.textarea_id).focus();

	//$marked_textarea = jQuery(this);
    }

});



};

jQuery(document).ready(function()
{
    //check permission and if any dtable exists
    if(JSINFO['write'] === true && jQuery(".dtable").length > 0)
	dtable.init()
});
jQuery(window).unload( function () { dtable.unlock(); } );
