<?php
/**
 * サービステーブルに公開フォームの数量固定フラグを追加するマイグレーション
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

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $service_table ) ) !== $service_table ) {
	return;
}

$existing_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $service_table ) );

if ( ! in_array( 'public_quantity_fixed', $existing_columns, true ) ) {
	$sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `public_quantity_fixed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '公開フォームの数量を1固定（1=固定・数量欄非表示）' AFTER `stock`", $service_table );
	$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。

	if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		ktpwp_debug_log( "KTPWP Migration: Added public_quantity_fixed column to {$service_table}" );
	}
}

update_option( 'ktpwp_service_public_quantity_fixed_migration_completed', '2026-06-18' );
} )();
