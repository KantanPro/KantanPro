<?php
/**
 * Supplier Security Class for KTPWP plugin
 *
 * Handles security-related operations for supplier data.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Supplier_Security' ) ) {

	/**
	 * Class KTPWP_Supplier_Security
	 *
	 * Handles security-related operations for supplier data.
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Supplier_Security {

		/**
		 * Set cookie for supplier data
		 *
		 * @since 1.0.0
		 * @param string $name The name parameter for cookie
		 * @return int The query ID
		 */
		public function set_cookie( $name ) {
			if ( empty( $name ) ) {
				return 1;
			}

			$cookie_name = 'ktp_' . sanitize_key( $name ) . '_id';
			$query_id = 1;

			if ( isset( $_COOKIE[ $cookie_name ] ) ) {
				$query_id = absint( $_COOKIE[ $cookie_name ] );
			} elseif ( isset( $_GET['data_id'] ) ) {
				$query_id = absint( $_GET['data_id'] );
			}

			// Validate ID is positive
			return ( $query_id > 0 ) ? $query_id : 1;
		}
	}
}
