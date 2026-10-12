<?php
/**
 * 定期契約に入金期日の参照先カラムを追加
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ファイル内の変数をグローバルに出さないよう、処理全体を無名関数で包む（読み込んだ時点で実行される）。
( static function () {

global $wpdb;

$table = $wpdb->prefix . 'ktpwp_contract';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
	return;
}

$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );

if ( ! in_array( 'payment_due_mode', $columns, true ) ) {
	$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `payment_due_mode` VARCHAR(20) NOT NULL DEFAULT 'contract' COMMENT 'contract=契約請求日, client=顧客締め支払日' AFTER `billing_day`", $table ) );
}

update_option( 'ktpwp_contract_payment_due_mode_migration_completed', '2026-06-15' );

if ( ! function_exists( 'ktpwp_add_payment_due_mode_to_contract' ) ) {
	/**
	 * マイグレーション runner 用
	 *
	 * @return bool
	 */
	function ktpwp_add_payment_due_mode_to_contract() {
		return true;
	}
}
} )();
