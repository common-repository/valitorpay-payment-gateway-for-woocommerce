<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Subscriptions.
 *
 * @extends WC_Gateway_Valitorpay
 */
class WC_Gateway_Valitorpay_Subscriptions extends WC_Gateway_Valitorpay {

	function __construct() {

		parent::__construct();

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );

	}

	/**
	 * Process the payment and return the result
	 *
	 * Get and update the order being processed
	 * Return success and redirect URL (in this case the thanks page)
	 *
	 * @access public
	 * @param  int $order_id
	 * @return array
	 */

	public function process_payment( $order_id ) {
		$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
		if ( !empty($subscriptions) ) {
			$this->recurring_payments = true;
		}
		return parent::process_payment( $order_id );
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$this->process_subscription_payment($renewal_order, $amount_to_charge);
	}

	/**
	 * Process_subscription_payment function.
	 *
	 * @since 1.0
	 *
	 * @param mixed $renewal_order
	 * @param float $amount
	 */
	public function process_subscription_payment( $renewal_order, $amount = 0.0 ) {
		$order_id = $renewal_order->get_id();
		$this->ensure_subscription_has_customer_id( $order_id );

		$virtual_card = $this->helper->get_virtual_card($renewal_order);

		if(!$virtual_card){
			$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
			if ( !empty($subscriptions) ) {
				foreach ( $subscriptions as $subscription_id => $subscription ) {
					$parent_order_id = $subscription->get_parent_id();
					$virtual_card = get_post_meta( $parent_order_id, '_valitorpay_virtualCard', true );
					if($virtual_card){
						update_post_meta( $subscription_id, '_valitorpay_virtualCard', $virtual_card );
						break;
					}
				}
			}
		}

		if($virtual_card){
			$allow_payment = true;

			$prevent_payment_attempt = get_post_meta( $order_id, '_valitorpay_prevent_payment_attempt', true);
			if($prevent_payment_attempt == 1){
				WC_Gateway_Valitorpay::log( __('Valitorpay response of the previous transaction does not allow to make a re-request', 'valitorpay-payment-gateway-for-woocommerce') );
				$renewal_order->add_order_note( __( 'Valitorpay response of the previous transaction does not allow to make a re-request', 'valitorpay-payment-gateway-for-woocommerce' ) );
				$allow_payment = false;
			}

			$payment_attempt = get_post_meta( $order_id, '_valitorpay_payment_attempt', true );
			$payment_attempt_date = (isset($payment_attempt['date']) && !empty($payment_attempt['date']) ) ? $payment_attempt['date'] : [];
			$attempts_number = (isset($payment_attempt['attempts_number']) && $payment_attempt['attempts_number']) ? (int)$payment_attempt['attempts_number'] : 0;
			$current_day = wp_date('Y-m-d');
			if( $allow_payment && !empty($payment_attempt_date) && in_array($current_day, $payment_attempt_date ) ){
				$log_data = ['payment_attempt'=>$payment_attempt, 'current_day'=>$current_day, 'renewal_order'=>$order_id];
				WC_Gateway_Valitorpay::log( sprintf( __('The maximum number of payment attempts to ValitorPay has been reached today: %s', 'valitorpay-payment-gateway-for-woocommerce'), wc_print_r($log_data, true) ) );
				$renewal_order->add_order_note(__( 'The maximum number of payment attempts to ValitorPay has been reached today', 'valitorpay-payment-gateway-for-woocommerce' ) );
				$allow_payment = false;
			}
			if( $allow_payment && $attempts_number >= 3 ){
				$log_data = ['payment_attempt'=>$payment_attempt, 'renewal_order'=>$order_id];
				WC_Gateway_Valitorpay::log( sprintf( __('The maximum number of payment attempts  to ValitorPay has been reached: %s', 'valitorpay-payment-gateway-for-woocommerce'), wc_print_r($log_data, true) ) );
				$renewal_order->add_order_note( __( 'The maximum number of payment attempts  to ValitorPay has been reached', 'valitorpay-payment-gateway-for-woocommerce' ) );
				$allow_payment = false;
			}

			if($allow_payment){
				$result = $this->api->virtual_card_payment($renewal_order, $virtual_card);
				WC_Gateway_Valitorpay::log( sprintf( __( 'Scheduled subscription payment response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($result, true) ) );

				if( isset($result->isSuccess) && $result->isSuccess) {

					// Removing the payment attempt metadata
					delete_post_meta($order_id, '_valitorpay_payment_attempt');

					$order_metas = array(
						'transactionID'=> ( isset($result->transactionID) ) ? sanitize_text_field($result->transactionID) : '',
						'responseDescription' => ( isset($result->responseDescription) ) ? sanitize_text_field($result->responseDescription) : '',
						'acquirerReferenceNumber'=> ( isset($result->acquirerReferenceNumber) ) ? sanitize_text_field($result->acquirerReferenceNumber) : ''
					);

					$renewal_order->add_order_note( sprintf(__( 'Transaction ID: %s', 'valitorpay-payment-gateway-for-woocommerce' ), $order_metas['transactionID'] ) );
					$renewal_order->add_order_note( sprintf(__( 'Scheduled payment valitorpay response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), $order_metas['responseDescription'] ) );

					$this->save_order_metas( $renewal_order, $order_metas );

					$renewal_order->update_status( 'processing' );
				}else{
					$message = __( 'Authorization failed', 'valitorpay-payment-gateway-for-woocommerce' );
					$response_code = ( isset($result->responseCode) && !empty($result->responseCode) ) ? sanitize_text_field($result->responseCode) : '';
					if( isset($result->responseDescription) && !empty($result->responseDescription) ){
						$message = sanitize_text_field($result->responseDescription);
					}elseif( isset($result->Message) && !empty($result->Message) ){
						$message = sanitize_text_field($result->Message);
					}

					if( !empty($response_code) ){
						$message = sprintf(__( 'Valitorpay response: %s(Code: %s)', 'valitorpay-payment-gateway-for-woocommerce' ), $message, $response_code);

						$code = substr($response_code, 0, 2);
						switch ($code) {
							case '05':
							case '51':
								//05 or 51 - only one retry per day and max 3 retries.
								++$attempts_number;
								if(empty($payment_attempt)){
									$payment_attempt = [];
									$payment_attempt['date']= [];
								}
								$payment_attempt['date'][] = wp_date('Y-m-d');
								$payment_attempt['attempts_number'] = $attempts_number;
								update_post_meta($order_id, '_valitorpay_payment_attempt', $payment_attempt);
								break;
							case '1A':
							case '65':
								//1A or 65 - retry should be done as a fully authenticated 3DSecure transaction.
								break;
							case 'AV':
							case 'AW':
							case '91':
							case '92':
							case 'AU':
							case 'V2':
							case '96':
							case 'C5':
								//The response codes AV, AW, 91, 92, AU, V2, 96, C5 are related to network issues and may be retried. Resending after several seconds/minutes prevents a high load on the network.
								break;
							default:
								//Transactions that receive any other error code should not be retried.
								update_post_meta($order_id, '_valitorpay_prevent_payment_attempt', 1);
								break;
						}
					}else{
						//Transactions that receive any other error code should not be retried.
						update_post_meta($order_id, '_valitorpay_prevent_payment_attempt', 1);
					}

					$renewal_order->add_order_note($message);
					$renewal_order->update_status( 'failed' );
				}
			}else{
				WC_Gateway_Valitorpay::log( __('Valitorpay payment attempt skipped.', 'valitorpay-payment-gateway-for-woocommerce') );
			}
		}else{
			$renewal_order->add_order_note(__( 'Virtual card not found', 'valitorpay-payment-gateway-for-woocommerce' ));
		}
	}

	/**
	 * Checks if subscription has a Valitorpay customer ID and adds it if doesn't.
	 *
	 * @param int $order_id subscription renewal order id.
	 */
	public function ensure_subscription_has_customer_id( $order_id ) {
		$subscriptions_ids = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
		if( !empty($subscriptions_ids) ){
			foreach( $subscriptions_ids as $subscription_id => $subscription ) {
				if ( ! metadata_exists( 'post', $subscription_id, '_valitorpay_customer_id' ) ) {
					$valitorpay_customer = new WC_Valitorpay_Customer( $subscription->get_user_id() );
					update_post_meta( $subscription_id, '_valitorpay_customer_id', $valitorpay_customer->get_id() );
					update_post_meta( $order_id, '_valitorpay_customer_id', $valitorpay_customer->get_id() );
				}
			}
		}
	}

	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_valitorpay_customer_id' => array(
					'value' => get_post_meta( $subscription->get_id(), '_valitorpay_customer_id', true ),
					'label' => __( 'Customer id', 'valitorpay-payment-gateway-for-woocommerce' )
				),
			),
		);

		return $payment_meta;
	}

	public function intent_payment($order, $data){
		$order_id = $order->get_id();
		$first_transaction = true;
		$first_payment = $this->api->payment_with_verification($order, $data, $first_transaction);
		WC_Gateway_Valitorpay::log(__( 'Subscription intent_payment'));
		do_action('valitorpay_api_payment_response', $order, $first_payment);

		if( isset($first_payment->isSuccess) && $first_payment->isSuccess ) {
			$result = $this->api->create_virtual_card($order, $data);
			$virtual_card = ( isset($result->virtualCard) ) ? sanitize_text_field($result->virtualCard) : null;
			if($virtual_card){
				WC_Gateway_Valitorpay::log(__( 'Virtual card created'));
				$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
				if ( !empty($subscriptions) ) {
					foreach ( $subscriptions as $subscription_id => $subscription ) {
						update_post_meta( $subscription_id, '_valitorpay_virtualCard', $virtual_card );
					}
				}
				$first_payment->virtualCardNumber = $virtual_card;
			}
		}
		return $first_payment;
	}
}