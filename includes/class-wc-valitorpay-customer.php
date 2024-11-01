<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Valitorpay_Customer class.
 *
 * Represents a Valitorpay Customer.
 */
class WC_Valitorpay_Customer {

	/**
	 * Valitorpay customer ID
	 * @var string
	 */
	private $id = '';

	/**
	 * WP User ID
	 * @var integer
	 */
	private $user_id = 0;

	/**
	 * Data from API
	 * @var array
	 */
	private $customer_data = array();

	/**
	 * Constructor
	 * @param int $user_id The WP user ID
	 */
	public function __construct( $user_id = 0 ) {
		if ( $user_id ) {
			$this->set_user_id( $user_id );
			$this->set_id( $this->get_id_from_meta( $user_id ) );
		}
	}

	/**
	 * Get Valitorpay customer ID.
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set Valitorpay customer ID.
	 * @param [type] $id [description]
	 */
	public function set_id( $id ) {
		// Backwards compat for customer ID stored in array format. (Pre 3.0)
		if ( is_array( $id ) && isset( $id['customer_id'] ) ) {
			$id = $id['customer_id'];

			$this->update_id_in_meta( $id );
		}

		$this->id = wc_clean( $id );
	}

	/**
	 * User ID in WordPress.
	 * @return int
	 */
	public function get_user_id() {
		return absint( $this->user_id );
	}

	/**
	 * Set User ID used by WordPress.
	 * @param int $user_id
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = absint( $user_id );
	}

	/**
	 * Get user object.
	 * @return WP_User
	 */
	protected function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Checks to see if error is of invalid request
	 * error and it is no such customer.
	 *
	 * @param array $error
	 */
	public function is_no_such_customer_error( $error ) {
		return (
			$error &&
			'invalid_request_error' === $error->type &&
			preg_match( '/No such customer/i', $error->message )
		);
	}

	/**
	 * Retrieves the Valitorpay Customer ID from the user meta.
	 *
	 * @param  int $user_id The ID of the WordPress user.
	 * @return string|bool  Either the Valitorpay ID or false.
	 */
	public function get_id_from_meta( $user_id ) {
		return get_user_option( '_valitorpay_customer_id', $user_id );
	}

	/**
	 * Updates the current user with the right Valitorpay ID in the meta table.
	 *
	 * @param string $id The Valitorpay customer ID.
	 */
	public function update_id_in_meta( $id ) {
		update_user_option( $this->get_user_id(), '_valitorpay_customer_id', $id, false );
	}

	/**
	 * Deletes the user ID from the meta table with the right key.
	 */
	public function delete_id_from_meta() {
		delete_user_option( $this->get_user_id(), '_valitorpay_customer_id', false );
	}
}
