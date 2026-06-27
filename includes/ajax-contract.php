<?php
/**
 * 定期契約 AJAX 処理
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
function ktpwp_contract_ajax_can_manage() {
	return current_user_can( 'edit_posts' ) || current_user_can( 'ktpwp_access' );
}

/**
 * 契約保存
 */
function ktp_save_contract_ajax() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ktp_contract_nonce' ) ) {
		wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
	}

	if ( ! ktpwp_contract_ajax_can_manage() ) {
		wp_send_json_error( __( '権限がありません。', 'ktpwp' ) );
	}

	ktpwp_require_contracts_feature_or_ajax_error();

	if ( ! class_exists( 'KTPWP_Contract_DB' ) ) {
		wp_send_json_error( __( '定期契約機能が利用できません。', 'ktpwp' ) );
	}

	$db = KTPWP_Contract_DB::get_instance();

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

	$data = array(
		'id'                 => absint( $_POST['contract_id'] ?? 0 ),
		'client_id'          => absint( $_POST['client_id'] ?? 0 ),
		'service_id'         => absint( $_POST['service_id'] ?? 0 ),
		'contract_name'      => sanitize_text_field( wp_unslash( $_POST['contract_name'] ?? '' ) ),
		'amount'             => floatval( $_POST['amount'] ?? 0 ),
		'billing_cycle'      => sanitize_key( wp_unslash( $_POST['billing_cycle'] ?? 'monthly' ) ),
		'billing_day'        => absint( $_POST['billing_day'] ?? 1 ),
		'payment_due_mode'   => sanitize_key( wp_unslash( $_POST['payment_due_mode'] ?? 'contract' ) ),
		'start_date'         => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ),
		'end_date'           => sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) ),
		'status'             => sanitize_key( wp_unslash( $_POST['status'] ?? 'active' ) ),
		'send_reminder_mail' => ! empty( $_POST['send_reminder_mail'] ) ? 1 : 0,
		'memo'               => sanitize_textarea_field( wp_unslash( $_POST['memo'] ?? '' ) ),
	);

	$result = $db->save_contract( $data, $initial_fees, $recurring_items );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success(
		array(
			'contract_id' => (int) $result,
			'message'       => __( '定期契約を保存しました。', 'ktpwp' ),
		)
	);
}

/**
 * 契約取得（編集用）
 */
function ktp_get_contract_ajax() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ktp_contract_nonce' ) ) {
		wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
	}

	if ( ! ktpwp_contract_ajax_can_manage() ) {
		wp_send_json_error( __( '権限がありません。', 'ktpwp' ) );
	}

	ktpwp_require_contracts_feature_or_ajax_error();

	$contract_id = absint( $_POST['contract_id'] ?? 0 );
	$client_id   = absint( $_POST['client_id'] ?? 0 );

	if ( $contract_id <= 0 || $client_id <= 0 || ! class_exists( 'KTPWP_Contract_DB' ) ) {
		wp_send_json_error( __( '契約が見つかりません。', 'ktpwp' ) );
	}

	$db       = KTPWP_Contract_DB::get_instance();
	$payload  = $db->get_contract_payload( $contract_id );

	if ( ! $payload || (int) $payload['client_id'] !== $client_id ) {
		wp_send_json_error( __( '契約が見つかりません。', 'ktpwp' ) );
	}

	if ( class_exists( 'KTPWP_Stripe_Subscription' ) ) {
		$stripe_status = KTPWP_Stripe_Subscription::get_instance()->get_subscription_status_for_contract( $contract_id );
		if ( is_array( $stripe_status ) ) {
			$payload['stripe_subscription'] = $stripe_status;
		}
	}

	wp_send_json_success( $payload );
}

/**
 * 契約削除
 */
function ktp_delete_contract_ajax() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ktp_contract_nonce' ) ) {
		wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
	}

	if ( ! ktpwp_contract_ajax_can_manage() ) {
		wp_send_json_error( __( '権限がありません。', 'ktpwp' ) );
	}

	ktpwp_require_contracts_feature_or_ajax_error();

	$contract_id = absint( $_POST['contract_id'] ?? 0 );
	$client_id   = absint( $_POST['client_id'] ?? 0 );

	if ( $contract_id <= 0 || $client_id <= 0 || ! class_exists( 'KTPWP_Contract_DB' ) ) {
		wp_send_json_error( __( '契約が見つかりません。', 'ktpwp' ) );
	}

	$db     = KTPWP_Contract_DB::get_instance();
	$result = $db->delete_contract( $contract_id, $client_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( array( 'message' => __( '定期契約を削除しました。', 'ktpwp' ) ) );
}

/**
 * カード登録 Setup Checkout URL 発行
 */
function ktp_create_contract_setup_checkout_ajax() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ktp_contract_nonce' ) ) {
		wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
	}

	if ( ! ktpwp_contract_ajax_can_manage() ) {
		wp_send_json_error( __( '権限がありません。', 'ktpwp' ) );
	}

	ktpwp_require_contracts_feature_or_ajax_error();

	$contract_id = absint( $_POST['contract_id'] ?? 0 );
	$client_id   = absint( $_POST['client_id'] ?? 0 );

	if ( $contract_id <= 0 || $client_id <= 0 || ! class_exists( 'KTPWP_Contract_DB' ) || ! class_exists( 'KTPWP_Stripe_Subscription' ) ) {
		wp_send_json_error( __( '契約が見つかりません。', 'ktpwp' ) );
	}

	$db       = KTPWP_Contract_DB::get_instance();
	$contract = $db->get_contract_by_id( $contract_id );

	if ( ! $contract || (int) $contract->client_id !== $client_id ) {
		wp_send_json_error( __( '契約が見つかりません。', 'ktpwp' ) );
	}

	$result = KTPWP_Stripe_Subscription::get_instance()->issue_setup_checkout_for_contract( $contract_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success(
		array(
			'url'     => (string) ( $result['url'] ?? '' ),
			'message' => __( 'カード登録リンクを発行しました。', 'ktpwp' ),
		)
	);
}

add_action( 'wp_ajax_ktp_save_contract', 'ktp_save_contract_ajax' );
add_action( 'wp_ajax_ktp_get_contract', 'ktp_get_contract_ajax' );
add_action( 'wp_ajax_ktp_delete_contract', 'ktp_delete_contract_ajax' );
add_action( 'wp_ajax_ktp_create_contract_setup_checkout', 'ktp_create_contract_setup_checkout_ajax' );
