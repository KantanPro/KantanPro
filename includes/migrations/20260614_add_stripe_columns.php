<?php
/**
 * Stripe 連携用カラムを追加するマイグレーション
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ファイル内の変数をグローバルに出さないよう、処理全体を無名関数で包む（読み込んだ時点で実行される）。
( static function () {

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
			$wpdb->prefix . 'ktpwp_client' => array(
				'stripe_customer_id' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stripe Customer ID'",
			),
			$wpdb->prefix . 'ktpwp_order'  => array(
				'stripe_invoice_id'  => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stripe Invoice ID'",
				'stripe_invoice_url' => "VARCHAR(512) NULL DEFAULT NULL COMMENT 'Stripe hosted invoice URL'",
				'stripe_paid_at'     => "DATETIME NULL DEFAULT NULL COMMENT 'Stripe入金日時'",
			),
		);

		foreach ( $tables as $table => $columns ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				continue;
			}

			$existing = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), 0 );

			foreach ( $columns as $col => $def ) {
				if ( in_array( $col, $existing, true ) ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ktpwp_migration_add_column( $table, $col, $def );
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
			$wpdb->prefix . 'ktpwp_client' => array( 'stripe_customer_id' ),
			$wpdb->prefix . 'ktpwp_order'  => array( 'stripe_invoice_id', 'stripe_invoice_url', 'stripe_paid_at' ),
		);

		foreach ( $drops as $table => $columns ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				continue;
			}
			$existing = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), 0 );
			foreach ( $columns as $col ) {
				if ( ! in_array( $col, $existing, true ) ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i DROP COLUMN %i", $table, $col ) );
			}
		}

		return true;
	}
}
} )();
