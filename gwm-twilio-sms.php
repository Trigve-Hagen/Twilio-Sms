<?php
/*
Plugin Name:  Twilio Text SMS
Plugin URI:   http://www.globalwebmethods.com
Description:  Allows Wordpress Admin to send SMS Reminder messages to users who sign up for text reminders. New Users are signed up at register but can opt out by erasing and saving the phone number field blank.
Version:      1.0
Author:       Trigve Rex Hagen
Author URI:   http://www.globalwebmethods.com
*/

add_action( 'woocommerce_before_my_account', 'gwm_add_form_to_myaccount_page' );
function gwm_add_form_to_myaccount_page() {
	if(isset($_POST["form1"])) {
		if(isset($_POST['cell_number'])) {
			update_usermeta( absint( get_current_user_id() ), 'cell_number', wp_kses_post( $_POST['cell_number'] ) );
		}
	}
	$phone = get_user_meta(get_current_user_id(), 'cell_number', true);
	//$carrier = get_user_meta(get_current_user_id(), 'cell_carrier', true);

	echo '<p><b>Need Reminders to place orders before cut off time? Input Cell Phone Information for order reminders via SMS. The default order reminders are Monday at 6pm and Friday at 6pm. To opt out at any time simply erase your number, save changes and the Reminders will stop.</b></p>';
	if(isset($phone) && $phone != '') echo '<p>Cell Phone: '.$phone.'</p>';
	if( ! empty( esc_attr( get_option('gwm_twilio_myaccount_url') ) ) ) {
		$url_string = esc_attr( get_option('gwm_twilio_myaccount_url') );
	} else $url_string = "my-account";
	?><form action="<?php get_home_url(); ?>/<?php echo $url_string; ?>/" method="post">
	<p class="form-row form-row-wide">
		<label for="cell_number"><?php _e( 'Cell Number(Numbers Only)', 'woocommerce' ); ?></label>
		<input type="text" class="input-text" name="cell_number" id="cell_number" value="<?php if(isset($phone) && $phone != '') echo $phone; ?>" placeholder="0000000000" />
	</p>
	<p>
		<input type="submit" class="button" name="form1" value="<?php _e( 'Save Details', 'woocommerce' ); ?>" />
	</p>
	</form><?php
	$current_user = wp_get_current_user();
}

add_action( 'register_form', 'gwm_register_form' );
function gwm_register_form() {
	$phone = ( ! empty( $_POST['phone'] ) ) ? trim( $_POST['phone'] ) : '';
	?><p class="form-row form-row-wide">
		<label for="cell_number"><?php _e( 'Cell Phone Number', 'woocommerce' ); ?></label>
		<input type="text" class="input-text" name="cell_number" id="cell_number" value="<?php echo esc_attr( wp_unslash( $phone ) ); ?>" placeholder="(000)000-0000" />
	</p><?php
}

add_filter( 'registration_errors', 'gwm_registration_errors', 10, 3 );
function gwm_registration_errors( $errors, $sanitized_user_login, $user_email ) {
	if ( (empty( $_POST['phone'] ) || ! empty( $_POST['phone'] ) && trim( $_POST['phone'] ) == '') ) {
		$errors->add( 'first_name_error', __( '<strong>ERROR</strong>: You must include a first name.', 'mydomain' ) );
	}
	return $errors;
}

add_action( 'user_register', 'gwm_user_register' );
function gwm_user_register( $user_id ) {
	if ( ! empty( $_POST['phone'] ) ) {
		update_user_meta( $user_id, 'phone', trim( $_POST['phone'] ) );
	}
}

add_action('admin_menu', 'gwm_twilio_setup_menu');
function gwm_twilio_setup_menu() {
	add_menu_page( 'Twilio Text SMS', 'Twilio Text SMS', 'manage_options', 'gwm-twilio-sms', 'gwm_initiate_twilio_plugin', 'dashicons-admin-comments' ); // last a number for placement?
	add_submenu_page('gwm-twilio-sms', __('Settings'), __('Settings'), 'manage_options', 'gwm-twilio-sms-settings', 'gwm_initiate_twilio_settings');
	add_action( 'admin_init', 'gwm_twilio_sms_settings_function' );
}

function gwm_twilio_sms_settings_function() {
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_field_message1' );
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_field_message2' );
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_field_message3' );
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_field_message4' );
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_field_message5' );
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_field_message6' );
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_field_numbers' );
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_phone' );
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_field_key' );
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_field_secret' );
	register_setting( 'gwm-twilio-sms-settings-group', 'gwm_twilio_myaccount_url' );
	register_setting( 'gwm-twilio-sms-group', 'gwm_twilio_field_message' );
}

function gwm_initiate_twilio_settings() {
	//global $post, $woocommerce, $wpdb;
	if(isset($_POST["form2"])) {
		if(isset($_POST['gwm_twilio_field_message']) && isset($_POST['gwm_twilio_field_numbers'])) {
			update_usermeta( absint( get_current_user_id() ), 'gwm_twilio_field_message1', wp_kses_post( $_POST['gwm_twilio_field_message1'] ) );
			update_usermeta( absint( get_current_user_id() ), 'gwm_twilio_field_message2', wp_kses_post( $_POST['gwm_twilio_field_message2'] ) );
			update_usermeta( absint( get_current_user_id() ), 'gwm_twilio_field_message3', wp_kses_post( $_POST['gwm_twilio_field_message3'] ) );
			update_usermeta( absint( get_current_user_id() ), 'gwm_twilio_field_message4', wp_kses_post( $_POST['gwm_twilio_field_message4'] ) );
			update_usermeta( absint( get_current_user_id() ), 'gwm_twilio_field_message5', wp_kses_post( $_POST['gwm_twilio_field_message5'] ) );
			update_usermeta( absint( get_current_user_id() ), 'gwm_twilio_field_message6', wp_kses_post( $_POST['gwm_twilio_field_message6'] ) );
			update_usermeta( absint( get_current_user_id() ), 'gwm_twilio_phone', wp_kses_post( $_POST['gwm_twilio_phone'] ) );
			update_usermeta( absint( get_current_user_id() ), 'gwm_twilio_field_key', wp_kses_post( $_POST['gwm_twilio_field_key'] ) );
			update_usermeta( absint( get_current_user_id() ), 'gwm_twilio_field_secret', wp_kses_post( $_POST['gwm_twilio_field_secret'] ) );
			update_usermeta( absint( get_current_user_id() ), 'gwm_twilio_myaccount_url', wp_kses_post( $_POST['gwm_twilio_myaccount_url'] ) );
		} else echo '<p>Please fill in the meassage to send.</p>';
	}
	//test_handle_post();
	echo '<div class="wrap">';
		echo '<form method="post" action="options.php">'; ?>
			<?php settings_fields( 'gwm-twilio-sms-settings-group' ); ?>
			<?php do_settings_sections( 'gwm-twilio-sms-settings-group' ); ?>
			<?php
			echo '<div class="wrap">';
				?><a href="<?php get_home_url(); ?>/wp-admin/admin.php?page=gwm-twilio-sms-settings">Settings</a> | <a href="<?php get_home_url(); ?>/wp-admin/admin.php?page=gwm-twilio-sms">Send Texts</a><?php
				echo '<p>Fill in the fields and hit save.</p>';
				echo '<p>Account Phone <input type="text" name="gwm_twilio_phone" value="' . esc_attr( get_option('gwm_twilio_phone') ). '" style="width:100%;" />';
				echo '</p>';
				echo '<p>Account SID <input type="text" name="gwm_twilio_field_key" value="' . esc_attr( get_option('gwm_twilio_field_key') ). '" style="width:100%;" />';
				echo '</p>';
				echo '<p>Auth Token <input type="text" name="gwm_twilio_field_secret" value="' . esc_attr( get_option('gwm_twilio_field_secret') ) . '" style="width:100%;" />';
				echo '</p>';
				echo '<p>My Account Url Segment - If you are using SEO keywords in the url segment my-account else leave blank.<input type="text" name="gwm_twilio_myaccount_url" value="' . esc_attr( get_option('gwm_twilio_myaccount_url') ) . '" style="width:100%;" />';
				echo '</p>';
				echo '<p>Message 1<br /><textarea style="width:100%;" name="gwm_twilio_field_message1">'. esc_attr( get_option('gwm_twilio_field_message1') ) .'</textarea>';
				echo '</p>';
				echo '<p>Message 2<br /><textarea style="width:100%;" name="gwm_twilio_field_message2">'. esc_attr( get_option('gwm_twilio_field_message2') ) .'</textarea>';
				echo '</p>';
				echo '<p>Message 3<br /><textarea style="width:100%;" name="gwm_twilio_field_message3">'. esc_attr( get_option('gwm_twilio_field_message3') ) .'</textarea>';
				echo '</p>';
				echo '<p>Message 4<br /><textarea style="width:100%;" name="gwm_twilio_field_message4">'. esc_attr( get_option('gwm_twilio_field_message4') ) .'</textarea>';
				echo '</p>';
				echo '<p>Message 5<br /><textarea style="width:100%;" name="gwm_twilio_field_message5">'. esc_attr( get_option('gwm_twilio_field_message5') ) .'</textarea>';
				echo '</p>';
				echo '<p>Message 6<br /><textarea style="width:100%;" name="gwm_twilio_field_message6">'. esc_attr( get_option('gwm_twilio_field_message6') ) .'</textarea>';
				echo '</p>';
				echo '<p><input type="submit" class="button" name="form2" value="Save Settings" /></p>';
			echo '</div>';
		echo '</form>';
	echo '</div>';
}
 
function gwm_initiate_twilio_plugin() {
	if(isset($_POST['gwm_twilio_field_message'])) {
		if($_POST['gwm_twilio_field_message'] == 1) $message = esc_attr( get_option('gwm_twilio_field_message1') );
		else if($_POST['gwm_twilio_field_message'] == 2) $message = esc_attr( get_option('gwm_twilio_field_message2') );
		else if($_POST['gwm_twilio_field_message'] == 3) $message = esc_attr( get_option('gwm_twilio_field_message3') );
		else if($_POST['gwm_twilio_field_message'] == 4) $message = esc_attr( get_option('gwm_twilio_field_message4') );
		else if($_POST['gwm_twilio_field_message'] == 5) $message = esc_attr( get_option('gwm_twilio_field_message5') );
		else if($_POST['gwm_twilio_field_message'] == 6) $message = esc_attr( get_option('gwm_twilio_field_message6') );
		$list = $_POST['gwm_twilio_field_numbers'];
		$numbers = explode(",",$list);
		$name = $_POST['gwm_twilio_field_names'];
		$names = explode(",",$name);
 
		echo '<div class="clearfix" style="width:100%;margin-top:30px;">';
		
		for($i=0; $i<count($numbers); $i++) {
			$strFromNumber = esc_attr( get_option('gwm_twilio_phone') );
			$strToNumber = $numbers[$i];
			$strMsg = $message;
			$aryResponse = array();
			
			require_once (__dir__ . "/twilio.php");
			
			$AccountSid = esc_attr( get_option('gwm_twilio_field_key') );
			$AuthToken = esc_attr( get_option('gwm_twilio_field_secret') );
			$objConnection = new Services_Twilio($AccountSid, $AuthToken);
			$bSuccess = $objConnection->account->sms_messages->create(
				$strFromNumber, 	// number we are sending From 
				$strToNumber,           // number we are sending To
				$strMsg			// the sms body
			);
			$aryResponse["SentMsg"] = $strMsg;
			echo 'To: '.$names[$i].' - Message: '.$aryResponse["SentMsg"]."<br />";
		}
		echo '</div>';
	}
	echo '<div class="wrap">';?>
		<form method="post" action="<?php get_home_url(); ?>/wp-admin/admin.php?page=gwm-twilio-sms">
			<?php settings_fields( 'gwm-twilio-sms-group' ); ?>
			<?php do_settings_sections( 'gwm-twilio-sms-group' ); ?>
			<?php
			echo '<div class="wrap">';
				?><a href="<?php get_home_url(); ?>/wp-admin/admin.php?page=gwm-twilio-sms-settings">Settings</a> | <a href="<?php get_home_url(); ?>/wp-admin/admin.php?page=gwm-twilio-sms">Send Texts</a>
				<p>Send a message to all who are signed up. Type your message in the box and hit send. Everyone on the mailer who put their number in correctly will recieve one.</p>
				<p><input type="radio" name="gwm_twilio_field_message" value="1" checked/> Message 1 - <?php echo esc_attr( get_option('gwm_twilio_field_message1') ); ?></p>
				<p><input type="radio" name="gwm_twilio_field_message" value="2"/> Message 2 - <?php echo esc_attr( get_option('gwm_twilio_field_message2') ); ?></p>
				<p><input type="radio" name="gwm_twilio_field_message" value="3"/> Message 3 - <?php echo esc_attr( get_option('gwm_twilio_field_message3') ); ?></p>
				<p><input type="radio" name="gwm_twilio_field_message" value="4"/> Message 4 - <?php echo esc_attr( get_option('gwm_twilio_field_message4') ); ?></p>
				<p><input type="radio" name="gwm_twilio_field_message" value="5"/> Message 5 - <?php echo esc_attr( get_option('gwm_twilio_field_message5') ); ?></p>
				<p><input type="radio" name="gwm_twilio_field_message" value="6"/> Message 6 - <?php echo esc_attr( get_option('gwm_twilio_field_message6') ); ?></p><?php
				$blogusers = get_users( 'blog_id=1' ); $phones_array = array(); $names_array = array();
				foreach ( $blogusers as $user ) {
					$phone = get_user_meta($user->ID, 'cell_number', true);
					$fname = get_user_meta($user->ID, 'first_name', true);
					$lname = get_user_meta($user->ID, 'last_name', true);
					$name = $fname . " " . $lname;
					if((isset($phone) && $phone != '')) {
						$phone = preg_replace("/[^0-9]/","",$phone);
						if(strlen($phone) == 10) $phone = '1'.$phone;
						if(strlen($phone) == 11) {
							array_push($phones_array, $phone);
							array_push($names_array, $name);
						}
					}
				}
				$args_count = 0; $string = ''; $numbers = array(); $names = array();
				foreach($phones_array as $arg) {
					$numbers[$args_count] = '+'.$arg;
					$names[$args_count] = $names_array[$args_count];
					$args_count++;
				}
				echo '<p>You have '.count($phones_array).' Users</p>';
				$number_string = implode(",",$numbers);
				$names_string = implode(",",$names);
				//echo $number_string;
				echo '<input type="hidden" value="'.$number_string.'" name="gwm_twilio_field_numbers"/>';
				echo '<input type="hidden" value="'.$names_string.'" name="gwm_twilio_field_names"/>';
				echo '<p><input type="submit" class="button" name="form1" value="Send Mail" /></p>';
			echo '</div>';
		echo '</form>';
	echo '</div>';
}

?>
