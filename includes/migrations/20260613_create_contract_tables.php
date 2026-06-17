<?php
/**
 * 定期契約関連テーブルを作成するマイグレーション
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

$contract_table = $wpdb->prefix . 'ktp_contract';
$initial_fee_table = $wpdb->prefix . 'ktp_contract_initial_fee';
$billing_log_table = $wpdb->prefix . 'ktp_contract_billing_log';

$contract_sql = "CREATE TABLE {$contract_table} (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	client_id MEDIUMINT(9) NOT NULL DEFAULT 0,
	service_id MEDIUMINT(9) NOT NULL DEFAULT 0,
	contract_name VARCHAR(255) NOT NULL DEFAULT '',
	amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	billing_cycle VARCHAR(20) NOT NULL DEFAULT 'monthly',
	billing_day TINYINT(2) NOT NULL DEFAULT 1 COMMENT '1-28, 99=月末',
	start_date DATE NULL DEFAULT NULL,
	end_date DATE NULL DEFAULT NULL,
	status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, paused, cancelled',
	next_billing_date DATE NULL DEFAULT NULL,
	first_billed TINYINT(1) NOT NULL DEFAULT 0 COMMENT '初回請求済み',
	send_reminder_mail TINYINT(1) NOT NULL DEFAULT 1 COMMENT '請求予定メール送信',
	memo TEXT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY client_id (client_id),
	KEY service_id (service_id),
	KEY status (status),
	KEY next_billing_date (next_billing_date)
) {$charset_collate};";

$initial_fee_sql = "CREATE TABLE {$initial_fee_table} (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	contract_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
	fee_name VARCHAR(255) NOT NULL DEFAULT '',
	amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	tax_rate DECIMAL(5,2) NULL DEFAULT NULL,
	sort_order SMALLINT(5) NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY contract_id (contract_id)
) {$charset_collate};";

$billing_log_sql = "CREATE TABLE {$billing_log_table} (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	contract_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
	order_id MEDIUMINT(9) NOT NULL DEFAULT 0,
	billing_period CHAR(7) NOT NULL DEFAULT '' COMMENT 'YYYY-MM',
	reminder_sent_at DATETIME NULL DEFAULT NULL,
	invoice_sent_at DATETIME NULL DEFAULT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY contract_period (contract_id, billing_period),
	KEY order_id (order_id)
) {$charset_collate};";

dbDelta( $contract_sql );
dbDelta( $initial_fee_sql );
dbDelta( $billing_log_sql );

update_option( 'ktp_contract_tables_migration_completed', '2026-06-13' );

if ( ! function_exists( 'ktpwp_create_contract_tables' ) ) {
	/**
	 * マイグレーション runner 用
	 *
	 * @return bool
	 */
	function ktpwp_create_contract_tables() {
		return true;
	}
}
