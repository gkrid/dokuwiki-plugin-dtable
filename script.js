dtable = {};

//acl >= 2 - użytkownik może modyfikować tabelkę
//dtable.init = function(acl, self_url, wiki_url, page_id)

dtable.error = function(msg)
{
    alert(msg);
}
dtable.init = function()
{
var $menu_item = jQuery("#dtable_context_menu");
var $row = jQuery(".tr_hover");
$menu_item.appendTo("body");
$row.live("contextmenu",function(e){
		return false;
});
var f_row_mousedown = function(e){
    var $this_row = jQuery(this);
    var offsetX = e.pageX + 1;
    var offsetY = e.pageY + 1;
    if(e.button == "2") {
	e.stopPropagation();
	$menu_item.show();
	$menu_item.css('top',offsetY);
	$menu_item.css('left',offsetX);

	var row_id = $this_row.attr("id");
	var $table = $this_row.parents("table");
	var table_id = $table.attr("id");
	var table_ex = table_id.split('_');
	var table = table_ex[1];
	jQuery("#dtable_context_menu a").bind("click",
	function(e)
	{
	    e.preventDefault();
	    var ev = jQuery(this).attr("href");
	    
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
			  console.log($table.find("tr").length);
			  if($table.find("tr").length <= 2 )
			  {
			    $table.find(".form").show();
			  }
		      } else
		      {
			  dtable.error(res.msg);
		      }
		  });
		break;
		case '#edit':
			$this_row.after($table.find(".form").show());

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
		      });
			
			$this_row.remove();
		break;
		case '#insert_after':
			$this_row.after($table.find(".form").show());
			jQuery("#dtable_action").val($this_row.attr('id'));
		break;
		case '#insert_before':
			var $before_elm = $this_row.prev();
			var add = -1
			if($before_elm.length != 0)
			    add = $before_elm.attr("id");

			jQuery("#dtable_action").val(add);
			$this_row.before($table.find(".form").show());
		break;
	    }
	    jQuery("#dtable_context_menu a").unbind();
	    $menu_item.hide();
	});
    } else {
	    jQuery("#dtable_context_menu a").unbind();
	    $menu_item.hide();
    }
}
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
		      $form.find(".form").hide();
		      $form.find(".form input, textarea").val('');
		      
		  } else
		  {
		      dtable.error(res.msg);
	          }
		  jQuery("#dtable_action").attr("name", "add").attr("value", "-1");
	   });
	   return false;
	});
$menu_item.mousedown(function(e) {
    e.stopPropagation();
});
jQuery("#dtable_form .form").mousedown(function(e) {
    e.stopPropagation();
});


jQuery(document).mousedown(function(e){
	jQuery("#dtable_context_menu a").unbind();
	$menu_item.hide();
	if(jQuery("#dtable_form .form").find(":visible").length > 0)
	    jQuery("#dtable_form").submit();
});



    /*var add_file = document.getElementById("wstaw_plik");

    if(add_file != null)
    {
	add_file.onclick = function()
	{
	window.open(wiki_url+"/lib/exe/mediamanager.php?ns="+page_id+"&edid=wiki__text", "pliki","width=800,height=600");
	}
    }

    /*id("aDodaj").onclick = function()
    {
	id("aform").style.display = "table-row";
	id("divContext").style.display = "none";
	clear_tr_hover();
	var td = id("aform").getElementsByTagName("td");
	var td0 = td[0];
	td0.firstChild.focus();
    } 
*/
};

jQuery(document).ready(function()
{
    dtable.init()
});
