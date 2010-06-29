<?php
$allowed_levels = array(9);
require_once('includes/includes.php');

if ($_GET['do']=='edit' || isset($_POST['edit_who'])) {
	$page_title = $page_title_edituser;
}
else {
	$page_title = $page_title_newuser;
}

include('header.php');

$database->MySQLDB();

if ($_GET['do']=='edit') {
	//if we are editing a client, then the info to show on the form comes from the database
	$edit_id = $_GET['user'];
	$editing = $database->query("SELECT * FROM tbl_users WHERE id=$edit_id");
	$count=mysql_num_rows($editing);
	if (!$count) {
		$process_state = 'edit_not_exists';
	}
	else {
		while($data = mysql_fetch_array($editing)) {
			$add_user_data_name = $data['name'];
			$add_user_data_user = $data['user'];
			$add_user_data_pass = '';
			$add_user_data_pass2 = '';
			$add_user_data_email = $data['email'];
			$add_user_data_level = $data['level'];
		}
	}
}
else {
	$add_user_data_name = mysql_real_escape_string($_POST['add_user_form_name']);
	$add_user_data_pass = md5(mysql_real_escape_string($_POST['add_user_form_pass']));
	$add_user_data_pass2 = mysql_real_escape_string(md5($_POST['add_user_form_pass2']));
	$add_user_data_email = mysql_real_escape_string($_POST['add_user_form_email']);
	$add_user_data_level = mysql_real_escape_string($_POST['add_user_form_level']);
	// fix for showing the correct user when editing but php validation failed
	// SHOULD WE AVOID ALL THIS BY NOT SHOWING THE USERNAME FIELD WHEN EDITING?
	if (isset($_POST['edit_who'])) {
		$edit_who = $_POST['edit_who'];
		$editing_user = $database->query("SELECT * FROM tbl_users WHERE id=$edit_who");
		while($userrow = mysql_fetch_array($editing_user)) {
			$add_user_data_user = $userrow['user'];
		}
	}
	else {
		$add_user_data_user = mysql_real_escape_string($_POST['add_user_form_user']);
	}
}

require_once('includes/form_validation_class.php');

if ($_POST) {
	
	// set this when editing
	$edit_who = $_POST['edit_who'];

	// begin form validation
	$valid_me->validate('completed',$add_user_data_name,$validation_no_name);
	$valid_me->validate('completed',$_POST['add_user_form_pass'],$validation_no_pass);
	$valid_me->validate('completed',$add_user_data_email,$validation_no_email);
	$valid_me->validate('completed',$add_user_data_level,$validation_no_level); // just a precaution
	$valid_me->validate('email',$add_user_data_email,$validation_invalid_mail);
	$valid_me->validate('alpha',$_POST['add_user_form_pass'],$validation_alpha_pass);
	$valid_me->validate('length',$_POST['add_user_form_pass'],$validation_length_pass,MIN_PASS_CHARS,MAX_PASS_CHARS);
	$valid_me->validate('pass_match','',$validation_match_pass,'','',$_POST['add_user_form_pass'],$_POST['add_user_form_pass2']);

	if (!isset($edit_who)) {
		// only check this values when adding a new uset, not when editing
		$valid_me->validate('user_exists',$add_user_data_user,$add_user_exists,'','','','','tbl_users','user');
		$valid_me->validate('user_exists',$add_user_data_email,$add_user_mail_exists,'','','','','tbl_users','email');
		// user field is only checked when adding a new client because it returns an empty value when it is disabled
		$valid_me->validate('completed',$add_user_data_user,$validation_no_user);
		$valid_me->validate('alpha',$add_user_data_user,$validation_alpha_user);
		$valid_me->validate('length',$add_user_data_user,$validation_length_user,MIN_USER_CHARS,MAX_USER_CHARS);
	}

	if ($valid_me->return_val) { //lets continue

		if (isset($edit_who)) {
			//we are editing a user
			$editing = $database->query("SELECT * FROM tbl_users WHERE id=$edit_who");
			$count=mysql_num_rows($editing);
			if (!$count) {
				// there is no user with the posted id
				$process_state = 'edit_not_exists';
			}
			else {
				// posted data is valid and the user does exist for editing, so do it
				$success = mysql_query("UPDATE tbl_users SET 
										password = '$add_user_data_pass',
										name = '$add_user_data_name',
										email = '$add_user_data_email',
										level = '$add_user_data_level'
										WHERE id = $edit_who");
				if ($success){
					$process_state = 'edit_ok';
				}
				else {
					$process_state = 'edit_err';
				}
			}
		}
		else {
			//we are adding a new user to the system

			// add new user to DB
			$timestampdate = time();
			$success = mysql_query("INSERT INTO tbl_users (id,user,password,name,email,level,timestamp)"
			."VALUES ('NULL', '$add_user_data_user', '$add_user_data_pass', '$add_user_data_name', '$add_user_data_email','$add_user_data_level', '$timestampdate')");
			
			if ($success){
				$process_state = 'ok';
	
				// prepare email using the template
				$email_body = file_get_contents('emails/newuser.php');
	
				$email_body = str_replace('%BODY1%',$add_user_mail_body,$email_body);
				$email_body = str_replace('%BODY2%',$add_user_mail_body_2,$email_body);
				$email_body = str_replace('%BODY3%',$add_user_mail_body_3,$email_body);
				$email_body = str_replace('%LBLUSER%',$add_mail_body_user,$email_body);
				$email_body = str_replace('%LBLPASS%',$add_mail_body_pass,$email_body);
				$email_body = str_replace('%URI%',$baseuri,$email_body);
				$email_body = str_replace('%SUBJECT%',$add_user_mail_subject,$email_body);
				$email_body = str_replace('%USERNAME%',$add_user_data_user,$email_body);
				$email_body = str_replace('%PASSWORD%',$_POST['add_user_form_pass'],$email_body);
	
				// send account data by email
				$confirmmail = @mail($add_user_data_email, $add_user_mail_subject, $email_body, "From:<$admin_email_address>\r\nReply-to:<$admin_email_address>\r\nContent-type: text/html; charset=us-ascii");
				if ($confirmmail){
					$email_state = 'ok';
				}
				else{
					$email_state = 'err';
				}
			}
			else {
				$process_state = 'err';
			}
		} // edit or add end
	} //validation ends here
	

} // no form info sent ?>

<div id="main">
	<h2><?php echo $page_title; ?></h2>
	
		<div class="whiteform whitebox">
		
		<?php $valid_me->list_errors(); // if the form was submited with errors, show them here ?>
		
		<?php
			if (isset($process_state)) {
				// get the process state and show the corresponding ok or error message
				switch ($process_state) {
					case 'ok':
						echo '<div class="message message_ok"><p>'.$add_user_ok.'</p></div>';
					break;
					case 'err':
						echo '<div class="message message_error"><p>'.$add_user_error.'</p></div>';
					break;
					case 'edit_not_exists':
						echo '<div class="message message_error"><p>'.$edit_user_exists.'</p></div>';
					break;
					case 'edit_ok':
						echo '<div class="message message_ok"><p>'.$edit_user_ok.'</p></div>';
					break;
					case 'edit_err':
						echo '<div class="message message_error"><p>'.$edit_user_error.'</p></div>';
					break;
				}
				// ok or error message for the email notification
				switch ($email_state) {
					case 'ok':
						echo '<div class="message message_ok"><p>'.$add_client_notify_ok.'</p></div>';
					break;
					case 'err':
						echo '<div class="message message_error"><p>'.$add_client_notify_error.'</p></div>';
					break;
				}
			}
			else {
			// if not $process_state is set, it means we are just entering for the first time
		?>

	<?php include_once('includes/js/js.validations.php'); ?>

	<script type="text/javascript">
	
		window.onload = default_field;

		var js_err_name = "<?php echo $validation_no_name; ?>"
		var js_err_user = "<?php echo $validation_no_user; ?>"
		var js_err_pass = "<?php echo $validation_no_pass; ?>"
		var js_err_pass2 = "<?php echo $validation_no_pass2; ?>"
		var js_err_email = "<?php echo $validation_no_email; ?>"
		var js_err_level = "<?php echo $validation_no_level; ?>"
		var js_err_invalid_mail = "<?php echo $validation_invalid_mail; ?>"
		var js_err_pass_mismatch = "<?php echo $validation_match_pass; ?>"
		var js_err_user_length = "<?php echo $validation_length_user; ?>"
		var js_err_pass_length = "<?php echo $validation_length_pass; ?>"
		var je_err_pass_chars = "<?php echo $validation_alpha_pass; ?>"
		var js_err_user_chars = "<?php echo $validation_alpha_user; ?>"

		function validateform(theform){
			is_complete(theform.add_user_form_name,js_err_name);
			is_complete(theform.add_user_form_user,js_err_user);
			is_complete(theform.add_user_form_pass,js_err_pass);
			is_complete(theform.add_user_form_pass2,js_err_pass2);
			is_complete(theform.add_user_form_email,js_err_email);
			is_complete(theform.add_user_form_level,js_err_level);
			is_length(theform.add_user_form_user,<?php echo MIN_USER_CHARS; ?>,<?php echo MAX_USER_CHARS; ?>,js_err_user_length);
			is_length(theform.add_user_form_pass,<?php echo MIN_PASS_CHARS; ?>,<?php echo MAX_PASS_CHARS; ?>,js_err_pass_length);
			is_email(theform.add_user_form_email,js_err_invalid_mail);
			is_alpha(theform.add_user_form_user,js_err_user_chars);
			is_alpha(theform.add_user_form_pass,je_err_pass_chars);
			is_match(theform.add_user_form_pass,theform.add_user_form_pass2,js_err_pass_mismatch);
			// show the errors or continue if everything is ok
			if (error_list != '') {
				alert(error_title+error_list)
				error_list = '';
				return false;
			}
		}
	
	</script>

		<form action="userform.php" name="adduser" method="post" onsubmit="return validateform(this);">
			<?php if ($_GET['do']=='edit' || isset($_POST['edit_who'])) { ?>
				<input type="hidden" name="edit_who" id="edit_who" value="<?php echo ($_GET['do']=='edit') ? $_GET['user'] : $_POST['edit_who']; ?>" />
			<?php } ?>
			<table border="0" cellspacing="1" cellpadding="1">
			  <tr>
				<td width="40%"><?php echo $add_user_form_name; ?></td>
				<td><input name="add_user_form_name" id="add_user_form_name" class="txtfield" value="<?php echo $add_user_data_name; ?>" /></td>
			  </tr>
			  <tr>
				<td><?php echo $add_user_form_user; ?></td>
				<td><input name="add_user_form_user" id="add_user_form_user" class="txtfield" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo $add_user_data_user; ?>" <?php if ($_GET['do']=='edit' || isset($_POST['edit_who'])) { ?>disabled="disabled"<?php }?> /></td>
			  </tr>
			  <tr>
				<td><?php echo $add_user_form_pass; ?></td>
				<td><input name="add_user_form_pass" id="add_user_form_pass" class="txtfield" type="password" maxlength="<?php echo MAX_PASS_CHARS; ?>" /></td>
			  </tr>
			  <tr>
				<td><?php echo $add_user_form_pass2; ?></td>
				<td><input name="add_user_form_pass2" id="add_user_form_pass2" class="txtfield" type="password" maxlength="<?php echo MAX_PASS_CHARS; ?>" /></td>
			  </tr>
			  <tr>
				<td><?php echo $add_user_form_email; ?></td>
				<td><input name="add_user_form_email" id="add_user_form_email" class="txtfield" value="<?php echo $add_user_data_email; ?>" /></td>
			  </tr>
			  <tr>
				<td><?php echo $add_user_form_level; ?></td>
				<td>
					<select name="add_user_form_level" id="add_user_form_level" class="txtfield">
						<option value="9" <?php if( $add_user_data_level == '9') { echo 'selected="selected"'; } ?>><?php echo $user_role_lvl9; ?></option>
						<option value="8" <?php if( $add_user_data_level == '8') { echo 'selected="selected"'; } ?>><?php echo $user_role_lvl8; ?></option>
						<option value="7" <?php if( $add_user_data_level == '7') { echo 'selected="selected"'; } ?>><?php echo $user_role_lvl7; ?></option>
					</select>
				</td>
			  </tr>
			  <tr>
				<td colspan="2">
					<div align="right">
						<input type="submit" name="Submit" value="<?php if ($_GET['do']=='edit' || isset($_POST['edit_who'])) { echo $edit_user_form_submit; } else { echo $add_user_form_submit; } ?>" class="boton" />
					</div>
					<?php if ($_GET['do']!='edit' && empty($_POST['edit_who'])) { ?>
					<div class="message message_info">
						<p><?php echo $add_client_mail_info; ?></p>
					</div>
					<?php } ?>
				</td>
				</tr>
		  </table>
	
		</form>

		<?php } ?>
		
	</div>

</div>

<?php
	$database->Close();
	include('footer.php');
?>