<?php
/**
 * サービステーブルに在庫数（stock）を追加するマイグレーション
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

if ( ! in_array( 'stock', $existing_columns, true ) ) {
	$sql    = "ALTER TABLE `{$service_table}` ADD COLUMN `stock` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '在庫数（定期契約の受付枠）' AFTER `contract_billing_cycle`";
	$result = $wpdb->query( $sql );

	if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( "KTPWP Migration: Added stock column to {$service_table}" );
	}
}

update_option( 'ktp_service_stock_migration_completed', '2026-06-17' );
