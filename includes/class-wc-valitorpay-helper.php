<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Valitorpay_Helper{
	const ENDPOINT_TEST = 'https://uat.valitorpay.com/';
	const ENDPOINT_LIVE = 'https://valitorpay.com/';

	/**
	 * Pointer to gateway making the request.
	 *
	 * @var WC_Gateway_Paypal
	 */
	protected $gateway;

	protected $gateway_settings;
	/**
	 * Endpoint for requests from Valitorpay.
	 *
	 * @var string
	 */
	protected $ipn_url;

	private $testmode;
	private $api_key;
	private $payment_with_verification;
	private $payment_with_virtual_card;

	/**
	 * Valitorpay agreement Number
	 *
	 * @var string
	 */
	private $agreementNumber;

	/**
	 * Valitorpay terminal Id
	 *
	 * @var string
	 */
	private $terminalId;

	public function __construct($gateway){
		$this->gateway = $gateway;
		$this->ipn_url = WC()->api_request_url( 'WC_Gateway_Valitorpay' );
		$this->gateway_settings = $this->gateway->settings;

		$this->testmode = $this->gateway_settings['testmode'];
		$this->api_key = $this->gateway_settings['api_key'];
		$this->payment_with_verification = 'yes' === $this->gateway_settings['payment_with_verification'];
		$this->agreementNumber = ( isset($this->gateway_settings['agreementNumber']) && $this->gateway_settings['agreementNumber'] ) ? $this->gateway_settings['agreementNumber'] : null ;
		$this->terminalId = ( isset($this->gateway_settings['terminalId']) && $this->gateway_settings['terminalId'] ) ? $this->gateway_settings['terminalId'] : null ;
	}

	/**
	 * Returns Api endpoint
	 *
	 * @param string $service  API endpoint card_verification|payment|refund|virtual_card
	 * @param string $method  API endpoint action CardVerification|CardPayment|VirtualCardPayment|CardPayment|GetVirtualCardData|CreateVirtualCard|UpdateExpirationDate
	 *
	 * @return string
	*/
	public function getEndpoint($service, $method = ''){
		$endpoints = array();
		switch ($service) {
			case 'card_verification':
				$endpoints[] = 'CardVerification';
				break;
			case 'payment':
			case 'refund':
				$endpoints[] = 'Payment';
				switch ($method) {
					case 'card':
						$endpoints[] = 'CardPayment';
						break;
					case 'virtual_card':
						$endpoints[] = 'VirtualCardPayment';
						break;
					case 'card_with_verification':
							$endpoints[] = 'CardPayment';
						break;
				}

				break;
			case 'virtual_card':
				$endpoints[] = 'VirtualCard';

				if( !empty($method) ){
					switch ($method) {
						case 'get_data':
							$endpoints[] = 'GetVirtualCardData';
							break;
						case 'create':
							$endpoints[] = 'CreateVirtualCard';
							break;
						case 'update_expiration':
							$endpoints[] = 'UpdateExpirationDate';
							break;
					}
				}

				break;
		}

		if($this->testmode == 'yes'){
			return self::ENDPOINT_TEST . implode('/', $endpoints);
		}

		return self::ENDPOINT_LIVE . implode('/', $endpoints);
	}


	/**
	 * Returns api key
	 *
	 * @return array
	*/
	public function getApiKey(){
		return $this->api_key;
	}

	/**
	 * Prepare card args
	 *
	 * @param array $data  Checkout args
	 *
	 * @return array
	*/
	public function get_card_args($data){
		$args = array();
		$expirationMonth = $expirationYear = null;
		$expiration = isset($data[$this->gateway->id . '-card-expiry']) ?  sanitize_text_field($data[$this->gateway->id . '-card-expiry']) : '';
		if(!empty($expiration)){
			$pieces = explode('/', $expiration);
			$expirationMonth = trim($pieces[0]);
			$expirationYear = trim($pieces[1]);
		}

		if(strlen($expirationYear) == 2){
			$dt = DateTime::createFromFormat('y', $expirationYear);
			$expirationYear =  $dt->format('Y');
		}

		$card_number = isset($data[$this->gateway->id . '-card-number']) ? sanitize_text_field( $data[$this->gateway->id . '-card-number'] ) : '' ;
		$card_number = preg_replace('/\s+/', '', $card_number);

		$args = array(
			'cardNumber'=> $card_number,
			'expirationMonth' => $expirationMonth,
			'expirationYear' =>  $expirationYear,
			'cvc' => isset($data[$this->gateway->id . '-card-cvc']) ? $data[$this->gateway->id . '-card-cvc']  : ''
		);

		return $args;
	}

	/**
	 * Return amount from WC order
	 *
	 * @param  WC_Order  $order Order object
	 *
	 * @return string
	*/
	public function get_amount($order){
		$amount = $this->number_format( $order->get_total(), $order);
		// integer <int64> The total amount of the payment specified in a minor currency unit.
		return $amount*100;
	}

	/**
	 * Return intent page url
	 *
	 * @param int $order_id  WC_Order id
	 *
	 * @return string
	*/
	static function get_checkout_intent_url($order_id){
		$checkout_url = wc_get_checkout_url();
		$checkout_url = substr($checkout_url, 0, strrpos($checkout_url, "/"));

		return add_query_arg(
			array(
				'order'       => $order_id,
				'response' => 1
			),
			$checkout_url . WC_AJAX::get_endpoint( 'wc_valitorpay_verify_intent' )
		);
	}

	/**
	 * Check if currency has decimals.
	 *
	 * @param  string $currency Currency to check.
	 * @return bool
	 */
	protected function currency_has_decimals( $currency ) {
		if ( in_array( $currency, array( 'HUF', 'JPY', 'TWD' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Format prices.
	 *
	 * @param  float|int $price Price to format.
	 * @param  WC_Order  $order Order object.
	 * @return string
	 */
	protected function number_format( $price, $order ) {
		$decimals = 2;

		if ( ! $this->currency_has_decimals( $order->get_currency() ) ) {
			$decimals = 0;
		}

		return number_format( $price, $decimals, '.', '' );
	}

	/**
	 * Specify the name and version of the system calling ValitorPay
	 *
	 * @return string
	 */
	public function getMerchantSoftware(){
		global $wp_version;
		return sprintf('Tactica WP %s WC %s VP %s', $wp_version, WC()->version, VALITORPAY_VERSION);
	}

	/**
	 * Encrypt string
	 *
	 * @param string $string String to be encoded
	 * @param string $key Encoded key
	 *
	 * @return string
	*/
	public function data_encrypt($string, $key){
		$cipher_method = 'aes-128-ctr';
		$enc_key = openssl_digest($key, 'SHA256', TRUE);
		$ivlen = openssl_cipher_iv_length($cipher_method);
		$enc_iv = openssl_random_pseudo_bytes($ivlen);
		$crypted_string = openssl_encrypt($string, $cipher_method, $enc_key, 0, $enc_iv) . "::" . bin2hex($enc_iv);
		unset($string, $cipher_method, $enc_key, $enc_iv);
		return base64_encode($crypted_string);
	}

	/**
	 * Decrypt string
	 *
	 * @param string $crypted_string Crypted string
	 * @param string $key Encoded key
	 *
	 * @return string
	*/
	public function data_decrypt($crypted_string, $key){
		$crypted_string = base64_decode($crypted_string);
		list($crypted_string, $enc_iv) = explode("::", $crypted_string);
		$cipher_method = 'aes-128-ctr';
		$enc_key = openssl_digest($key, 'SHA256', TRUE);
		$string = openssl_decrypt($crypted_string, $cipher_method, $enc_key, 0, hex2bin($enc_iv));
		unset($crypted_string, $cipher_method, $enc_key, $enc_iv);
		return $string;
	}

	/**
	 * Encode checkout data
	 *
	 * @param array $data Data to be encoded
	 * @param string $key Encoded key
	 *
	 * @return array
	*/
	public function encode_checkout_args($data, $key){
		$args = array();
		if( isset($data[$this->gateway->id . '-card-number']) && !empty( $data[$this->gateway->id . '-card-number'] ) ){
			$card = array( $this->gateway->id . '-card-number' => sanitize_text_field( $data[$this->gateway->id . '-card-number'] ),
							$this->gateway->id . '-card-expiry'=> sanitize_text_field( $data[$this->gateway->id . '-card-expiry'] ),
							$this->gateway->id . '-card-cvc'=> sanitize_text_field( $data[$this->gateway->id . '-card-cvc'] )
						);
			$card_str = implode('|',$card);
			$args['token'] = $this->data_encrypt($card_str,$key);
		}
		return $args;
	}

	/**
	 * Decode checkout data
	 *
	 * @param string $encoded_data Encoded data
	 * @param string $key Encoded key
	 *
	 * @return array
	*/
	public function decode_checkout_args( $encoded_data, $key){
		$data = [];
		$data_str = $this->data_decrypt($encoded_data, $key);
		if(!empty($data_str)){
			$data_arr  = explode('|', $data_str);
			$data[$this->gateway->id . '-card-number'] = (isset($data_arr[0])) ? $data_arr[0] : null;
			$data[$this->gateway->id . '-card-expiry'] = (isset($data_arr[1])) ? $data_arr[1] : null;
			$data[$this->gateway->id . '-card-cvc'] = (isset($data_arr[2])) ? $data_arr[2] : null;
		}
		return $data;
	}

	/**
	 * Returns masked card
	 *
	 * @param string $card_number Card number
	 *
	 * @return string Masked card
	*/
	public function get_masked_card($card_number){
		$card_number = preg_replace('/\s+/', '', $card_number);

		if(!$card_number) return;

		$length = strlen($card_number);
		return  substr($card_number, 0, 6) . str_repeat("*", ($length-6-4) ) . substr($card_number, -4);
	}

	/**
	 * Returns secure values
	 *
	 * @param array $data parameters values to be processed
	 *
	 * @return array
	*/
	public function secureLogData($data){
		$response = [];

		if(!empty($data)){
			foreach ($data as $key => $value) {
				if(is_array($value)){
					$response[$key] = [];
					foreach ($value as $arr_key => $arr_value) {
						$sanitized_value = $this->get_sanitized_value($arr_key, $arr_value);
						$response[$key][$arr_key] = $sanitized_value;
					}
				}else{
					$sanitized_value = $this->get_sanitized_value($key, $value);
					$response[$key] = $sanitized_value;
				}
			}
		}
		return $response;
	}

	/**
	 * Returns secure value
	 *
	 * @param string $key parameter key
	 * @param string $value parameter value to be processed
	 *
	 * @return string
	*/
	public function get_sanitized_value($key, $value){
		$secure_params = ['cardNumber', 'virtualCard', 'virtualCardNumber', 'cardholderAuthenticationVerificationData', 'token', 'cavv', 'dsTransId', 'TDS2_dsTransID', 'xid', 'MD'];
		$sanitized_value = sanitize_text_field($value);
		if(!empty($sanitized_value)){
			switch ($key) {
				case 'expirationMonth':
				case 'expirationYear':
				case 'cvc':
					$length = strlen($value);
					$sanitized_value = str_repeat('*', $length);
					break;
				case 'authenticationUrl':
					$sanitized_value = remove_query_arg( 'token', $sanitized_value);
					break;
				case 'cardVerificationRawResponse':
					$sanitized_value =  !empty($value) ? __( 'Verification html...', 'valitorpay-payment-gateway-for-woocommerce' ) :'';
					break;
				case 'cardNumber':
					$length = strlen($sanitized_value);
					$sanitized_value = substr($sanitized_value, 0, 6) . str_repeat("*", ($length-6-4) ) . substr($sanitized_value, -4);
					break;
				default:
					if(in_array($key, $secure_params)){
						$length = strlen($value);
						$visibleCount = (int) round($length / 4);
						$hiddenCount = $length - ($visibleCount * 2);
						$sanitized_value = substr($value, 0, $visibleCount) . str_repeat('*', $hiddenCount) . substr($value, ($visibleCount * -1), $visibleCount);
					}
					break;
			}
		}
		return $sanitized_value;
	}

	/**
	 * Get saved Virtual card
	 *
	 * @param $order WC_Order
	 *
	 * @return string
	 */
	public function get_virtual_card($order){
		$virtual_card = null;
		$order_id = $order->get_id();
		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$subscriptions_ids = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
			if( !empty( $subscriptions_ids) ){
				foreach( $subscriptions_ids as $subscription_id => $subscription_obj ){
					$virtual_card = get_post_meta( $subscription_id , '_valitorpay_virtualCard', true );
					if($virtual_card) break;
				}
			}
		}

		return $virtual_card;
	}
}