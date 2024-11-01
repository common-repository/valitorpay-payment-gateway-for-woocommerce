<?php

class Valitorpay_Api{

	/**
	 * WC_Gateway_Valitorpay
	 *
	 * @var WC_Gateway_Valitorpay
	 */
	private $gateway;

	/**
	 * Gateway settings
	 *
	 * @var array
	 */
	private $gateway_settings;

	/**
	 * Valitorpay Helper
	 *
	 * @var Valitorpay_Helper
	 */
	private $helper;

	public function __construct( $gateway ){
		$this->gateway = $gateway;
		$this->gateway_settings = $gateway->settings;
		$this->helper = $gateway->helper;

	}

	/**
	 * Return gateway id
	 *
	 * @return string
	*/
	public function gateway_id(){
		return $this->gateway->id;
	}

	/**
	 * Return gateway option
	 *
	 * @param  string $option Gateway option key
	 *
	 * @return string
	*/
	public function gateway_option($option){
		$gateway_settings = $this->gateway_settings;
		return (isset($gateway_settings[$option])) ? $gateway_settings[$option] : null ;
	}

	/**
	 * Do request 
	 *
	 * @param  string $endpoint Request endpoint
	 * @param  array $request_args Request args
	 *
	 * @return array
	*/
	private function request($endpoint, $request_args ){
		$raw_response = wp_safe_remote_post(
			$endpoint,
			array(
				'method'  => 'POST',
				'headers' => array('Authorization'=> 'APIKey ' . $this->helper->getApiKey(), 'Content-Type' =>'application/json', 'valitorpay-api-version'=> '2.0'),
				'body'    => json_encode($request_args),
				'timeout' => 70,
			)
		);

		if ( empty( $raw_response['body'] ) ) {
			return new WP_Error( 'valitorpay-api', __( 'Empty Response', 'valitorpay-payment-gateway-for-woocommerce') );
			WC_Gateway_Valitorpay::log( __( 'Empty Response', 'valitorpay-payment-gateway-for-woocommerce') );
		} elseif ( is_wp_error( $raw_response ) ) {
			WC_Gateway_Valitorpay::log( 'Request wp_error: ' . $raw_response->get_error_message() );
			return $raw_response;
		}

		$response = json_decode( $raw_response['body'] );

		return (object) $response;
	}

	/**
	 * Card verification request
	 *
	 * @param  WC_Order  $order Order object.
	 * @param  array $data Api args
	 *
	 * @return array
	*/
	public function card_verification( $order, $data){
		$order_id = $order->get_id();
		$currency = $order->get_currency();

		$endpoint = $this->helper->getEndpoint('card_verification');
		WC_Gateway_Valitorpay::log( 'Endpoint: ' . wc_print_r( $endpoint, true ) );

		$default_exponent = '2';
		if($currency == 'ISK') {
			$default_exponent = '0';
		}

		$card_args = $this->helper->get_card_args($data);
		$encoded = $this->helper->encode_checkout_args($data, $order->get_order_key());

		$args = array(
			'amount' => $this->helper->get_amount($order),
			'currency' => $order->get_currency(),
			'agreementNumber' => $this->gateway_option('agreementNumber'),
			'terminalId' => $this->gateway_option('terminalId'),
			'cardNumber' => $card_args['cardNumber'],
			'expirationMonth' => $card_args['expirationMonth'],
			'expirationYear' => $card_args['expirationYear'],
			'cardholderDeviceType' => "WWW",
			'authenticationUrl'=> $this->helper->get_checkout_intent_url($order_id),
			'exponent' => (isset($data['exponent'])) ? $data['exponent'] : $default_exponent,
/*			'virtualCard' => null,*/
			'MD' => $encoded['token'],
			'systemCalling'=>$this->helper->getMerchantSoftware()
		);

		$log_args = $this->helper->secureLogData($args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'card_verification - args: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($log_args, true) ) );

		$response = $this->request($endpoint, $args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'card_verification - response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($response, true) ) );

		return $response;
	}

	/**
	 * Payment request
	 *
	 * @param  WC_Order  $order Order object.
	 * @param  array $data Api args
	 *
	 * @return array
	*/
	public function payment($order, $data){
		$order_id = $order->get_id();
		$currency = $order->get_currency();
		$card_args = $this->helper->get_card_args($data);

		$endpoint = $this->helper->getEndpoint('payment', 'card');
		WC_Gateway_Valitorpay::log( 'Endpoint: ' . wc_print_r( $endpoint, true ) );

		$args = array(
			'operation' => 'Sale',
			'transactionType' => 'ECommerce',
			"cardNumber"=> $card_args['cardNumber'],
			"expirationMonth"=> $card_args['expirationMonth'],
			"expirationYear"=> $card_args['expirationYear'],
			"cvc"=>$card_args['cvc'],
			"additionalData"=> array(
				"merchantReferenceData"=> 'WC' . $order->get_order_number()
			),
			"currency"=> $currency,
			"amount"=> $this->helper->get_amount($order),
			'terminalId' => $this->gateway_option('terminalId'),
			'agreementNumber' => $this->gateway_option('agreementNumber'),
			'systemCalling'=> $this->helper->getMerchantSoftware()
		);

		$log_args = $this->helper->secureLogData($args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'card_payment - args: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($log_args, true) ) );

		$response = $this->request($endpoint, $args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'card_payment - response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($response, true) ) );

		return $response;
	}

	/**
	 * Payment with verification request
	 *
	 * @param  WC_Order  $order Order object.
	 * @param  array $data Api args
	 *
	 * @return array
	*/
	public function payment_with_verification($order, $data, $first_transaction=false){
		$order_id = $order->get_id();
		$currency = $order->get_currency();
		$card_args = $this->helper->get_card_args($data);

		$endpoint = $this->helper->getEndpoint('payment', 'card_with_verification');
		WC_Gateway_Valitorpay::log( 'Endpoint: ' . wc_print_r( $endpoint, true ) );

		$verification = [];
		$verification_data = $data['verification'];
		$verification['cavv'] = $verification_data['cavv'];
		$verification['mdStatus'] = $verification_data['mdStatus'];
		if(isset($verification_data['xid'])) $verification['xid'] = $verification_data['xid'];
		if(isset($verification_data['dsTransId'])) $verification['dsTransId'] = $verification_data['dsTransId']; 
		
		$args = array(
			'operation'=> 'Sale',
			'transactionType'=> 'ECommerce',
			'terminalId'=> $this->gateway_option('terminalId'),
			'agreementNumber'=> $this->gateway_option('agreementNumber'),
			"amount"=> $this->helper->get_amount($order),
			"currency"=> $currency,
			"cardNumber"=> $card_args['cardNumber'],
			"expirationMonth"=> $card_args['expirationMonth'],
			"expirationYear"=> $card_args['expirationYear'],
			"cvc"=>$card_args['cvc'],
			"additionalData"=> array(
				"merchantReferenceData"=> 'WC' . $order->get_order_number()
			),
			'cardVerificationData' => $verification,
			'systemCalling'=> $this->helper->getMerchantSoftware()
		);

		if($first_transaction){
			$args['firstTransactionData'] = ['initiationReason'=>'Recurring'];
		}

		$log_args = $this->helper->secureLogData($args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'payment_with_verification - args: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($log_args, true) ) );

		$response = $this->request($endpoint, $args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'payment_with_verification - response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($response, true) ) );

		return $response;
	}


	/**
	 * Virtual card payment request
	 *
	 * @param  WC_Order  $order Order object.
	 * @param  array $virtual_card Virtual card
	 *
	 * @return array
	*/
	public function virtual_card_payment($order, $virtual_card){
		$order_id = $order->get_id();

		$endpoint = $this->helper->getEndpoint('payment', 'virtual_card');
		WC_Gateway_Valitorpay::log( 'Endpoint: ' . wc_print_r( $endpoint, true ) );

		$args = array(
			'operation' => 'Sale',
			"currency"=> $order->get_currency(),
			"amount"=> $this->helper->get_amount($order),
			'terminalId'=> $this->gateway_option('terminalId'),
			'agreementNumber'=> $this->gateway_option('agreementNumber'),
			'virtualCardNumber' => $virtual_card,
			'virtualCardPaymentAdditionalData'=>array(
				"merchantReferenceData"=> 'WC' . $order->get_order_number()
			),
			'systemCalling'=> $this->helper->getMerchantSoftware()
		);
		$log_args = $this->helper->secureLogData($args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'virtual card payment - args: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($log_args, true) ) );

		$response = $this->request($endpoint, $args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'virtual card payment - response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($response, true) ) );
		return $response;
	}

	/**
	 * Refund request
	 *
	 * @param  WC_Order  $order Order object.
	 * @param  array $data Api args
	 *
	 * @return array
	*/
	public function refund($order, $data){
		$order_id = $order->get_id();
		$amount = (int)$data['refund_amount'];
		$amount = $amount*100;

		$virtual_card = $this->helper->get_virtual_card($order);
		$refund_method = ($virtual_card) ? 'virtual_card' : 'card';

		$endpoint = $this->helper->getEndpoint('refund', $refund_method);
		WC_Gateway_Valitorpay::log( 'Endpoint: ' . wc_print_r( $endpoint, true ) );

		$maskedCardNumber = get_post_meta( $order_id, '_' . $this->gateway_id() . '_maskedCardNumber', true );
		if(empty($maskedCardNumber)){
			$maskedCardNumber = $order->get_meta('_' . $this->gateway_id() . '_maskedCardNumber');
		}

		$acquirerReferenceNumber = get_post_meta( $order_id, '_' . $this->gateway_id() . '_acquirerReferenceNumber', true );
		if(empty($acquirerReferenceNumber)){
			$acquirerReferenceNumber = $order->get_meta('_' . $this->gateway_id() . '_acquirerReferenceNumber');
		}

		if($refund_method == 'card'){
			$args = array(
				'operation' => 'Refund',
				'transactionType' => 'ECommerce',
				'terminalId' => $this->gateway_option('terminalId'),
				'agreementNumber' => $this->gateway_option('agreementNumber'),
				"amount"=> $amount,
				"currency"=> $order->get_currency(),
				"acquirerReferenceNumber"=> $acquirerReferenceNumber,
				'additionalData' => array(
					'merchantReferenceData'=> 'WC' . $order->get_order_number()
				),
				'maskedCardNumber'=> $maskedCardNumber,
				'systemCalling'=> $this->helper->getMerchantSoftware()
			);
		}else{
			$args = array(
				'operation' => 'Refund',
				'terminalId' => $this->gateway_option('terminalId'),
				'agreementNumber' => $this->gateway_option('agreementNumber'),
				"amount"=> $amount,
				"currency"=> $order->get_currency(),
				"acquirerReferenceNumber"=> $acquirerReferenceNumber,
				'virtualCardNumber'=> $virtual_card,
				'systemCalling'=> $this->helper->getMerchantSoftware()
			);
		}

		$log_args = $this->helper->secureLogData($args);
		WC_Gateway_Valitorpay::log( sprintf( __( '%s refund - args: %s', 'valitorpay-payment-gateway-for-woocommerce' ), $refund_method, wc_print_r($log_args, true) ) );
		$response = $this->request($endpoint, $args);
		WC_Gateway_Valitorpay::log( sprintf( __( '%s refund - response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), $refund_method, wc_print_r($response, true) ) );
		return $response;
	}

	/**
	 * Create virtual card request
	 *
	 * @param  WC_Order  $order Order object.
	 * @param  array $data Api args
	 *
	 * @return array
	*/
	public function create_virtual_card($order, $data){
		$order_id = $order->get_id();

		$endpoint = $this->helper->getEndpoint('virtual_card', 'create');
		WC_Gateway_Valitorpay::log( 'Endpoint: ' . wc_print_r( $endpoint, true ) );

		$card_args = $this->helper->get_card_args($data);
		$args = array(
			"cardNumber"=> $card_args['cardNumber'],
			"expirationMonth"=> $card_args['expirationMonth'],
			"expirationYear"=> $card_args['expirationYear'],
			"cvc"=>$card_args['cvc'],
			'terminalId'=> $this->gateway_option('terminalId'),
			'agreementNumber'=> $this->gateway_option('agreementNumber'),
			'subsequentTransactionType' => 'MerchantInitiatedRecurring',
			'currency' => $order->get_currency(),
			'systemCalling'=> $this->helper->getMerchantSoftware()
		);

		$log_args = $this->helper->secureLogData($args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'create virtual card - args: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($log_args, true) ) );

		$response = $this->request($endpoint, $args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'create virtual card - response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($response, true) ) );
		return $response;
	}

	/**
	 * Virtual card details request
	 *
	 * @param  WC_Order  $order Order object.
	 * @param  string $virtual_card Virtual card
	 *
	 * @return array
	*/
	public function get_virtual_card_data( $order, $virtual_card){
		$order_id = $order->get_id();

		$endpoint = $this->helper->getEndpoint($service, $method);
		WC_Gateway_Valitorpay::log( 'Endpoint: ' . wc_print_r( $endpoint, true ) );

		$args = array(
			'virtualCard'=>$virtual_card,
			'agreementNumber' => $this->gateway_option('agreementNumber'),
			'systemCalling'=> $this->getMerchantSoftware()
		);
		$log_args = $this->helper->secureLogData($args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'virtual card data - args: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($log_args, true) ) );

		$response = $this->request($endpoint, $args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'virtual card data- response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($response, true) ) );
		return $response;
	}

	/**
	 * Virtual card expiration renewal request
	 *
	 * @param  WC_Order  $order Order object.
	 * @param  array $data Api args
	 *
	 * @return array
	*/
	public function virtual_card_expiration_renewal($order, $data){
		$order_id = $order->get_id();
		$endpoint = $this->helper->getEndpoint('virtual_card', 'update_expiration');
		WC_Gateway_Valitorpay::log( 'Endpoint: ' . wc_print_r( $endpoint, true ) );

		$args = array(
			'virtualCardNumber'=>$data['virtualCard'],
			'expirationMonth'=>$data['expirationMonth'],
			'expirationYear'=>$data['expirationYear'],
			'terminalId'=> $this->gateway_option('terminalId'),
			'agreementNumber'=> $this->gateway_option('agreementNumber'),
			'systemCalling'=> $this->helper->getMerchantSoftware()
		);
		$log_args = $this->helper->secureLogData($args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'virtual card expiration renewal - args: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($log_args, true) ) );

		$response = $this->request($endpoint, $args);
		WC_Gateway_Valitorpay::log( sprintf( __( 'virtual card expiration renewal - response: %s', 'valitorpay-payment-gateway-for-woocommerce' ), wc_print_r($response, true) ) );
		return $response;
	}
}
