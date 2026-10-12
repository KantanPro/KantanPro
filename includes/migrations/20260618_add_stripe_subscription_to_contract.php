<?php
/**
 * 定期契約に Stripe Subscription ID カラムを追加
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

if ( ! in_array( 'stripe_subscription_id', $columns, true ) ) {
	$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `stripe_subscription_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stripe Subscription ID' AFTER `status`", $table ) );
}

update_option( 'ktpwp_contract_stripe_subscription_migration_completed', '2026-06-18' );
} )();
