<?php
/**
 * 定期契約に Stripe Subscription ID カラムを追加
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'ktp_contract';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
	return;
}

$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );

if ( ! in_array( 'stripe_subscription_id', $columns, true ) ) {
	$wpdb->query(
		"ALTER TABLE `{$table}` ADD COLUMN `stripe_subscription_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stripe Subscription ID' AFTER `status`"
	);
}

update_option( 'ktp_contract_stripe_subscription_migration_completed', '2026-06-18' );
