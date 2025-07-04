<?php
/**
 * KTPWP Contact Form 7連携クラス
 *
 * @package KTPWP
 * @since 0.1.0
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Contact Form 7連携クラス
 */
class KTPWP_Contact_Form {

    /**
     * シングルトンインスタンス
     *
     * @var KTPWP_Contact_Form|null
     */
    private static $instance = null;

    /**
     * フィールドマッピング設定
     *
     * @var array
     */
    private $field_mapping = array();

    /**
     * デフォルト値設定
     *
     * @var array
     */
    private $default_values = array();

    /**
     * シングルトンインスタンス取得
     *
     * @return KTPWP_Contact_Form
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        // デバッグログ: コンストラクタ呼び出し
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP CF7: Contact Form constructor called' );
        }

        $this->init_config();

        // すぐにフックを初期化してみる
        $this->init_hooks();

        // Contact Form 7の読み込みを待つ
        add_action( 'plugins_loaded', array( $this, 'init_hooks_after_plugins_loaded' ) );
        add_action( 'init', array( $this, 'init_hooks_after_init' ) );
    }

    /**
     * 設定初期化
     */
    private function init_config() {
        // フィールドマッピング設定
        $this->field_mapping = array(
            'company_name' => array( 'your_company_name', 'company-name' ),
            'name' => array( 'your-name' ),
            'email' => array( 'your-email' ),
            'subject' => array( 'your-subject' ),
            'message' => array( 'your-message' ),
            'category' => array( 'select-996' ),
        );

        // デフォルト値設定
        $this->default_values = array(
            'client_status' => '対象',
            'project_name' => __( 'お問い合わせの件', 'ktpwp' ),
            'progress' => 1, // "受付中"
            'user_name' => '',
        );
    }

    /**
     * フック初期化
     */
    private function init_hooks() {
        error_log( 'KTPWP DEBUG: init_hooks called' );
        // Contact Form 7が有効な場合のみフックを追加
        if ( class_exists( 'WPCF7_ContactForm' ) ) {
            add_action( 'wpcf7_mail_sent', array( $this, 'capture_contact_form_data' ) );
            error_log( 'KTPWP DEBUG: wpcf7_mail_sent hook registered' );
        } else {
            error_log( 'KTPWP DEBUG: WPCF7_ContactForm class not found' );
        }
    }

    /**
     * プラグイン読み込み後のフック初期化
     */
    public function init_hooks_after_plugins_loaded() {
        // デバッグログ: plugins_loaded後の処理
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP CF7: init_hooks_after_plugins_loaded called' );
        }

        $this->init_hooks();
    }

    /**
     * init後のフック初期化
     */
    public function init_hooks_after_init() {
        // デバッグログ: init後の処理
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP CF7: init_hooks_after_init called' );
        }

        $this->init_hooks();
    }

    /**
     * Contact Form 7送信データをキャプチャ
     *
     * @param WPCF7_ContactForm $contact_form Contact Form 7のフォームオブジェクト
     */
    public function capture_contact_form_data( $contact_form ) {
        error_log( 'KTPWP DEBUG: capture_contact_form_data called' );
        // デバッグログ: メソッド呼び出し確認
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP CF7: capture_contact_form_data method called' );
        }

        $submission = WPCF7_Submission::get_instance();

        if ( ! $submission ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP CF7: No submission instance found' );
            }
            return;
        }

        $posted_data = $submission->get_posted_data();

        if ( empty( $posted_data ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP CF7: Posted data is empty' );
            }
            return;
        }

        // デバッグログ: 受信データを記録
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP CF7 Posted Data: ' . print_r( $posted_data, true ) );
        }

        // データを処理してデータベースに保存
        $client_data = $this->prepare_client_data( $posted_data );
        $client_id = $this->save_client_data( $client_data );

        if ( $client_id ) {
            // 受注データも作成
            $order_data = $this->prepare_order_data( $posted_data, $client_id );

            // デバッグログ: 受注データを記録
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP CF7 Prepared Order Data: ' . print_r( $order_data, true ) );
            }

            $this->save_order_data( $order_data );

            // クッキー設定
            $this->set_client_cookie( $client_id );
        }
    }

    /**
     * 顧客データの準備
     *
     * @param array $posted_data 送信されたデータ
     * @return array 準備された顧客データ
     */
    private function prepare_client_data( $posted_data ) {
        // フィールドマッピングに基づいてデータを取得
        $data = array();

        foreach ( $this->field_mapping as $key => $field_names ) {
            $value = $this->get_field_value( $posted_data, $field_names );

            switch ( $key ) {
                case 'company_name':
                case 'name':
                case 'subject':
                case 'category':
                    $data[ $key ] = sanitize_text_field( $value );
                    break;

                case 'email':
                    $data[ $key ] = sanitize_email( $value );
                    break;

                case 'message':
                    $data[ $key ] = sanitize_textarea_field( $value );
                    break;
            }
        }

        // メモの作成
        $memo = $this->create_memo( $data['subject'] ?? '', $data['message'] ?? '' );

        return array(
            'company_name' => $data['company_name'] ?? '',
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'memo' => $memo,
            'time' => current_time( 'mysql' ),
            'client_status' => $this->default_values['client_status'],
        );
    }

    /**
     * 受注データの準備
     *
     * @param array $posted_data 送信されたデータ
     * @param int   $client_id 顧客ID
     * @return array 準備された受注データ
     */
    private function prepare_order_data( $posted_data, $client_id ) {
        $customer_name = $this->get_field_value( $posted_data, $this->field_mapping['name'] );
        $company_name = $this->get_field_value( $posted_data, $this->field_mapping['company_name'] );
        $subject      = $this->get_field_value( $posted_data, $this->field_mapping['subject'] );

        // デバッグログ: フィールドマッピングの結果を記録
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP CF7 Field Mapping Debug:' );
            error_log( '  - customer_name (your-name): ' . $customer_name );
            error_log( '  - company_name (your_company_name): ' . $company_name );
            error_log( '  - subject (your-subject): ' . $subject );
            error_log( '  - field_mapping[name]: ' . print_r( $this->field_mapping['name'], true ) );
            error_log( '  - field_mapping[company_name]: ' . print_r( $this->field_mapping['company_name'], true ) );
            error_log( '  - field_mapping[subject]: ' . print_r( $this->field_mapping['subject'], true ) );
        }

        return array(
            'client_id' => $client_id,
            'customer_name' => sanitize_text_field( $company_name ),
            'company_name' => sanitize_text_field( $company_name ),
            'user_name' => sanitize_text_field( $customer_name ),
            'project_name' => ! empty( $subject ) ? sanitize_text_field( $subject ) : $this->default_values['project_name'],
            'progress' => $this->default_values['progress'],
            'time' => time(),
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        );
    }

    /**
     * フィールド値の取得
     *
     * @param array $posted_data 送信されたデータ
     * @param array $field_names フィールド名配列
     * @return string
     */
    private function get_field_value( $posted_data, $field_names ) {
        foreach ( $field_names as $field_name ) {
            if ( isset( $posted_data[ $field_name ] ) ) {
                return $posted_data[ $field_name ];
            }
        }
        return '';
    }

    /**
     * メモの作成
     *
     * @param string $subject 件名
     * @param string $message メッセージ
     * @return string
     */
    private function create_memo( $subject, $message ) {
        $memo = '';

        if ( ! empty( $subject ) ) {
            $memo .= __( '件名:', 'ktpwp' ) . ' ' . $subject;
        }

        if ( ! empty( $message ) ) {
            if ( ! empty( $memo ) ) {
                $memo .= "\n";
            }
            $memo .= __( 'メッセージ本文:', 'ktpwp' ) . ' ' . $message;
        }

        return $memo;
    }

    /**
     * 顧客データの保存
     *
     * @param array $client_data 顧客データ
     * @return int|false 挿入された顧客ID、失敗時はfalse
     */
    private function save_client_data( $client_data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ktp_client';

        $format = array(
            '%s', // company_name
            '%s', // name
            '%s', // email
            '%s', // memo
            '%s', // time
            '%s', // client_status
        );

        $result = $wpdb->insert( $table_name, $client_data, $format );

        if ( $result === false ) {
            $this->log_error(
                'Failed to insert client data',
                array(
					'query' => $wpdb->last_query,
					'error' => $wpdb->last_error,
					'data' => $client_data,
                )
            );
            return false;
        }

        $client_id = $wpdb->insert_id;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Contact Form: Client data saved with ID ' . $client_id );
        }

        return $client_id;
    }

    /**
     * 受注データの保存
     *
     * @param array $order_data 受注データ
     * @return int|false 挿入された受注ID、失敗時はfalse
     */
    private function save_order_data( $order_data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ktp_order';

        // 新しいIDを取得（データが完全に0の場合は1から開始）
        $new_id_query = "SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM {$table_name}";
        $new_id_result = $wpdb->get_row( $new_id_query );
        $new_id = $new_id_result && isset( $new_id_result->new_id ) ? intval( $new_id_result->new_id ) : 1;

        // IDを明示的に設定
        $order_data['id'] = $new_id;

        // wpdb->insert() はキーによるマッピングを使用するため、
        // formatの順序はorder_dataのキーの順序と一致させる
        $format = array(
            '%d', // id
            '%d', // client_id
            '%s', // customer_name
            '%s', // company_name
            '%s', // user_name
            '%s', // project_name
            '%d', // progress
            '%d', // time
            '%s', // created_at
            '%s', // updated_at
        );

        $result = $wpdb->insert( $table_name, $order_data, $format );

        if ( $result === false ) {
            $this->log_error(
                'Failed to insert order data',
                array(
					'query' => $wpdb->last_query,
					'error' => $wpdb->last_error,
					'data' => $order_data,
                )
            );
            return false;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Contact Form: Order data saved with ID ' . $new_id );
        }

        return $new_id;
    }

    /**
     * クライアントクッキーの設定
     *
     * @param int $client_id 顧客ID
     */
    private function set_client_cookie( $client_id ) {
        $cookie_name = 'ktp_client_id';

        if ( ! headers_sent() ) {
            setcookie( $cookie_name, $client_id, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Contact Form: Client cookie set for ID ' . $client_id );
            }
        } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Contact Form: Failed to set cookie - headers already sent' );
        }
    }

    /**
     * フィールドマッピングの更新
     *
     * @param array $mapping 新しいマッピング設定
     */
    public function update_field_mapping( $mapping ) {
        $this->field_mapping = array_merge( $this->field_mapping, $mapping );
    }

    /**
     * デフォルト値の更新
     *
     * @param array $defaults 新しいデフォルト値
     */
    public function update_default_values( $defaults ) {
        $this->default_values = array_merge( $this->default_values, $defaults );
    }

    /**
     * フィールドマッピング取得
     *
     * @return array
     */
    public function get_field_mapping() {
        return $this->field_mapping;
    }

    /**
     * デフォルト値取得
     *
     * @return array
     */
    public function get_default_values() {
        return $this->default_values;
    }

    /**
     * Contact Form 7の有効性確認
     *
     * @return bool
     */
    public function is_contact_form_7_active() {
        return class_exists( 'WPCF7_ContactForm' );
    }

    /**
     * エラーログ記録
     *
     * @param string $message エラーメッセージ
     * @param array  $context 追加コンテキスト
     */
    private function log_error( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = 'KTPWP Contact Form Error: ' . $message;

            if ( ! empty( $context ) ) {
                $log_message .= ' | Context: ' . wp_json_encode( $context );
            }

            error_log( $log_message );
        }
    }

    /**
     * データベーステーブルの存在確認
     *
     * @param string $table_name テーブル名
     * @return bool
     */
    private function table_exists( $table_name ) {
        global $wpdb;

        $query = $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_name
        );

        return $wpdb->get_var( $query ) === $table_name;
    }

    /**
     * 必要なテーブルの存在確認
     *
     * @return bool
     */
    public function check_required_tables() {
        global $wpdb;

        $required_tables = array(
            $wpdb->prefix . 'ktp_client',
            $wpdb->prefix . 'ktp_order',
        );

        foreach ( $required_tables as $table ) {
            if ( ! $this->table_exists( $table ) ) {
                $this->log_error( 'Required table not found: ' . $table );
                return false;
            }
        }

        return true;
    }

    /**
     * Contact Form 7送信データをキャプチャ（before_send_mail用）
     *
     * @param WPCF7_ContactForm $contact_form Contact Form 7のフォームオブジェクト
     */
    public function capture_contact_form_data_before( $contact_form ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP CF7: capture_contact_form_data_before method called' );
        }

        // メイン処理を呼び出し
        $this->capture_contact_form_data( $contact_form );
    }

    /**
     * Contact Form 7送信データをキャプチャ（submit用）
     *
     * @param WPCF7_ContactForm $contact_form Contact Form 7のフォームオブジェクト
     * @param array             $result 送信結果
     */
    public function capture_contact_form_data_submit( $contact_form, $result ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP CF7: capture_contact_form_data_submit method called' );
            error_log( 'KTPWP CF7 Submit Result: ' . print_r( $result, true ) );
        }

        // 送信が成功した場合のみ処理
        if ( isset( $result['status'] ) && $result['status'] === 'mail_sent' ) {
            $this->capture_contact_form_data( $contact_form );
        }
    }

    /**
     * Contact Form 7のposted_dataフィルターをキャプチャ
     *
     * @param array $posted_data 送信データ
     * @return array 変更されていないデータを返す
     */
    public function capture_posted_data( $posted_data ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP CF7: capture_posted_data filter called' );
            error_log( 'KTPWP CF7 Posted Data Filter: ' . print_r( $posted_data, true ) );
        }

        // フィルターなので、データを変更せずに処理
        if ( ! empty( $posted_data ) ) {
            // データを処理してデータベースに保存
            $client_data = $this->prepare_client_data( $posted_data );
            $client_id = $this->save_client_data( $client_data );

            if ( $client_id ) {
                // 受注データも作成
                $order_data = $this->prepare_order_data( $posted_data, $client_id );

                // デバッグログ: 受注データを記録
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP CF7 Prepared Order Data (from filter): ' . print_r( $order_data, true ) );
                }

                $this->save_order_data( $order_data );

                // クッキー設定
                $this->set_client_cookie( $client_id );
            }
        }

        // フィルターなので元のデータを返す
        return $posted_data;
    }
}
