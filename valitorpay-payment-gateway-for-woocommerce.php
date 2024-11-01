<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://tactica.is/
 * @since             1.0.0
 * @package           Valitorpay_Payment_Gateway_For_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Payment Gateway via ValitorPay for WooCommerce
 * Description:       Extends WooCommerce with a <a href="https://www.valitor.com/" target="_blank">ValitorPay</a> gateway
 * Version:           1.2.19
 * Author:            Tactica
 * Author URI:        http://tactica.is/
 * Text Domain:       valitorpay-payment-gateway-for-woocommerce
 * Domain Path:       /languages
 * Requires at least: 5.5
 * WC requires at least: 3.2.3
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'VALITORPAY_VERSION', '1.2.19' );
define( 'VALITORPAY_MAIN_FILE', __FILE__ );
define( 'VALITORPAY_DIR', plugin_dir_path( __FILE__ ) );

/**
 * WooCommerce fallback notice.
 *
 * @since  1.0.0
 * @return string
 */
function woocommerce_valitorpay_missing_wc_notice() {
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'ValitorPay requires WooCommerce to be installed and active. You can download %s here.', 'valitorpay-payment-gateway-for-woocommerce' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
add_action( 'plugins_loaded', 'valitorpay_payment_gateway_for_woocommerce_init' );

function valitorpay_payment_gateway_for_woocommerce_init() {
	load_plugin_textdomain( 'valitorpay-payment-gateway-for-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_valitorpay_missing_wc_notice' );
		return;
	}
	if ( ! class_exists( 'WC_ValitorPay' ) ) :

		class WC_ValitorPay {
			/**
			 * @var Singleton The reference the *Singleton* instance of this class
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Singleton The *Singleton* instance.
			 */
			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			private function __construct() {
				$this->init();
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function init() {
				if ( is_admin() ) {
				}

				require_once dirname( __FILE__ ) . '/includes/class-wc-valitorpay-customer.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-valitorpay-helper.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-valitorpay-api.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-valitorpay.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-valitorpay-intent-controller.php';

				if ( class_exists( 'WC_Subscriptions_Order' ) ) {
					require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-valitorpay-subscriptions.php';
				}

				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );


			}

			/**
			 * Add the gateways to WooCommerce.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			*/
			public function add_gateways( $gateways ) {
				if ( class_exists( 'WC_Subscriptions_Order' ) ) {
					$gateways[] = 'WC_Gateway_Valitorpay_Subscriptions';
				} else {
					$gateways[] = 'WC_Gateway_Valitorpay';
				}

				return $gateways;
			}
		}

		WC_ValitorPay::get_instance();
	endif;
}

add_action( 'init', 'woocommerce_valitorpay_ensure_session' );
// Ensure there is a customer session so that nonce is not invalidated by new session
function woocommerce_valitorpay_ensure_session() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	if ( ! empty( WC()->session ) && ! WC()->session->has_session() ) {
		WC()->session->set_customer_session_cookie( true );
	}
}

/**
 * Register and enqueue a custom scripts.
 */
function woocommerce_valitorpay_enqueue_admin_scripts() {
	wp_register_style( 'valitorpay_admin_css',  plugin_dir_url( __FILE__ ) . 'admin/styles/valitorpay-admin.css', false, VALITORPAY_VERSION );
	wp_enqueue_style( 'valitorpay_admin_css' );
	wp_enqueue_script('valitorpay_admin_scripts', plugin_dir_url( __FILE__ ) . 'admin/js/valitorpay-admin.js', array( 'jquery' ), VALITORPAY_VERSION, false );
}
add_action( 'admin_enqueue_scripts', 'woocommerce_valitorpay_enqueue_admin_scripts' );

/**
 * Validate valitorpay CC form fields
 *
 * @since 1.2.5
 * @version 1.2.5
*/
add_action('woocommerce_checkout_process', 'woocommerce_valitorpay_checkout_field_validation');
function woocommerce_valitorpay_checkout_field_validation() {
	if ( $_POST['payment_method'] === 'valitorpay'){
		$cc_fields = [
			'card-number'=> esc_html__( 'Card number', 'woocommerce' ),
			'card-expiry'=> esc_html__( 'Expiry (MM/YY)', 'woocommerce' ),
			'card-cvc'=> esc_html__( 'Card code', 'woocommerce' )
		];
		foreach ($cc_fields as $field_key => $field_label) {
			if( isset($_POST['valitorpay-' . $field_key]) && empty($_POST['valitorpay-' . $field_key]) ){
				wc_add_notice( sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' ), 'error' );
			}
		}
	}
}

/**
 * Add notice to order-pay
 *
 * @since 1.2.7
 * @version 1.2.7
*/
add_action('woocommerce_init', 'valitorpay_order_pay_redirect');
function valitorpay_order_pay_redirect(){
	if ( isset($_GET['valitorpay-verification-failed']) && $_GET['valitorpay-verification-failed'] == 1 ){
		$message = ( isset($_GET['message']) && !empty($_GET['message'] )) ? sanitize_text_field($_GET['message']) : '';
		if( empty($message) )
			$message = __( 'Card verification failed', 'valitorpay-payment-gateway-for-woocommerce');
		wc_add_notice( $message, 'error' );
		$url = remove_query_arg(['valitorpay-verification-failed', 'message']);
		wp_safe_redirect($url);
		exit;
	}
}

add_action( 'woocommerce_blocks_loaded', 'valitorpay_woocommerce_blocks_support' );
function valitorpay_woocommerce_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {

		require_once VALITORPAY_DIR . 'includes/class-payment-method-valitorpay-registration.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new PaymentMethodValitorpay );
			}
		);
	}
}