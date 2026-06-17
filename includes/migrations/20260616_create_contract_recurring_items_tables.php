<?php
/**
 * 定期請求明細行テーブルを作成するマイグレーション
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

$service_table = $wpdb->prefix . 'ktp_service_recurring_item';
$contract_table = $wpdb->prefix . 'ktp_contract_recurring_item';

$service_sql = "CREATE TABLE {$service_table} (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	service_id MEDIUMINT(9) NOT NULL DEFAULT 0,
	item_name VARCHAR(255) NOT NULL DEFAULT '',
	amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	tax_rate DECIMAL(5,2) NULL DEFAULT NULL,
	sort_order SMALLINT(5) NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY service_id (service_id)
) {$charset_collate};";

$contract_sql = "CREATE TABLE {$contract_table} (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	contract_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
	item_name VARCHAR(255) NOT NULL DEFAULT '',
	amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	tax_rate DECIMAL(5,2) NULL DEFAULT NULL,
	sort_order SMALLINT(5) NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY contract_id (contract_id)
) {$charset_collate};";

dbDelta( $service_sql );
dbDelta( $contract_sql );

update_option( 'ktp_contract_recurring_items_migration_completed', '2026-06-16' );

if ( ! function_exists( 'ktpwp_create_contract_recurring_items_tables' ) ) {
	/**
	 * マイグレーション runner 用
	 *
	 * @return bool
	 */
	function ktpwp_create_contract_recurring_items_tables() {
		if ( class_exists( 'KTPWP_Contract_Recurring_Items' ) ) {
			return KTPWP_Contract_Recurring_Items::ensure_tables();
		}

		return false;
	}
}
