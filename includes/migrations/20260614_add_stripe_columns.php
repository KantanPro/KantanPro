<?php
/**
 * Stripe 連携用カラムを追加するマイグレーション
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration: Stripe columns for client, order
 */
class KTPWP_Migration_20260614_Add_Stripe_Columns {

	/**
	 * Run migration.
	 *
	 * @return bool
	 */
	public static function up() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'ktp_client' => array(
				'stripe_customer_id' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stripe Customer ID'",
			),
			$wpdb->prefix . 'ktp_order'  => array(
				'stripe_invoice_id'  => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stripe Invoice ID'",
				'stripe_invoice_url' => "VARCHAR(512) NULL DEFAULT NULL COMMENT 'Stripe hosted invoice URL'",
				'stripe_paid_at'     => "DATETIME NULL DEFAULT NULL COMMENT 'Stripe入金日時'",
			),
		);

		foreach ( $tables as $table => $columns ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				continue;
			}

			$existing = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

			foreach ( $columns as $col => $def ) {
				if ( in_array( $col, $existing, true ) ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}" );
			}
		}

		return true;
	}

	/**
	 * Rollback migration.
	 *
	 * @return bool
	 */
	public static function down() {
		global $wpdb;

		$drops = array(
			$wpdb->prefix . 'ktp_client' => array( 'stripe_customer_id' ),
			$wpdb->prefix . 'ktp_order'  => array( 'stripe_invoice_id', 'stripe_invoice_url', 'stripe_paid_at' ),
		);

		foreach ( $drops as $table => $columns ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				continue;
			}
			$existing = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
			foreach ( $columns as $col ) {
				if ( ! in_array( $col, $existing, true ) ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `{$col}`" );
			}
		}

		return true;
	}
}
