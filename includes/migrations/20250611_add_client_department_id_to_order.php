<?php
/**
 * Migration: Add client_department_id to order table
 *
 * 受注ごとに依頼元部署を保持し、問い合わせごとの部署変更が他受注に影響しないようにする。
 *
 * @package KTPWP
 * @subpackage Migrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ファイル内の変数をグローバルに出さないよう、処理全体を無名関数で包む（読み込んだ時点で実行される）。
( static function () {

/**
 * Migration class: Add client_department_id to ktpwp_order
 */
class KTPWP_Migration_20250611_Add_Client_Department_Id_To_Order {

	/**
	 * Run the migration
	 *
	 * @return bool True on success, false on failure
	 */
	public static function up() {
		global $wpdb;

		$order_table = $wpdb->prefix . 'ktpwp_order';

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $order_table )
		) === $order_table;

		if ( ! $table_exists ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				ktpwp_debug_log( 'KTPWP Migration: Table ' . $order_table . ' does not exist. Skipping client_department_id column.' );
			}
			return false;
		}

		$existing_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $order_table ), 0 );
		if ( in_array( 'client_department_id', $existing_columns, true ) ) {
			return true;
		}

		$sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `client_department_id` INT NULL DEFAULT NULL COMMENT '依頼元部署ID' AFTER `client_id`", $order_table );
		if ( $wpdb->query( $sql ) === false ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				ktpwp_debug_log( 'KTPWP Migration: Failed to add client_department_id to ' . $order_table . '. ' . $wpdb->last_error );
			}
			return false;
		}

		$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD INDEX `client_department_id` (`client_department_id`)", $order_table ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			ktpwp_debug_log( 'KTPWP Migration: client_department_id column added to ' . $order_table );
		}

		return true;
	}

	/**
	 * Rollback the migration
	 *
	 * @return bool True on success, false on failure
	 */
	public static function down() {
		global $wpdb;

		$order_table = $wpdb->prefix . 'ktpwp_order';
		$existing_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $order_table ), 0 );
		if ( ! in_array( 'client_department_id', $existing_columns, true ) ) {
			return true;
		}

		$wpdb->query( $wpdb->prepare( "ALTER TABLE %i DROP COLUMN `client_department_id`", $order_table ) );

		return true;
	}
}
} )();
