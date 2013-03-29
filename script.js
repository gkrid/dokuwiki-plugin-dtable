dtable = {};

//acl >= 2 - użytkownik może modyfikować tabelkę
//dtable.init = function(acl, self_url, wiki_url, page_id)

dtable.toolbar_id = "dtable_tool__bar";
//I need it to use dokuwiki toolbar
dtable.textarea_id = "dtable_wiki__text";

//Store informatino about actual clicked row
dtable.row = {};

dtable.error = function(msg)
{
    alert(msg);
};
dtable.show_form = function($parent)
{
    var $form = $parent.find(".form"); 
    var $toolbar = jQuery("#"+dtable.toolbar_id);
    $form.show();
    var offset = $form.offset();
    $toolbar.css({
	"left": offset.left, 
	"top": offset.top-$toolbar.height()
    });
    $toolbar.show();
};
dtable.hide_form = function($parent)
{
    var $form = $parent.find(".form"); 
    var $toolbar = jQuery("#"+dtable.toolbar_id);
    $form.hide();
    $toolbar.hide();
};
dtable.init = function()
{
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

console.log(jQuery.ui.dialog.prototype);

//If I won't do it, initToolbar will not work.
//$marked_textarea = 
jQuery("#dtable_form textarea").first().attr("id", dtable.textarea_id);

initToolbar(dtable.toolbar_id,dtable.textarea_id,toolbar);

var $menu_item = jQuery("#dtable_context_menu");
var $row = jQuery(".tr_hover");
$menu_item.appendTo("body");
$row.live("contextmenu",function(e){
		return false;
});


contex_handler = function(e) {
    e.preventDefault();

    $this_row = dtable.row;

    var row_id = $this_row.attr("id");
    var $table = $this_row.parents("table");
    var table = $table.attr("id");
    table = table.replace(/^dtable_/, '');

    //hide current form
    var ev = jQuery(e.currentTarget).attr("href");
    
    switch(ev)
    {
	case '#remove':
	  jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', 
	  {
	      'call': 'dtable',
	      'table': table,
	      'remove': row_id
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
	      } else
	      {
		  dtable.error(res.msg);
	      }
	  });
	break;
	case '#edit':
		$this_row.after($table.find(".form"));

		jQuery("#dtable_action").attr("name", "edit")
					.attr("value", row_id);

	      jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', 
	      {
		  'call': 'dtable',
		  'table': table,
		  'get': row_id
	      },
	      function(data)
	      {
		  var res = jQuery.parseJSON(data);
		  var $form_elm = jQuery("#dtable_form .form td").find("input, textarea");
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

			    $table.find(".form input, .form textarea").val('');
			    jQuery("#dtable_action").attr("name", "add").attr("value", "-1");
			
			    old_row.show();
			    contex_handler(e);
			});
	break;
	case '#insert_after':
		$this_row.after($table.find(".form"));
		dtable.show_form($table);  
		jQuery("#dtable_action").val($this_row.attr('id'));
	break;
	case '#insert_before':
		var $before_elm = $this_row.prev();
		var add = -1;
		if($before_elm.length != 0)
		    add = $before_elm.attr("id");

		jQuery("#dtable_action").val(add);
		$this_row.before($table.find(".form"));
		dtable.show_form($table);  
	break;
    }
    //jQuery("#dtable_context_menu a").unbind();
    $menu_item.hide();
};

jQuery("#dtable_context_menu a").live("click", contex_handler);

var f_row_mousedown = function(e) {
    var $this_row = jQuery(this);
    var offsetX = e.pageX + 1;
    var offsetY = e.pageY + 1;
    if(e.button == "2") {
	e.stopPropagation();
	$menu_item.show();
	$menu_item.css('top',offsetY);
	$menu_item.css('left',offsetX);

	dtable.row = $this_row;

    } else {
	    //jQuery("#dtable_context_menu a").unbind();
	    $menu_item.hide();
    }
};
$row.mousedown(f_row_mousedown);
//Add is set on id of element after we want to add new element if set to -1 we adding element at the top of the table
jQuery("#dtable_form").submit(
	function()
	{
	    var data = {};
	    var $form = jQuery(this);
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
		  if(res.type == 'success')
		  {
		      
		      $new_elm = jQuery('<tr id="'+res.id+'" class="tr_hover">');
		      $form.find(".form").after($new_elm);

		      for(f in res.fileds)
		      {
			  $new_elm.append("<td>"+res.fileds[f]+"</td>");
		      }

		      $new_elm.mousedown(f_row_mousedown);

		      dtable.hide_form($form);
		      $form.find(".form input, textarea").val('');

		    $edit_link = jQuery("#dtable_context_menu a");
		    
		    $edit_link.die();
		    $edit_link.live('click', contex_handler);
		      
		  } else
		  {
		      dtable.error(res.msg);
	          }
		  jQuery("#dtable_action").attr("name", "add").attr("value", "-1");
	   });
	   return false;
	});
jQuery("#dtable_form textarea").bind('focus', function(e) {
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

	jQuery("#"+dtable.textarea_id).focus();

	//$marked_textarea = jQuery(this);
    }

});
$menu_item.dblclick(function(e) {
    e.stopPropagation();
});
jQuery("#dtable_form .form").dblclick(function(e) {
    e.stopPropagation();
});

jQuery("#"+dtable.toolbar_id).dblclick(function(e) {
    e.stopPropagation();
});

jQuery(document).dblclick(function(e){
	//jQuery("#dtable_context_menu a").unbind();
	$menu_item.hide();
	if(jQuery("#dtable_form .form").find(":visible").length > 0)
	    jQuery("#dtable_form").submit();
});



};

jQuery(document).ready(function()
{
    dtable.init()
});
