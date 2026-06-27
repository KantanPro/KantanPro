<?php
/**
 * サービステーブルに公開商品の即時購入フラグを追加するマイグレーション
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$service_table = $wpdb->prefix . 'ktp_service';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$service_table}`", 0 );

if ( ! is_array( $existing_columns ) ) {
	$existing_columns = array();
}

if ( ! in_array( 'public_instant_purchase', $existing_columns, true ) ) {
	$sql = "ALTER TABLE `{$service_table}` ADD COLUMN `public_instant_purchase` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '公開商品でStripe即時購入（1=有効）' AFTER `public_quantity_fixed`";
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
	$result = $wpdb->query( $sql );
	if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( "KTPWP Migration: Failed to add public_instant_purchase column to {$service_table}" );
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( "KTPWP Migration: Added public_instant_purchase column to {$service_table}" );
	}
}

update_option( 'ktp_service_public_instant_purchase_migration_completed', '2026-06-20' );
