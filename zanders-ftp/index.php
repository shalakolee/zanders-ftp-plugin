<?php
/**
 * Plugin Name: Zanders FTP Products
 * Plugin URI: http://git.shalakolee.com/woocommerce/zanders-ftp-plugin
 * Description: Download Inventory from Zanders ftp and sync with woocommerce products
 * Version: 0.1a
 * Author: Shalako Lee
 * Author URI: http://git.shalakolee.com
 * License: GPL2
 */
session_start();

//forbid inet users
if (!defined('ABSPATH')):
	header('HTTP/1.0 403 Forbidden');
exit;


endif;
//include the ftpconnection class
if(!class_exists("shalako_ftpConnection")):
	include dirname(__FILE__) .'/classes/ftpConnection.php';
endif;

function option_enabled( $option, $default=false ){
	global $plugin_settings;

	$option = get_option( $option );
    if( $option === false): //see if this is the first time loading, if it is, the option is not on - return false
    	if( $default == true):// want this on by default
    		return true;
		else:
	        return false;
    	endif;
    else:
        if( $option != "" ): // option is not blank, return true
            return true;
        else: // the option has no value, return false
            return false;
        endif;
     endif;
}

// register our own form handler
add_action( 'admin_post_update_custom_settings', 'admin_update_custom_settings' );
function admin_update_custom_settings() {
	
	//check commands and find out which one to run
	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'update_general_settings' && wp_verify_nonce( $_POST['check_ftp'], 'check_ftp' ) ):

	  	//set the zanders ftp options
	  	$message .= update_option('zanders_ftp_server',isset($_POST['zanders_ftp_server']) ? $_POST['zanders_ftp_server'] : '') ? "<p><strong>Updated Server URL</strong></p>" : "" ;
	  	$message .= update_option('zanders_ftp_port',isset($_POST['zanders_ftp_port']) ? $_POST['zanders_ftp_port'] : '') ? "<p><strong>Updated Server Port</strong></p>" : "" ;
	  	$message .= update_option('zanders_ftp_username',isset($_POST['zanders_ftp_server']) ? $_POST['zanders_ftp_username'] : '') ? "<p><strong>Updated Username</strong></p>" : "" ;
	  	$message .= update_option('zanders_ftp_password',isset($_POST['zanders_ftp_password']) ? $_POST['zanders_ftp_password'] : '') ? "<p><strong>Updated Password</strong></p>" : "" ;

	  	if($message):
	  		$_SESSION['message'] .= "<div class='updated dismissable'>";
	  		$_SESSION['message'] .= $message;
	  		$_SESSION['message'] .= "</div>";

  		endif;

		header('Location: ' . $_REQUEST['returl'], true, 301);
		die();
	endif;


}
//handle ajax stuff

add_action( 'wp_ajax_check_ftp', 'checkftp' );
function checkftp() {
	error_reporting(0);
	$server = $_REQUEST['server'];
	$port = $_REQUEST['port'];
	$username = $_REQUEST['username'];
	$password = $_REQUEST['password'];

	$myconn = new shalako_FtpConnection($server, $username, $password, true, $port, 10 );
	$connstatus = $myconn->connect(); 

	if($connstatus): // we are connected to the server and logged in
		echo "true";
	else:
		echo "false";
	endif;
		$myconn->closeconnection();

	wp_die(); 
}
add_action( 'wp_ajax_list_remote_files', 'list_remote_files' );
function list_remote_files(){
	error_reporting(0);
	$server 	= get_option("zanders_ftp_server");
	$port 		= get_option("zanders_ftp_port");
	$username 	= get_option("zanders_ftp_username");
	$password 	= get_option("zanders_ftp_password");

	$myconn = new shalako_FtpConnection($server, $username, $password, true, $port, 10 );
	$connstatus = $myconn->connect(); 

	if($connstatus): // we are connected to the server and logged in
		//get a list of files and return them 
		echo json_encode($myconn->listdir("/Inventory/"));
	else:
		echo "false";
	endif;
	$myconn->closeconnection();

	wp_die(); 
}
add_action( 'wp_ajax_update_filelist', 'update_filelist' );
function update_filelist(){
	$result = update_option("zanders_file_list", $_POST['filelist']);
	echo json_encode(get_option("zanders_file_list"));
	wp_die(); 
}
add_action( 'wp_ajax_download_files', 'download_files' );
function download_files(){
	$result = update_option("zanders_file_list", $_POST['filelist']);
	echo json_encode(get_option("zanders_file_list"));
	wp_die(); 
}





//add menu page
add_action('admin_menu', '_add_custom_menu_pages',99);
function _add_custom_menu_pages(){
	add_menu_page("Zanders FTP", "Zanders FTP", 'manage_options', "zanders", '_include_custom_main_page','');
}
function _include_custom_main_page(){
	// output the admin page
	?>
	<div class="wrap">
		<?php  echo "<h2>Zanders FTP </h2>"; ?>
		<?php
		$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general_settings';
		?>
		<h2 class="nav-tab-wrapper">
			<a href="?page=zanders&tab=general_settings" class="nav-tab <?php echo $active_tab == 'general_settings' ? 'nav-tab-active' : ''; ?>">General Settings</a>
			<a href="?page=zanders&tab=download_files" class="nav-tab <?php echo $active_tab == 'download_files' ? 'nav-tab-active' : ''; ?>">Download Files</a>
			<a href="?page=zanders&tab=import_products" class="nav-tab <?php echo $active_tab == 'import_products' ? 'nav-tab-active' : ''; ?>">Import Products</a>
			<a href="?page=zanders&tab=cron_settings" class="nav-tab <?php echo $active_tab == 'cron_settings' ? 'nav-tab-active' : ''; ?>">Cron Settings</a>
		</h2>
		<?php if($active_tab == 'general_settings'): ?>
			<?php 
			if (isset($_SESSION['message'])) {
		    echo $_SESSION['message']; //display the message
		    unset($_SESSION['message']); //free it up
		}
		?>
		<script>
			jQuery(document).ready(function($){
				$(".dismissable").click(function(){
					$(this).hide();
				});
				//disable the submit button until we have checked the username and password
				$('#submit').prop("disabled",true);
				$('#submit').hide();
			    $("#checkftp").click(function(){
			    	$('.overlay').fadeIn();
	                jQuery.ajax({
		                url: "<?php echo admin_url('admin-ajax.php'); ?>",
		                type: 'POST',
		                data: {
		                    action: 'check_ftp',
		                    server: $("#zanders_ftp_server").val(),
		                    port: $("#zanders_ftp_port").val(),
		                    username: $("#zanders_ftp_username").val(),
		                    password: $("#zanders_ftp_password").val()
		                },
		                dataType: 'html',
		                success: function(response) {
		                    //console.log(response);
		                    if(response == "true"){
            					$('#submit').prop("disabled",false);
								$('#submit').show();
            					//console.log("true")
		                    }else{
            					$('#submit').prop("disabled",true);
								$('#submit').hide();
								alert("There was an error connecting")
		                    }
		                    $(".overlay").fadeOut();
		                }
			    	});
	            });
            });
		</script>
		<style>
		.overlay{
				background-image: url(data:image/gif;base64,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);				
				background-position:center center;
			  background-repeat:no-repeat;
			}
		</style>
		<form name="general_settings_form" method="post" action="/wp-admin/admin-post.php">
			<style>
				input[type="text"], input[type="password"]{width:500px !important;}
			</style>
			<h2>FTP Connection Options</h2>
			<div class="mytable" style="position:relative;">
			<div class="overlay" style="background-color:rgba(1,1,1,.2);position:absolute;width:100%;height:100%;z-index:10;display:none;"></div>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">FTP Server</th>
					<td>
						<input type="text" name="zanders_ftp_server" id="zanders_ftp_server" value="<?php echo get_option("zanders_ftp_server"); ?>">
						<em></em>
					</td>
				</tr>       
		        <tr valign="top">
		        	<th scope="row">FTP Port</th>
		        	<td>
		        		<input type="text" name="zanders_ftp_port" id="zanders_ftp_port" value="<?php echo get_option("zanders_ftp_port"); ?>" placeholder="21">
		        		<em></em>
		        	</td>
		        </tr>				
		        <tr valign="top">
					<th scope="row">FTP Username</th>
					<td>
						<input type="text" name="zanders_ftp_username" id="zanders_ftp_username" value="<?php echo get_option("zanders_ftp_username"); ?>">
						<em></em>
					</td>
				</tr>        
				<tr valign="top">
					<th scope="row">FTP Password</th>
					<td>
						<input type="password" name="zanders_ftp_password" id="zanders_ftp_password" value="<?php echo get_option("zanders_ftp_password"); ?>">
						<em></em>
					</td>
				</tr>

			</table>
			</div>
			<input type="button" id="checkftp" value="Test Connection">
		 <?php wp_nonce_field( 'check_ftp', 'check_ftp' ); ?>
		<input type="hidden" name="action" value="update_custom_settings">
		<input type="hidden" name="command" value="update_general_settings">
		<input type="hidden" name="returl" value="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">


		<?php submit_button( 'Update Settings', 'primary', 'submit' ) ?>
	</form>

	<?php  elseif($active_tab == 'download_files'): ?>
		<?php 
		if (isset($_SESSION['message'])) {
		    echo $_SESSION['message']; //display the message
		    unset($_SESSION['message']); //free it up
		}
		?>
		<form name="download_files_form" method="post" action="/wp-admin/admin-post.php">
			<style>
				input[type="text"], input[type="password"]{width:500px !important;}
			</style>
			<h2>File Transfer Utility</h2>
			<div class="mytable" style="position:relative;">
			<div class="overlay" style="background-color:rgba(1,1,1,.2);position:absolute;width:100%;height:100%;z-index:10;display:none;"></div>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Local Directory</th>
					<td>
						<input type="text" name="zanders_local_directory" id="zanders_local_directory" value="<?php echo get_option("zanders_local_directory"); ?>" placeholder="/wp-content/uploads/zandersftp/">
						<em>leave blank to use the default</em>
					</td>
				</tr>
		        <tr valign="top">
		        	<th scope="row">Remote Directory</th>
		        	<td>
		        		<input type="text" name="zanders_remote_directory" id="zanders_remote_directory" value="<?php echo get_option("zanders_remote_directory"); ?>" placeholder="/Inventory/">
		        		<em>leave blank to use the default</em>
		        	</td>
		        </tr>

			</table>
			</div>
			<input type="button" id="checkftp" value="Connect and Choose Files">

			<?php //here i want to connect to the ftp and show a list of files as well as a local list of files ?>

			 <style>
			  #sortable1, #sortable2 {
			    border: 1px solid #eee;
			    width: 282px;
			    min-height: 20px;
			    list-style-type: none;
			    margin: 0;
			    padding: 5px 0 0 0;
			    float: left;
			    margin-right: 10px;
			  }
			  #sortable1 li, #sortable2 li {
			    margin: 0 5px 5px 5px;
			    padding: 5px;
			    font-size: 1.2em;
			    width: 260px;
			    cursor:move;
			  }
			  </style>
			  <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
			  <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
			  <script>
			  jQuery(function($) {
			  		$("#checkftp").click(function(){
	                jQuery.ajax({
		                url: "<?php echo admin_url('admin-ajax.php'); ?>",
		                type: 'POST',
		                data: {
		                    action: 'list_remote_files',
		                    server: '<?php echo get_option('zanders_ftp_server'); ?>',
		                    port: '<?php echo get_option('zanders_ftp_port'); ?>',
		                    username: '<?php echo get_option('zanders_ftp_username'); ?>',
		                    password: '<?php echo get_option('zanders_ftp_password'); ?>'
		                },
		                dataType: 'json',
		                success: function(response) {
		                    //console.log(response);
	                    	$("#sortable1").html("");
	                    	$.each(response, function(i, item){
	                    		if(item.indexOf(".") > 0 ){
	                    			if( $("#sortable2").children("li:contains(" + item + ")").length < 1 ){ // check for duplicates
	                    				$("#sortable1").append("<li class='ui-state-default'>" + item + "</li>");
	                    			}
	                    		}
	                    	});
		                }
			    	});
				});




			    $( "#sortable1, #sortable2" ).sortable({
			      connectWith: ".connectedSortable",
			    }).disableSelection();
			    //only updating the list of files that we want to keep
			    $( "#sortable2" ).sortable({
			      	update : function(){
			      		//save the array to the database here
		      			var filelist = new Array();
		      			$("#sortable2 li").each(function(){
		      				var file = $(this).text();
		      				filelist.push(file);
		      			});
		      			$.post("<?php echo admin_url('admin-ajax.php'); ?>",{'action': 'update_filelist', 'filelist': filelist},function(data){
	      					console.log(data);
		      			}, "json");
		      		}
			    });

			  });
			  </script>
			<div style="width:800px;margin-top:10px;">
				<div>
					<ul id="sortable1" class="connectedSortable" style="min-height:300px;background-color:#fff;">
					</ul>
				 </div>
				 <div>
					<ul id="sortable2" class="connectedSortable" style="min-height:300px;background-color:#fff;">
					<?php 
						$files = get_option("zanders_file_list"); 
						if($files):
						foreach($files as $file):
							echo "<li class='ui-state-default'>{$file}</li>";
						endforeach;
						endif;
					?>
					</ul>
				</div>
			</div>
			<div>
				<input type="button" id="btnDownload" value="Download Selected Files">
			</div>
			<div id="downloadstatus">
			</div>


		</script>
		 <?php wp_nonce_field( 'check_download_files', 'check_download_files' ); ?>
		<input type="hidden" name="action" value="update_download_files">
		<input type="hidden" name="command" value="update_download_files">
		<input type="hidden" name="returl" value="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">


		<?php// submit_button( 'Update Settings', 'primary', 'submit' ) ?>
	</form>








	<?php  elseif($active_tab == 'import_products'): ?>
	<?php  elseif($active_tab == 'cron_settings'): ?>






<?php endif; ?>
</div>
<?php
}