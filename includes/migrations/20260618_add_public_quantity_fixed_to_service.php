<?php
/**
 * サービステーブルに公開フォームの数量固定フラグを追加するマイグレーション
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

if ( ! in_array( 'public_quantity_fixed', $existing_columns, true ) ) {
	$sql    = "ALTER TABLE `{$service_table}` ADD COLUMN `public_quantity_fixed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '公開フォームの数量を1固定（1=固定・数量欄非表示）' AFTER `stock`";
	$result = $wpdb->query( $sql );

	if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( "KTPWP Migration: Added public_quantity_fixed column to {$service_table}" );
	}
}

update_option( 'ktp_service_public_quantity_fixed_migration_completed', '2026-06-18' );
