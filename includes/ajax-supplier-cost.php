<?php
/**
 * Ajax: コスト項目用 協力会社・職能リスト取得
 *
 * @package KTPWP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ktpwp_supplier_cost_ajax_authorize' ) ) {
	/**
	 * コスト項目用 AJAX の共通チェック（権限と nonce）。失敗したら JSON エラーで終了する。
	 *
	 * 画面側のスクリプトは ktp_ajax_nonce / ktpwp_ajax_nonce のどちらの nonce を
	 * 持っている場合もあるため、class-ktpwp-ajax.php と同じく両方を受け付ける。
	 *
	 * @return void
	 */
	function ktpwp_supplier_cost_ajax_authorize() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			wp_send_json_error( __( '権限がありません', 'kantanpro' ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( $nonce === '' || ( ! wp_verify_nonce( $nonce, 'ktp_ajax_nonce' ) && ! wp_verify_nonce( $nonce, 'ktpwp_ajax_nonce' ) ) ) {
			wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'kantanpro' ) );
		}
	}
}

add_action(
	'wp_ajax_ktpwp_get_suppliers_for_cost',
	function () {
		ktpwp_supplier_cost_ajax_authorize();

		global $wpdb;
		$table = $wpdb->prefix . 'ktp_supplier';

		// テーブルの存在確認
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
		if ( ! $table_exists ) {
			wp_send_json( array() );
		}

		$suppliers = $wpdb->get_results( "SELECT id, company_name FROM `{$table}` WHERE 1 ORDER BY company_name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table は $wpdb->prefix 由来の固定名。

		if ( $wpdb->last_error ) {
			wp_send_json_error( __( 'データベースエラーが発生しました。', 'kantanpro' ) );
		}

		wp_send_json( $suppliers );
	}
);

add_action(
	'wp_ajax_ktpwp_get_supplier_skills_for_cost',
	function () {
		ktpwp_supplier_cost_ajax_authorize();

		$supplier_id = isset( $_POST['supplier_id'] ) ? absint( wp_unslash( $_POST['supplier_id'] ) ) : 0;
		if ( ! $supplier_id ) {
			wp_send_json_error( __( 'supplier_idが不正です', 'kantanpro' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ktp_supplier_skills';

		if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {
			require_once __DIR__ . '/class-ktpwp-supplier-skills.php';
		}
		if ( class_exists( 'KTPWP_Supplier_Skills' ) ) {
			KTPWP_Supplier_Skills::get_instance()->ensure_table_exists();
		}

		// テーブルの存在確認
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
		if ( ! $table_exists ) {
			wp_send_json( array() );
		}

		$sql = $wpdb->prepare(
			"
			SELECT
				id,
				product_name,
				CASE
					WHEN CAST(unit_price AS CHAR) REGEXP '^[0-9]+\\.$' THEN
						CAST(unit_price AS UNSIGNED)
					WHEN CAST(unit_price AS CHAR) REGEXP '^[0-9]+\\.0+$' THEN
						CAST(unit_price AS UNSIGNED)
					ELSE
						TRIM(TRAILING '0' FROM TRIM(TRAILING '.' FROM CAST(unit_price AS DECIMAL(20,10))))
				END as unit_price,
				quantity,
				unit,
				tax_rate,
				frequency,
				updated_at,
				created_at
			FROM `{$table}`
			WHERE supplier_id = %d
			ORDER BY id ASC
			", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table は $wpdb->prefix 由来の固定名。
			$supplier_id
		);

		$skills = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- 上で prepare 済み。

		if ( $wpdb->last_error ) {
			wp_send_json_error( __( 'データベースエラーが発生しました。', 'kantanpro' ) );
		}

		wp_send_json( $skills );
	}
);

add_action(
	'wp_ajax_ktpwp_increment_skill_frequency',
	function () {
		ktpwp_supplier_cost_ajax_authorize();

		$skill_id = isset( $_POST['skill_id'] ) ? absint( wp_unslash( $_POST['skill_id'] ) ) : 0;
		if ( $skill_id <= 0 ) {
			wp_send_json_error( __( '職能 ID が不正です。', 'kantanpro' ) );
		}

		if ( function_exists( 'ktpwp_increment_record_frequency' ) ) {
			ktpwp_increment_record_frequency( 'supplier_skill', $skill_id );
		}

		wp_send_json_success();
	}
);
