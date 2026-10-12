<?php
/**
 * サービステーブルに契約（請求サイクル）カラムを追加するマイグレーション
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

if ( ! in_array( 'contract_billing_cycle', $existing_columns, true ) ) {
	$sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `contract_billing_cycle` VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT '契約請求サイクル' AFTER `is_public`", $service_table );
	$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。

	if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		ktpwp_debug_log( "KTPWP Migration: Added contract_billing_cycle column to {$service_table}" );
	}
}

update_option( 'ktpwp_service_contract_billing_cycle_migration_completed', '2026-06-13' );

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
} )();
