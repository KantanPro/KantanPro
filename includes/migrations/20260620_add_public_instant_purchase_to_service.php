<?php
/**
 * サービステーブルに公開商品の即時購入フラグを追加するマイグレーション
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ファイル内の変数をグローバルに出さないよう、処理全体を無名関数で包む（読み込んだ時点で実行される）。
( static function () {

global $wpdb;

$service_table = $wpdb->prefix . 'ktpwp_service';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$existing_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $service_table ), 0 );

if ( ! is_array( $existing_columns ) ) {
	$existing_columns = array();
}

if ( ! in_array( 'public_instant_purchase', $existing_columns, true ) ) {
	$sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `public_instant_purchase` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '公開商品でStripe即時購入（1=有効）' AFTER `public_quantity_fixed`", $service_table );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
	$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。
	if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		ktpwp_debug_log( "KTPWP Migration: Failed to add public_instant_purchase column to {$service_table}" );
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		ktpwp_debug_log( "KTPWP Migration: Added public_instant_purchase column to {$service_table}" );
	}
}

update_option( 'ktpwp_service_public_instant_purchase_migration_completed', '2026-06-20' );
} )();
