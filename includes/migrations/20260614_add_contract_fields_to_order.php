<?php
/**
 * 受注書テーブルに定期契約関連カラムを追加
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$order_table = $wpdb->prefix . 'ktp_order';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $order_table ) ) !== $order_table ) {
	return;
}

$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`" );

if ( ! in_array( 'contract_id', $existing_columns, true ) ) {
	$wpdb->query( "ALTER TABLE `{$order_table}` ADD COLUMN `contract_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '定期契約ID' AFTER `client_id`" );
}

$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`" );

if ( ! in_array( 'billing_period', $existing_columns, true ) ) {
	$wpdb->query( "ALTER TABLE `{$order_table}` ADD COLUMN `billing_period` CHAR(7) NOT NULL DEFAULT '' COMMENT '請求対象月YYYY-MM' AFTER `contract_id`" );
}

update_option( 'ktp_order_contract_fields_migration_completed', '2026-06-14' );

if ( ! function_exists( 'ktpwp_add_contract_fields_to_order' ) ) {
	/**
	 * マイグレーション runner 用
	 *
	 * @return bool
	 */
	function ktpwp_add_contract_fields_to_order() {
		return true;
	}
}
