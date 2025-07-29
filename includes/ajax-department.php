<?php
/**
 * 部署管理AJAX処理
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 部署追加AJAX処理
 */
function ktp_add_department_ajax() {
    // セキュリティチェック
    if ( ! wp_verify_nonce( $_POST['nonce'], 'ktp_department_nonce' ) ) {
        wp_die( 'セキュリティチェックに失敗しました。' );
    }

    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
        wp_die( '権限がありません。' );
    }

    // 部署管理クラスが存在するかチェック
    if ( ! class_exists( 'KTPWP_Department_Manager' ) ) {
        wp_send_json_error( '部署管理クラスが見つかりません。' );
    }

    // 部署テーブルが存在するかチェック
    if ( ! KTPWP_Department_Manager::table_exists() ) {
        wp_send_json_error( '部署テーブルが存在しません。' );
    }

    // パラメータの取得とバリデーション
    $client_id = intval( $_POST['client_id'] );
    $department_name = sanitize_text_field( $_POST['department_name'] );
    $contact_person = sanitize_text_field( $_POST['contact_person'] );
    $email = sanitize_email( $_POST['email'] );

    if ( empty( $client_id ) || empty( $contact_person ) || empty( $email ) ) {
        wp_send_json_error( '担当者名とメールアドレスを入力してください。' );
    }

    // 部署を追加
    $result = KTPWP_Department_Manager::add_department( $client_id, $department_name, $contact_person, $email );

    if ( $result ) {
        wp_send_json_success( '部署を追加しました。' );
    } else {
        wp_send_json_error( '部署の追加に失敗しました。' );
    }
}

/**
 * 部署削除AJAX処理
 */
function ktp_delete_department_ajax() {
    // セキュリティチェック
    if ( ! wp_verify_nonce( $_POST['nonce'], 'ktp_department_nonce' ) ) {
        wp_die( 'セキュリティチェックに失敗しました。' );
    }

    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
        wp_die( '権限がありません。' );
    }

    // 部署管理クラスが存在するかチェック
    if ( ! class_exists( 'KTPWP_Department_Manager' ) ) {
        wp_send_json_error( '部署管理クラスが見つかりません。' );
    }

    // 部署テーブルが存在するかチェック
    if ( ! KTPWP_Department_Manager::table_exists() ) {
        wp_send_json_error( '部署テーブルが存在しません。' );
    }

    // パラメータの取得とバリデーション
    $department_id = intval( $_POST['department_id'] );

    if ( empty( $department_id ) ) {
        wp_send_json_error( '部署IDが指定されていません。' );
    }

    // 部署を削除
    $result = KTPWP_Department_Manager::delete_department( $department_id );

    if ( $result ) {
        wp_send_json_success( '部署を削除しました。' );
    } else {
        wp_send_json_error( '部署の削除に失敗しました。' );
    }
}

/**
 * 部署選択状態更新AJAX処理
 */
function ktp_update_department_selection_ajax() {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP AJAX: ktp_update_department_selection_ajax called' );
        error_log( 'KTPWP AJAX: POST data: ' . print_r( $_POST, true ) );
    }
    
    // セキュリティチェック
    if ( ! wp_verify_nonce( $_POST['nonce'], 'ktp_department_nonce' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP AJAX: Nonce verification failed' );
        }
        wp_die( 'セキュリティチェックに失敗しました。' );
    }

    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
        wp_die( '権限がありません。' );
    }

    // 部署管理クラスが存在するかチェック
    if ( ! class_exists( 'KTPWP_Department_Manager' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP AJAX: KTPWP_Department_Manager class not found' );
        }
        wp_send_json_error( '部署管理クラスが見つかりません。' );
    }

    // 部署テーブルが存在するかチェック
    if ( ! KTPWP_Department_Manager::table_exists() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP AJAX: Department table does not exist, attempting to create it' );
        }
        
        // テーブル作成を試行
        if ( function_exists( 'ktpwp_create_department_table' ) ) {
            $table_created = ktpwp_create_department_table();
            if ( ! $table_created ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP AJAX: Failed to create department table' );
                }
                wp_send_json_error( '部署テーブルの作成に失敗しました。' );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP AJAX: ktpwp_create_department_table function not found' );
            }
            wp_send_json_error( '部署テーブル作成関数が見つかりません。' );
        }
    }

    // パラメータの取得とバリデーション
    $department_id = intval( $_POST['department_id'] );
    $is_selected = isset( $_POST['is_selected'] ) ? (bool) $_POST['is_selected'] : false;

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "KTPWP AJAX: update_department_selection called - department_id: {$department_id}, is_selected: " . ( $is_selected ? 'true' : 'false' ) );
    }

    if ( empty( $department_id ) ) {
        wp_send_json_error( '部署IDが指定されていません。' );
    }

    // 部署選択状態を更新
    $result = KTPWP_Department_Manager::update_department_selection( $department_id, $is_selected );

    if ( $result ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP AJAX: update_department_selection successful' );
        }
        wp_send_json_success( '部署選択状態を更新しました。' );
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP AJAX: update_department_selection failed' );
        }
        wp_send_json_error( '部署選択状態の更新に失敗しました。' );
    }
}

// AJAXアクションを登録
add_action( 'wp_ajax_ktp_add_department', 'ktp_add_department_ajax' );
add_action( 'wp_ajax_ktp_delete_department', 'ktp_delete_department_ajax' );
add_action( 'wp_ajax_ktp_update_department_selection', 'ktp_update_department_selection_ajax' );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP AJAX: Department AJAX handlers registered' );
}
