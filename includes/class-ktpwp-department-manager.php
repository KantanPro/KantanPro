<?php
/**
 * 部署管理クラス
 *
 * 顧客の部署情報を管理するクラス
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KTPWP_Department_Managerクラス
 */
class KTPWP_Department_Manager {

    /**
     * 部署テーブル名を取得
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ktp_department';
    }

    /**
     * 顧客の部署一覧を取得
     *
     * @param int $client_id 顧客ID
     * @return array 部署一覧
     */
    public static function get_departments_by_client( $client_id ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $departments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE client_id = %d ORDER BY id ASC",
                $client_id
            )
        );

        return $departments ?: array();
    }

    /**
     * 部署を追加
     *
     * @param int    $client_id 顧客ID
     * @param string $department_name 部署名
     * @param string $contact_person 担当者名
     * @param string $email メールアドレス
     * @return int|false 挿入されたID、失敗時はfalse
     */
    public static function add_department( $client_id, $department_name, $contact_person, $email ) {
        global $wpdb;

        $table_name = self::get_table_name();

        // データのサニタイズ
        $client_id = intval( $client_id );
        $department_name = sanitize_text_field( $department_name );
        $contact_person = sanitize_text_field( $contact_person );
        $email = sanitize_email( $email );

        // バリデーション（部署名は空欄でも可）
        if ( empty( $client_id ) || empty( $contact_person ) || empty( $email ) ) {
            return false;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'client_id' => $client_id,
                'department_name' => $department_name,
                'contact_person' => $contact_person,
                'email' => $email,
                'is_selected' => 0, // 新規追加時は未選択状態
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( $result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 部署の追加に失敗しました。' . $wpdb->last_error );
            }
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * 部署を更新
     *
     * @param int    $department_id 部署ID
     * @param string $department_name 部署名
     * @param string $contact_person 担当者名
     * @param string $email メールアドレス
     * @return bool 成功時はtrue
     */
    public static function update_department( $department_id, $department_name, $contact_person, $email ) {
        global $wpdb;

        $table_name = self::get_table_name();

        // データのサニタイズ
        $department_id = intval( $department_id );
        $department_name = sanitize_text_field( $department_name );
        $contact_person = sanitize_text_field( $contact_person );
        $email = sanitize_email( $email );

        // バリデーション（部署名は空欄でも可）
        if ( empty( $department_id ) || empty( $contact_person ) || empty( $email ) ) {
            return false;
        }

        $result = $wpdb->update(
            $table_name,
            array(
                'department_name' => $department_name,
                'contact_person' => $contact_person,
                'email' => $email,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $department_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 部署の更新に失敗しました。' . $wpdb->last_error );
            }
            return false;
        }

        return true;
    }

    /**
     * 部署を削除
     *
     * @param int $department_id 部署ID
     * @return bool 成功時はtrue
     */
    public static function delete_department( $department_id ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $department_id = intval( $department_id );

        if ( empty( $department_id ) ) {
            return false;
        }

        $result = $wpdb->delete(
            $table_name,
            array( 'id' => $department_id ),
            array( '%d' )
        );

        if ( $result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 部署の削除に失敗しました。' . $wpdb->last_error );
            }
            return false;
        }

        return true;
    }

    /**
     * 部署情報を取得
     *
     * @param int $department_id 部署ID
     * @return object|null 部署情報
     */
    public static function get_department( $department_id ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $department_id = intval( $department_id );

        if ( empty( $department_id ) ) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $department_id
            )
        );
    }

    /**
     * 顧客のメイン部署（最初の部署）を取得
     *
     * @param int $client_id 顧客ID
     * @return object|null 部署情報
     */
    public static function get_main_department( $client_id ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $client_id = intval( $client_id );

        if ( empty( $client_id ) ) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE client_id = %d ORDER BY id ASC LIMIT 1",
                $client_id
            )
        );
    }

    /**
     * 顧客の全部署のメールアドレスを取得
     *
     * @param int $client_id 顧客ID
     * @return array メールアドレス一覧
     */
    public static function get_client_emails( $client_id ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $client_id = intval( $client_id );

        if ( empty( $client_id ) ) {
            return array();
        }

        $emails = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT email FROM {$table_name} WHERE client_id = %d ORDER BY id ASC",
                $client_id
            )
        );

        return $emails ?: array();
    }

    /**
     * 部署テーブルが存在するかチェック
     *
     * @return bool 存在する場合はtrue
     */
    public static function table_exists() {
        global $wpdb;

        $table_name = self::get_table_name();
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Department: table_exists check - table_name: {$table_name}, exists: " . ( $table_exists === $table_name ? 'true' : 'false' ) );
        }

        return $table_exists === $table_name;
    }

    /**
     * 選択された部署の情報を取得
     *
     * @param int $client_id 顧客ID
     * @param int $selected_department_id 選択された部署ID
     * @return object|null 部署情報
     */
    public static function get_selected_department( $client_id, $selected_department_id ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $client_id = intval( $client_id );
        $selected_department_id = intval( $selected_department_id );

        if ( empty( $client_id ) || empty( $selected_department_id ) ) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE client_id = %d AND id = %d",
                $client_id,
                $selected_department_id
            )
        );
    }

    /**
     * 顧客の選択された部署のメールアドレスを取得
     *
     * @param int $client_id 顧客ID
     * @param int $selected_department_id 選択された部署ID
     * @return string メールアドレス
     */
    public static function get_selected_department_email( $client_id, $selected_department_id ) {
        $department = self::get_selected_department( $client_id, $selected_department_id );

        if ( $department ) {
            return $department->email;
        }

        return '';
    }

    /**
     * 顧客の選択された部署を取得
     *
     * @param int $client_id 顧客ID
     * @return object|null 選択された部署情報
     */
    public static function get_selected_department_by_client( $client_id ) {
        global $wpdb;

        $table_name = self::get_table_name();
        $client_table = $wpdb->prefix . 'ktp_client';

        $client_id = intval( $client_id );

        if ( empty( $client_id ) ) {
            return null;
        }

        // 顧客テーブルから選択された部署IDを取得
        $selected_department_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT selected_department_id FROM {$client_table} WHERE id = %d",
                $client_id
            )
        );

        if ( empty( $selected_department_id ) ) {
            return null;
        }

        // 選択された部署IDで部署情報を取得
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE client_id = %d AND id = %d",
                $client_id,
                $selected_department_id
            )
        );
    }

    /**
     * 部署の選択状態を更新
     *
     * @param int  $department_id 部署ID
     * @param bool $is_selected 選択状態
     * @return bool 更新成功時true
     */
    public static function update_department_selection( $department_id, $is_selected ) {
        global $wpdb;

        $table_name = self::get_table_name();
        $client_table = $wpdb->prefix . 'ktp_client';

        $department_id = intval( $department_id );
        // 文字列の"false"も正しく処理する
        $is_selected = ( $is_selected === true || $is_selected === 'true' ) ? 1 : 0;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Department: update_department_selection called - department_id: {$department_id}, is_selected: {$is_selected}" );
        }

        if ( empty( $department_id ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Department: department_id is empty' );
            }
            return false;
        }

        // 部署の顧客IDを取得
        $department = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT client_id FROM {$table_name} WHERE id = %d",
                $department_id
            )
        );

        if ( ! $department ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Department: department not found for id: {$department_id}" );
            }
            return false;
        }

        $client_id = $department->client_id;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Department: found client_id: {$client_id} for department_id: {$department_id}" );
        }

        if ( $is_selected ) {
            // 選択された場合：顧客テーブルのselected_department_idを更新
            $result = $wpdb->update(
                $client_table,
                array( 'selected_department_id' => $department_id ),
                array( 'id' => $client_id ),
                array( '%d' ),
                array( '%d' )
            );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                if ( $result === false ) {
                    error_log( 'KTPWP Department: failed to update selected_department_id - SQL error: ' . $wpdb->last_error );
                } else {
                    error_log( "KTPWP Department: successfully updated selected_department_id to {$department_id}" );
                }
            }
        } else {
            // 選択解除の場合：顧客テーブルのselected_department_idをNULLに設定
            $result = $wpdb->update(
                $client_table,
                array( 'selected_department_id' => null ),
                array( 'id' => $client_id ),
                array( null ),
                array( '%d' )
            );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                if ( $result === false ) {
                    error_log( 'KTPWP Department: failed to clear selected_department_id - SQL error: ' . $wpdb->last_error );
                } else {
                    error_log( "KTPWP Department: successfully cleared selected_department_id for client_id: {$client_id}" );

                    // 更新後の状態を確認
                    $updated_selection = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT selected_department_id FROM {$client_table} WHERE id = %d",
                            $client_id
                        )
                    );
                    error_log( 'KTPWP Department: updated selected_department_id is now: ' . ( $updated_selection ?: 'NULL' ) );
                }
            }
        }

        return $result !== false;
    }

    /**
     * 顧客の選択された部署のメールアドレスを取得（新しい方式）
     *
     * @param int $client_id 顧客ID
     * @return string メールアドレス
     */
    public static function get_selected_department_email_new( $client_id ) {
        $department = self::get_selected_department_by_client( $client_id );

        if ( $department ) {
            return $department->email;
        }

        return '';
    }

    /**
     * Webフォーム等で作成する部署名（{フォームの会社名}: {担当者名}）。
     *
     * @param string $company_name フォームの会社名。
     * @param string $contact_name 担当者名。
     * @return string
     */
    public static function build_inquiry_department_name( $company_name, $contact_name ) {
        $company_name = trim( sanitize_text_field( (string) $company_name ) );
        $contact_name = trim( sanitize_text_field( (string) $contact_name ) );

        if ( $company_name === '' ) {
            return '';
        }
        if ( $contact_name === '' ) {
            return mb_substr( $company_name, 0, 255 );
        }
        if ( mb_strtolower( $company_name ) === mb_strtolower( $contact_name ) ) {
            return mb_substr( $company_name, 0, 255 );
        }

        return mb_substr( $company_name . ': ' . $contact_name, 0, 255 );
    }

    /**
     * 受注ヘッダー・メール用の親会社名（顧客マスタの登録会社名を優先）。
     *
     * @param object|null $order               ktp_order 行。
     * @param object|null $client              ktp_client 行。
     * @param string      $display_customer_name 画面表示用の会社名。
     * @param object|null $selected_department 選択部署。
     * @return string
     */
    public static function resolve_parent_company_name_for_order( $order, $client, $display_customer_name, $selected_department = null ) {
        unset( $order, $selected_department );

        if ( $client && trim( (string) ( $client->company_name ?? '' ) ) !== '' ) {
            return trim( sanitize_text_field( (string) $client->company_name ) );
        }

        return trim( sanitize_text_field( (string) $display_customer_name ) );
    }

    /**
     * 受注の担当者名（フォームのお名前を優先）。
     *
     * @param object|null $order ktp_order 行。
     * @return string
     */
    public static function resolve_contact_name_for_order( $order ) {
        if ( ! $order ) {
            return '';
        }

        $user_name = trim( (string) ( $order->user_name ?? '' ) );
        if ( $user_name !== '' ) {
            return sanitize_text_field( $user_name );
        }

        $department = self::resolve_department_for_order( $order );
        if ( $department && trim( (string) ( $department->contact_person ?? '' ) ) !== '' ) {
            return sanitize_text_field( (string) $department->contact_person );
        }

        if ( ! empty( $order->client_id ) ) {
            global $wpdb;
            $client_table = $wpdb->prefix . 'ktp_client';
            $client_contact = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(NULLIF(representative_name, ''), name) FROM {$client_table} WHERE id = %d",
                    (int) $order->client_id
                )
            );
            if ( is_string( $client_contact ) && trim( $client_contact ) !== '' ) {
                return sanitize_text_field( trim( $client_contact ) );
            }
        }

        return '';
    }

    /**
     * メール宛先2行目用の部署表示名。「会社名: 担当者名」形式のとき会社名部分のみ返す。
     *
     * @param string $department_name 部署名。
     * @return string
     */
    public static function department_name_for_mail_addressee( $department_name ) {
        $department_name = trim( (string) $department_name );
        if ( $department_name === '' ) {
            return '';
        }

        $pos = mb_strpos( $department_name, ': ' );
        if ( $pos !== false ) {
            return trim( mb_substr( $department_name, 0, $pos ) );
        }

        return $department_name;
    }

    /**
     * 問い合わせ部署名の誤登録（例: 野中: 野中）か。
     *
     * @param object|null $department 部署行。
     * @return bool
     */
    public static function is_bogus_inquiry_department( $department ) {
        if ( ! $department ) {
            return false;
        }

        $department_name = trim( (string) ( $department->department_name ?? '' ) );
        $contact_person  = trim( (string) ( $department->contact_person ?? '' ) );

        if ( $department_name === '' || $contact_person === '' ) {
            return false;
        }
        if ( mb_strpos( $department_name, ': ' ) === false ) {
            return false;
        }

        $prefix = self::department_name_for_mail_addressee( $department_name );

        return mb_strtolower( $prefix ) === mb_strtolower( $contact_person );
    }

    /**
     * 受注に紐づく部署を解決する（受注の client_department_id を優先。顧客の選択部署は使わない）。
     *
     * @param object|null $order ktp_order 行。
     * @return object|null
     */
    public static function resolve_department_for_order( $order ) {
        if ( ! $order || empty( $order->client_id ) ) {
            return null;
        }

        $client_id = (int) $order->client_id;

        if ( isset( $order->client_department_id ) && (int) $order->client_department_id > 0 ) {
            $dept = self::get_selected_department( $client_id, (int) $order->client_department_id );
            if ( $dept && ! self::is_bogus_inquiry_department( $dept ) ) {
                return $dept;
            }
        }

        return null;
    }

    /**
     * 受注に紐づく部署メールアドレス（なければ空文字）。
     *
     * @param object|null $order ktp_order 行。
     * @return string
     */
    public static function get_department_email_for_order( $order ) {
        $dept = self::resolve_department_for_order( $order );
        if ( ! $dept || empty( $dept->email ) ) {
            return '';
        }
        $email = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) $dept->email ) );
        if ( $email === '' || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return '';
        }
        return sanitize_email( $email );
    }

    /**
     * 仕事リスト等の顧客表示用（親会社・部署・担当者）。
     *
     * @param object|null $order ktp_order 行。
     * @return array{parent: string, department: string, contact: string}
     */
    public static function work_list_client_parts_for_order( $order ) {
        $fallback_company = isset( $order->customer_name ) ? (string) $order->customer_name : '';
        $fallback_contact = isset( $order->user_name ) ? (string) $order->user_name : '';
        $client_id        = isset( $order->client_id ) ? (int) $order->client_id : 0;

        $parent     = trim( sanitize_text_field( $fallback_company ) );
        $department = '';
        $contact    = trim( sanitize_text_field( $fallback_contact ) );

        $selected = self::resolve_department_for_order( $order );
        $contact    = self::resolve_contact_name_for_order( $order );

        if ( $client_id > 0 ) {
            global $wpdb;
            $client_table = $wpdb->prefix . 'ktp_client';
            $client       = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT company_name, name, representative_name FROM {$client_table} WHERE id = %d",
                    $client_id
                )
            );
            if ( $client ) {
                $parent = self::resolve_parent_company_name_for_order( $order, $client, $parent, $selected );
            }
        }

        if ( $selected ) {
            $department = self::department_name_for_mail_addressee( $selected->department_name );
        }

        return array(
            'parent'     => $parent,
            'department' => $department,
            'contact'    => $contact,
        );
    }

    /**
     * 仕事リスト行の顧客ラベル（空白区切り）。
     *
     * @param object|null $order ktp_order 行。
     * @return string
     */
    public static function format_work_list_client_label_for_order( $order ) {
        $parts    = self::work_list_client_parts_for_order( $order );
        $segments = array();
        foreach ( array( 'parent', 'department', 'contact' ) as $key ) {
            if ( $parts[ $key ] !== '' ) {
                $segments[] = $parts[ $key ];
            }
        }
        return implode( ' ', $segments );
    }

    /**
     * 一括請求・宛名印刷の「部署 ご担当」行（例: 営業部 山田太郎）。
     *
     * @param object|null $department ktp_department 行。
     * @return string
     */
    public static function bulk_invoice_department_contact_line( $department ) {
        if ( ! $department ) {
            return '';
        }

        $dept_name = self::department_name_for_mail_addressee( (string) ( $department->department_name ?? '' ) );
        $contact   = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $department->contact_person ?? '' ) ) );

        if ( $dept_name === '' && $contact === '' ) {
            return '';
        }
        if ( $dept_name === '' ) {
            return $contact;
        }
        if ( $contact === '' ) {
            return $dept_name;
        }

        return $dept_name . ' ' . $contact;
    }
}
