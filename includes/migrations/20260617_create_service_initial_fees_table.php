<?php
/**
 * サービス初回費用テーブルを作成するマイグレーション
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dbDelta' ) ) {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
}

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();
$table           = $wpdb->prefix . 'ktp_service_initial_fee';

$sql = "CREATE TABLE {$table} (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	service_id MEDIUMINT(9) NOT NULL DEFAULT 0,
	fee_name VARCHAR(255) NOT NULL DEFAULT '',
	amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	tax_rate DECIMAL(5,2) NULL DEFAULT NULL,
	sort_order SMALLINT(5) NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY service_id (service_id)
) {$charset_collate};";

dbDelta( $sql );

update_option( 'ktp_service_initial_fees_migration_completed', '2026-06-17' );

if ( ! function_exists( 'ktpwp_create_service_initial_fees_table' ) ) {
	/**
	 * マイグレーション runner 用
	 *
	 * @return bool
	 */
	function ktpwp_create_service_initial_fees_table() {
		if ( class_exists( 'KTPWP_Service_Initial_Fees' ) ) {
			return KTPWP_Service_Initial_Fees::ensure_tables();
		}

		return false;
	}
}
