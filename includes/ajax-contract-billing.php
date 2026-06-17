<?php
/**
 * 定期請求（案件自動生成）AJAX
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 権限チェック
 *
 * @return bool
 */
function ktpwp_contract_billing_ajax_can_manage() {
	return current_user_can( 'edit_posts' ) || current_user_can( 'ktpwp_access' );
}

/**
 * フロントの KantanPro ページ URL（Ajax 中は referer を優先）
 *
 * @return string
 */
function ktpwp_contract_billing_get_frontend_base_url() {
	$referer = wp_get_referer();
	if ( is_string( $referer ) && $referer !== '' && false === strpos( $referer, 'admin-ajax.php' ) ) {
		return remove_query_arg(
			array(
				'tab_name',
				'order_id',
				'recurring_billing',
				'billing_period',
				'progress',
				'list_type',
				'page_start',
				'page_stage',
				'flg',
				'list_search',
			),
			$referer
		);
	}

	if ( class_exists( 'KTPWP_Main' ) ) {
		return KTPWP_Main::get_current_page_base_url();
	}

	return home_url( '/' );
}

/**
 * 単一契約の案件紐付け
 */
function ktp_generate_contract_order_ajax() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ktp_contract_billing_nonce' ) ) {
		wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
	}

	if ( ! ktpwp_contract_billing_ajax_can_manage() ) {
		wp_send_json_error( __( '権限がありません。', 'ktpwp' ) );
	}

	if ( ! class_exists( 'KTPWP_Contract_Billing' ) ) {
		wp_send_json_error( __( '定期請求機能が利用できません。', 'ktpwp' ) );
	}

	$contract_id = absint( $_POST['contract_id'] ?? 0 );
	$period      = sanitize_text_field( wp_unslash( $_POST['period'] ?? '' ) );

	$billing = KTPWP_Contract_Billing::get_instance();
	$result  = $billing->generate_order_for_contract( $contract_id, $period ? $period : null );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	$base_url = ktpwp_contract_billing_get_frontend_base_url();
	$order_url = add_query_arg(
		array(
			'tab_name' => 'order',
			'order_id' => (int) $result,
		),
		$base_url
	);

	wp_send_json_success(
		array(
			'order_id'  => (int) $result,
			'order_url' => $order_url,
			'message'   => __( '案件を紐付けしました。', 'ktpwp' ),
		)
	);
}

/**
 * 未紐付け分を一括案件紐付け
 */
function ktp_generate_all_contract_orders_ajax() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ktp_contract_billing_nonce' ) ) {
		wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
	}

	if ( ! ktpwp_contract_billing_ajax_can_manage() ) {
		wp_send_json_error( __( '権限がありません。', 'ktpwp' ) );
	}

	if ( ! class_exists( 'KTPWP_Contract_Billing' ) ) {
		wp_send_json_error( __( '定期請求機能が利用できません。', 'ktpwp' ) );
	}

	$period  = sanitize_text_field( wp_unslash( $_POST['period'] ?? '' ) );
	$billing = KTPWP_Contract_Billing::get_instance();
	$result  = $billing->generate_all_pending( $period ? $period : null );

	$message = sprintf(
		/* translators: %d: created count */
		__( '%d件の案件を紐付けしました。', 'ktpwp' ),
		(int) $result['created']
	);

	if ( ! empty( $result['errors'] ) ) {
		$message .= ' ' . implode( ' / ', array_map( 'sanitize_text_field', $result['errors'] ) );
	}

	wp_send_json_success(
		array(
			'created' => (int) $result['created'],
			'errors'  => $result['errors'],
			'message' => $message,
		)
	);
}

add_action( 'wp_ajax_ktp_generate_contract_order', 'ktp_generate_contract_order_ajax' );
add_action( 'wp_ajax_ktp_generate_all_contract_orders', 'ktp_generate_all_contract_orders_ajax' );

/**
 * 1契約の予告メール送信
 */
function ktp_send_contract_reminder_ajax() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ktp_contract_billing_nonce' ) ) {
		wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
	}

	if ( ! ktpwp_contract_billing_ajax_can_manage() ) {
		wp_send_json_error( __( '権限がありません。', 'ktpwp' ) );
	}

	if ( ! class_exists( 'KTPWP_Contract_Reminder_Mail' ) ) {
		wp_send_json_error( __( '請求予定メール機能が利用できません。', 'ktpwp' ) );
	}

	$contract_id = absint( $_POST['contract_id'] ?? 0 );
	$period      = sanitize_text_field( wp_unslash( $_POST['period'] ?? '' ) );
	if ( $period === '' && class_exists( 'KTPWP_Contract_Billing' ) ) {
		$period = KTPWP_Contract_Billing::get_instance()->get_billing_period();
	}

	$reminder = KTPWP_Contract_Reminder_Mail::get_instance();
	$result   = $reminder->send_reminder_for_contract( $contract_id, $period );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success(
		array(
			'message' => __( '請求予定メールを送信しました。', 'ktpwp' ),
		)
	);
}

/**
 * 未送信の予告メールを一括送信
 */
function ktp_send_pending_contract_reminders_ajax() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ktp_contract_billing_nonce' ) ) {
		wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
	}

	if ( ! ktpwp_contract_billing_ajax_can_manage() ) {
		wp_send_json_error( __( '権限がありません。', 'ktpwp' ) );
	}

	if ( ! class_exists( 'KTPWP_Contract_Reminder_Mail' ) ) {
		wp_send_json_error( __( '請求予定メール機能が利用できません。', 'ktpwp' ) );
	}

	$period   = sanitize_text_field( wp_unslash( $_POST['period'] ?? '' ) );
	if ( $period === '' && class_exists( 'KTPWP_Contract_Billing' ) ) {
		$period = KTPWP_Contract_Billing::get_instance()->get_billing_period();
	}
	$reminder = KTPWP_Contract_Reminder_Mail::get_instance();
	$result   = $reminder->send_pending_reminders( $period );

	$message = sprintf(
		/* translators: %d: sent count */
		__( '%d件の請求予定メールを送信しました。', 'ktpwp' ),
		(int) $result['sent']
	);

	if ( ! empty( $result['errors'] ) ) {
		$message .= ' ' . implode( ' / ', array_map( 'sanitize_text_field', $result['errors'] ) );
	}

	wp_send_json_success(
		array(
			'sent'    => (int) $result['sent'],
			'errors'  => $result['errors'],
			'message' => $message,
		)
	);
}

add_action( 'wp_ajax_ktp_send_contract_reminder', 'ktp_send_contract_reminder_ajax' );
add_action( 'wp_ajax_ktp_send_pending_contract_reminders', 'ktp_send_pending_contract_reminders_ajax' );
