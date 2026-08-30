<?php
/**
 * 案件→定期契約変換 AJAX
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
function ktpwp_order_contract_ajax_can_manage() {
	return current_user_can( 'edit_posts' ) || current_user_can( 'ktpwp_access' );
}

/**
 * ドラフト取得
 */
function ktp_get_order_contract_draft_ajax() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ktp_order_contract_nonce' ) ) {
		wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'kantanpro' ) );
	}

	if ( ! ktpwp_order_contract_ajax_can_manage() ) {
		wp_send_json_error( __( '権限がありません。', 'kantanpro' ) );
	}

	ktpwp_require_contracts_feature_or_ajax_error();

	$order_id = absint( $_POST['order_id'] ?? 0 );
	if ( $order_id <= 0 || ! class_exists( 'KTPWP_Order_Contract_Conversion' ) ) {
		wp_send_json_error( __( '案件が見つかりません。', 'kantanpro' ) );
	}

	$conversion = KTPWP_Order_Contract_Conversion::get_instance();
	$draft      = $conversion->get_draft( $order_id );

	if ( ! $draft ) {
		wp_send_json_error( __( '定期契約用サービスが特定できません。', 'kantanpro' ) );
	}

	$billing = class_exists( 'KTPWP_Contract_Billing' )
		? KTPWP_Contract_Billing::get_instance()
		: null;

	wp_send_json_success(
		array(
			'order_id'             => (int) $draft['order_id'],
			'client_id'            => (int) $draft['client_id'],
			'service_id'           => (int) $draft['service_id'],
			'service_name'         => (string) $draft['service_name'],
			'contract_name'        => (string) $draft['contract_name'],
			'amount'               => (float) $draft['amount'],
			'billing_cycle'        => (string) $draft['billing_cycle'],
			'billing_cycle_label'  => (string) $draft['billing_cycle_label'],
			'from_web_application' => ! empty( $draft['from_web_application'] ),
			'initial_fees'         => $draft['initial_fees'] ?? array(),
			'recurring_items'      => $draft['recurring_items'] ?? array(),
			'billing_period'       => $billing ? $billing->get_billing_period() : wp_date( 'Y-m' ),
			'default_billing_day'  => min( 28, max( 1, (int) wp_date( 'j' ) ) ),
			'default_memo'         => sprintf(
				/* translators: %d: order id */
				__( '案件 #%d から作成', 'kantanpro' ),
				$order_id
			),
			'default_status'       => 'paused',
		)
	);
}

/**
 * 変換実行
 */
function ktp_convert_order_to_contract_ajax() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ktp_order_contract_nonce' ) ) {
		wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'kantanpro' ) );
	}

	if ( ! ktpwp_order_contract_ajax_can_manage() ) {
		wp_send_json_error( __( '権限がありません。', 'kantanpro' ) );
	}

	ktpwp_require_contracts_feature_or_ajax_error();

	if ( ! class_exists( 'KTPWP_Order_Contract_Conversion' ) ) {
		wp_send_json_error( __( '定期契約機能が利用できません。', 'kantanpro' ) );
	}

	$order_id = absint( $_POST['order_id'] ?? 0 );
	$client_id = absint( $_POST['client_id'] ?? 0 );

	if ( $order_id <= 0 || $client_id <= 0 ) {
		wp_send_json_error( __( '案件が見つかりません。', 'kantanpro' ) );
	}

	$initial_fees_raw = isset( $_POST['initial_fees'] ) ? wp_unslash( $_POST['initial_fees'] ) : '[]';
	$initial_fees     = json_decode( $initial_fees_raw, true );
	if ( ! is_array( $initial_fees ) ) {
		$initial_fees = array();
	}

	$recurring_items_raw = isset( $_POST['recurring_items'] ) ? wp_unslash( $_POST['recurring_items'] ) : '[]';
	$recurring_items     = json_decode( $recurring_items_raw, true );
	if ( ! is_array( $recurring_items ) ) {
		$recurring_items = array();
	}

	$link_order = ! empty( $_POST['link_order_as_billing'] );
	$billing_period = sanitize_text_field( wp_unslash( $_POST['billing_period'] ?? '' ) );

	$contract_data = array(
		'client_id'          => $client_id,
		'service_id'         => absint( $_POST['service_id'] ?? 0 ),
		'contract_name'      => sanitize_text_field( wp_unslash( $_POST['contract_name'] ?? '' ) ),
		'amount'             => floatval( $_POST['amount'] ?? 0 ),
		'billing_cycle'      => sanitize_key( wp_unslash( $_POST['billing_cycle'] ?? 'monthly' ) ),
		'billing_day'        => absint( $_POST['billing_day'] ?? 1 ),
		'payment_due_mode'   => sanitize_key( wp_unslash( $_POST['payment_due_mode'] ?? 'client' ) ),
		'start_date'         => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ),
		'end_date'           => sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) ),
		'status'             => sanitize_key( wp_unslash( $_POST['status'] ?? 'active' ) ),
		'send_reminder_mail' => ! empty( $_POST['send_reminder_mail'] ) ? 1 : 0,
		'memo'               => sanitize_textarea_field( wp_unslash( $_POST['memo'] ?? '' ) ),
	);

	$conversion = KTPWP_Order_Contract_Conversion::get_instance();
	$result     = $conversion->convert(
		$order_id,
		$contract_data,
		$initial_fees,
		$recurring_items,
		$link_order,
		$billing_period !== '' ? $billing_period : null
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	$message = __( '定期契約を登録しました。', 'kantanpro' );
	if ( $link_order ) {
		$message .= ' ' . __( 'この案件を定期請求案件として紐付けました。', 'kantanpro' );
	}

	wp_send_json_success(
		array(
			'contract_id' => (int) $result,
			'message'     => $message,
		)
	);
}

add_action( 'wp_ajax_ktp_get_order_contract_draft', 'ktp_get_order_contract_draft_ajax' );
add_action( 'wp_ajax_ktp_convert_order_to_contract', 'ktp_convert_order_to_contract_ajax' );
