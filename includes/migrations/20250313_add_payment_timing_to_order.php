<?php
/**
 * Migration: Add payment_timing, external_source, external_order_id to order table
 *
 * 受注の支払タイミング（顧客に従う/後払い/前払い）と外部連携用IDを追加する。
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
 * Migration class: Add payment_timing, external_source, external_order_id to ktpwp_order
 */
class KTPWP_Migration_20250313_Add_Payment_Timing_To_Order {

	/**
	 * Run the migration
	 *
	 * @return bool True on success, false on failure
	 */
	public static function up() {
		global $wpdb;

		$order_table = $wpdb->prefix . 'ktpwp_order';

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( "SHOW TABLES LIKE %s", $order_table )
		) === $order_table;

		if ( ! $table_exists ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				ktpwp_debug_log( 'KTPWP Migration: Table ' . $order_table . ' does not exist. Skipping payment_timing columns.' );
			}
			return false;
		}

		$existing_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $order_table ), 0 );
		$columns_to_add = array(
			'payment_timing'     => "VARCHAR(20) NULL DEFAULT NULL COMMENT '支払タイミング（NULL=顧客に従う, postpay, prepay）'",
			'external_source'   => "VARCHAR(50) NULL DEFAULT NULL COMMENT '連携元（例: woocommerce）'",
			'external_order_id' => "VARCHAR(100) NULL DEFAULT NULL COMMENT '外部注文ID'",
		);

		foreach ( $columns_to_add as $col => $def ) {
			if ( in_array( $col, $existing_columns, true ) ) {
				continue;
			}
			if ( ktpwp_migration_add_column( $order_table, $col, $def ) === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				ktpwp_debug_log( 'KTPWP Migration: Failed to add ' . $col . ' to ' . $order_table . '. ' . $wpdb->last_error );
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			ktpwp_debug_log( 'KTPWP Migration: payment_timing columns check completed for ' . $order_table );
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
		$to_drop = array( 'payment_timing', 'external_source', 'external_order_id' );
		foreach ( $to_drop as $col ) {
			if ( ! in_array( $col, $existing_columns, true ) ) {
				continue;
			}
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i DROP COLUMN %i", $order_table, $col ) );
		}
		return true;
	}
}
} )();
