<?php
/**
 * 請求項目の is_provisional・サービス定期項目の bill_on_first_invoice を追加
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$invoice_table = $wpdb->prefix . 'ktp_order_invoice_items';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $invoice_table ) ) === $invoice_table ) {
	$invoice_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$invoice_table}`" );
	if ( ! in_array( 'is_provisional', $invoice_columns, true ) ) {
		$wpdb->query(
			"ALTER TABLE `{$invoice_table}` ADD COLUMN `is_provisional` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=定期参考（今回請求しない）' AFTER `remarks`"
		);
	}
}

$service_recurring_table = $wpdb->prefix . 'ktp_service_recurring_item';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $service_recurring_table ) ) === $service_recurring_table ) {
	$recurring_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$service_recurring_table}`" );
	if ( ! in_array( 'bill_on_first_invoice', $recurring_columns, true ) ) {
		$wpdb->query(
			"ALTER TABLE `{$service_recurring_table}` ADD COLUMN `bill_on_first_invoice` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=WEB初回見積に含める' AFTER `tax_rate`"
		);
	}
}

update_option( 'ktp_invoice_billing_scope_migration_completed', '2026-06-20' );
