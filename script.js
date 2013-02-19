/* DOKUWIKI:include_once jquery.contextMenu.js */

dtable = {};
//acl >= 2 - użytkownik może modyfikować tabelkę
//dtable.init = function(acl, self_url, wiki_url, page_id)
dtable.init = function()
{
    jQuery(".tr_hover").contextMenu(
    {
	menu: "dtable_context_menu",
    },
    function(action, el, pos)
    {
	alert(el.attr("id"));
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
    } */
};

jQuery(dtable.init());
