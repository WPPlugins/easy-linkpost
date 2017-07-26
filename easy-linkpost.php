<?php
/*
Plugin Name: Easy Linkpost 
Plugin URI: http://tmpla.info
Description: Create link on Post more conveniently.
Version: 0.2
Author: kyokoshima
Author URI:  http://tmpla.info
*/
/*  Copyright 2011 kyokoshima (email : k.yokoshima@gmail.com:)
 
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class EasyLinkPost{
	private $version = 0.2;
	
	function isPostEditable(){
		$ru = $_SERVER['REQUEST_URI'];
		return strpos($ru, 'post.php') ||
				strpos($ru, 'post-new.php') ||
				strpos($ru, 'page.php') ||
				strpos($ru, 'page-new.php');
	}

	function doCreateLinkData(){
		$url = $_GET['linkUrl'];
		$html = file_get_contents($url); //(1)
		//wp_remote_get()
    	mb_language('Japanese');
		$html = mb_convert_encoding($html, mb_internal_encoding(), 'auto' ); //(2)

    	$title = '';
    	if ( preg_match( "/<title>(.*?)<\/title>/i", $html, $matches) ) { //(3)
        	$title = $matches[1];
    	}
	    if(!class_exists("Services_JSON")){
			require_once( ABSPATH . WPINC . '/class-json.php' );
		}
		
		$data = array( 'title' => $title);
		 
		$json 	= new Services_JSON; 
	
		header("Content-Type: application/json; charset=utf-8");	
		echo $json->encode($data);
  		die();
	}
	
	function onAdminPrintFooterScripts(){
		if ($this->isPostEditable()){
			$pluginPath = WP_PLUGIN_URL . '/' . basename(dirname(__FILE__));
			//$pluginJs = $pluginPath . '/js/easy-linkpost_js.php';
			$pluginJs = admin_url() . 'admin.php?action=easy-linkpost';
			wp_register_script('easy-linkpost', $pluginJs
				, array('jquery', 'quicktags', 'jquery-ui-dialog'), $this->version);
			$pluginCss = $pluginPath . '/css/jquery-ui-1.8.16.dialog.css';
			wp_register_style('jquery-dialog', $pluginCss);
			wp_print_scripts('easy-linkpost');
			wp_enqueue_style('jquery-dialog');
			
		}
	}
	
	function js(){
		$expires_offset = 31536000;
		header('Content-Type: application/x-javascript; charset=UTF-8');
		header('Vary: Accept-Encoding'); // Handle proxies
		header('Expires: ' . gmdate( "D, d M Y H:i:s", time() + $expires_offset ) . ' GMT');
		header("Cache-Control: public, max-age=$expires_offset");

		$postUrl = admin_url() . 'admin-ajax.php';
		$script = <<<EOD
jQuery(document).ready(function($){
	
	var ezlpCallback = function(){
		var postFunc = function(url){
			$.ajax({
				type:'get',
				url:'$postUrl',
				dataType:'json',
				data:{
					action:'easy-linkpost',
					linkUrl:url
				},
				success:function(resp){
					var title;
					if (resp.title){
						title = resp.title;
					}
					var output;
					
					if ($('.displayStyle:checked').val() == '0'){
						output = title + '\\r\\n<a href="' + url + '">' + url + '</a>';
					}else{
						output = '<a href="' + url + '">' + title + '</a>';
					}
					//edInsertContent(edCanvas, output);
					QTags.insertContent(output);
				},
				error:function(xhr, textStatus, errorThrown){
					//edInsertContent(edCanvas, '<a href="' + url + '"></a>');
					QTags.insertContent(output);
				},
			});
		}
		var dlg = $('#easy-linkpost-dialog');
		$('[name="url"]', dlg).val('http://');
		dlg.dialog({
			dialogClass: 'wp-dialog',
			title:quicktagsL10n.enterURL,
			modal:true,
			draggable: false,
			resizable: false,
			buttons:
				{
				'OK':function(){
					var url = $('[name="url"]', this).val();
						postFunc(url);
						$(this).dialog('close').dialog('destroy');
					},
				'Cancel':function(){ 
						$(this).dialog('close').dialog('destroy'); 
					}
				}
		});
	}
	
	QTags.addButton('ez_linkpost_btn', 'ez-link!', ezlpCallback, null, null, null, 35);
});
EOD;
echo $script;
	}
	
	function onAdminFooter(){
		$dialog = <<<EOD
<div id="easy-linkpost-dialog" title="input URL" style="display:none;">
<input type="text" size="30" name="url" /><br />
<input type="radio" name="displayStyle" value="0" class="displayStyle" checked="checked"/>
<label for="displayStyle0">Plain Title and URL anchor.</label><br />
<input type="radio" name="displayStyle" value="1" class="displayStyle" />
<label for="displayStyle1">Pagetitle into URL anchor.</label>
<div id="style-example"></div>
</div>
<script type="text/javascript">
jQuery(document).ready(function($){
	$('.displayStyle').live('change', function(){
		var exampleText;
		if ($(this).val() == '0'){
			exampleText = '[TITLE]<br /><a>http://example.com/</a>';
		}else{
			exampleText = '<a>[TITLE]</a>';
		}
		$('#style-example').html(exampleText);
	});
	
	$('.displayStyle[value=0]').trigger('change');
});
</script>
EOD;
		echo $dialog;
	}
	
}

$_easy_linkpost = new EasyLinkPost();
	if (is_admin()){
		add_filter('admin_print_footer_scripts'
			, array($_easy_linkpost, 'onAdminPrintFooterScripts'));
		add_action('admin_footer', array($_easy_linkpost, 'onAdminFooter'));
		add_action('wp_ajax_easy-linkpost', array($_easy_linkpost, 'doCreateLinkData'));
		add_action('admin_action_easy-linkpost', array($_easy_linkpost, 'js'));
	}