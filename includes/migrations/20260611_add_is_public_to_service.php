<?php
/**
 * サービステーブルにサイト公開フラグ（is_public）を追加するマイグレーション
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

if ( ! in_array( 'is_public', $existing_columns, true ) ) {
	$sql = "ALTER TABLE `{$service_table}` ADD COLUMN `is_public` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'サイト公開（1=公開）' AFTER `category`";
	$result = $wpdb->query( $sql );

	if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( "KTPWP Migration: Added is_public column to {$service_table}" );
	}
}

update_option( 'ktp_service_is_public_migration_completed', '2026-06-11' );
