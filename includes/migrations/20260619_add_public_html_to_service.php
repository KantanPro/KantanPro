<?php
/**
 * サービステーブルに公開用HTML（public_html）を追加するマイグレーション
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

if ( ! in_array( 'public_html', $existing_columns, true ) ) {
	$sql    = "ALTER TABLE `{$service_table}` ADD COLUMN `public_html` TEXT NULL COMMENT '公開商品カード・詳細用HTML（LINEボタン等）' AFTER `public_quantity_fixed`";
	$result = $wpdb->query( $sql );

	if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( "KTPWP Migration: Added public_html column to {$service_table}" );
	}
}

update_option( 'ktp_service_public_html_migration_completed', '2026-06-19' );
