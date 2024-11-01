<?php
/**
 * Class WC_Gateway_Valitorpay file.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

/**
 * Gateway class
 */
class WC_Gateway_Valitorpay extends WC_Payment_Gateway_CC {

	/**
	 * Whether or not logging is enabled
	 *
	 * @var bool
	 */
	public static $log_enabled = false;

	/**
	 * Logger instance
	 *
	 * @var WC_Logger
	 */
	public static $log = false;

	/**
	 * Test mode
	 *
	 * @var bool
	 */
	private $testmode;

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * 3dSecure
	 *
	 * @var bool
	 */
	private $payment_with_verification;

	/**
	 * Payment with virtual card
	 *
	 * @var bool
	 */
	private $payment_with_virtual_card;

	/**
	 * Terminal id
	 *
	 * @var string
	 */
	private $terminal_id;

	/**
	 * Agreement number
	 *
	 * @var string
	 */
	private $agreement_number;

	/**
	 * Debug
	 *
	 * @var bool
	 */
	private $debug;

	/**
	 * Nonce check
	 *
	 * @var bool
	 */
	private $nonce_check;

	/**
	 * Recurring payments
	 *
	 * @var bool
	 */
	public $recurring_payments;

	/**
	 * Valitorpay Helper
	 *
	 * @var Valitorpay_Helper
	 */
	public $helper;

	/**
	 * api
	 *
	 * @var Valitorpay api
	 */
	public $api;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id = 'valitorpay';
		$this->icon = apply_filters( 'valitorpay_payment_gateway_for_wc', '' );
		$this->has_fields = true;
		$this->method_title = __( 'ValitorPay', 'valitorpay-payment-gateway-for-woocommerce' );
		$this->method_description = __( 'ValitorPay take direct values for payment', 'valitorpay-payment-gateway-for-woocommerce' );

		// What methods do support ValitorPay and plugin
		$this->supports = array(
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions'
		);

		// Define and load settings fields
		$this->init_form_fields();
		$this->set_default_options();
		$this->init_settings();

		// User settings variables
		$this->enabled 						= $this->get_option( 'enabled' );
		$this->title 						= $this->get_option( 'title' );
		$this->description 					= $this->get_option( 'description' );
		$this->testmode 					= 'yes' === $this->get_option( 'testmode', 'no' );
		$this->api_key 						= $this->get_option( 'api_key' );
		$this->payment_with_verification 	= 'yes' === $this->get_option( 'payment_with_verification', 'no' );
		$this->payment_with_virtual_card = false;
		$this->terminal_id 					= $this->get_option( 'terminal_id' );
		$this->agreement_number 			= $this->get_option( 'agreement_number' );
		$this->debug						= 'yes' === $this->get_option( 'debug', 'no' );
		$this->nonce_check			= 'yes' === $this->get_option( 'nonce_check', 'yes' );
		$this->recurring_payments = false;
		self::$log_enabled    = $this->debug;
		$this->helper = new Valitorpay_Helper($this);
		$this->api = new Valitorpay_Api($this);
		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'check_thankyou_response' ) );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @access public
	 * @return void
	 */
	public function set_default_options(){

		// Prevent usage payment_with_virtual_card by default(overwrite plugin setting before v1.1.14)
		if('yes' === $this->get_option( 'payment_with_virtual_card', 'no' ))
			$this->update_option( 'payment_with_virtual_card', 'no' );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @access public
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' 			=> __( 'Enable/Disable', 'valitorpay-payment-gateway-for-woocommerce' ),
				'type' 				=> 'checkbox',
				'label' 			=> __( 'Enable ValitorPay Payments', 'valitorpay-payment-gateway-for-woocommerce' ),
				'default' 		=> 'no'
			),
			'title' => array(
				'title' 			=> __( 'Title', 'valitorpay-payment-gateway-for-woocommerce' ),
				'type' 				=> 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'valitorpay-payment-gateway-for-woocommerce' ),
				'default'			=> __( 'ValitorPay', 'valitorpay-payment-gateway-for-woocommerce' ),
				'desc_tip'		=> true
			),
			'description' => array(
				'title'				=> __( 'Description', 'valitorpay-payment-gateway-for-woocommerce' ),
				'type'				=> 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'valitorpay-payment-gateway-for-woocommerce' ),
				'default'			=> ''
			),
			'testmode' => array(
				'title'       => __( 'Test mode', 'valitorpay-payment-gateway-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'valitorpay-payment-gateway-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode .', 'valitorpay-payment-gateway-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true
			),
			'api_key'	 => array(
				'title' 			=> __( 'API key', 'valitorpay-payment-gateway-for-woocommerce' ),
				'type' 				=> 'text',
				'description' => __( 'Please enter here your API key for ValitorPay.', 'valitorpay-payment-gateway-for-woocommerce' ),
				'default'			=> '',
				'desc_tip'		=> true
			),
			'advanced_settings' => array(
				'type' 				=> 'title',
				'title'			=>	sprintf('%s <a href=""><span></span></a>', __( 'Advanced features', 'valitorpay-payment-gateway-for-woocommerce' ) ),
				'class'				=> 'valitorpay-advanced-settings'
			),
			'payment_with_verification' => array(
				'title' 			=> __( '3dSecure', 'valitorpay-payment-gateway-for-woocommerce' ),
				'label'       => __( 'Enable 3dSecure for singular payments', 'valitorpay-payment-gateway-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'yes',
				'desc_tip'		=> true
			),
			'terminal_id' => array(
				'title' 			=> __( 'Terminal Id', 'valitorpay-payment-gateway-for-woocommerce' ),
				'type' 				=> 'text',
				'description' => __( 'The ValitorPay merchant terminal identifier. Must be a numeric value with no leading zeros.', 'valitorpay-payment-gateway-for-woocommerce' ),
				'desc_tip'		=> true
			),
			'agreement_number' => array(
				'title' 			=> __( 'Agreement number', 'valitorpay-payment-gateway-for-woocommerce' ),
				'type' 				=> 'text',
				'description' => __( 'The ValitorPay merchant agreement number. Must be a numeric value with no leading zeros', 'valitorpay-payment-gateway-for-woocommerce' ),
				'desc_tip'		=> true
			),
			'debug' => array(
				'title'       => __( 'Debug', 'valitorpay-payment-gateway-for-woocommerce' ),
				'label'       => __( 'Enable Debug Mode', 'valitorpay-payment-gateway-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'nonce_check' => array(
				'title'       => __( 'Use nonce check', 'valitorpay-payment-gateway-for-woocommerce' ),
				'label'       => __( 'Use nonce check while 3dSecure processing', 'valitorpay-payment-gateway-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'yes',
				'desc_tip'    => true
			),
		);
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'. Possible values:
	 *                      emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'valitorpay' ) );
		}
	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 *
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();

		// Maybe clear logs.
		if(!$this->debug){
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->clear( 'valitorpay' );
		}

		return $saved;
	}

	public function payment_fields(){
		if($this->testmode){
			_e( 'In test mode, you can use cardNumbers: 5526830589243348(MasterCard), 4921810880068101(Visa), 379999835077294(Amex)', 'valitorpay-payment-gateway-for-woocommerce' );
		}

		$this->form();

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

		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		} else {
			$order = new WC_Order( $order_id );
		}
		if ( 0 >= $order->get_total() && !$this->recurring_payments) {
			return $this->complete_free_order( $order );
		}
		WC_Gateway_Valitorpay::log( sprintf( __( 'process_payment - start: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r(['order_id'=>$order_id], true) ) );

		$card_data = [];
		$card_data[$this->id . '-card-number'] = sanitize_text_field( $_POST[$this->id . '-card-number'] );
		$card_data[$this->id . '-card-expiry'] = sanitize_text_field( $_POST[$this->id . '-card-expiry'] );
		$card_data[$this->id . '-card-cvc'] = sanitize_text_field( $_POST[$this->id . '-card-cvc'] );
		if($this->payment_with_verification || $this->recurring_payments){
			$result = $this->api->card_verification($order, $card_data);

			/**
			* Correction of compatibility with the old version for the order-pay page.
			* Make sure the parameter is removed for new payment attempt
			*/
			delete_post_meta($order_id, '_valitorpay_verification_error');

			if ( is_wp_error( $result ) ){
				wc_add_notice( $result->get_error_message(), 'error') ;
				return array(
					'result' => 'failure',
					'messages' => $result->get_error_message()
				);
			}
			if(isset($result->errors)){
				wc_add_notice( $result->title, 'error');
				return array(
					'result' => 'failure',
					'messages' => $result->title
				);
			}

			if( (isset($result->cardVerificationRawResponse) && $result->cardVerificationRawResponse ) ||
				(isset($result->verificationHtml) && $result->verificationHtml ) ){
				$verification_html = isset($result->cardVerificationRawResponse) ?  $result->cardVerificationRawResponse : $result->verificationHtml;
				WC()->session->set( 'wc_valitorpay_verification_' . $order_id, null );
				WC()->session->set( 'wc_valitorpay_verification_' . $order_id, $verification_html);
				WC()->session->save_data();

				$checkout_url = wc_get_checkout_url();
				$checkout_url = substr($checkout_url, 0, strrpos($checkout_url, "/"));

				return array(
					'result'   => 'success',
					'redirect' => add_query_arg(
						array(
							'order'       => $order_id,
							'nonce'       => wp_create_nonce( 'wc_valitorpay_confirm_' . $order_id )
						),
						$checkout_url . WC_AJAX::get_endpoint( 'wc_valitorpay_verify_intent' )
					)
				);
			}else{
				$message = ( isset($result->Message) && !empty($result->Message) )? sanitize_text_field($result->Message) : __('Card verification failed', 'valitorpay-payment-gateway-for-woocommerce') ;
				wc_add_notice( $message, 'error');
				return array(
					'result' => 'failure',
					'messages' => $message
				);
			}
		}else{
			$result = $this->api->payment($order, $card_data);
			do_action('valitorpay_api_payment_response', $order, $result);
			if ( is_wp_error( $result ) ){
				wc_add_notice( $result->get_error_message(), 'error') ;
				return array(
					'result'   => 'failure',
					'messages' => $result->get_error_message()
				);
			}elseif(isset($result->errors)) {
				wc_add_notice( $result->title, 'error') ;
				return array(
					'result'   => 'failure',
					'messages' => $result->title
				);
			}elseif( isset($result->isSuccess) && $result->isSuccess ) {
				$order_metas = array(
					'maskedCardNumber'=> $this->helper->get_masked_card($card_data[$this->id . '-card-number']),
					'transactionID'=> ( isset($result->transactionID) ) ? sanitize_text_field($result->transactionID) : '',
					'acquirerReferenceNumber'=> ( isset($result->acquirerReferenceNumber) ) ? sanitize_text_field($result->acquirerReferenceNumber) : '',
					'responseDescription'=> ( isset($result->responseDescription) ) ? sanitize_text_field($result->responseDescription) : ''
				);

				$order->add_order_note( sprintf(__( 'Transaction ID: %s', 'valitorpay-payment-gateway-for-woocommerce' ), $order_metas['transactionID'] ) );
				$order->add_order_note( sprintf( __('Valitorpay response: %s', 'valitorpay-payment-gateway-for-woocommerce'), $order_metas['responseDescription'] ) );
				WC_Gateway_Valitorpay::log( sprintf( __( 'Payment: %s', 'valitorpay-payment-gateway-for-woocommerce' ), $order_metas['responseDescription'] ) );
				unset($order_metas['responseDescription']);
				$this->save_order_metas( $order, $order_metas );
			}else{
				$message = __( 'Authorization failed', 'valitorpay-payment-gateway-for-woocommerce' );
				if( isset($result->responseDescription) && !empty($result->responseDescription) ){
					$message = sanitize_text_field($result->responseDescription);
				}elseif( isset($result->Message) && !empty($result->Message) ){
					$message = sanitize_text_field($result->Message);
				}
				wc_add_notice($message, 'error');
				return array(
					'result'   => 'failure',
					'messages' => $message
				);
			}

			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
			);

		}

		return;
	}

 	protected function do_order_complete_tasks($order, $order_note){
		// return if order status id already completed.
		if ($order->get_status() == 'completed')
		   return;

		if(!empty($order_note)){
			$notes = implode("\n", $order_note);
			$order->add_order_note($notes);
		}
		$order->payment_complete();
		WC()->cart->empty_cart();
	}

	/**
	 * Completes an order without a positive value.
	 */
	public function complete_free_order($order){
		return array(
			'result'              => 'success',
			'redirect'            => $this->get_return_url( $order ),
		);
	}

	/**
	 * Process a refund if supported.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Refund amount.
	 * @param  string $reason Refund reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = NULL, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $this->can_refund_order( $order ) ) {
			return new WP_Error( 'error', __( 'Refund failed.', 'valitorpay-payment-gateway-for-woocommerce' ) );
		}
		$data = array( 'refund_amount'=>$amount, 'refund_reason'=>$reason );
		$result = $this->api->refund( $order, $data );
		if( !empty($result) ){
			if( isset($result->errors) ){
				foreach ($result->errors as $key => $error) {
					$order->add_order_note( sanitize_text_field($error) );
				}
				return new WP_Error( 'error', $result->title );
			}

			if( isset($result->isSuccess) && $result->isSuccess ){
				$order_metas = array(
								'transactionID'=> ( isset($result->transactionID) ) ? sanitize_text_field($result->transactionID) : '',
								'acquirerReferenceNumber'=> ( isset($result->acquirerReferenceNumber) ) ? sanitize_text_field($result->acquirerReferenceNumber) : '',
								'responseDescription'=> ( isset($result->responseDescription) ) ? sanitize_text_field($result->responseDescription) : ''
							);
				$order->add_order_note( sprintf( __( 'Transaction ID: %s', 'valitorpay-payment-gateway-for-woocommerce' ), $order_metas['transactionID']) );
				$order->add_order_note( sprintf( __( 'Refund response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), $order_metas['responseDescription']) );
				WC_Gateway_Valitorpay::log( sprintf( __( 'Refund: %s', 'valitorpay-payment-gateway-for-woocommerce' ), $order_metas['responseDescription'] ) );
				unset($order_metas['responseDescription']);
				$this->save_order_metas( $order, $order_metas );
				return true;
			}else{
				if( isset($result->responseDescription) && !empty($result->responseDescription) ){
					$order->add_order_note(  __( 'Refund failed: ', 'valitorpay-payment-gateway-for-woocommerce' ) . sanitize_text_field($result->responseDescription) );
				}
			}
		}

		return;
	}

	/**
	 * Executed between the "Checkout" and "Thank you" pages.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order The order which is in a transitional state.
	 */
	public function verify_intent_after_checkout( $order ) {
		$order_id  = $order->get_id();

		WC_Gateway_Valitorpay::log( __('Successful redirect to intent checkout', 'valitorpay-payment-gateway-for-woocommerce') );

		if($this->render_verification_form($order_id)){
			exit;
		}

		$response = $this->read_verification($order);
		if( isset($response['resend_verification']) && $response['resend_verification']){
				update_post_meta($order_id, '_valitorpay_verification_error', 1);
				$payment_args = (isset($response['card'])) ? $response['card'] : [];
				if(empty($payment_args)){
					return new WP_Error( 'error', __( 'Empty checkout args', 'valitorpay-payment-gateway-for-woocommerce' ) );
				}
				$args = $payment_args;
				if( $order->get_currency() == 'ISK') {
					$args['exponent'] = '2';
				}
				else {
					$args['exponent'] = '0';
				}
				$args['token'] = $token;

				$result = $this->api->card_verification($order, $args, $print = 1 );
				if( (isset($result->cardVerificationRawResponse) && $result->cardVerificationRawResponse ) ||
						(isset($result->verificationHtml) && $result->verificationHtml ) ){
						$verification_html = isset($result->cardVerificationRawResponse) ?  $result->cardVerificationRawResponse : $result->verificationHtml;
						WC()->session->set( 'wc_valitorpay_verification_' . $order_id, null );
						WC()->session->set( 'wc_valitorpay_verification_' . $order_id, $verification_html);
						WC()->session->save_data();
						wp_redirect( $_SERVER['REQUEST_URI'] );
						exit;
				}
		}elseif( isset($response['card_verification']) && isset($response['card_verification']['mdStatus']) ){
			WC_Gateway_Valitorpay::log( __('Successful authenticationSuccessUrl callback', 'valitorpay-payment-gateway-for-woocommerce') );
			delete_post_meta($order_id, '_valitorpay_verification_error');
			$payment_args = (isset($response['card'])) ? $response['card'] : [];
			if(empty($payment_args)){
				return new WP_Error( 'error', __( 'Empty checkout args', 'valitorpay-payment-gateway-for-woocommerce' ) );
			}

			$args = array_merge($payment_args, array('verification'=>$response['card_verification']));
			$result = $this->intent_payment($order, $args);
			do_action('valitorpay_api_payment_response', $order, $result);
			if( empty($result) ){
				$order->update_status( 'failed' );
				return new WP_Error( 'error', __( 'Empty response', 'valitorpay-payment-gateway-for-woocommerce' ) );
			}
			if( isset($result->errors) ){
				$order->update_status( 'failed' );
				return new WP_Error( 'error', $result->title );
			}

			if( isset($result->isSuccess) && $result->isSuccess  ) {
				$order_note = [];
				$order_metas = array(
					'transactionID'=> ( isset($result->transactionID) ) ? sanitize_text_field($result->transactionID) : '',
					'acquirerReferenceNumber'=> ( isset($result->acquirerReferenceNumber) ) ? sanitize_text_field($result->acquirerReferenceNumber) : '',
					'responseDescription'=>( isset($result->responseDescription) ) ? sanitize_text_field($result->responseDescription) : ''
				);

				$masked_card = $this->helper->get_masked_card($payment_args['valitorpay-card-number']);
				if($this->testmode){
					if( !empty($order_metas['virtualCard']) ){
						$order_note[] = sprintf( __( 'Virtual card: %s(test mode info only)', 'valitorpay-payment-gateway-for-woocommerce' ), $order_metas['virtualCard']);
					}else{
						$order_note[] = sprintf( __( 'Masked card: %s(test mode info only)', 'valitorpay-payment-gateway-for-woocommerce' ), $masked_card );
					}
				}
				if( isset($result->virtualCardNumber) ){
					$order_metas['virtualCard'] = sanitize_text_field($result->virtualCardNumber);
				}else{
					$order_metas['maskedCardNumber'] = $masked_card;
				}
				$order_note[] = __( 'Transaction ID: ', 'valitorpay-payment-gateway-for-woocommerce' ) . $order_metas['transactionID'];
				$order_note[] = __( 'Valitorpay response: ', 'valitorpay-payment-gateway-for-woocommerce' ) . $order_metas['responseDescription'];

				unset($order_metas['responseDescription']);
				$this->save_order_metas( $order, $order_metas );
				$this->do_order_complete_tasks($order, $order_note);
			}else{
				$order->update_status( 'failed' );
				$message = '';
				$order_note = [];
				if( isset($result->responseCode) && $result->responseCode &&  isset($result->responseDescription) && $result->responseDescription){
					$order_note[] = sprintf( __( 'Payment error. %s; Error code: %s', 'valitorpay-payment-gateway-for-woocommerce' ),
						sanitize_text_field($result->responseDescription),
						sanitize_text_field($result->responseCode)
					);
					$message = $result->responseDescription;
				}elseif(isset($result->Message) && $result->Message){
					$order_note[] = sprintf( __( 'Payment error. %s', 'valitorpay-payment-gateway-for-woocommerce' ),
						sanitize_text_field($result->Message)
					);
					$message = $result->Message;
				}

				if(!empty($order_note)){
					$notes = implode("\n", $order_note);
					$order->add_order_note($notes);
				}

				if(!empty($message)){
					return new WP_Error('error', $message);
				}else{
					return new WP_Error( 'error',  __( 'Payment error', 'valitorpay-payment-gateway-for-woocommerce' ) );
				}
			}
		}else{
			$error = (isset($response['error'])) ? sanitize_text_field($response['error']) : __( 'Card verification failed', 'valitorpay-payment-gateway-for-woocommerce' );
			delete_post_meta($order_id, '_valitorpay_verification_error');
			return new WP_Error( 'error', $error);
		}
	}

	public function intent_payment($order, $data){
		return $this->api->payment_with_verification($order, $data);
	}

	/**
	 * Save masked card number in order(Required for Valitorpay Refund)
	 * @since 1.0.0
	 * @param WC_Order $order The order which is in a transitional state.
	 * @param array $meta Response meta data
	 */
	public function save_order_metas($order, $metas ){
		if( !empty($metas) ){
			foreach ($metas as $key => $meta) {
				if( !empty($meta) ) $order->update_meta_data( '_' . $this->id . '_' . $key, $meta );
			}
			$order->save();

		}
	}

	public function check_thankyou_response( $order_id ){
		global $woocommerce;

		if(isset( $order_id ) ){
			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
			} else {
				$order = new WC_Order( $order_id );
			}

			if(!empty($order) && !$order->is_paid() ){
				$transaction_ID = get_post_meta( $order_id, '_' . $this->id . '_transactionID', true );
				if(empty($transaction_ID)){
					$transaction_ID = $order->get_meta('_' . $this->id . '_transactionID');
				}
				if( !empty($transaction_ID) ){
					$order->payment_complete();
					$woocommerce->cart->empty_cart();
				}
			}
		}
	}

	/**
	 * Save masked card number in order(Required for Valitorpay Refund)
	 * @param $order_id Current WC_Order order id
	 * 
	 * @return bool
	 */
	private function render_verification_form($order_id ){
		$verification_form = false;
		$verification = WC()->session->get( 'wc_valitorpay_verification_' . $order_id );
		if(!empty($verification)){
			WC_Gateway_Valitorpay::log(__( '3D secure verification: redirected user to external verification server', 'valitorpay-payment-gateway-for-woocommerce'));
			WC()->session->set( 'wc_valitorpay_verification_' . $order_id, null );
			echo $verification . PHP_EOL;
			$verification_form = true;
		}
		return $verification_form;
	}

	/**
	 * Read verification response

	 * @param $order WC_Order
	 * 
	 * @return array
	 */
	private function read_verification($order){
		$response = [];
		$order_id  = $order->get_id();
		$meta_verification_error = get_post_meta( $order_id, '_valitorpay_verification_error', true );

		$log_data = $this->helper->secureLogData($_REQUEST);
		WC_Gateway_Valitorpay::log( sprintf( __( 'External verification - response($_REQUEST): %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($log_data, true) ) );
		$mdStatus = isset($_REQUEST['mdStatus']) ? sanitize_text_field($_REQUEST['mdStatus']) : null;
		if($mdStatus && in_array($mdStatus, [1,2,4,5,6])){
			$md = isset($_REQUEST['MD']) ? sanitize_text_field($_REQUEST['MD']) : '';
			$response['card'] = $this->helper->decode_checkout_args($md, $order->get_order_key());
			if( in_array($mdStatus, [5,6]) && empty($meta_verification_error) ){
				//resend if mdStatus = 6 and this is first try
				$response['resend_verification'] = true;
			}else{
				$card_verification = [];
				$card_verification['mdStatus'] = $mdStatus;
				$card_verification['xid'] = ( isset($_POST['xid']) ) ? sanitize_text_field($_POST['xid']) : null;
				if( isset($_POST['cavv']) && $cavv = sanitize_text_field($_POST['cavv']) ){
					$card_verification['cavv'] = $cavv;
				}elseif( isset($_POST['ucaf']) && $ucaf = sanitize_text_field($_POST['ucaf']) ){
					$card_verification['cavv'] = $ucaf;
				}
				if( isset($_POST['TDS2_dsTransID']) && $dsTransID = sanitize_text_field($_POST['TDS2_dsTransID']) ){
					$card_verification['dsTransId'] = $dsTransID;
				}
				if( isset($_REQUEST['mdErrorMsg']) && !empty($_REQUEST['mdErrorMsg']) ){
					$card_verification['mdErrorMsg'] = sanitize_text_field($_REQUEST['mdErrorMsg']);
				}

				$response['card_verification'] = $card_verification;
			}
		}else{
			$response['error'] = (isset($_REQUEST['mdErrorMsg'])) ? sprintf( __( 'Card verification failed(%s)', 'valitorpay-payment-gateway-for-woocommerce' ), sanitize_text_field($_REQUEST['mdErrorMsg']) ) : '';
		}

		return $response;
	}
}
