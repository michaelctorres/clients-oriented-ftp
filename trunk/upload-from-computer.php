<?php
/**
 * Uploading files from computer, step 1
 * Shows the plupload form that handles the uploads and moves
 * them to a temporary folder. When the queue is empty, the user
 * is redirected to step 2, and prompted to enter the name,
 * description and client for each uploaded file.
 *
 * @package ProjectSend
 * @subpackage Upload
 */
$plupload = 1;
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');
$page_title = __('Upload files', 'cftp_admin');
include('header.php');

$database->MySQLDB();
?>

<div id="main">
	<h2><?php echo $page_title; ?></h2>
	
	<?php
		/** Count the clients to show an error or the form */
		$sql = $database->query("SELECT * FROM tbl_users WHERE level = '0'");
		$count = mysql_num_rows($sql);
		if (!$count) {
			/** Echo the no clients default message */
			message_no_clients();
		}
		else { 
	?>
			<p>
				<?php
					_e('Click on Add files to select all the files that you want to upload, and then click continue. On the next step, you will be able to set a name and description for each uploaded file. Remember that the maximum allowed file size (in mb.) is ','cftp_admin');
					echo '<strong>'.MAX_FILESIZE.'</strong>.';
				?>
			</p>

			<?php
				/**
				 * Load a plupload translation file, if the ProjectSend language
				 * on sys.config.php is set to anything other than "en", and the
				 * corresponding plupload file exists.
				 */
				if(SITE_LANG != 'en') {
					$plupload_lang_file = 'includes/plupload/js/i18n/'.SITE_LANG.'.js';
					if(file_exists($plupload_lang_file)) {
						echo '<script type="text/javascript" src="'.$plupload_lang_file.'"></script>';
					}
				}
			?>

			<script type="text/javascript">
				$(document).ready(function() {
					setInterval(function(){
						// Send a keep alive action every 1 minute
						var timestamp = new Date().getTime()
						$.ajax({
							type:	'GET',
							cache:	false,
							url:	'includes/ajax-keep-alive.php',
							data:	'timestamp='+timestamp,
							success: function(result) {
								var dummy = result;
							}
						});
					},1000*60);
				});

				$(function() {
					$("#uploader").pluploadQueue({
						runtimes : 'gears,flash,silverlight,browserplus,html5,html4',
						url : 'process-upload.php',
						max_file_size : '<?php echo MAX_FILESIZE; ?>mb',
						chunk_size : '1mb',
						multipart : true,
						filters : [
							{title : "Allowed files", extensions : "<?php echo $options_values['allowed_file_types']; ?>"}
						],
						flash_swf_url : 'includes/plupload/js/plupload.flash.swf',
						silverlight_xap_url : 'includes/plupload/js/plupload.silverlight.xap'
						/*
						,init : {
							QueueChanged: function(up) {
								var uploader = $('#uploader').pluploadQueue();
								uploader.start();
							}
						}
						*/
					});
	
					$('form').submit(function(e) {
						var uploader = $('#uploader').pluploadQueue();
	
						if (uploader.files.length > 0) {
							uploader.bind('StateChanged', function() {
								if (uploader.files.length === (uploader.total.uploaded + uploader.total.failed)) {
									$('form')[0].submit();
								}
							});
								
							uploader.start();
	
							uploader.bind('FileUploaded', function (up, file, info) {
								var obj = JSON.parse(info.response);
								var new_file_field = '<input type="hidden" name="finished_files[]" value="'+obj.NewFileName+'" />'
								$('form').append(new_file_field);
							});
	
							return false;
						} else {
							alert('<?php _e("You must select at least one file to upload.",'cftp_admin'); ?>');
						}
				
						return false;
					});
				});
			</script>			

			<form action="upload-process-form.php" name="upload_by_client" id="upload_by_client" method="post" enctype="multipart/form-data">
				<input type="hidden" name="uploaded_files" id="uploaded_files" value="" />
				<div id="uploader">
					<p><?php _e("Your browser doesn't have Flash, Silverlight, Google Gears, BrowserPlus or HTML5 support. Please update your browser or install Adobe Flash to continue.",'cftp_admin'); ?></p>
				</div>
				<div align="right">
					<input type="submit" name="Submit" value="<?php _e('Continue','cftp_admin'); ?>" class="button button_blue button_submit" />
				</div>
			</form>
	
	<?php
		/** End if for users count */
		}
	?>

</div>

<?php
	$database->Close();
	include('footer.php');
?>