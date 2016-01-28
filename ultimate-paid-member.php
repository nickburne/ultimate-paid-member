<?php

/*
  Plugin Name: Ultimate Paid Membership
  Plugin URI: http://musofinder.com/
  Description: A feature rich addon plugin which adds paid membership feauture to Ultimate Member plugin
  Version: 3.1.7
  Author: Shramee
  Author URI: http://shramee.com/
  License: GPLv2 or later
  Text Domain: UPM
 */

add_action('um_account_tab__musof_pro', 'musof_stripe_checkout');
add_shortcode('musof_checkout_form', 'musof_stripe_checkout');

function musof_stripe_checkout() {
	global $ultimatemember;
	$user_id = get_current_user_id();
	$theme = get_stylesheet_directory_uri();
	//@TODO reload cache
	$role = $ultimatemember->user->profile['role'];
	$ajax_url = admin_url('/admin-ajax.php');
	echo '<div class="um-account-heading uimob340-hide uimob500-hide"><i class="um-faicon-star"></i>Pro Membership</div>';
	
	if ($role != 'pro-member') {
		$plans_data = array();
		
		require dirname(__FILE__) . '/stripe-php/init.php';
		\Stripe\Stripe::setApiKey( "sk_test_P7kUSLtvnEa1JLzaJyk7GGIX" );
		$plans = \Stripe\Plan::all( array("limit" => 3) );
		
		$plans = $plans->__toArray( true );
		
		foreach ( $plans['data'] as $plan ) {
			$plans_data[ $plan['id'] ] = $plan['amount'];
		}
		
		echo <<<FREE
		<div class="um-field um-field-user_pro" data-key="user_pro">

		<script src="https://checkout.stripe.com/checkout.js"></script>
		<p>Upgrade to pro membership:</p>
		<a class="button" id="pro-yearly">Pay Annually</a>
		<a class="button" id="pro-monthly">Pay Monthly</a>
		<style>
	#musof_payment_processing, #musof_payment_processing img {
		margin: auto;
		bottom: 0;
		top: 0;
		left: 0;
		right: 0;
		background-color: rgba(255,255,255,0.8);
		position: fixed;
	}
	</style>
	<div id="musof_payment_processing" style="display:none;">
	<img src="https://cdnjs.cloudflare.com/ajax/libs/file-uploader/3.7.0/processing.gif">
	</div>
	
	<script>
	(function($) {
		var name = 'Muso Net Ltd',
		currency = "usd",
		plan = 'pro-monthly',
		url = window.location.origin + window.location.pathname;
		var handler = StripeCheckout.configure({
			key: 'pk_test_PxOWZ6rBgc7dmckuYUx24bnO',
			image: '$theme/images/muso-square.png',
			locale: 'auto',
			token: function(token) {
				query = {};
				query.action = 'musof_pro';
				query.description = description;
				query.currency = currency;
				query.amount = amount;
				query.plan = plan;
				query.tokenData = token;
				query.token = token.id;
				jQuery.post( '$ajax_url', query, function ( response ) {
					console.log( response );
					if ( 'Payment successful' == response ) {
						console.log( 'Success!!!' );
						window.location = url + '?updated=payment-successful';
					} else {
						console.log( 'Error!' );
						response = JSON.parse( response );
						console.log( response );
					}
				} );
}
});

$('#pro-yearly').on('click', function(e) {
	description = 'yearly';
	amount = {$plans_data['pro-yearly']};
	plan = 'pro-yearly';
		    // Open Checkout with further options
	handler.open({
		name: name,
		description: description,
		currency: currency,
		amount: amount
	});
e.preventDefault();
});

$('#pro-monthly').on('click', function(e) {
    // Open Checkout with further options
	description = 'monthly';
	amount = {$plans_data['pro-monthly']};
	plan = 'pro-monthly';
	handler.open({
		name: name,
		description: description,
		currency: currency,
		amount: amount
	});
e.preventDefault();
});
		// Close Checkout on page navigation
$(window).on('popstate', function() {
	console.log( 'popstate' );
	$('#musof_payment_processing').show();
	handler.close();
});

})(jQuery);
</script>

</div>
FREE;
	} else {

		$payment_log = get_user_meta($user_id, 'musof_payment_log', true);
		echo <<<PRO
	<p id='member-status'>You are a Pro Memeber
	<a href='?cancel-membership=$user_id'>Cancel Membership</a>
	</p>
	
	<style>
	member-status
		#tbl-header {
	font-size: 15px !important;
	line-height: 22px !important;
	font-weight: 600;
}
		#pay-history-tbl th {
font-size: 12px !important;
line-height: 22px !important;
font-weight: 600;
padding-left: 20px;
padding-top: 20px;
}	
</style>

PRO;

		if (!empty($_GET['cancel-membership'])) {
			musof_membership_cancel();
		}

		echo '</table>';
	}
	/*
	$payment_log = get_user_meta($user_id, 'musof_payment_log', true);
	$payment_log = $payment_log ? $payment_log : array();

	foreach ($payment_log as $time => $value) {

		print_r($value);
	}
	 * 
	 */
}

add_action('um_account_content_hook_musof_pro', '__return_true');

add_action('um_account_page_default_tabs_hook', 'musof_pro_tab');

function musof_pro_tab($tabs) {
	$tabs[160]['musof_pro']['icon'] = 'um-faicon-star';
	$tabs[160]['musof_pro']['title'] = __('Pro Member', 'ultimatemember');
	$tabs[160]['musof_pro']['custom'] = 1;
	remove_action('um_account_tab__delete', 'um_account_tab__delete');

	return $tabs;
}

add_action('wp_ajax_musof_pro', 'musof_pro_callback');

function musof_pro_callback() {

	if (!empty($_POST['token'])) {

		require dirname(__FILE__) . '/stripe-php/init.php';

		// Get the Stripe.js token 
		$token = $_POST['token'];
		
		$user = wp_get_current_user();

		// See your keys here https://dashboard.stripe.com/account/apikeys
		\Stripe\Stripe::setApiKey("sk_test_P7kUSLtvnEa1JLzaJyk7GGIX");

		$customer = \Stripe\Customer::create(array(
		'description' => "#$user->ID Paying $_POST[description] for $user->user_login (  $user->user_email )",
		'source' => $token,
		'plan' => $_POST['plan']
		));
		
		$customer = $customer->__toArray( true );
		
		if ( ! empty( $customer['subscriptions']['total_count'] ) ) {
			update_user_meta( $user->ID, 'stripe_customer_id', $customer['id'] );
			musof_upgrade_user( $customer );
		} else {
			echo json_encode( $customer );
		}
	}

	die();
}

function musof_upgrade_user( $customer ) {
	global $ultimatemember;
	
	$user_id = get_current_user_id();
	$ultimatemember->user->set_role( 'pro-member' ); //Change user role to pro

	//Payment log
	$payment_log = get_user_meta($user_id, 'musof_payment_log', true);
 	if (empty( $payment_log )) {
		$payment_log = array();
	}
	$data = wp_parse_args( $_POST, array( 'customer' => $customer['id'] ) );
	$payment_log[ time() ] = $data; //Creating a log entry
	update_user_meta($user_id, 'musof_payment_log', $payment_log); //Updating log

	echo 'Payment successful';
}

add_filter('um_custom_success_message_handler', 'musof_payment_successful_message', 10, 2);

function musof_payment_successful_message($msg, $type) {
	if ('payment-successful' == $type) {
		return 'Payment recieved, You are now a Pro Member.';
	}
	if ('membership-cancelled' == $type) {
		return "Pro Membership cancelled, Your card won't be charged again.";
	}

	return $msg;
}

add_action('um_account_tab__delete', 'musof_account_tab__delete');

function musof_account_tab__delete() {
	global $ultimatemember;
	$user_id = get_current_user_id();
	$role = $ultimatemember->user->profile['role'];
	if ($role != 'pro-member') {
		um_account_tab__delete();
	} else {
		echo "
		<style>
			#del-acc-heading {	
				font-size: 18px;
				line-height: 18px;
				font-weight: 700;
				color: #555;
			}
		</style>
		
		<div id='del-acc-heading'>
			Delete Account
		</div>
		
		<p> Pro Members cannot delete their accounts </p>
		<p> To delete your account, become a free member
		by cancelling your Pro Pembership </p>
		";
	}
}

function musof_membership_cancel() {
	global $ultimatemember;
	$user_id = get_current_user_id();
	$id = get_user_meta( $user_id, 'stripe_customer_id', true ); //cus_7npOQiGwWp15Zl
	
	if ( $id ) {
		require dirname(__FILE__) . '/stripe-php/init.php';
		echo "<br>Getting customer data...";
		\Stripe\Stripe::setApiKey("sk_test_P7kUSLtvnEa1JLzaJyk7GGIX");
		$customer = \Stripe\Customer::retrieve( $id );
		echo '<br>Removing recurring charge...';
		$customer->delete();
	}

	echo '<br>Removing membership...';
	
	$ultimatemember->user->set_role( 'free-member' ); //Change user role to free
	$url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	$url = str_replace( 'cancel-membership', 'membership-cancelled', $url );

	echo "<br>All done, Redirecting...";
	header( "Location: $url&updated=membership-cancelled" );
}
