<?php
/**
 * Migration: Add payment_timing column to client table
 *
 * 顧客のデフォルト支払タイミング（後払い/前払い）を管理するカラムを追加する。
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
 * Migration class: Add payment_timing to ktpwp_client
 */
class KTPWP_Migration_20250312_Add_Payment_Timing_To_Client {

	/**
	 * Run the migration
	 *
	 * @return bool True on success, false on failure
	 */
	public static function up() {
		global $wpdb;

		$client_table = $wpdb->prefix . 'ktpwp_client';

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( "SHOW TABLES LIKE %s", $client_table )
		) === $client_table;

		if ( ! $table_exists ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				ktpwp_debug_log( 'KTPWP Migration: Table ' . $client_table . ' does not exist. Skipping payment_timing.' );
			}
			return false;
		}

		$column_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $client_table, 'payment_timing' )
		);

		if ( $column_exists ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				ktpwp_debug_log( 'KTPWP Migration: Column payment_timing already exists in ' . $client_table );
			}
			return true;
		}

		$sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `payment_timing` VARCHAR(20) NOT NULL DEFAULT 'postpay' COMMENT '支払タイミング（postpay=後払い, prepay=前払い）'", $client_table );
		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。

		if ( $result === false ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				ktpwp_debug_log( 'KTPWP Migration: Failed to add payment_timing to ' . $client_table . '. Error: ' . $wpdb->last_error );
			}
			return false;
		}

		$wpdb->query( $wpdb->prepare( "UPDATE %i SET `payment_timing` = 'postpay' WHERE `payment_timing` IS NULL OR `payment_timing` = ''", $client_table ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			ktpwp_debug_log( 'KTPWP Migration: Successfully added payment_timing to ' . $client_table );
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
		$client_table = $wpdb->prefix . 'ktpwp_client';
		$column_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $client_table, 'payment_timing' )
		);
		if ( ! $column_exists ) {
			return true;
		}
		$result = $wpdb->query( $wpdb->prepare( "ALTER TABLE %i DROP COLUMN `payment_timing`", $client_table ) );
		return $result !== false;
	}
}
} )();
