<?php
/**
 * サービステーブルに契約（請求サイクル）カラムを追加するマイグレーション
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$service_table = $wpdb->prefix . 'ktp_service';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $service_table ) ) !== $service_table ) {
	return;
}

$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$service_table}`" );

if ( ! in_array( 'contract_billing_cycle', $existing_columns, true ) ) {
	$sql    = "ALTER TABLE `{$service_table}` ADD COLUMN `contract_billing_cycle` VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT '契約請求サイクル' AFTER `is_public`";
	$result = $wpdb->query( $sql );

	if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( "KTPWP Migration: Added contract_billing_cycle column to {$service_table}" );
	}
}

update_option( 'ktp_service_contract_billing_cycle_migration_completed', '2026-06-13' );

if ( ! function_exists( 'ktpwp_add_contract_billing_cycle_to_service' ) ) {
	/**
	 * マイグレーション runner 用
	 *
	 * @return bool
	 */
	function ktpwp_add_contract_billing_cycle_to_service() {
		return true;
	}
}
