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
dtable.error = function(msg)
{
    alert(msg);
};
dtable.show_form = function($parent)
{
    var $form = $parent.find(".form_row"); 
    var $toolbar = jQuery("#"+dtable.toolbar_id);
    $form.show();
    console.log($form);
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
}
dtable.get_row_id = function($table, $row)
{
    return dtable.get_data_rows($table).index($row);
}
dtable.init = function()
{

//create form

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
jQuery(".dtable textarea").first().attr("id", dtable.textarea_id);

initToolbar(dtable.toolbar_id,dtable.textarea_id,toolbar);

var $menu_item = jQuery("#dtable_context_menu");

var $row = dtable.get_data_rows(jQuery(".dtable"));

$menu_item.appendTo("body");
$row.live("contextmenu",function(e){
		return false;
});


contex_handler = function(e) {
    e.preventDefault();

    $this_row = dtable.row;
    dtable.id = $this_row.parents(".dtable").attr("id");

    var row_id = $this_row.attr("id");
    var $table = $this_row.parents("table");
    var $form = $this_row.parents("form");

    var table = $form.attr("id");
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
		  'call': 'dtable',
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
		$this_row.after($table.find(".form_row"));
		dtable.show_form($table);  
		$form.find(".dtable_action").val(dtable.get_row_id($table, $this_row));
	break;
	case '#insert_before':
		var $before_elm = $this_row.prev();
		var add = -1;
		if($before_elm.length != 0)
		    add = dtable.get_row_id($table, $before_elm);

		$form.find(".dtable_action").val(add);
		$this_row.before($table.find(".form_row"));
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

    $menu_item.show();
    $menu_item.css('top',offsetY);
    $menu_item.css('left',offsetX);

    dtable.row = $this_row;
    e.preventDefault();

};

$row.bind("contextmenu", f_row_mousedown);

$menu_item.click(function(e) {
    e.preventDefault();
});

/*jQuery(document).mousedown(function(e) {
    if(e.button !== 2) 
	$menu_item.hide();
});*/

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
		      if(res.type == 'success')
		      {
			  
			  $new_elm = jQuery('<tr>');
			  $form.find(".form_row").after($new_elm);

			  for(f in res.fileds)
			  {
			      $new_elm.append("<td>"+res.fileds[f]+"</td>");
			  }

			  $new_elm.bind("contextmenu", f_row_mousedown);

			  dtable.hide_form($form);
			  $form.find(".form_row input, textarea").val('');

			$edit_link = jQuery("#dtable_context_menu a");
			
			$edit_link.die();
			$edit_link.live('click', contex_handler);
			  
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

    dtable.id = jQuery(this).parents(".dtable").attr("id");

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
jQuery("table.dynamyc .form_row").dblclick(function(e) {
    e.stopPropagation();
});

jQuery("#"+dtable.toolbar_id).dblclick(function(e) {
    e.stopPropagation();
});

jQuery(document).dblclick(function(e){
	//sent form only once
	if(dtable.form_processing == false)
	{
	    $menu_item.hide();
	    if(jQuery(".dtable .form_row").find(":visible").length > 0)
		jQuery(".dtable").submit();
	}
});



};

jQuery(document).ready(function()
{
    dtable.init()
});
