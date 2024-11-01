<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Valitorpay_Intent_Controller class.
 *
 * Handles in-checkout AJAX calls, related to Payment Intents.
 */
class WC_Valitorpay_Intent_Controller {
	/**
	 * Holds an instance of the gateway class.
	 *
	 * @since 1.0.0
	 * @var WC_Gateway_Valitorpay
	 */
	protected $gateway;

	/**
	 * Class constructor, adds the necessary hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wc_ajax_wc_valitorpay_verify_intent', array( $this, 'verify_intent' ) );
	}

	/**
	 * Returns an instantiated gateway.
	 *
	 * @since 1.0.0
	 * @return WC_Gateway_Valitorpay
	 */
	protected function get_gateway( $order ) {
		if ( ! isset( $this->gateway ) ) {
			if ( $this->is_subscription_intent($order) ){
				$class_name = 'WC_Gateway_Valitorpay_Subscriptions';
			}else{
				$class_name = 'WC_Gateway_Valitorpay';
			}

			$this->gateway = new $class_name();
		}
		return $this->gateway;
	}

	protected function is_subscription_intent($order){
		$subscription = false;
		if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$order_id = $order->get_id();
			$subscriptions_ids = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
			if( !empty($subscriptions_ids) ){
				foreach( $subscriptions_ids as $subscription_id => $subscription_obj ){
					$parent_id = $subscription_obj->get_parent_id();
					$subscription_order = $subscription_obj->get_parent();
					if($parent_id == $order_id || $subscription_order->get_id() == $order_id){
						$subscription = true;
						break;
					}
				}
			}
		}

		return $subscription;
	}
	
	/**
	 * Loads the order from the current request.
	 *
	 * @since 1.0.0
	 * @throws WP_Error An exception if there is no order ID or the order does not exist.
	 * @return WC_Order
	 */
	protected function get_order_from_request() {

		// Load the order ID.
		$order_id = null;
		if ( isset( $_GET['order'] ) && absint( $_GET['order'] ) ) {
			$order_id = absint( $_GET['order'] );
		}



		// Retrieve the order.
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			throw new WC_Data_Exception( 'missing-order', __( 'Missing order ID for payment confirmation', 'valitorpay-payment-gateway-for-woocommerce' ), 400, array( 'order_id' => $order_id ));
		}
		if( $this->is_paid_order($order) ){
			throw new WC_Data_Exception( 'paid-order', __( 'Order is paid', 'valitorpay-payment-gateway-for-woocommerce' ), 400, array( 'order_id' => $order_id ));
		}

		// Checking nonce before 3d secure
		$gateway = $this->get_gateway( $order );
		$nonce_check = (!empty($gateway) && isset($gateway->nonce_check) && !$gateway->nonce_check)? false : true;

		//Ignoring nonce verification after second card_verification
		$verification_error = get_post_meta( $order_id, '_valitorpay_verification_error', true );
		if(!empty($verification_error)) $nonce_check = false;

		//Ignoring nonce verification after POST: AuthenticationSuccessUrl callback
		if ( isset( $_GET['response'] ) )  $nonce_check = false;

		if($nonce_check){
			if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'wc_valitorpay_confirm_' . $order_id ) ) {
				throw new WC_Data_Exception( 'missing-nonce', __( 'CSRF verification failed.', 'valitorpay-payment-gateway-for-woocommerce' ), 400, array( 'order_id' => $order_id ));
			}
		}

		return $order;
	}

	/**
	 * Handles successful PaymentIntent authentications.
	 *
	 * @since 1.0.0
	 */
	public function verify_intent() {
		global $woocommerce;
		try {
			$order = $this->get_order_from_request();
		} catch ( WC_Data_Exception $e ) {
			$redirect_url = add_query_arg( array(
				'valitorpay-verification-failed' => true,
				'message' => $e->getMessage()
			), wc_get_checkout_url() );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$gateway = $this->get_gateway( $order );
		$verify_intent = $gateway->verify_intent_after_checkout( $order );
		if( is_wp_error( $verify_intent ) ){
			$message = '';
			foreach ( $verify_intent->get_error_messages() as $error_message ) {
				if(!empty($message)) $message .= "\r\n";
				$message .= $error_message;
			}
			$order->add_order_note($message);
			$gateway::log( sprintf( __( 'Card verification failed: %s', 'valitorpay-payment-gateway-for-woocommerce' ), $message ) );
			$redirect_url = add_query_arg( array(
				'valitorpay-verification-failed' => true,
				'message' => $message
			), $order->get_checkout_payment_url( false ) );
			wp_safe_redirect( $redirect_url );
		}elseif( ! isset( $_GET['is_ajax'] ) ) {
			$redirect_url = isset( $_GET['redirect_to'] ) // wpcs: csrf ok.
				? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) // wpcs: csrf ok.
				: $gateway->get_return_url( $order );
			wp_safe_redirect( $redirect_url );
		}
		exit;
	}

	/**
	 * Handles exceptions during intent verification.
	 *
	 * @since 1.0.0
	 * @param WP_Error $e           The exception that was thrown.
	 */
	protected function handle_error( $e) {
		// Log the exception before redirecting.
		$message = '';
		if( method_exists($e,'getMessage') ){
			$message =  $e->getMessage();
		}elseif( method_exists($e,'get_error_message') ){
			$message =  $e->get_error_message();
		}
	}

	/**
	 * Check if order is paid
	 *
	 * @since 1.1
	 * @param WC_Order $order
	 * @return bool
	 */
	protected function is_paid_order($order) {
		global $wpdb;
		$order_id = $order->get_id();
		$order_status = $wpdb->get_var( $wpdb->prepare( "SELECT post_status from $wpdb->posts WHERE ID =  %d", $order_id ) );
		return ($order->is_paid() || in_array( $order_status, wc_get_is_paid_statuses() ) ) ? true : false ;
	}
}

new WC_Valitorpay_Intent_Controller();
