<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

/**
 * Class for integrating with WooCommerce Blocks scripts
 *
 * @package 
 * @since
 */
final class PaymentMethodValitorpay extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'valitorpay';

	/**
	 * Settings from the WP options table
	 *
	 * @var array
	 */
	protected $settings = [];

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize(){
		$this->settings = get_option( 'woocommerce_valitorpay_settings', [] );
		$this->register_scripts();
		$this->register_edit_scripts();
	}

	/**
	 * @return bool
	 */
	public function register_scripts(){
		$script_path       = 'blocks/build/view.js';
		$script_url = plugin_dir_url( __DIR__ ) . $script_path;
		$script_asset_path = plugin_dir_url( __DIR__ )  . 'blocks/build/view.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		$result = wp_register_script(
			'valitorpay-script-frontend',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if (!$result) {
			return false;
		}

		wp_set_script_translations(
			'valitorpay-script-frontend',
			'valitorpay-payment-gateway-for-woocommerce',
			dirname(dirname( __FILE__ )) . '/languages'
		);


/*		$style_path       = 'blocks/build/view.css';
		$style_url = plugin_dir_url( __DIR__ ) . $style_path;
		$result = wp_register_style(
			'valitorpay-styles-frontend',
			$style_url,
			[],
			false,
			'all'
		);

		if(is_checkout()){
			wp_enqueue_style( 'valitorpay-styles-frontend' );
		}*/

		return true;
	}

	/**
	 * @return bool
	 */
	public function register_edit_scripts(){
		$script_path       = 'blocks/build/index.js';
		$script_url = plugin_dir_url( __DIR__ ) . $script_path;
		$script_asset_path = plugin_dir_url( __DIR__ )  . 'blocks/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		$result = wp_register_script(
			'valitorpay-script-editor',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if (!$result) {
			return false;
		}

		wp_set_script_translations(
			'valitorpay-script-editor',
			'valitorpay-payment-gateway-for-woocommerce',
			dirname(dirname( __FILE__ )) . '/languages'
		);
		return true;
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Returns an array of script handles to enqueue for this payment method in
	 * the frontend context
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		return ['valitorpay-script-frontend'];
	}

	/**
	 * Returns an array of script handles to enqueue for this payment method in
	 * the admin context
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles_for_admin() {
		return ['valitorpay-script-editor'];
	}

	/**
	 * An array of key, value pairs of data made available to payment methods
	 * client side.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => [ 'products' ],
			'testmode'    => $this->get_setting('testmode') === 'yes'
		];
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ){
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			return filemtime( $file );
		}
		return VALITORPAY_VERSION;
	}
}