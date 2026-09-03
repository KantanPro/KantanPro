<?php
/**
 * Settings class for KTPWP plugin
 *
 * Handles plugin settings including SMTP configuration,
 * admin interface, and security implementations.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 * @author Kantan Pro
 * @copyright 2024 Kantan Pro
 * @license GPL-2.0+
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings class for managing plugin settings
 *
 * @since 1.0.0
 */
class KTPWP_Settings {

    /**
     * Single instance of the class
     *
     * @var KTPWP_Settings
     */
    private static $instance = null;

    /**
     * Options group name
     *
     * @var string
     */
    private $options_group = 'ktp_settings';

    /**
     * Option name for SMTP settings
     *
     * @var string
     */
    private $option_name = 'ktp_smtp_settings';

    /**
     * Test mail message
     *
     * @var string
     */
    private $test_mail_message = '';

    /**
     * Test mail status
     *
     * @var string
     */
    private $test_mail_status = '';

    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return KTPWP_Settings
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get work list range setting
     *
     * @since 1.0.0
     * @return int Work list range setting (default: 20)
     */
    public static function get_work_list_range() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['work_list_range'] ) ? intval( $options['work_list_range'] ) : 20;
    }

    /**
     * Get delivery warning days setting
     *
     * @since 1.0.0
     * @return int Delivery warning days setting (default: 3)
     */
    public static function get_delivery_warning_days() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['delivery_warning_days'] ) ? intval( $options['delivery_warning_days'] ) : 3;
    }

    /**
     * Get qualified invoice number setting
     *
     * @since 1.0.0
     * @return string Qualified invoice number setting
     */
    public static function get_qualified_invoice_number() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['qualified_invoice_number'] ) ? $options['qualified_invoice_number'] : '';
    }

    /**
     * Get company info setting
     *
     * @since 1.0.0
     * @return string Company info setting
     */
    public static function get_company_info() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['company_info'] ) ? $options['company_info'] : '';
    }

    /**
     * 業務画面ショートコードの検索パターン。
     *
     * @return string[]
     */
    public static function get_ktpwp_shortcode_search_patterns() {
        return array( '[ktpwp_all_tab]', '[kantanpro_ex]', '[kantanAllTab]' );
    }

    /**
     * 固定ページに業務画面ショートコードが設置されているか。
     *
     * @param int $page_id 固定ページ ID。
     * @return bool
     */
    public static function page_has_ktpwp_shortcode( $page_id ) {
        $page_id = (int) $page_id;
        if ( $page_id <= 0 ) {
            return false;
        }

        $post = get_post( $page_id );
        if ( ! $post || $post->post_type !== 'page' || $post->post_status !== 'publish' ) {
            return false;
        }

        $content = (string) $post->post_content;
        foreach ( self::get_ktpwp_shortcode_search_patterns() as $pattern ) {
            if ( strpos( $content, $pattern ) !== false ) {
                return true;
            }
        }

        return has_shortcode( $content, 'ktpwp_all_tab' ) || has_shortcode( $content, 'kantanpro_ex' );
    }

    /**
     * 業務画面ショートコード設置ページを自動検出する。
     *
     * @return int
     */
    public static function detect_ktpwp_business_page_id() {
        static $cached_page_id = null;
        if ( $cached_page_id !== null ) {
            return (int) $cached_page_id;
        }

        global $wpdb;
        $likes = array();
        foreach ( self::get_ktpwp_shortcode_search_patterns() as $pattern ) {
            $likes[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( $pattern ) . '%' );
        }

        $like_sql = implode( ' OR ', $likes );
        $cached_page_id = (int) $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'page'
            AND post_status = 'publish'
            AND ( {$like_sql} )
            ORDER BY ID ASC
            LIMIT 1"
        );

        return (int) $cached_page_id;
    }

    /**
     * 業務画面ページ ID を取得する（一般設定 → 自動検出の順）。
     *
     * @return int
     */
    public static function get_ktpwp_business_page_id() {
        $options    = get_option( 'ktp_general_settings', array() );
        $configured = isset( $options['ktp_page_id'] ) ? (int) $options['ktp_page_id'] : 0;

        if ( $configured > 0 && self::page_has_ktpwp_shortcode( $configured ) ) {
            return $configured;
        }

        return self::detect_ktpwp_business_page_id();
    }

    /**
     * 業務画面ページ URL を取得する。
     *
     * @return string
     */
    public static function get_ktpwp_business_page_url() {
        static $cached_url = null;
        if ( $cached_url !== null ) {
            return $cached_url;
        }

        $page_id = self::get_ktpwp_business_page_id();
        if ( $page_id <= 0 ) {
            $cached_url = '';
            return $cached_url;
        }

        $permalink = get_permalink( $page_id );
        if ( ! $permalink ) {
            $cached_url = '';
            return $cached_url;
        }

        $cached_url = (string) add_query_arg( array( 'page_id' => $page_id ), $permalink );
        return $cached_url;
    }

    /**
     * 業務画面ショートコード設置ページ一覧を取得する。
     *
     * @return array<int, WP_Post>
     */
    public static function get_ktpwp_shortcode_pages() {
        global $wpdb;

        $likes = array();
        foreach ( self::get_ktpwp_shortcode_search_patterns() as $pattern ) {
            $likes[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( $pattern ) . '%' );
        }

        $like_sql = implode( ' OR ', $likes );
        $ids      = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'page'
            AND post_status = 'publish'
            AND ( {$like_sql} )
            ORDER BY post_title ASC"
        );

        $pages = array();
        foreach ( $ids as $id ) {
            $post = get_post( (int) $id );
            if ( $post instanceof WP_Post ) {
                $pages[] = $post;
            }
        }

        return $pages;
    }

    /**
     * 固定ページ幅の標準値（未設定時のデフォルト）。
     *
     * @return string
     */
    public static function get_default_page_content_width() {
        return '1400px';
    }

    /**
     * 固定ページのコンテンツ幅プリセット一覧。
     *
     * @return array<string, string>
     */
    public static function get_page_content_width_presets() {
        return array(
            ''       => __( 'デフォルト（テーマの幅）', 'kantanpro' ),
            '960px'  => __( '狭い（960px）', 'kantanpro' ),
            '1200px' => __( 'やや狭い（1200px）', 'kantanpro' ),
            '1400px' => __( '標準（1400px）', 'kantanpro' ),
            '1600px' => __( 'より広い（1600px）', 'kantanpro' ),
            '1920px' => __( '最大（1920px）', 'kantanpro' ),
            'custom' => __( 'カスタム', 'kantanpro' ),
        );
    }

    /**
     * ショートコード設置済み固定ページ向けの標準幅マップを返す。
     *
     * @return array<int, string>
     */
    public static function build_default_page_content_widths() {
        $default_width = self::get_default_page_content_width();
        $widths        = array();

        foreach ( self::get_ktpwp_shortcode_pages() as $page ) {
            $page_id = (int) $page->ID;
            if ( $page_id > 0 ) {
                $widths[ $page_id ] = $default_width;
            }
        }

        return $widths;
    }

    /**
     * 固定ページ幅の標準値をデザイン設定に書き込む（インストール時・未設定ページの補完）。
     *
     * @return void
     */
    public static function seed_default_page_content_widths() {
        $default_widths = self::build_default_page_content_widths();
        if ( empty( $default_widths ) ) {
            return;
        }

        $design_option_name = 'ktp_design_settings';
        $design             = get_option( $design_option_name, array() );
        if ( ! is_array( $design ) ) {
            $design = array();
        }

        $widths = isset( $design['page_content_widths'] ) && is_array( $design['page_content_widths'] )
            ? $design['page_content_widths']
            : array();

        $changed = false;
        if ( empty( $widths ) ) {
            $widths  = $default_widths;
            $changed = true;
        } else {
            foreach ( $default_widths as $page_id => $width ) {
                if ( ! array_key_exists( $page_id, $widths ) ) {
                    $widths[ $page_id ] = $width;
                    $changed            = true;
                }
            }
        }

        if ( ! $changed ) {
            return;
        }

        $design['page_content_widths'] = $widths;
        update_option( $design_option_name, $design );
    }

    /**
     * ページ幅の CSS 値をサニタイズする。
     *
     * @param string $value 入力値。
     * @return string 空文字または CSS 長さ（例: 1400px）。
     */
    public static function sanitize_page_content_width_value( $value ) {
        $value = trim( (string) $value );
        if ( $value === '' || $value === 'custom' ) {
            return '';
        }

        if ( preg_match( '/^\d+(\.\d+)?(px|rem|%)$/i', $value ) ) {
            return strtolower( $value );
        }

        if ( preg_match( '/^\d+(\.\d+)?$/', $value ) ) {
            return $value . 'px';
        }

        return '';
    }

    /**
     * 固定ページに設定されたコンテンツ幅を取得する。
     *
     * @param int $page_id 固定ページ ID。
     * @return string 空文字または CSS 長さ。
     */
    public static function get_page_content_width_for_page( $page_id ) {
        $page_id = (int) $page_id;
        if ( $page_id <= 0 || ! self::page_has_ktpwp_shortcode( $page_id ) ) {
            return '';
        }

        $design_options = self::get_design_settings();
        $widths         = isset( $design_options['page_content_widths'] ) && is_array( $design_options['page_content_widths'] )
            ? $design_options['page_content_widths']
            : array();

        if ( ! isset( $widths[ $page_id ] ) ) {
            return self::get_default_page_content_width();
        }

        $saved = trim( (string) $widths[ $page_id ] );
        if ( $saved === '' ) {
            return '';
        }

        return self::sanitize_page_content_width_value( $saved );
    }

    /**
     * 表示中の KantanPro 固定ページ ID を取得する。
     *
     * @return int
     */
    public static function get_current_ktpwp_page_id() {
        $page_id = (int) get_queried_object_id();
        if ( $page_id <= 0 && isset( $_GET['page_id'] ) ) {
            $page_id = absint( wp_unslash( $_GET['page_id'] ) );
        }

        return $page_id;
    }

    /**
     * ページ幅ラッパー用の HTML 属性文字列を返す。
     *
     * @param int      $page_id       固定ページ ID（0 のときは表示中ページ）。
     * @param string[] $extra_classes 追加 CSS クラス。
     * @return string
     */
    public static function get_page_layout_wrapper_attributes( $page_id = 0, $extra_classes = array() ) {
        $page_id = (int) $page_id;
        if ( $page_id <= 0 ) {
            $page_id = self::get_current_ktpwp_page_id();
        }

        $classes = array( 'ktpwp-page-layout' );
        if ( is_array( $extra_classes ) ) {
            foreach ( $extra_classes as $class_name ) {
                $class_name = sanitize_html_class( (string) $class_name );
                if ( $class_name !== '' ) {
                    $classes[] = $class_name;
                }
            }
        }
        $attrs   = array(
            'class' => implode( ' ', $classes ),
        );

        $width = self::get_page_content_width_for_page( $page_id );
        if ( $width !== '' ) {
            $attrs['class'] .= ' ktpwp-page-layout--sized';
            $attrs['data-ktp-page-id'] = (string) $page_id;
            $attrs['style']            = 'max-width:' . $width . ';margin-left:auto;margin-right:auto;width:100%;box-sizing:border-box;';
        }

        $html = '';
        foreach ( $attrs as $name => $value ) {
            $html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
        }

        return ltrim( $html );
    }

    /**
     * 固定ページごとのコンテンツ幅 CSS を生成する。
     *
     * @param int    $page_id 固定ページ ID。
     * @param string $width   CSS 長さ。
     * @return string
     */
    private static function build_page_content_width_css( $page_id, $width ) {
        $page_id = (int) $page_id;
        $width   = self::sanitize_page_content_width_value( $width );
        if ( $page_id <= 0 || $width === '' ) {
            return '';
        }

        $selector = 'body.page-id-' . $page_id;
        $targets  = array(
            $selector . ' #container .content .main',
            $selector . ' #content .main',
            $selector . ' .entry-content',
            $selector . ' .article',
            $selector . ' #content-in.wrap',
            $selector . ' .ktpwp-page-layout',
            $selector . ' .ktpwp-shortcode-container',
            $selector . ' .ktp-before-header-banner',
            $selector . ' .ktp_header',
            $selector . ' .tabs.ktp_plugin_container',
        );

        return implode( ',', $targets ) . '{'
            . 'max-width:' . $width . ' !important;'
            . 'margin-left:auto !important;'
            . 'margin-right:auto !important;'
            . 'width:100% !important;'
            . 'box-sizing:border-box !important;'
            . '}';
    }

    /**
     * 一般設定で指定された業務画面ページ ID（0 は未指定）。
     *
     * @return int
     */
    public static function get_configured_ktpwp_business_page_id() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['ktp_page_id'] ) ? (int) $options['ktp_page_id'] : 0;
    }

    /**
     * 請求書下部に印字する振込先口座ブロック（HTML）
     *
     * @return string 未入力のときは空文字
     */
    public static function get_bank_transfer_invoice_html() {
        $options = get_option( 'ktp_general_settings', array() );
        $branch = isset( $options['bank_transfer_bank_branch'] ) ? trim( (string) $options['bank_transfer_bank_branch'] ) : '';
        $type   = isset( $options['bank_transfer_account_type'] ) && $options['bank_transfer_account_type'] === 'current' ? 'current' : 'ordinary';
        $number = isset( $options['bank_transfer_account_number'] ) ? trim( (string) $options['bank_transfer_account_number'] ) : '';
        $holder = isset( $options['bank_transfer_account_holder_kana'] ) ? trim( (string) $options['bank_transfer_account_holder_kana'] ) : '';

        if ( $branch === '' && $number === '' && $holder === '' ) {
            return '';
        }

        $type_label = ( $type === 'current' ) ? __( '当座', 'kantanpro' ) : __( '普通', 'kantanpro' );

        $html  = '<div class="ktp-invoice-bank-transfer" style="margin-top:16px;padding:14px 16px;border:1px solid #ccc;background:#fafafa;border-radius:4px;font-size:13px;line-height:1.6;color:#333;text-align:left;">';
        $html .= '<div style="font-weight:bold;margin-bottom:8px;border-bottom:1px solid #ddd;padding-bottom:6px;">' . esc_html__( '振込先口座', 'kantanpro' ) . '</div>';
        if ( $branch !== '' ) {
            $html .= '<div><span style="font-weight:600;">' . esc_html__( '銀行名・支店名', 'kantanpro' ) . '</span>　' . esc_html( $branch ) . '</div>';
        }
        $html .= '<div><span style="font-weight:600;">' . esc_html__( '口座種別', 'kantanpro' ) . '</span>　' . esc_html( $type_label ) . '</div>';
        if ( $number !== '' ) {
            $html .= '<div><span style="font-weight:600;">' . esc_html__( '口座番号', 'kantanpro' ) . '</span>　' . esc_html( $number ) . '</div>';
        }
        if ( $holder !== '' ) {
            $html .= '<div><span style="font-weight:600;">' . esc_html__( '口座名義（カナ）', 'kantanpro' ) . '</span>　' . esc_html( $holder ) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * 一括請求 issuer stack 用の振込先（枠・背景色なし）
     *
     * @return string 未入力のときは空文字
     */
    public static function get_bank_transfer_bulk_issuer_html() {
        $plain = self::get_bank_transfer_plain_text();
        if ( $plain === '' ) {
            return '';
        }

        return '<div class="ktp-bulk-invoice-issuer-bank-text" style="white-space:pre-line;background:#fff;">'
            . esc_html( $plain )
            . '</div>';
    }

    /**
     * 見積メール等用の振込先口座（プレーンテキスト）
     *
     * @return string 未入力のときは空文字
     */
    public static function get_bank_transfer_plain_text() {
        $options = get_option( 'ktp_general_settings', array() );
        $branch = isset( $options['bank_transfer_bank_branch'] ) ? trim( (string) $options['bank_transfer_bank_branch'] ) : '';
        $type   = isset( $options['bank_transfer_account_type'] ) && $options['bank_transfer_account_type'] === 'current' ? 'current' : 'ordinary';
        $number = isset( $options['bank_transfer_account_number'] ) ? trim( (string) $options['bank_transfer_account_number'] ) : '';
        $holder = isset( $options['bank_transfer_account_holder_kana'] ) ? trim( (string) $options['bank_transfer_account_holder_kana'] ) : '';

        if ( $branch === '' && $number === '' && $holder === '' ) {
            return '';
        }

        $type_label = ( $type === 'current' ) ? __( '当座', 'kantanpro' ) : __( '普通', 'kantanpro' );
        $lines      = array( __( '【振込先口座】', 'kantanpro' ) );
        if ( $branch !== '' ) {
            $lines[] = __( '銀行名・支店名：', 'kantanpro' ) . $branch;
        }
        $lines[] = __( '口座種別：', 'kantanpro' ) . $type_label;
        if ( $number !== '' ) {
            $lines[] = __( '口座番号：', 'kantanpro' ) . $number;
        }
        if ( $holder !== '' ) {
            $lines[] = __( '口座名義（カナ）：', 'kantanpro' ) . $holder;
        }

        return implode( "\n", $lines );
    }


    /**
     * Get supported currency definitions.
     *
     * @return array<string,array{label:string,symbol:string,position:string,decimals:int}>
     */
    public static function get_supported_currencies() {
        return array(
            'JPY' => array(
                'label'    => __( '日本円（JPY）', 'kantanpro' ),
                'symbol'   => '円',
                'position' => 'after',
                'decimals' => 0,
            ),
            'USD' => array(
                'label'    => __( '米ドル（USD）', 'kantanpro' ),
                'symbol'   => '$',
                'position' => 'before',
                'decimals' => 2,
            ),
            'EUR' => array(
                'label'    => __( 'ユーロ（EUR）', 'kantanpro' ),
                'symbol'   => '€',
                'position' => 'before',
                'decimals' => 2,
            ),
            'GBP' => array(
                'label'    => __( '英ポンド（GBP）', 'kantanpro' ),
                'symbol'   => '£',
                'position' => 'before',
                'decimals' => 2,
            ),
            'AUD' => array(
                'label'    => __( '豪ドル（AUD）', 'kantanpro' ),
                'symbol'   => 'A$',
                'position' => 'before',
                'decimals' => 2,
            ),
            'CAD' => array(
                'label'    => __( 'カナダドル（CAD）', 'kantanpro' ),
                'symbol'   => 'C$',
                'position' => 'before',
                'decimals' => 2,
            ),
        );
    }

    /**
     * Get selected currency code.
     *
     * @return string
     */
    public static function get_currency_code() {
        $options = get_option( 'ktp_general_settings', array() );
        $code = isset( $options['currency_code'] ) ? strtoupper( sanitize_text_field( $options['currency_code'] ) ) : 'JPY';
        $supported = self::get_supported_currencies();
        return isset( $supported[ $code ] ) ? $code : 'JPY';
    }

    /**
     * Get selected currency settings for JavaScript.
     *
     * @return array<string,mixed>
     */
    public static function get_currency_config() {
        $code = self::get_currency_code();
        $supported = self::get_supported_currencies();
        $config = $supported[ $code ];
        $config['code'] = $code;
        return $config;
    }

    /**
     * Format a numeric amount using the selected currency.
     * 整数値は小数点以下を表示しない（50000.00 → 50,000円）。
     *
     * @param float|int|string $amount Amount value.
     * @return string
     */
    public static function format_money( $amount ) {
        $config   = self::get_currency_config();
        $symbol   = isset( $config['symbol'] ) ? (string) $config['symbol'] : '';
        $position = isset( $config['position'] ) ? (string) $config['position'] : 'after';
        $plain    = self::format_decimal_trimmed( $amount );

        if ( $plain === '' ) {
            $plain = '0';
        }

        $parts    = explode( '.', $plain );
        $parts[0] = number_format( (float) $parts[0], 0, '.', ',' );
        $formatted = isset( $parts[1] ) ? $parts[0] . '.' . $parts[1] : $parts[0];

        if ( $position === 'before' ) {
            return $symbol . $formatted;
        }

        return $formatted . $symbol;
    }

    /**
     * 末尾の不要な 0 を省略して数値を表示する（税率・数量など）。
     *
     * @param float|int|string|null $value        数値。
     * @param int                   $max_decimals 最大小数桁。
     * @return string
     */
    public static function format_decimal_trimmed( $value, $max_decimals = 4 ) {
        if ( $value === null || $value === '' ) {
            return '';
        }

        if ( ! is_numeric( $value ) ) {
            return (string) $value;
        }

        $number    = (float) $value;
        $formatted = rtrim( rtrim( number_format( $number, $max_decimals, '.', '' ), '0' ), '.' );

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * フォーム input の value 属性用（DB の DECIMAL 由来の .00 を省略）。
     *
     * @param float|int|string|null $value        数値。
     * @param int                   $max_decimals 最大小数桁。
     * @return string
     */
    public static function format_number_field_value( $value, $max_decimals = 4 ) {
        return self::format_decimal_trimmed( $value, $max_decimals );
    }

    /**
     * 通貨記号付きで金額を表示する（{@see format_money} のエイリアス）。
     *
     * @param float|int|string $amount 金額。
     * @return string
     */
    public static function format_money_trimmed( $amount ) {
        return self::format_money( $amount );
    }

    /**
     * Get default tax rate setting
     *
     * @since 1.0.0
     * @return float Default tax rate setting (default: 10.00)
     */
    public static function get_default_tax_rate() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['default_tax_rate'] ) ? floatval( $options['default_tax_rate'] ) : 10.00;
    }

    /**
     * Get reduced tax rate setting
     *
     * @since 1.0.0
     * @return float Reduced tax rate setting (default: 8.00)
     */
    public static function get_reduced_tax_rate() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['reduced_tax_rate'] ) ? floatval( $options['reduced_tax_rate'] ) : 8.00;
    }



    /**
     * Get design settings
     *
     * @since 1.0.0
     * @return array Design settings
     */
    public static function get_design_settings() {
        // システムデフォルト値
        $system_defaults = array(
            'tab_active_color' => '#B7CBFB',
            'tab_inactive_color' => '#E6EDFF',
            'tab_border_color' => '#B7CBFB',
            'odd_row_color' => '#E7EEFD',
            'even_row_color' => '#FFFFFF',
            'public_product_card_bg_color' => '#e2e8f0',
            'header_bg_image' => 'images/default/header_bg_image.png',
            'page_content_widths' => array(),
        );

        return get_option( 'ktp_design_settings', $system_defaults );
    }

    /**
     * Get header background image URL
     *
     * @since 1.0.0
     * @return string Header background image URL (empty string if not set)
     */
    public static function get_header_bg_image_url() {
        $design_settings = self::get_design_settings();

        $header_bg_image = ! empty( $design_settings['header_bg_image'] ) ? $design_settings['header_bg_image'] : 'images/default/header_bg_image.png';

        // 数値の場合はWordPressの添付ファイルIDとして処理
        if ( is_numeric( $header_bg_image ) ) {
            return wp_get_attachment_image_url( $header_bg_image, 'full' );
        } else {
            // 文字列の場合は直接パスとして処理
            $image_path = $header_bg_image;
            // 相対パスの場合は、プラグインディレクトリからの絶対URLに変換
            if ( strpos( $image_path, 'http' ) !== 0 ) {
                return plugin_dir_url( __DIR__ ) . $image_path;
            }
            return $image_path;
        }
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_menu', array( $this, 'register_developer_submenu' ), 100 );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'rest_api_init', array( $this, 'register_central_banner_rest_route' ) );
        add_action( 'phpmailer_init', array( $this, 'setup_smtp_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_scripts' ) );
        // wp_head/admin_head だと既にスタイルが出力済みで wp_add_inline_style が効かないため、
        // enqueue のタイミング（遅めの優先度）に移す。
        add_action( 'wp_enqueue_scripts', array( $this, 'output_custom_styles' ), 20 );
        add_action( 'admin_enqueue_scripts', array( $this, 'output_custom_styles' ), 20 );
        // wp_head だとスタイル出力後で wp_add_inline_style が効かないため enqueue 時点に移す
        add_action( 'wp_enqueue_scripts', array( $this, 'output_page_content_width_styles' ), 21 );
        add_filter( 'body_class', array( $this, 'filter_body_class_for_page_content_width' ) );
        add_action( 'admin_init', array( $this, 'handle_default_settings_actions' ) );

		// データエクスポート/リストア用ハンドラ
		add_action( 'admin_post_ktpwp_export_data', array( $this, 'handle_export_data' ) );
		add_action( 'admin_post_ktpwp_restore_data', array( $this, 'handle_restore_data' ) );

        // ロゴマークのデフォルト値チェック
        add_action( 'init', array( $this, 'ensure_logo_default_value' ) );
        add_action( 'init', array( __CLASS__, 'seed_default_page_content_widths' ), 25 );

        // ユーザーアクティビティの追跡
        add_action( 'wp_login', array( $this, 'record_user_last_login' ), 10, 2 );
    }

    /**
     * Enqueue media scripts for image upload
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_media_scripts( $hook ) {
        // KTPWPのデザイン設定ページでのみメディアライブラリを読み込む
        if ( strpos( $hook, 'ktp-design' ) !== false ) {
            wp_enqueue_media();
            wp_enqueue_script(
                'ktp-media-upload',
                plugin_dir_url( __DIR__ ) . 'js/ktp-media-upload.js',
                array( 'jquery' ),
                '1.0.0',
                true
            );
        }
    }

    /**
     * Enqueue admin styles
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_styles( $hook ) {
        // Load CSS on KTPWP settings pages only
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( strpos( $hook, 'ktp-' ) !== false ) {
            wp_enqueue_style(
                'ktp-admin-settings',
                plugin_dir_url( __DIR__ ) . 'css/ktp-admin-settings.css',
                array(),
                '1.0.1'
            );

            wp_enqueue_style(
                'ktp-setting-tab',
                plugin_dir_url( __DIR__ ) . 'css/ktp-setting-tab.css',
                array(),
                '1.0.1'
            );
        }
    }

    /**
     * Activate plugin and set default options
     *
     * @since 1.0.0
     * @return void
     */
    public static function activate() {
        // 出力バッファリングを開始（予期しない出力を防ぐ）
        ob_start();
        
        $option_name = 'ktp_smtp_settings';
        if ( false === get_option( $option_name ) ) {
            add_option(
                $option_name,
                array(
					'email_address' => '',
					'smtp_host' => '',
					'smtp_port' => '',
					'smtp_user' => '',
					'smtp_pass' => '',
					'smtp_secure' => '',
					'smtp_from_name' => '',
                )
            );
        }

        // 一般設定のデフォルト値を設定
        $general_option_name = 'ktp_general_settings';
        if ( false === get_option( $general_option_name ) ) {
            add_option(
                $general_option_name,
                array(
					'work_list_range' => 20,
					'delivery_warning_days' => 3,
					'qualified_invoice_number' => '',
					'company_info' => '',
					'currency_code' => 'JPY',
                )
            );
        } else {
            // 既存設定に新しいフィールドが不足している場合は追加
            $existing_general = get_option( $general_option_name );
            $general_updated = false;

            // 適格請求書番号フィールドが存在しない場合は追加
            if ( ! array_key_exists( 'qualified_invoice_number', $existing_general ) ) {
                $existing_general['qualified_invoice_number'] = '';
                $general_updated = true;
            }

            if ( ! array_key_exists( 'currency_code', $existing_general ) ) {
                $existing_general['currency_code'] = 'JPY';
                $general_updated = true;
            }

            if ( ! array_key_exists( 'ktp_page_id', $existing_general ) ) {
                $existing_general['ktp_page_id'] = 0;
                $general_updated = true;
            }

            if ( $general_updated ) {
                update_option( $general_option_name, $existing_general );
            }
        }

        // デザイン設定のデフォルト値を設定
        $design_option_name = 'ktp_design_settings';
        $design_defaults = array(
            'tab_active_color' => '#B7CBFB',
            'tab_inactive_color' => '#E6EDFF',
            'tab_border_color' => '#B7CBFB',
            'odd_row_color' => '#E7EEFD',
            'even_row_color' => '#FFFFFF',
            'public_product_card_bg_color' => '#e2e8f0',
            'header_bg_image' => 'images/default/header_bg_image.png',
            'page_content_widths' => self::build_default_page_content_widths(),
        );

        if ( false === get_option( $design_option_name ) ) {
            add_option( $design_option_name, $design_defaults );
        } else {
            // 既存設定に新しいフィールドが不足している場合は追加
            $existing_design = get_option( $design_option_name );
            $updated = false;

            // 古いmain_color、sub_color、tab_bg_colorを削除
            if ( array_key_exists( 'main_color', $existing_design ) ) {
                unset( $existing_design['main_color'] );
                $updated = true;
            }
            if ( array_key_exists( 'sub_color', $existing_design ) ) {
                unset( $existing_design['sub_color'] );
                $updated = true;
            }
            if ( array_key_exists( 'tab_bg_color', $existing_design ) ) {
                unset( $existing_design['tab_bg_color'] );
                $updated = true;
            }

            foreach ( $design_defaults as $key => $default_value ) {
                if ( ! array_key_exists( $key, $existing_design ) ) {
                    $existing_design[ $key ] = $default_value;
                    $updated = true;
                }
            }

            if ( $updated ) {
                update_option( $design_option_name, $existing_design );
            }
        }

        self::seed_default_page_content_widths();

        // ロゴマークのデフォルト値を設定
        $default_logo = function_exists( 'ktpwp_plugin_asset_url' )
            ? ktpwp_plugin_asset_url( 'images/default/icon.png' )
            : plugins_url( 'images/default/icon.png', KANTANPRO_PLUGIN_FILE );
        if ( false === get_option( 'ktp_logo_image' ) ) {
            add_option( 'ktp_logo_image', $default_logo );
        }

        // 寄付設定のデフォルト値を設定
        $donation_option_name = 'ktp_donation_settings';
        if ( false === get_option( $donation_option_name ) ) {
            add_option(
                $donation_option_name,
                array(
                    'enabled' => false,
                    'monthly_goal' => 10000,
                    'suggested_amounts' => '1000,3000,5000,10000',
                    'frontend_notice_enabled' => false,
                    'notice_display_interval' => 7,
                    'notice_message' => __( 'KantanProの開発を支援してください。', 'kantanpro' ),
                    'donation_url' => ''
                )
            );
        } else {
            // 既存設定に新しいフィールドが不足している場合は追加
            $existing_donation = get_option( $donation_option_name );
            $donation_updated = false;

            $donation_defaults = array(
                'enabled' => false,
                'monthly_goal' => 10000,
                'suggested_amounts' => '1000,3000,5000,10000',
                'frontend_notice_enabled' => false,
                'notice_display_interval' => 7,
                'notice_message' => __( 'KantanProの開発を支援してください。', 'kantanpro' ),
                'donation_url' => ''
            );

            foreach ( $donation_defaults as $key => $default_value ) {
                if ( ! array_key_exists( $key, $existing_donation ) ) {
                    $existing_donation[ $key ] = $default_value;
                    $donation_updated = true;
                }
            }

            if ( $donation_updated ) {
                update_option( $donation_option_name, $existing_donation );
            }
        }

        // 更新通知設定のデフォルト値を設定
        $update_notification_option_name = 'ktp_update_notification_settings';
        if ( false === get_option( $update_notification_option_name ) ) {
            add_option(
                $update_notification_option_name,
                array(
                    'enable_notifications' => true,
                    'enable_admin_notifications' => true,
                    'enable_frontend_notifications' => true,
                    'check_interval' => 24,
                    'notification_roles' => array( 'administrator' )
                )
            );
        } else {
            // 既存設定に新しいフィールドが不足している場合は追加
            $existing_update_notification = get_option( $update_notification_option_name );
            $update_notification_updated = false;

            $update_notification_defaults = array(
                'enable_notifications' => true,
                'enable_admin_notifications' => true,
                'enable_frontend_notifications' => true,
                'check_interval' => 24,
                'notification_roles' => array( 'administrator' )
            );

            foreach ( $update_notification_defaults as $key => $default_value ) {
                if ( ! array_key_exists( $key, $existing_update_notification ) ) {
                    $existing_update_notification[ $key ] = $default_value;
                    $update_notification_updated = true;
                }
            }

            if ( $update_notification_updated ) {
                update_option( $update_notification_option_name, $existing_update_notification );
            }
        }



        // 旧システムから新システムへのデータ移行処理
        self::migrate_company_info_from_old_system();

        self::create_or_update_tables(); // テーブル作成/更新処理を呼び出す
        
        // 出力バッファをクリア（予期しない出力を除去）
        $output = ob_get_clean();
        
        // デバッグ時のみ、予期しない出力があればログに記録
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $output ) ) {
            error_log( 'KTPWP: KTPWP_Settings::activate中に予期しない出力を検出: ' . substr( $output, 0, 1000 ) );
        }
    }

    /**
     * Create or update database tables.
     *
     * @since 1.0.1 // バージョンは適宜更新
     */
    public static function create_or_update_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // wp_ktp_client テーブル
        $table_name_client = $wpdb->prefix . 'ktp_client';
        $sql_client = "CREATE TABLE $table_name_client (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            company_name varchar(255) DEFAULT '' NOT NULL,
            name varchar(255) DEFAULT '' NOT NULL,
            email varchar(100) DEFAULT '' NOT NULL,
            memo text,
            category varchar(100) DEFAULT '',
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY email (email)
        ) $charset_collate;";
        dbDelta( $sql_client );

        // wp_ktp_order テーブル
        $table_name_order = $wpdb->prefix . 'ktp_order';
        $sql_order = "CREATE TABLE $table_name_order (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            time BIGINT(11) DEFAULT 0 NOT NULL,
            client_id MEDIUMINT(9) DEFAULT NULL,
            customer_name VARCHAR(100) NOT NULL,
            company_name VARCHAR(255) DEFAULT NULL,
            user_name TINYTEXT,
            project_name VARCHAR(255),
            progress TINYINT(1) NOT NULL DEFAULT 1,
            invoice_items TEXT,
            cost_items TEXT,
            memo TEXT,
            search_field TEXT,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL, 
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY client_id (client_id) 
        ) $charset_collate;";
        dbDelta( $sql_order );

        // テーブル作成後、AUTO_INCREMENTカウンターを確実に1に設定
        $order_row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_order}" );
        if ( $order_row_count == 0 ) {
            $wpdb->query( "ALTER TABLE {$table_name_order} AUTO_INCREMENT = 1" );
        }

        // 顧客テーブルのAUTO_INCREMENTカウンターも確実に1に設定
        $client_row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_client}" );
        if ( $client_row_count == 0 ) {
            $wpdb->query( "ALTER TABLE {$table_name_client} AUTO_INCREMENT = 1" );
        }

        // 既存テーブルにカラムを追加
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name_order'" ) == $table_name_order ) {
            $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM $table_name_order LIKE 'company_name'" );
            if ( empty( $column_exists ) ) {
                $wpdb->query( "ALTER TABLE $table_name_order ADD company_name VARCHAR(255) DEFAULT NULL;" );
            }
        }

        // 他のテーブルも同様に追加・更新

        // デバッグ用: テーブル作成/更新が試行されたことをログに記録
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            // テーブル構造の確認 (デバッグ時のみ)
        }
    }

    public function add_plugin_page() {
        // メインメニュー
        add_menu_page(
            KANTANPRO_PLUGIN_NAME, // ページタイトル
            KANTANPRO_PLUGIN_NAME, // メニュータイトル
            'manage_options', // 権限
            'ktp-settings', // メニューのスラッグ
            array( $this, 'create_general_page' ), // 表示を処理する関数（一般設定を最初に表示）
            'dashicons-chart-area', // アイコン（グラフ・分析を表すアイコン）
            80 // メニューの位置
        );

        // サブメニュー - 一般設定（最初に表示）
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( '一般設定', 'kantanpro' ), // ページタイトル
            __( '一般設定', 'kantanpro' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-settings', // メニューのスラッグ（親と同じにすると選択時にハイライト）
            array( $this, 'create_general_page' ) // 表示を処理する関数
        );

        // サブメニュー - メール・SMTP設定
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( 'メール・SMTP設定', 'kantanpro' ), // ページタイトル
            __( 'メール・SMTP設定', 'kantanpro' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-mail-settings', // メニューのスラッグ
            array( $this, 'create_admin_page' ) // 表示を処理する関数
        );

        // サブメニュー - デザイン設定
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( 'デザイン設定', 'kantanpro' ), // ページタイトル
            __( 'デザイン', 'kantanpro' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-design-settings', // メニューのスラッグ
            array( $this, 'create_design_page' ) // 表示を処理する関数
        );

        // サブメニュー - スタッフ管理
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( 'スタッフ管理', 'kantanpro' ), // ページタイトル
            __( 'スタッフ管理', 'kantanpro' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-staff', // メニューのスラッグ
            array( $this, 'create_staff_page' ) // 表示を処理する関数
        );

        // サブメニュー - ダミーデータ作成
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( 'ダミーデータ作成', 'kantanpro' ), // ページタイトル
            __( 'ダミーデータ作成', 'kantanpro' ), // メニュータイトル
            'manage_options', // 権限
            'ktpwp-dummy-data', // メニューのスラッグ（既存ページのスラッグを踏襲）
            'ktpwp_dummy_data_page' // 既存の表示関数を流用
        );

		// サブメニュー - バックアップ（エクスポート/インポート）
		add_submenu_page(
			'ktp-settings', // 親メニューのスラッグ
			__( 'バックアップ', 'kantanpro' ), // ページタイトル
			__( 'バックアップ', 'kantanpro' ), // メニュータイトル
			'manage_options', // 権限
			'ktp-data-tools', // メニューのスラッグ
			array( $this, 'create_data_tools_page' ) // 表示を処理する関数
		);

		// サブメニュー - FileMaker 版データ取り込み（顧客 CSV/TSV）
		add_submenu_page(
			'ktp-settings',
			__( 'FileMaker版データ取り込み', 'kantanpro' ),
			__( 'FileMaker版データ取り込み', 'kantanpro' ),
			'manage_options',
			'ktp-fm-import',
			array( $this, 'create_fm_import_page' )
		);
    }

    /**
     * 開発者設定はサブメニュー最下部に表示（帳票表示設定などより後に登録）
     *
     * @since 1.0.0
     */
    public function register_developer_submenu() {
        if ( ! $this->is_developer_settings_enabled() ) {
            return;
        }

        add_submenu_page(
            'ktp-settings',
            __( '開発者設定', 'kantanpro' ),
            __( '開発者設定', 'kantanpro' ),
            'manage_options',
            'ktp-developer-settings',
            array( $this, 'create_developer_page' )
        );
    }

	/**
	 * FileMaker 版データ取り込み（クラス未読込時の致命エラーを避けるラッパー）
	 *
	 * @return void
	 */
	public function create_fm_import_page() {
		try {
			if ( ! class_exists( 'KTPWP_FM_Import', false ) ) {
				$fm_path = ( defined( 'MY_PLUGIN_PATH' ) && is_string( MY_PLUGIN_PATH ) && MY_PLUGIN_PATH !== '' )
					? trailingslashit( MY_PLUGIN_PATH ) . 'includes/class-ktpwp-fm-import.php'
					: dirname( __DIR__ ) . '/includes/class-ktpwp-fm-import.php';
				if ( is_readable( $fm_path ) ) {
					require_once $fm_path;
				}
			}
			if ( ! class_exists( 'KTPWP_FM_Import', false ) ) {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'このページにアクセスする権限がありません。', 'kantanpro' ) );
				}
				echo '<div class="wrap ktp-admin-wrap"><h1>' . esc_html__( 'FileMaker版データ取り込み', 'kantanpro' ) . '</h1>';
				echo '<div class="notice notice-error"><p>' . esc_html__( '取り込みモジュール（includes/class-ktpwp-fm-import.php）を読み込めません。プラグインを再アップロードするか、コンテナ／ボリュームに最新ファイルが載っているか確認してください。', 'kantanpro' ) . '</p></div></div>';
				return;
			}
			KTPWP_FM_Import::bootstrap();
			KTPWP_FM_Import::render_admin_page();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( 'KTPWP create_fm_import_page: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'このページにアクセスする権限がありません。', 'kantanpro' ) );
			}
			echo '<div class="wrap ktp-admin-wrap"><h1>' . esc_html__( 'FileMaker版データ取り込み', 'kantanpro' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'この画面の表示中にエラーが発生しました。', 'kantanpro' ) . '</p>';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) {
				echo '<pre style="white-space:pre-wrap;max-height:320px;overflow:auto;background:#fff;padding:12px;border:1px solid #ccc;">';
				echo esc_html( $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString() );
				echo '</pre>';
			}
			echo '</div></div>';
		}
	}

	/**
	 * バックアップページ（エクスポート/インポート）
	 */
	public function create_data_tools_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'この設定ページにアクセスする権限がありません。', 'kantanpro' ) );
		}

		echo '<div class="wrap ktp-admin-wrap">';
		if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'backup' ) ) {
			echo class_exists( 'KTPWP_Edition' )
				? KTPWP_Edition::get_admin_moved_to_ex_message_html( __( 'バックアップ機能', 'kantanpro' ) )
				: '';
			echo '</div>';
			return;
		}

		$notice = '';
		if ( isset( $_GET['ktp_action'] ) ) {
			$action = sanitize_text_field( $_GET['ktp_action'] );
			if ( $action === 'restore_success' ) {
				$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'リストアが完了しました。', 'kantanpro' ) . '</p></div>';
			} elseif ( $action === 'restore_failed' ) {
				$notice = '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'リストアに失敗しました。ファイル形式をご確認ください。', 'kantanpro' ) . '</p></div>';
			}
		}

		echo '<div class="wrap ktp-admin-wrap">';
		echo '<h1><span class="dashicons dashicons-database-export" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span>' . esc_html__( 'バックアップ', 'kantanpro' ) . '</h1>';
		if ( $notice ) {
			echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// バックアップページ用JavaScriptを読み込み
		wp_enqueue_script( 'ktp-backup-page', plugin_dir_url( __FILE__ ) . '../js/ktp-backup-page.js', array(), KANTANPRO_PLUGIN_VERSION, true );

		// エクスポート
		echo '<div class="ktp-settings-section">';
		echo '<h2>' . esc_html__( 'エクスポート', 'kantanpro' ) . '</h2>';
		echo '<p>' . esc_html__( 'KantanProの設定とデータを1つのファイルに出力します。', 'kantanpro' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="ktpwp_export_data" />';
		echo wp_nonce_field( 'ktpwp_export_data', 'ktpwp_export_nonce', true, false );
		echo '<p>';
		echo '<label for="ktp_export_format">' . esc_html__( '形式', 'kantanpro' ) . ':</label> ';
		echo '<select id="ktp_export_format" name="format">';
		echo '<option value="json" selected>JSON</option>';
		echo '<option value="csv">CSV</option>';
		echo '</select>';
		echo '</p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'エクスポート実行', 'kantanpro' ) . '</button></p>';
		echo '</form>';
		echo '</div>';

		// リストア
		echo '<div class="ktp-settings-section">';
		echo '<h2>' . esc_html__( 'リストア', 'kantanpro' ) . '</h2>';
		echo '<p>' . esc_html__( 'エクスポートしたJSON/CSVファイルを選択してリストアします。', 'kantanpro' ) . '</p>';
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( '注意:', 'kantanpro' ) . '</strong> ' . esc_html__( 'リストアを実行すると、現在のデータは全て削除されます。', 'kantanpro' ) . '</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" id="ktp-restore-form">';
		echo '<input type="hidden" name="action" value="ktpwp_restore_data" />';
		echo wp_nonce_field( 'ktpwp_restore_data', 'ktpwp_restore_nonce', true, false );
		echo '<p>';
		echo '<label for="ktp_import_format">' . esc_html__( '形式', 'kantanpro' ) . ':</label> ';
		echo '<select id="ktp_import_format" name="format">';
		echo '<option value="json" selected>JSON</option>';
		echo '<option value="csv">CSV</option>';
		echo '</select>';
		echo '</p>';
		echo '<input type="file" name="ktp_import_file" accept="application/json,text/csv,.csv" required /> ';
		echo '<p><button type="submit" class="button button-primary" id="ktp-restore-button">' . esc_html__( 'リストア実行', 'kantanpro' ) . '</button></p>';
		echo '</form>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * エクスポート実行
	 */
	public function handle_export_data() {
		if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'backup' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=ktp-data-tools' ) );
			exit;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'kantanpro' ) );
		}
		if ( ! isset( $_POST['ktpwp_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ktpwp_export_nonce'] ) ), 'ktpwp_export_data' ) ) {
			wp_die( esc_html__( 'セキュリティチェックに失敗しました。', 'kantanpro' ) );
		}

		global $wpdb;
		$export = array();
		$export['metadata'] = array(
			'exported_at' => current_time( 'mysql' ),
			'site_url' => site_url(),
			'plugin_version' => defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : 'unknown',
			'db_prefix' => $wpdb->prefix,
		);

		// オプションの収集
		$export['options'] = $this->collect_plugin_options();

		// テーブルの収集
		$tables = $this->get_plugin_tables();
		$export['tables'] = array();
		foreach ( $tables as $table_name ) {
			$rows = $wpdb->get_results( "SELECT * FROM `{$table_name}`", ARRAY_A );
			$export['tables'][ $table_name ] = $rows ? $rows : array();
		}

		$json = wp_json_encode( $export );
		if ( empty( $json ) ) {
			wp_die( esc_html__( 'エクスポートデータの生成に失敗しました。', 'kantanpro' ) );
		}

		$format = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : 'json';
		$format = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';

		if ( $format === 'json' ) {
			$filename = 'kantanpro-export-' . date_i18n( 'Ymd-His' ) . '.json';
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate' );
			header( 'Pragma: public' );
			echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		// CSV: 複数テーブルを1ファイルに含めるため、簡易的に疑似区切りを入れる
		$csv_output = '';
		// オプションをCSV化
		$csv_output .= "#OPTIONS\n";
		$csv_output .= "option_name,option_value\n";
		foreach ( $export['options'] as $name => $value ) {
			$csv_output .= $this->csv_line( array( $name, wp_json_encode( $value ) ) );
		}
		// テーブルごとにCSV化
		foreach ( $export['tables'] as $table => $rows ) {
			$csv_output .= "\n#TABLE:" . $table . "\n";
			if ( empty( $rows ) ) {
				continue;
			}
			$headers = array_keys( $rows[0] );
			$csv_output .= $this->csv_line( $headers );
			foreach ( $rows as $row ) {
				$line = array();
				foreach ( $headers as $h ) {
					$line[] = isset( $row[ $h ] ) ? (string) $row[ $h ] : '';
				}
				$csv_output .= $this->csv_line( $line );
			}
		}

		$filename = 'kantanpro-export-' . date_i18n( 'Ymd-His' ) . '.csv';
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		echo $csv_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * リストア実行
	 */
	public function handle_restore_data() {
		if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'backup' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=ktp-data-tools' ) );
			exit;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'kantanpro' ) );
		}
		if ( ! isset( $_POST['ktpwp_restore_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ktpwp_restore_nonce'] ) ), 'ktpwp_restore_data' ) ) {
			wp_die( esc_html__( 'セキュリティチェックに失敗しました。', 'kantanpro' ) );
		}

		$redirect = admin_url( 'admin.php?page=ktp-data-tools' );
		if ( ! isset( $_FILES['ktp_import_file'] ) || empty( $_FILES['ktp_import_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'ktp_action', 'restore_failed', $redirect ) );
			exit;
		}
		
		// 既存データを削除
		$this->clear_existing_data();

		$format = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : 'json';
		$format = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';

		if ( $format === 'json' ) {
			$contents = file_get_contents( $_FILES['ktp_import_file']['tmp_name'] );
			$data = json_decode( $contents, true );
			if ( ! is_array( $data ) || ! isset( $data['tables'] ) || ! isset( $data['options'] ) ) {
				wp_safe_redirect( add_query_arg( 'ktp_action', 'restore_failed', $redirect ) );
				exit;
			}
			$source_prefix = isset( $data['metadata']['db_prefix'] ) ? (string) $data['metadata']['db_prefix'] : '';
			$this->import_from_array( $data['options'], $data['tables'], $source_prefix );
		} else {
			// CSVインポート: 簡易フォーマット (#OPTIONS と #TABLE: テーブル名)
			$raw = file( $_FILES['ktp_import_file']['tmp_name'], FILE_IGNORE_NEW_LINES );
			if ( $raw === false ) {
				wp_safe_redirect( add_query_arg( 'ktp_action', 'restore_failed', $redirect ) );
				exit;
			}
			list( $options, $tables, $source_prefix ) = $this->parse_mixed_csv( $raw );
			if ( $options === null ) {
				wp_safe_redirect( add_query_arg( 'ktp_action', 'restore_failed', $redirect ) );
				exit;
			}
			$this->import_from_array( $options, $tables, $source_prefix );
		}

		wp_safe_redirect( add_query_arg( 'ktp_action', 'restore_success', $redirect ) );
		exit;
	}

	/**
	 * 既存データを削除
	 */
	private function clear_existing_data() {
		global $wpdb;
		
		// 外部キー制約を無効化
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		
		// 削除対象テーブル（外部キー制約のため順序が重要）
		$tables_to_clear = array(
			'ktp_order_cost_items',
			'ktp_order_invoice_items', 
			'ktp_order_staff_chat',
			'ktp_order',
			'ktp_supplier_skills',
			'ktp_service',
			'ktp_supplier',
			'ktp_client',
		);
		
		foreach ( $tables_to_clear as $table ) {
			$table_name = $wpdb->prefix . $table;
			$wpdb->query( "DELETE FROM {$table_name}" );
			$wpdb->query( "ALTER TABLE {$table_name} AUTO_INCREMENT = 1" );
		}
		
		// 外部キー制約を再有効化
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
	}

	/**
	 * プラグイン関連のオプション収集
	 *
	 * @return array
	 */
	private function collect_plugin_options() {
		global $wpdb;
		$like1 = $wpdb->esc_like( 'ktp_' ) . '%';
		$like2 = $wpdb->esc_like( 'ktpwp_' ) . '%';
		$option_names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like1, $like2 ) );
		$options = array();
		if ( $option_names ) {
			foreach ( $option_names as $name ) {
				$options[ $name ] = get_option( $name );
			}
		}
		return $options;
	}

	/**
	 * CSV 1行生成（RFC4180に近い簡易実装）
	 */
	private function csv_line( $fields ) {
		$escaped = array();
		foreach ( (array) $fields as $field ) {
			$field = (string) $field;
			$field = str_replace( '"', '""', $field );
			if ( strpos( $field, ',' ) !== false || strpos( $field, '"' ) !== false || strpos( $field, "\n" ) !== false || strpos( $field, "\r" ) !== false ) {
				$field = '"' . $field . '"';
			}
			$escaped[] = $field;
		}
		return implode( ',', $escaped ) . "\n";
	}

	/**
	 * 混在CSVのパース（#OPTIONS ブロックと #TABLE: ブロックを解析）
	 * @return array{0:?array,1:?array,2:string}
	 */
	private function parse_mixed_csv( array $lines ) {
		$options = array();
		$tables = array();
		$current = '';
		$headers = array();
		$table_name = '';
		$source_prefix = '';

		for ( $i = 0; $i < count( $lines ); $i++ ) {
			$line = (string) $lines[ $i ];
			if ( $line === '' ) { continue; }
			if ( strpos( $line, '#OPTIONS' ) === 0 ) {
				$current = 'options_header';
				$headers = array();
				continue;
			}
			if ( strpos( $line, '#TABLE:' ) === 0 ) {
				$current = 'table_header';
				$table_name = trim( substr( $line, 7 ) );
				$headers = array();
				continue;
			}
			// ヘッダー行処理
			if ( $current === 'options_header' ) {
				$headers = $this->parse_csv_row( $line );
				$current = 'options_rows';
				continue;
			}
			if ( $current === 'table_header' ) {
				$headers = $this->parse_csv_row( $line );
				$current = 'table_rows';
				continue;
			}
			// データ行
			if ( $current === 'options_rows' ) {
				$row = $this->parse_csv_row( $line );
				if ( count( $row ) >= 2 ) {
					$options[ $row[0] ] = json_decode( $row[1], true );
				}
				continue;
			}
			if ( $current === 'table_rows' ) {
				$row = $this->parse_csv_row( $line );
				if ( empty( $headers ) ) { continue; }
				$assoc = array();
				foreach ( $headers as $idx => $key ) {
					$assoc[ $key ] = isset( $row[ $idx ] ) ? $row[ $idx ] : '';
				}
				if ( ! isset( $tables[ $table_name ] ) ) { $tables[ $table_name ] = array(); }
				$tables[ $table_name ][] = $assoc;
				continue;
			}
		}

		return array( $options, $tables, $source_prefix );
	}

	/**
	 * CSV1行のパース
	 */
	private function parse_csv_row( $line ) {
		$fp = fopen( 'php://temp', 'r+' );
		fwrite( $fp, $line );
		rewind( $fp );
		$row = fgetcsv( $fp );
		fclose( $fp );
		return is_array( $row ) ? $row : array();
	}

	/**
	 * 共通配列形式からのインポート
	 */
	private function import_from_array( array $options, array $tables, $source_prefix ) {
		global $wpdb;
		// オプション
		foreach ( $options as $option_name => $option_value ) {
			update_option( $option_name, $option_value );
		}
		// テーブル
		foreach ( $tables as $source_table => $rows ) {
			$target_table = $this->map_table_to_current_prefix( $source_table, (string) $source_prefix, $wpdb->prefix );
			if ( empty( $target_table ) ) { continue; }
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $target_table ) );
			if ( $table_exists !== $target_table ) { continue; }
			$wpdb->query( 'START TRANSACTION' );
			try {
				if ( is_array( $rows ) ) {
					foreach ( $rows as $row ) {
						if ( ! is_array( $row ) ) { continue; }
						$columns = $wpdb->get_col( "DESC `{$target_table}`", 0 );
						$insert = array();
						foreach ( $row as $key => $value ) {
							if ( in_array( $key, $columns, true ) ) {
								$insert[ $key ] = $value;
							}
						}
						if ( ! empty( $insert ) ) {
							$wpdb->insert( $target_table, $insert );
						}
					}
				}
				$wpdb->query( 'COMMIT' );
			} catch ( Exception $e ) {
				$wpdb->query( 'ROLLBACK' );
			}
		}
	}

	/**
	 * プラグイン関連テーブル名の取得（現在のプレフィックス）
	 *
	 * @return string[]
	 */
	private function get_plugin_tables() {
		global $wpdb;
		$like = $wpdb->esc_like( $wpdb->prefix . 'ktp_' ) . '%';
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		return is_array( $tables ) ? $tables : array();
	}

	/**
	 * 別環境でエクスポートされたテーブル名を現在環境のプレフィックスに合わせて変換
	 *
	 * @param string $source_table  例: wp_ktp_order
	 * @param string $source_prefix 例: wp_
	 * @param string $target_prefix 現在の $wpdb->prefix
	 * @return string 変換後のテーブル名
	 */
	private function map_table_to_current_prefix( $source_table, $source_prefix, $target_prefix ) {
		$pos = strpos( $source_table, 'ktp_' );
		if ( $pos === false ) {
			return '';
		}
		$suffix = substr( $source_table, $pos );
		return $target_prefix . $suffix;
	}
    /**
     * 開発者設定ページの表示
     */
    public function create_developer_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'この設定ページにアクセスする権限がありません。', 'kantanpro' ) );
        }

        if ( ! $this->is_developer_settings_enabled() ) {
            wp_die( esc_html__( 'この設定ページは開発環境でのみアクセスできます。', 'kantanpro' ) );
        }

        if ( isset( $_POST['ktp_developer_edition_override'] ) && check_admin_referer( 'ktp_developer_edition_settings', 'ktp_developer_edition_nonce' ) ) {
            $override = sanitize_text_field( wp_unslash( $_POST['ktp_developer_edition_override'] ) );
            if ( $override === '' || ( class_exists( 'KTPWP_Edition' ) && in_array( $override, KTPWP_Edition::get_valid_slugs(), true ) ) ) {
                update_option( 'ktp_developer_edition_override', $override, false );
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'エディション設定を保存しました。', 'kantanpro' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( '無効なエディションが指定されました。', 'kantanpro' ) . '</p></div>';
            }
        }

        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-admin-tools" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span><?php echo esc_html__( '開発者設定', 'kantanpro' ); ?></h1>
            <div class="ktp-settings-container">
                <?php $this->render_developer_edition_settings(); ?>
                <?php $this->render_developer_system_info(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * 開発者設定: エディション・スタッフ上限（ローカル検証用）
     *
     * @return void
     */
    private function render_developer_edition_settings() {
        if ( ! class_exists( 'KTPWP_Edition' ) ) {
            return;
        }

        $baked_edition   = defined( 'KTPWP_EDITION' ) ? (string) KTPWP_EDITION : 'pro';
        $active_edition  = KTPWP_Edition::get_active_edition();
        $override        = get_option( 'ktp_developer_edition_override', '' );
        $definitions     = KTPWP_Edition::get_definitions();
        $staff_count     = KTPWP_Edition::count_staff_users();
        $staff_limit     = KTPWP_Edition::get_staff_limit();
        $plugin_name     = KTPWP_Edition::get_plugin_name();
        ?>
        <div class="ktp-settings-section">
            <h2><?php esc_html_e( 'エディション（配布ビルド検証）', 'kantanpro' ); ?></h2>
            <p><?php esc_html_e( '本番配布 ZIP ではビルド時にエディションが固定されます。ここではローカル環境でのみ、エディションを一時的に切り替えてスタッフ上限を検証できます。', 'kantanpro' ); ?></p>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'ビルド済みエディション', 'kantanpro' ); ?></th>
                        <td><?php echo esc_html( KTPWP_Edition::get_edition_label( $baked_edition ) . ' (' . $baked_edition . ')' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( '現在有効なエディション', 'kantanpro' ); ?></th>
                        <td><?php echo esc_html( KTPWP_Edition::get_edition_label( $active_edition ) . ' (' . $active_edition . ')' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'プラグイン名', 'kantanpro' ); ?></th>
                        <td><?php echo esc_html( $plugin_name ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'スタッフ上限', 'kantanpro' ); ?></th>
                        <td><?php echo esc_html( /* translators: 1: 権限の名称, 2: 現在の人数 */ sprintf( __( '%1$s（現在 %2$d 人）', 'kantanpro' ), KTPWP_Edition::format_staff_limit_display(), $staff_count ) ); ?></td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="">
                <?php wp_nonce_field( 'ktp_developer_edition_settings', 'ktp_developer_edition_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ktp_developer_edition_override"><?php esc_html_e( 'ローカル上書き', 'kantanpro' ); ?></label></th>
                            <td>
                                <select name="ktp_developer_edition_override" id="ktp_developer_edition_override">
                                    <option value=""><?php esc_html_e( 'ビルド済みのまま（上書きなし）', 'kantanpro' ); ?></option>
                                    <?php foreach ( $definitions as $slug => $definition ) : ?>
                                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $override, $slug ); ?>>
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    '%s — %s（スタッフ上限: %s）',
                                                    $definition['label'],
                                                    $definition['plugin_name'],
                                                    $definition['staff_limit'] > 0 ? (string) $definition['staff_limit'] : __( '無制限', 'kantanpro' )
                                                )
                                            );
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( '配布 ZIP 生成前に、各エディションのスタッフ制限が意図どおりか確認するための設定です。', 'kantanpro' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'エディション設定を保存', 'kantanpro' ) ); ?>
            </form>

            <h3><?php esc_html_e( '配布 ZIP ビルドコマンド', 'kantanpro' ); ?></h3>
            <p class="description"><?php esc_html_e( 'プラグインディレクトリで実行します。エディションごとに別フォルダ名・別 ZIP が生成されます。', 'kantanpro' ); ?></p>
            <ul style="font-family: monospace; font-size: 13px;">
                <li>./create_release_zip.sh pro</li>
                <li>./create_release_zip.sh solo</li>
                <li>./create_release_zip.sh team</li>
                <li>./create_release_zip.sh business</li>
            </ul>
        </div>
        <?php
    }

    /**
     * 開発者設定: システム情報
     *
     * @return void
     */
    private function render_developer_system_info() {
        global $wpdb;

        $host              = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
        $plugin_version    = defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : '';
        $db_version        = get_option( 'ktpwp_db_version', '' );
        $installed_date    = get_option( 'ktpwp_installed_date', '' );
        $dev_mode          = defined( 'KTPWP_DEVELOPMENT_MODE' ) && KTPWP_DEVELOPMENT_MODE;
        $kantanpro_dev     = defined( 'KANTANPRO_DEV_MODE' ) && KANTANPRO_DEV_MODE;
        $wp_debug          = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $mysql_version     = method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : '';
        $is_dev_env        = $this->is_development_environment();

        $rows = array(
            __( 'プラグイン', 'kantanpro' )         => ( defined( 'KANTANPRO_PLUGIN_NAME' ) ? KANTANPRO_PLUGIN_NAME : 'KantanPro' ) . ' ' . $plugin_version,
            __( 'DBバージョン', 'kantanpro' )       => $db_version !== '' ? $db_version : __( '不明', 'kantanpro' ),
            __( 'インストール日', 'kantanpro' )     => $installed_date !== '' ? $installed_date : __( '不明', 'kantanpro' ),
            __( 'WordPress', 'kantanpro' )          => get_bloginfo( 'version' ),
            __( 'PHP', 'kantanpro' )                => PHP_VERSION,
            __( 'データベース', 'kantanpro' )       => $mysql_version !== '' ? $mysql_version : __( '不明', 'kantanpro' ),
            __( 'サイトURL', 'kantanpro' )          => home_url( '/' ),
            __( 'ホスト名', 'kantanpro' )           => $host !== '' ? $host : __( '不明', 'kantanpro' ),
            __( '開発環境として判定', 'kantanpro' ) => $is_dev_env ? __( 'はい', 'kantanpro' ) : __( 'いいえ', 'kantanpro' ),
            __( '開発モード', 'kantanpro' )         => $dev_mode ? __( '有効', 'kantanpro' ) : __( '無効', 'kantanpro' ),
            __( 'KANTANPRO_DEV_MODE', 'kantanpro' ) => $kantanpro_dev ? __( '有効', 'kantanpro' ) : __( '無効', 'kantanpro' ),
            __( 'WP_DEBUG', 'kantanpro' )           => $wp_debug ? __( '有効', 'kantanpro' ) : __( '無効', 'kantanpro' ),
        );

        if ( class_exists( 'KTPWP_Edition' ) ) {
            $rows[ __( 'エディション', 'kantanpro' ) ]       = KTPWP_Edition::get_edition_label() . ' (' . KTPWP_Edition::get_active_edition() . ')';
            $rows[ __( 'スタッフ上限', 'kantanpro' ) ]       = KTPWP_Edition::format_staff_limit_display();
            $rows[ __( '登録スタッフ数', 'kantanpro' ) ]     = (string) KTPWP_Edition::count_staff_users();
        }
        ?>
        <div class="ktp-settings-section">
            <h2><?php esc_html_e( 'システム情報', 'kantanpro' ); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                <?php foreach ( $rows as $label => $value ) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $label ); ?></th>
                        <td><?php echo esc_html( (string) $value ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }






    /**
     * 通知メッセージのコールバック
     */
    public function notice_message_callback() {
        $options = get_option( 'ktp_donation_settings' );
        $default_message = __( 'このサイトの運営にご協力いただける方は、寄付をお願いいたします。', 'kantanpro' );
        ?>
        <textarea id="notice_message" 
                  name="ktp_donation_settings[notice_message]" 
                  rows="3" 
                  cols="50" 
                  class="large-text"><?php echo esc_textarea( isset( $options['notice_message'] ) ? $options['notice_message'] : $default_message ); ?></textarea>
        <p class="description"><?php esc_html_e( 'KantanPro管理権限を持つユーザー向けにフロントエンドで表示される寄付通知のメッセージ', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * 寄付設定セクション情報の表示
     */
    public function print_donation_section_info() {
        echo '<p>' . esc_html__( '寄付通知の表示設定を行います。フロントエンドでの寄付通知表示を制御できます。', 'kantanpro' ) . '</p>';
    }





    /**
     * フロントエンド通知の有効化コールバック
     */
    public function frontend_notice_enabled_callback() {
        $options = get_option( 'ktp_donation_settings' );
        $enabled = isset( $options['frontend_notice_enabled'] ) ? $options['frontend_notice_enabled'] : false;
        ?>
        <input type="checkbox" 
               id="frontend_notice_enabled" 
               name="ktp_donation_settings[frontend_notice_enabled]" 
               value="1" 
               <?php checked( $enabled, true ); ?>>
        <label for="frontend_notice_enabled"><?php esc_html_e( 'フロントエンド通知を有効にする', 'kantanpro' ); ?></label>
        <p class="description"><?php esc_html_e( 'このオプションを有効にすると、フロントエンドで寄付通知が表示されます。', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * 通知表示間隔のコールバック
     */
    public function notice_display_interval_callback() {
        $options = get_option( 'ktp_donation_settings' );
        $interval = isset( $options['notice_display_interval'] ) ? $options['notice_display_interval'] : 7;
        ?>
        <input type="number" 
               id="notice_display_interval" 
               name="ktp_donation_settings[notice_display_interval]" 
               value="<?php echo esc_attr( $interval ); ?>" 
               min="0" 
               max="365" 
               class="small-text">
        <p class="description"><?php esc_html_e( '通知を表示する間隔を日数で設定してください。0を設定すると他の条件に優先して常時表示されます（ローカル開発環境でのテスト用）。', 'kantanpro' ); ?></p>
        <?php
    }



    /**
     * 寄付URLのコールバック
     */
    public function donation_url_callback() {
        $options = get_option( 'ktp_donation_settings' );
        ?>
        <input type="url" 
               id="donation_url" 
               name="ktp_donation_settings[donation_url]" 
               value="<?php echo isset( $options['donation_url'] ) ? esc_url( $options['donation_url'] ) : ''; ?>" 
               class="regular-text" 
               placeholder="https://example.com/donation">
        <p class="description"><?php esc_html_e( '寄付通知の「寄付する」ボタンをクリックした際に遷移するURL', 'kantanpro' ); ?></p>
        <p class="description"><?php esc_html_e( '空欄の場合は https://www.kantanpro.com/donation が使用されます', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * 寄付通知プレビューのコールバック
     */
    public function donation_notice_preview_callback() {
        $donation_settings = get_option( 'ktp_donation_settings', array() );
        $message = isset( $donation_settings['notice_message'] ) ? $donation_settings['notice_message'] : __( 'このサイトの運営にご協力いただける方は、寄付をお願いいたします。', 'kantanpro' );
        
        ?>
        <div class="ktpwp-notice-preview-container">
            <h4><?php esc_html_e( '現在の設定での通知表示例：', 'kantanpro' ); ?></h4>
            
            <div id="ktpwp-notice-preview" class="ktpwp-donation-notice" style="position: relative; top: auto; left: auto; right: auto; z-index: 1; margin: 10px 0;">
                <div class="ktpwp-notice-content">
                    <span class="ktpwp-notice-icon">💝</span>
                    <span class="ktpwp-notice-message"><?php echo esc_html( $message ); ?></span>
                    <div class="ktpwp-notice-actions">
                        <?php
        // 管理者情報を取得
        $admin_email = get_option( 'admin_email' );
        $admin_name = get_option( 'blogname' );
        
        // プレビュー用のURL（実際の設定値またはデフォルト）
        $preview_url = isset( $donation_settings['donation_url'] ) && ! empty( $donation_settings['donation_url'] ) 
            ? $donation_settings['donation_url'] 
            : 'https://www.kantanpro.com/donation';
        
        // POSTパラメータを追加
        $preview_url_with_params = add_query_arg( array(
            'admin_email' => urlencode( $admin_email ),
            'admin_name' => urlencode( $admin_name )
        ), $preview_url );
        ?>
        <a href="<?php echo esc_url( $preview_url_with_params ); ?>" class="ktpwp-notice-donate-btn" target="_blank" rel="noopener"><?php esc_html_e( '寄付する', 'kantanpro' ); ?></a>
                        <button type="button" class="ktpwp-notice-dismiss-btn" aria-label="<?php esc_attr_e( '閉じる', 'kantanpro' ); ?>">×</button>
                    </div>
                </div>
            </div>
            
            <div class="ktpwp-preview-controls">
                <button type="button" class="button" onclick="testNoticeDisplay()">
                    <?php esc_html_e( '通知表示テスト', 'kantanpro' ); ?>
                </button>
                <button type="button" class="button" onclick="testNoticeDismiss()">
                    <?php esc_html_e( '閉じるテスト', 'kantanpro' ); ?>
                </button>
            </div>
            
            <div class="ktpwp-preview-info">
                <p><strong><?php esc_html_e( '表示条件：', 'kantanpro' ); ?></strong></p>
                <ul>
                    <li><?php esc_html_e( 'KantanPro管理権限を持つログインユーザーのみ', 'kantanpro' ); ?></li>
                    <li><?php esc_html_e( 'KantanProが設置されているページにアクセス', 'kantanpro' ); ?></li>
                    <li><?php esc_html_e( 'フロントエンド通知が有効', 'kantanpro' ); ?></li>
                    <li><?php esc_html_e( 'ユーザーがまだ寄付していない', 'kantanpro' ); ?></li>
                    <li><?php esc_html_e( 'ユーザーが通知を拒否していない（拒否した場合は月に1回表示）', 'kantanpro' ); ?></li>
                </ul>
            </div>
        </div>

        <script>
        function testNoticeDisplay() {
            var $preview = jQuery('#ktpwp-notice-preview');
            $preview.fadeOut(300, function() {
                setTimeout(function() {
                    $preview.fadeIn(500);
                }, 100);
            });
        }
        
        function testNoticeDismiss() {
            var $preview = jQuery('#ktpwp-notice-preview');
            $preview.fadeOut(300);
        }
        </script>

        <style>
        .ktpwp-notice-preview-container {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .ktpwp-preview-controls {
            margin: 15px 0;
        }
        
        .ktpwp-preview-controls .button {
            margin-right: 10px;
        }
        
        .ktpwp-preview-info {
            margin-top: 15px;
            padding: 15px;
            background: #fff;
            border-left: 4px solid #0073aa;
        }
        
        .ktpwp-preview-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .ktpwp-preview-info li {
            margin: 5px 0;
        }
        </style>
        <?php
    }





    /**
     * 指定月の寄付総額を取得
     */


    /**
     * 寄付セクションの表示
     */




    /**
     * 寄付設定のサニタイズ
     */
    public function sanitize_donation_settings( $input ) {
        // 既存の設定を取得
        $existing_settings = get_option( 'ktp_donation_settings', array() );
        $new_input = $existing_settings;
        
        // フロントエンド通知の有効化（チェックボックス）
        if ( isset( $input['frontend_notice_enabled'] ) ) {
            $new_input['frontend_notice_enabled'] = (bool) $input['frontend_notice_enabled'];
        } else {
            $new_input['frontend_notice_enabled'] = false;
        }
        
        // 通知表示間隔
        if ( isset( $input['notice_display_interval'] ) ) {
            $new_input['notice_display_interval'] = max( 0, min( 365, absint( $input['notice_display_interval'] ) ) );
        } else {
            $new_input['notice_display_interval'] = isset( $existing_settings['notice_display_interval'] ) ? $existing_settings['notice_display_interval'] : 7;
        }
        
        // 通知メッセージ
        if ( isset( $input['notice_message'] ) ) {
            $new_input['notice_message'] = sanitize_textarea_field( $input['notice_message'] );
        } else {
            $new_input['notice_message'] = isset( $existing_settings['notice_message'] ) ? $existing_settings['notice_message'] : __( 'このサイトの運営にご協力いただける方は、寄付をお願いいたします。', 'kantanpro' );
        }
        
        // 寄付URL
        if ( isset( $input['donation_url'] ) ) {
            $donation_url = esc_url_raw( $input['donation_url'] );
            $new_input['donation_url'] = $donation_url;
        } else {
            $new_input['donation_url'] = isset( $existing_settings['donation_url'] ) ? $existing_settings['donation_url'] : '';
        }
        
        return $new_input;
    }

    /**
     * API キーの暗号化
     */
    private function encrypt_api_key( $key ) {
        if ( empty( $key ) ) {
            return '';
        }
        
        return base64_encode( $key );
    }

    /**
     * API キーの復号化
     */
    private function decrypt_api_key( $encrypted_key ) {
        if ( empty( $encrypted_key ) ) {
            return '';
        }
        
        return base64_decode( $encrypted_key );
    }

    /**
     * API キーのマスク表示
     */
    private function mask_api_key( $key ) {
        if ( empty( $key ) ) {
            return '';
        }
        
        if ( strlen( $key ) <= 8 ) {
            return str_repeat( '*', strlen( $key ) );
        }
        
        return substr( $key, 0, 4 ) . str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -4 );
    }

    /**
     * スタッフ管理ページの表示
     */
    public function create_staff_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'この設定ページにアクセスする権限がありません。', 'kantanpro' ) );
        }

        // KantanPro利用権限（ktpwp_access）付加/削除処理
        if ( isset( $_POST['ktpwp_access_user'] ) && isset( $_POST['ktpwp_access_action'] ) && check_admin_referer( 'ktp_staff_role_action', 'ktp_staff_role_nonce' ) ) {
            $user_id = intval( $_POST['ktpwp_access_user'] );
            $action = sanitize_text_field( $_POST['ktpwp_access_action'] );
            $user_obj = get_userdata( $user_id );
            if ( $user_obj ) {
                if ( $action === 'add' ) {
                    if ( class_exists( 'KTPWP_Edition' ) && ! KTPWP_Edition::can_add_staff() ) {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( KTPWP_Edition::get_staff_limit_reached_message() ) . '</p></div>';
                    } else {
                    $user_obj->add_cap( 'ktpwp_access' );
                    // 最終変更日時を記録
                    update_user_meta( $user_id, 'last_activity', current_time( 'mysql' ) );
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'KantanPro利用権限（ktpwp_access）を付加しました。', 'kantanpro' ) . '</p></div>';

                    // スタッフ追加時のメール通知を送信
                    $mail_sent = $this->send_staff_notification_email( $user_obj, 'add' );
                    if ( $mail_sent ) {
                        /* translators: %s: 送信先メールアドレス */
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'スタッフ追加の通知メールを %s に送信しました。', 'kantanpro' ), $user_obj->user_email ) ) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'スタッフ追加の通知メール送信に失敗しました。メール設定をご確認ください。', 'kantanpro' ) . '</p></div>';
                    }
                    }
                } elseif ( $action === 'remove' ) {
                    $user_obj->remove_cap( 'ktpwp_access' );
                    // 最終変更日時を記録
                    update_user_meta( $user_id, 'last_activity', current_time( 'mysql' ) );
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'KantanPro利用権限（ktpwp_access）を削除しました。', 'kantanpro' ) . '</p></div>';

                    // スタッフ削除時のメール通知を送信
                    $mail_sent = $this->send_staff_notification_email( $user_obj, 'remove' );
                    if ( $mail_sent ) {
                        /* translators: %s: 送信先メールアドレス */
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'スタッフ削除の通知メールを %s に送信しました。', 'kantanpro' ), $user_obj->user_email ) ) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'スタッフ削除の通知メール送信に失敗しました。メール設定をご確認ください。', 'kantanpro' ) . '</p></div>';
                    }
                }
            }
        }

        // 管理者以外のユーザーのみ取得
        $users = get_users( array( 'role__not_in' => array( 'administrator' ) ) );
        $staff_count = class_exists( 'KTPWP_Edition' ) ? KTPWP_Edition::count_staff_users() : 0;
        $staff_limit = class_exists( 'KTPWP_Edition' ) ? KTPWP_Edition::get_staff_limit() : 0;
        $staff_limit_display = class_exists( 'KTPWP_Edition' ) ? KTPWP_Edition::format_staff_limit_display() : __( '無制限', 'kantanpro' );
        $can_add_staff = class_exists( 'KTPWP_Edition' ) ? KTPWP_Edition::can_add_staff() : true;
        global $wp_roles;
        // $all_roles = $wp_roles->roles; // プルダウンがなくなったため不要
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-groups" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span><?php echo esc_html__( 'スタッフ管理', 'kantanpro' ); ?></h1>



            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <h2><?php echo esc_html__( 'スタッフ枠', 'kantanpro' ); ?></h2>
                    <p style="margin-bottom: 0;">
                        <?php
                        if ( class_exists( 'KTPWP_Edition' ) ) {
                            echo esc_html(
                                sprintf(
                                    /* translators: 1: current staff count, 2: staff limit label */
                                    __( '現在 %1$d 人 / 上限 %2$s', 'kantanpro' ),
                                    $staff_count,
                                    $staff_limit_display
                                )
                            );
                            if ( class_exists( 'KTPWP_Edition' ) ) {
                                echo ' — ';
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: edition label */
                                        __( 'エディション: %s', 'kantanpro' ),
                                        KTPWP_Edition::get_edition_label()
                                    )
                                );
                            }
                        }
                        ?>
                    </p>
                    <?php if ( ! $can_add_staff ) : ?>
                        <div class="notice notice-warning inline" style="margin-top: 12px;">
                            <p><?php echo esc_html( KTPWP_Edition::get_staff_limit_reached_message() ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="ktp-settings-section">
                    <h2><?php echo esc_html__( '登録スタッフ一覧', 'kantanpro' ); ?></h2>
                    <div style="margin-bottom: 10px; color: #555; font-size: 13px;">
                        <?php echo esc_html__( '管理者は登録者の権限に関わらずここでスタッフの追加削除が行えます', 'kantanpro' ); ?>
                    </div>
                    <div style="margin-bottom: 15px; padding: 12px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px; font-size: 13px;">
                        <span class="dashicons dashicons-info" style="color: #0073aa; margin-right: 5px;"></span>
                        <strong><?php echo esc_html__( 'メール通知について：', 'kantanpro' ); ?></strong><?php echo esc_html__( 'スタッフの追加・削除時に、該当ユーザーへ自動でメール通知が送信されます。', 'kantanpro' ); ?>
                        <?php echo esc_html__( '通知内容にはログイン情報や権限の変更についての案内が含まれます。', 'kantanpro' ); ?>
                    </div>
                    <table class="widefat fixed striped ktp-staff-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( '表示名', 'kantanpro' ); ?></th>
                                <th><?php esc_html_e( 'メールアドレス', 'kantanpro' ); ?></th>
                                <th><?php esc_html_e( 'スタッフ', 'kantanpro' ); ?></th>
                                <th><?php esc_html_e( '最終変更日時', 'kantanpro' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $users as $user ) : ?>
                            <tr>
                                <td>
                                    <?php
                                    $profile_link = get_edit_user_link( $user->ID );
                                    if ( empty( $profile_link ) ) {
                                        $profile_link = admin_url( 'user-edit.php?user_id=' . (int) $user->ID );
                                    }
                                    ?>
                                    <a href="<?php echo esc_url( $profile_link ); ?>" title="<?php echo esc_attr__( 'プロフィールを開く', 'kantanpro' ); ?>">
                                        <?php echo esc_html( $user->display_name ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <?php if ( $user->has_cap( 'ktpwp_access' ) ) : ?>
                                            <span style="color:green;font-weight:bold;"><?php echo esc_html__( '利用中', 'kantanpro' ); ?></span>
                                        <?php else : ?>
                                            <span style="color:red;"><?php echo esc_html__( '未使用', 'kantanpro' ); ?></span>
                                        <?php endif; ?>

                                        <form method="post" class="ktpwp-staff-toggle-form" style="display: flex; align-items: center; gap: 10px; margin-bottom: 0;">
                                            <?php wp_nonce_field( 'ktp_staff_role_action', 'ktp_staff_role_nonce' ); ?>
                                            <input type="hidden" name="ktpwp_access_user" value="<?php echo esc_attr( $user->ID ); ?>">
                                            <input type="hidden" name="ktpwp_access_action" class="ktpwp-staff-toggle-action" value="">
                                            <?php
                                            $checkbox_id = 'ktpwp_staff_toggle_' . (int) $user->ID;
                                            $checked = $user->has_cap( 'ktpwp_access' );
                                            $toggle_disabled = ! $checked && ! $can_add_staff;
                                            ?>
                                            <label class="ktpwp-switch<?php echo $toggle_disabled ? ' ktpwp-switch--disabled' : ''; ?>" for="<?php echo esc_attr( $checkbox_id ); ?>" title="<?php echo esc_attr( $checked ? 'ON（利用中）' : ( $toggle_disabled ? __( 'スタッフ上限に達しています', 'kantanpro' ) : 'OFF（未使用）' ) ); ?>">
                                                <input
                                                    type="checkbox"
                                                    id="<?php echo esc_attr( $checkbox_id ); ?>"
                                                    class="ktpwp-staff-toggle-checkbox"
                                                    <?php checked( $checked ); ?>
                                                    <?php disabled( $toggle_disabled ); ?>
                                                    aria-label="<?php echo esc_attr( sprintf( 'staff-toggle-%d', (int) $user->ID ) ); ?>"
                                                />
                                                <span class="ktpwp-switch-slider" aria-hidden="true"></span>
                                            </label>
                                            <noscript>
                                                <button type="submit" class="button"><?php esc_html_e( '適用', 'kantanpro' ); ?></button>
                                            </noscript>
                                        </form>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    // WordPressのユーザーメタからカスタムフィールドで最終更新日時を取得
                                    $last_modified = get_user_meta( $user->ID, 'last_activity', true );

                                    // カスタムフィールドがない場合は、ユーザー登録日時を使用
                                    if ( empty( $last_modified ) ) {
                                        $last_modified = $user->user_registered;
                                    }

                                    // 日時をフォーマットして表示
                                    if ( ! empty( $last_modified ) ) {
                                        echo esc_html( date_i18n( 'Y-m-d H:i', strtotime( $last_modified ) ) );
                                    } else {
                                        echo esc_html__( '未記録', 'kantanpro' );
                                    } ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
        (function() {
            const forms = document.querySelectorAll('.ktpwp-staff-toggle-form');
            forms.forEach((form) => {
                const checkbox = form.querySelector('.ktpwp-staff-toggle-checkbox');
                const actionInput = form.querySelector('.ktpwp-staff-toggle-action');
                if (!checkbox || !actionInput) return;

                checkbox.addEventListener('change', function() {
                    // ON -> add, OFF -> remove
                    actionInput.value = this.checked ? 'add' : 'remove';
                    form.submit();
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * スタッフ追加・削除時のメール通知を送信
     *
     * @since 1.0.0
     * @param WP_User $user_obj 対象ユーザーオブジェクト
     * @param string  $action 'add' または 'remove'
     * @return bool 送信成功/失敗
     */
    private function send_staff_notification_email( $user_obj, $action ) {
        // メールアドレスが存在しない場合は送信しない
        if ( empty( $user_obj->user_email ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Staff Notification: メールアドレスが未設定のため通知を送信できません (User ID: ' . $user_obj->ID . ')' );
            }
            return false;
        }

        // SMTP設定を取得
        $smtp_settings = get_option( 'ktp_smtp_settings', array() );
        $from_email = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : get_option( 'admin_email' );
        $from_name = ! empty( $smtp_settings['smtp_from_name'] ) ? sanitize_text_field( $smtp_settings['smtp_from_name'] ) : get_bloginfo( 'name' );

        // 会社情報を取得
        $company_info = self::get_company_info();
        if ( empty( $company_info ) ) {
            $company_info = get_bloginfo( 'name' );
        } else {
            // HTMLタグを除去してプレーンテキストに変換
            $company_info = wp_strip_all_tags( $company_info );
        }

        // メール内容を生成
        $to = sanitize_email( $user_obj->user_email );
        $display_name = ! empty( $user_obj->display_name ) ? $user_obj->display_name : $user_obj->user_login;

        if ( $action === 'add' ) {
            /* translators: %s: サイト名 */
            $subject = sprintf( __( '[%s] スタッフ権限が付与されました', 'kantanpro' ), get_bloginfo( 'name' ) );
            /* translators: %s: 宛名（顧客名または担当者名） */
            $body = sprintf( __( '%s 様', 'kantanpro' ), $display_name ) . "\n\n";
            /* translators: %s: サイト名 */
            $body .= sprintf( __( 'この度、%s の業務管理システム（KantanPro）のスタッフ権限が付与されました。', 'kantanpro' ), get_bloginfo( 'name' ) ) . "\n\n";
            $body .= __( '以下のURLからログインして、システムをご利用ください：', 'kantanpro' ) . "\n";
            $body .= wp_login_url() . "\n\n";
            $body .= __( 'ログイン情報：', 'kantanpro' ) . "\n";
            $body .= __( 'ユーザー名: ', 'kantanpro' ) . $user_obj->user_login . "\n";
            $body .= __( 'メールアドレス: ', 'kantanpro' ) . $user_obj->user_email . "\n\n";
            $body .= __( 'パスワードをお忘れの場合は、ログイン画面の「パスワードをお忘れですか？」からリセットしてください。', 'kantanpro' ) . "\n\n";
            $body .= __( 'ご不明な点がございましたら、システム管理者までお問い合わせください。', 'kantanpro' ) . "\n\n";
        } else {
            /* translators: %s: サイト名 */
            $subject = sprintf( __( '[%s] スタッフ権限が削除されました', 'kantanpro' ), get_bloginfo( 'name' ) );
            /* translators: %s: 宛名（顧客名または担当者名） */
            $body = sprintf( __( '%s 様', 'kantanpro' ), $display_name ) . "\n\n";
            /* translators: %s: サイト名 */
            $body .= sprintf( __( '%s の業務管理システム（KantanPro）のスタッフ権限が削除されました。', 'kantanpro' ), get_bloginfo( 'name' ) ) . "\n\n";
            $body .= __( '今後、システムへのアクセスができなくなります。', 'kantanpro' ) . "\n";
            $body .= __( 'ご質問がございましたら、システム管理者までお問い合わせください。', 'kantanpro' ) . "\n\n";
        }

        // 署名を追加
        if ( ! empty( $company_info ) ) {
            $body .= '―――――――――――――――――――――――――――' . "\n";
            $body .= $company_info . "\n";
        }

        // 自動送信であることを明記
        $body .= "\n" . __( '※ このメールは自動送信されています。', 'kantanpro' ) . "\n";

        // ヘッダーを設定
        $headers = array();
        if ( ! empty( $from_email ) ) {
            if ( ! empty( $from_name ) ) {
                $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
            } else {
                $headers[] = 'From: ' . $from_email;
            }
        }

        // メール送信を実行
        $sent = wp_mail( $to, $subject, $body, $headers );

        // ログ出力（詳細なエラー情報を含む）
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( $sent ) {
                error_log( 'KTPWP Staff Notification: ' . $action . ' 通知メールを送信しました (User: ' . $display_name . ', Email: ' . $to . ')' );
            } else {
                // PHPMailerのエラー情報を取得
                global $phpmailer;
                $error_message = '';
                if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
                    $error_message = $phpmailer->ErrorInfo;
                }
                error_log( 'KTPWP Staff Notification: ' . $action . ' 通知メールの送信に失敗しました (User: ' . $display_name . ', Email: ' . $to . ', Error: ' . $error_message . ')' );
            }
        }

        return $sent;
    }

    /**
     * ユーザーの最終ログイン時間を記録
     *
     * @since 1.0.0
     * @param string  $user_login ユーザーログイン名
     * @param WP_User $user ユーザーオブジェクト
     * @return void
     */
    public function record_user_last_login( $user_login, $user ) {
        // KantanPro利用権限を持つユーザーのみ記録
        if ( $user->has_cap( 'ktpwp_access' ) || $user->has_cap( 'manage_options' ) ) {
            update_user_meta( $user->ID, 'last_activity', current_time( 'mysql' ) );
        }
    }

    public function create_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'この設定ページにアクセスする権限がありません。', 'kantanpro' ) );
        }

        // 初期設定値がない場合は作成
        if ( false === get_option( $this->option_name ) ) {
            add_option(
                $this->option_name,
                array(
					'email_address' => '',
					'smtp_host' => '',
					'smtp_port' => '',
					'smtp_user' => '',
					'smtp_pass' => '',
					'smtp_secure' => '',
					'smtp_from_name' => '',
                )
            );
        }

        $options = get_option( $this->option_name );
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-email" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span><?php echo esc_html__( 'メール・SMTP設定', 'kantanpro' ); ?></h1>
            
            <?php
            // 通知表示
            settings_errors( 'ktp_settings' );

            if ( isset( $_POST['test_email'] ) ) {
                $this->send_test_email();
            }

            // スタイリングされたコンテナ
            echo '<div class="ktp-settings-container">';

            // メール設定フォーム
            echo '<div class="ktp-settings-section">';
            echo '<form method="post" action="options.php">';
            settings_fields( $this->options_group );

            global $wp_settings_sections, $wp_settings_fields;

            // メール設定セクションの出力
            if ( isset( $wp_settings_sections['ktp-settings']['email_setting_section'] ) ) {
                $section = $wp_settings_sections['ktp-settings']['email_setting_section'];
                echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
                }
                if ( isset( $wp_settings_fields['ktp-settings']['email_setting_section'] ) ) {
                    echo '<table class="form-table">';
                    foreach ( $wp_settings_fields['ktp-settings']['email_setting_section'] as $field ) {
                        echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                        call_user_func( $field['callback'], $field['args'] );
                        echo '</td></tr>';
                    }
                    echo '</table>';
                }
            }

            // SMTP設定セクションの出力
            if ( isset( $wp_settings_sections['ktp-settings']['smtp_setting_section'] ) ) {
                $section = $wp_settings_sections['ktp-settings']['smtp_setting_section'];
                echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
                }
                if ( isset( $wp_settings_fields['ktp-settings']['smtp_setting_section'] ) ) {
                    echo '<table class="form-table">';
                    foreach ( $wp_settings_fields['ktp-settings']['smtp_setting_section'] as $field ) {
                        echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                        call_user_func( $field['callback'], $field['args'] );
                        echo '</td></tr>';
                    }
                    echo '</table>';
                }
            }

            echo '<div class="ktp-submit-button">';
            submit_button( __( '設定を保存', 'kantanpro' ), 'primary', 'submit', false );
            echo '</div>';
            echo '</form>';

            // テストメール送信フォーム
            echo '<div class="ktp-test-mail-form">';
            echo '<h3>' . esc_html__( 'テストメール送信', 'kantanpro' ) . '</h3>';
            echo '<p>' . esc_html__( 'SMTPの設定が正しく機能しているか確認するためのテストメールを送信します。', 'kantanpro' ) . '</p>';
            echo '<form method="post">';
            echo '<input type="hidden" name="test_email" value="1">';
            submit_button( __( 'テストメール送信', 'kantanpro' ), 'secondary', 'submit', false );
            echo '</form>';
            echo '</div>';

            // 印刷ボタンセクション
            // 印刷機能は削除されました

            echo '</div>'; // .ktp-settings-section
            echo '</div>'; // .ktp-settings-container
            ?>
        </div>
        <?php
    }

    /**
     * 一般設定ページの表示
     *
     * @since 1.0.0
     * @return void
     */
    public function create_general_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'この設定ページにアクセスする権限がありません。', 'kantanpro' ) );
        } ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-admin-settings" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span><?php echo esc_html__( '一般設定', 'kantanpro' ); ?></h1>
            
            <?php
            // 通知表示
            settings_errors( 'ktp_general_settings' );
            ?>
            
            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( 'ktp_general_group' );

                        // 一般設定セクションの出力
                        global $wp_settings_sections, $wp_settings_fields;
                        if ( isset( $wp_settings_sections['ktp-general']['general_setting_section'] ) ) {
                            $section = $wp_settings_sections['ktp-general']['general_setting_section'];
                            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                            if ( $section['callback'] ) {
                                call_user_func( $section['callback'], $section );
                            }
                            if ( isset( $wp_settings_fields['ktp-general']['general_setting_section'] ) ) {
                                echo '<table class="form-table">';
                                foreach ( $wp_settings_fields['ktp-general']['general_setting_section'] as $field ) {
                                    echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                    call_user_func( $field['callback'], $field['args'] );
                                    echo '</td></tr>';
                                }
                                echo '</table>';
                            }
                        }

                        // 消費税設定セクションの出力
                        if ( isset( $wp_settings_sections['ktp-general']['tax_setting_section'] ) ) {
                            $section = $wp_settings_sections['ktp-general']['tax_setting_section'];
                            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                            if ( $section['callback'] ) {
                                call_user_func( $section['callback'], $section );
                            }
                            if ( isset( $wp_settings_fields['ktp-general']['tax_setting_section'] ) ) {
                                echo '<table class="form-table">';
                                foreach ( $wp_settings_fields['ktp-general']['tax_setting_section'] as $field ) {
                                    echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                    call_user_func( $field['callback'], $field['args'] );
                                    echo '</td></tr>';
                                }
                                echo '</table>';
                            }
                        }

                        // 振込先口座セクションの出力
                        if ( isset( $wp_settings_sections['ktp-general']['bank_transfer_setting_section'] ) ) {
                            $section = $wp_settings_sections['ktp-general']['bank_transfer_setting_section'];
                            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                            if ( $section['callback'] ) {
                                call_user_func( $section['callback'], $section );
                            }
                            if ( isset( $wp_settings_fields['ktp-general']['bank_transfer_setting_section'] ) ) {
                                echo '<table class="form-table">';
                                foreach ( $wp_settings_fields['ktp-general']['bank_transfer_setting_section'] as $field ) {
                                    echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                    call_user_func( $field['callback'], $field['args'] );
                                    echo '</td></tr>';
                                }
                                echo '</table>';
                            }
                        }

                        // 定期請求メール
                        if ( isset( $wp_settings_sections['ktp-general']['contract_reminder_setting_section'] ) ) {
                            $section = $wp_settings_sections['ktp-general']['contract_reminder_setting_section'];
                            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                            if ( $section['callback'] ) {
                                call_user_func( $section['callback'], $section );
                            }
                            if ( isset( $wp_settings_fields['ktp-general']['contract_reminder_setting_section'] ) ) {
                                echo '<table class="form-table">';
                                foreach ( $wp_settings_fields['ktp-general']['contract_reminder_setting_section'] as $field ) {
                                    echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                    call_user_func( $field['callback'], $field['args'] );
                                    echo '</td></tr>';
                                }
                                echo '</table>';
                            }
                        }

                        // Stripe 請求連携
                        if ( isset( $wp_settings_sections['ktp-general']['stripe_billing_setting_section'] ) ) {
                            $section = $wp_settings_sections['ktp-general']['stripe_billing_setting_section'];
                            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                            if ( $section['callback'] ) {
                                call_user_func( $section['callback'], $section );
                            }
                            if ( isset( $wp_settings_fields['ktp-general']['stripe_billing_setting_section'] ) ) {
                                echo '<table class="form-table">';
                                foreach ( $wp_settings_fields['ktp-general']['stripe_billing_setting_section'] as $field ) {
                                    echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                    call_user_func( $field['callback'], $field['args'] );
                                    echo '</td></tr>';
                                }
                                echo '</table>';
                            }
                        }

                        // 日本郵便 郵便番号・デジタルアドレスAPI（顧客フォームの住所自動入力）
                        if ( isset( $wp_settings_sections['ktp-general']['japanpost_api_setting_section'] ) ) {
                            $section = $wp_settings_sections['ktp-general']['japanpost_api_setting_section'];
                            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                            if ( $section['callback'] ) {
                                call_user_func( $section['callback'], $section );
                            }
                            if ( isset( $wp_settings_fields['ktp-general']['japanpost_api_setting_section'] ) ) {
                                echo '<table class="form-table">';
                                foreach ( $wp_settings_fields['ktp-general']['japanpost_api_setting_section'] as $field ) {
                                    echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                    call_user_func( $field['callback'], $field['args'] );
                                    echo '</td></tr>';
                                }
                                echo '</table>';
                            }
                        }

                        // プラグイン削除時のデータ保持設定セクションの出力
                        if ( isset( $wp_settings_sections['ktp-general']['uninstall_setting_section'] ) ) {
                            $section = $wp_settings_sections['ktp-general']['uninstall_setting_section'];
                            echo '<h2 id="uninstall_setting_section" style="margin-top:40px;border-top:1px solid #ddd;padding-top:20px;">' . esc_html( $section['title'] ) . '</h2>';
                            if ( $section['callback'] ) {
                                call_user_func( $section['callback'], $section );
                            }
                            if ( isset( $wp_settings_fields['ktp-general']['uninstall_setting_section'] ) ) {
                                echo '<table class="form-table">';
                                foreach ( $wp_settings_fields['ktp-general']['uninstall_setting_section'] as $field ) {
                                    echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                    call_user_func( $field['callback'], $field['args'] );
                                    echo '</td></tr>';
                                }
                                echo '</table>';
                            }
                        } ?>
                        
                        <div class="ktp-submit-button">
                            <?php submit_button( __( '設定を保存', 'kantanpro' ), 'primary', 'submit', false ); ?>
                        </div>
                    </form>
                </div>

                <?php $this->render_shortcodes_reference_section(); ?>

            </div>
        </div>
        <?php
    }

    /**
     * 一般設定ページ下部：利用可能なショートコード一覧
     *
     * @return void
     */
    private function render_shortcodes_reference_section() {
        ?>
        <div class="ktp-settings-section ktp-shortcodes-reference">
            <h2><?php echo esc_html__( 'ショートコード', 'kantanpro' ); ?></h2>
            <p class="description">
                <?php echo esc_html__( '固定ページや投稿の本文に以下のショートコードを設置して利用できます。', 'kantanpro' ); ?>
            </p>

            <table class="widefat striped ktp-shortcodes-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__( 'ショートコード', 'kantanpro' ); ?></th>
                        <th scope="col"><?php echo esc_html__( '用途', 'kantanpro' ); ?></th>
                        <th scope="col"><?php echo esc_html__( '備考', 'kantanpro' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[ktpwp_all_tab]</code></td>
                        <td><?php echo esc_html__( 'KantanPro の業務画面（顧客・案件・商品など全タブ）', 'kantanpro' ); ?></td>
                        <td><?php echo esc_html__( 'ログイン必須。社内・会員向けページに設置します。', 'kantanpro' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>[kantanpro_ex]</code></td>
                        <td><?php echo esc_html__( '上記と同じ（別名）', 'kantanpro' ); ?></td>
                        <td><?php echo esc_html__( 'ログイン必須。', 'kantanpro' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ktpwp_public_products]</code></td>
                        <td><?php echo esc_html__( '公開商品の一覧表示', 'kantanpro' ); ?></td>
                        <td><?php echo esc_html__( 'ログイン不要。商品編集で「サイトに公開」にチェックした商品のみ表示されます。', 'kantanpro' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3 class="ktp-shortcodes-subheading"><?php echo esc_html__( '[ktpwp_public_products] の属性', 'kantanpro' ); ?></h3>
            <table class="widefat striped ktp-shortcodes-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__( '属性', 'kantanpro' ); ?></th>
                        <th scope="col"><?php echo esc_html__( '既定値', 'kantanpro' ); ?></th>
                        <th scope="col"><?php echo esc_html__( '説明', 'kantanpro' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>layout</code></td>
                        <td><code>grid</code></td>
                        <td><?php echo esc_html__( '表示形式: grid / table / cards', 'kantanpro' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>columns</code></td>
                        <td><code>3</code></td>
                        <td><?php echo esc_html__( '列数（1〜4）。grid / cards で有効', 'kantanpro' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>category</code></td>
                        <td><?php echo esc_html__( '（空）', 'kantanpro' ); ?></td>
                        <td><?php echo esc_html__( 'カテゴリで絞り込み（サーバー側）。複数指定時はカンマ区切り（例: サポート,WEB制作）。絞り込み UI 表示時は単一指定のみ初期値に使用', 'kantanpro' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>show_filter</code></td>
                        <td><code>yes</code></td>
                        <td><?php echo esc_html__( 'カテゴリ絞り込み UI（サジェスト付き）の表示 ON/OFF', 'kantanpro' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>ids</code></td>
                        <td><?php echo esc_html__( '（空）', 'kantanpro' ); ?></td>
                        <td><?php echo esc_html__( '表示する商品 ID（例: 2,5,8）', 'kantanpro' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>show_image</code> / <code>show_price</code> / <code>show_unit</code> / <code>show_category</code> / <code>show_tax</code> / <code>show_memo</code></td>
                        <td><code>yes</code>（<code>show_tax</code> は <code>no</code>）</td>
                        <td><?php echo esc_html__( '各項目の表示 ON/OFF（yes / no）', 'kantanpro' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3 class="ktp-shortcodes-subheading"><?php echo esc_html__( '記述例', 'kantanpro' ); ?></h3>
            <ul class="ktp-shortcodes-examples">
                <li><code>[ktpwp_public_products]</code></li>
                <li><code>[ktpwp_public_products layout="grid" columns="3"]</code></li>
                <li><code>[ktpwp_public_products layout="table"]</code></li>
                <li><code>[ktpwp_public_products layout="cards" columns="2" show_tax="yes"]</code></li>
                <li><code>[ktpwp_public_products category="Web制作" ids="2,5,8"]</code></li>
                <li><code>[ktpwp_public_products category="サポート,WEB制作"]</code></li>
            </ul>
        </div>
        <?php
    }

    /**
     * デザイン設定ページの表示
     */
    public function create_design_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'この設定ページにアクセスする権限がありません。', 'kantanpro' ) );
        } ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-art" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span><?php echo esc_html__( 'デザイン設定', 'kantanpro' ); ?></h1>
            
            <?php
            // 通知表示
            settings_errors( 'ktp_design_settings' );
            ?>
            
            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( 'ktp_design_group' );

                        // デザイン設定セクションの出力
                        global $wp_settings_sections, $wp_settings_fields;
                        if ( isset( $wp_settings_sections['ktp-design']['design_setting_section'] ) ) {
                            $section = $wp_settings_sections['ktp-design']['design_setting_section'];
                            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                            if ( $section['callback'] ) {
                                call_user_func( $section['callback'], $section );
                            }
                            if ( isset( $wp_settings_fields['ktp-design']['design_setting_section'] ) ) {
                                echo '<table class="form-table">';
                                foreach ( $wp_settings_fields['ktp-design']['design_setting_section'] as $field ) {
                                    echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                    call_user_func( $field['callback'], $field['args'] );
                                    echo '</td></tr>';
                                }
                                echo '</table>';
                            }
                        } ?>
                        
                        <div class="ktp-submit-button">
                            <?php submit_button( __( '設定を保存', 'kantanpro' ), 'primary', 'submit', false ); ?>
                        </div>
                    </form>
                    
                    <!-- デフォルト設定管理セクション -->
                    <div class="ktp-default-settings-section" style="margin-top: 30px;">
                        <form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'すべてのデザイン設定がデフォルト値にリセットされます。よろしいですか？', 'kantanpro' ) ); ?>');">
                            <?php wp_nonce_field( 'ktp_reset_to_default', 'ktp_reset_to_default_nonce' ); ?>
                            <input type="hidden" name="action" value="reset_to_default">
                            <?php submit_button( __( 'デフォルトに戻す', 'kantanpro' ), 'secondary', 'reset_to_default', false ); ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }



    public function page_init() {

        // メディアライブラリ用のスクリプトとスタイルを読み込み
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'ktp-settings' ) {
            wp_enqueue_media();
            wp_enqueue_script( 'media-upload' );
            wp_enqueue_script( 'thickbox' );
            wp_enqueue_style( 'thickbox' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // 一般設定グループの登録
        register_setting(
            'ktp_general_group',
            'ktp_general_settings',
            array( $this, 'sanitize_general_settings' )
        );

        // ロゴマークの登録
        register_setting(
            'ktp_general_group',
            'ktp_logo_image',
            array( $this, 'sanitize_fixed_logo_image' )
        );

        // システム名の登録
        register_setting(
            'ktp_general_group',
            'ktp_system_name',
            array( $this, 'sanitize_fixed_system_name' )
        );

        // システムの説明の登録
        register_setting(
            'ktp_general_group',
            'ktp_system_description',
            array( $this, 'sanitize_fixed_system_description' )
        );

        register_setting(
            $this->options_group,
            $this->option_name,
            array( $this, 'sanitize' )
        );

        // 一般設定ページ（page=ktp-settings）のフォームで保存するため ktp_general_group に登録
        register_setting(
            'ktp_general_group',
            'ktp_japanpost_api_settings',
            array( $this, 'sanitize_japanpost_api_settings' )
        );

        // デザイン設定グループの登録
        register_setting(
            'ktp_design_group',
            'ktp_design_settings',
            array( $this, 'sanitize_design_settings' )
        );

        // 寄付設定グループの登録
        register_setting(
            'ktp_donation_group',
            'ktp_donation_settings',
            array(
                'sanitize_callback' => array( $this, 'sanitize_donation_settings' ),
                'type' => 'object',
                'default' => array(
                    'frontend_notice_enabled' => false,
                    'notice_display_interval' => 7,
                    'notice_message' => __( 'このサイトの運営にご協力いただける方は、寄付をお願いいたします。', 'kantanpro' ),
                    'donation_url' => ''
                )
            )
        );

        // 更新通知設定グループの登録
        register_setting(
            'ktp_update_notification_group',
            'ktp_update_notification_settings',
            array(
                'sanitize_callback' => array( $this, 'sanitize_update_notification_settings' ),
                'type' => 'object',
                'default' => array(
                    'enable_notifications' => true,
                    'enable_admin_notifications' => true,
                    'enable_frontend_notifications' => true,
                    'check_interval' => 24,
                    'notification_roles' => array( 'administrator' ),
                    'github_token' => ''
                )
            )
        );

        // プラグイン削除時のデータ保持設定（エンドユーザー向け設定）
        // 一般設定タブ(ktp-general)に表示するため、ktp_general_group に登録
        register_setting(
            'ktp_general_group',
            'ktp_uninstall_settings',
            array(
                'sanitize_callback' => array( $this, 'sanitize_uninstall_settings' ),
                'type' => 'object',
                'default' => array(
                    'uninstall_mode' => 'keep_data',
                ),
            )
        );
        register_setting(
            'ktp_central_banner_group',
            'ktp_central_banner_settings',
            array( $this, 'sanitize_central_banner_settings' )
        );

        // 一般設定セクション
        add_settings_section(
            'general_setting_section',
            __( '基本設定', 'kantanpro' ),
            array( $this, 'print_general_section_info' ),
            'ktp-general'
        );

        // ロゴマーク
        add_settings_field(
            'ktp_logo_image',
            __( 'ロゴマーク', 'kantanpro' ),
            array( $this, 'logo_image_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // システム名
        add_settings_field(
            'ktp_system_name',
            __( 'システム名', 'kantanpro' ),
            array( $this, 'system_name_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // システムの説明
        add_settings_field(
            'ktp_system_description',
            __( 'システムの説明', 'kantanpro' ),
            array( $this, 'system_description_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        add_settings_field(
            'ktp_page_id',
            __( '業務画面ページ', 'kantanpro' ),
            array( $this, 'ktp_page_id_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // リストの表示件数
        add_settings_field(
            'work_list_range',
            __( 'リストの表示件数', 'kantanpro' ),
            array( $this, 'work_list_range_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // 納期警告日数
        add_settings_field(
            'delivery_warning_days',
            __( '納期警告日数', 'kantanpro' ),
            array( $this, 'delivery_warning_days_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // 適格請求書番号
        add_settings_field(
            'qualified_invoice_number',
            __( '適格請求書番号', 'kantanpro' ),
            array( $this, 'qualified_invoice_number_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // 会社情報
        add_settings_field(
            'company_info',
            __( '会社情報', 'kantanpro' ),
            array( $this, 'company_info_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        add_settings_field(
            'currency_code',
            __( '通貨', 'kantanpro' ),
            array( $this, 'currency_code_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // 消費税設定セクション
        add_settings_section(
            'tax_setting_section',
            __( '消費税設定', 'kantanpro' ),
            array( $this, 'print_tax_section_info' ),
            'ktp-general'
        );

        // 税制モード（UIは2択: 消費税あり/なし）
        add_settings_field(
            'tax_mode',
            __( '税制モード', 'kantanpro' ),
            array( $this, 'tax_mode_callback' ),
            'ktp-general',
            'tax_setting_section'
        );

        // 一律税率
        add_settings_field(
            'unified_tax_rate',
            __( '一律税率（%）', 'kantanpro' ),
            array( $this, 'unified_tax_rate_callback' ),
            'ktp-general',
            'tax_setting_section'
        );

        // 税率/税額列の非表示 と 明細税率ロックは税制モードにより自動化したため、設定項目の表示を廃止

        // 基本税率
        add_settings_field(
            'default_tax_rate',
            __( '基本税率（%）', 'kantanpro' ),
            array( $this, 'default_tax_rate_callback' ),
            'ktp-general',
            'tax_setting_section'
        );

        // 軽減税率
        add_settings_field(
            'reduced_tax_rate',
            __( '軽減税率（%）', 'kantanpro' ),
            array( $this, 'reduced_tax_rate_callback' ),
            'ktp-general',
            'tax_setting_section'
        );

        // 振込先口座（請求書印字用）
        add_settings_section(
            'bank_transfer_setting_section',
            __( '振込先口座', 'kantanpro' ),
            array( $this, 'print_bank_transfer_section_info' ),
            'ktp-general'
        );

        add_settings_field(
            'bank_transfer_bank_branch',
            __( '銀行名・支店名', 'kantanpro' ),
            array( $this, 'bank_transfer_bank_branch_callback' ),
            'ktp-general',
            'bank_transfer_setting_section'
        );

        add_settings_field(
            'bank_transfer_account_type',
            __( '口座種別', 'kantanpro' ),
            array( $this, 'bank_transfer_account_type_callback' ),
            'ktp-general',
            'bank_transfer_setting_section'
        );

        add_settings_field(
            'bank_transfer_account_number',
            __( '口座番号', 'kantanpro' ),
            array( $this, 'bank_transfer_account_number_callback' ),
            'ktp-general',
            'bank_transfer_setting_section'
        );

        add_settings_field(
            'bank_transfer_account_holder_kana',
            __( '口座名義（カナ）', 'kantanpro' ),
            array( $this, 'bank_transfer_account_holder_kana_callback' ),
            'ktp-general',
            'bank_transfer_setting_section'
        );

        if ( class_exists( 'KTPWP_Contract_Reminder_Mail' )
            && ( ! function_exists( 'ktpwp_contracts_feature_enabled' ) || ktpwp_contracts_feature_enabled() ) ) {
            add_settings_section(
                'contract_reminder_setting_section',
                __( '定期請求メール', 'kantanpro' ),
                array( 'KTPWP_Contract_Reminder_Mail', 'render_settings_section_info' ),
                'ktp-general'
            );

            add_settings_field(
                'contract_reminder_enabled',
                __( '自動送信', 'kantanpro' ),
                array( 'KTPWP_Contract_Reminder_Mail', 'render_enabled_field' ),
                'ktp-general',
                'contract_reminder_setting_section'
            );

            add_settings_field(
                'contract_reminder_days_before',
                __( '送信タイミング', 'kantanpro' ),
                array( 'KTPWP_Contract_Reminder_Mail', 'render_days_before_field' ),
                'ktp-general',
                'contract_reminder_setting_section'
            );

            add_settings_field(
                'contract_reminder_subject',
                __( '件名テンプレート', 'kantanpro' ),
                array( 'KTPWP_Contract_Reminder_Mail', 'render_subject_field' ),
                'ktp-general',
                'contract_reminder_setting_section'
            );

            add_settings_field(
                'contract_reminder_body',
                __( '本文テンプレート', 'kantanpro' ),
                array( 'KTPWP_Contract_Reminder_Mail', 'render_body_field' ),
                'ktp-general',
                'contract_reminder_setting_section'
            );
        }

        if ( class_exists( 'KTPWP_Stripe_Billing' ) ) {
            // 機能が有効でも、配布版によっては Stripe のクラスを同梱していない。
            // クラスの存在も見ないと、存在しないクラスを add_settings_field の
            // コールバックに登録して設定画面が fatal になる。
            $stripe_feature_enabled = ( ! function_exists( 'ktpwp_is_feature_enabled' ) || ktpwp_is_feature_enabled( 'stripe_billing' ) )
                && class_exists( 'KTPWP_Stripe_Billing' );
            add_settings_section(
                'stripe_billing_setting_section',
                __( 'Stripe 請求連携', 'kantanpro' ),
                array( 'KTPWP_Stripe_Billing', 'render_settings_section_info' ),
                'ktp-general'
            );

            if ( $stripe_feature_enabled ) {
                add_settings_field(
                    'stripe_enabled',
                    __( '有効化', 'kantanpro' ),
                    array( 'KTPWP_Stripe_Billing', 'render_enabled_field' ),
                    'ktp-general',
                    'stripe_billing_setting_section'
                );

                add_settings_field(
                    'stripe_test_mode',
                    __( 'テストモード', 'kantanpro' ),
                    array( 'KTPWP_Stripe_Billing', 'render_test_mode_field' ),
                    'ktp-general',
                    'stripe_billing_setting_section'
                );

                add_settings_field(
                    'stripe_secret_key_test',
                    __( 'Secret Key（テスト）', 'kantanpro' ),
                    array( 'KTPWP_Stripe_Billing', 'render_secret_key_test_field' ),
                    'ktp-general',
                    'stripe_billing_setting_section'
                );

                add_settings_field(
                    'stripe_secret_key_live',
                    __( 'Secret Key（本番）', 'kantanpro' ),
                    array( 'KTPWP_Stripe_Billing', 'render_secret_key_live_field' ),
                    'ktp-general',
                    'stripe_billing_setting_section'
                );

                add_settings_field(
                    'stripe_webhook_secret_test',
                    __( 'Webhook Secret（テスト）', 'kantanpro' ),
                    array( 'KTPWP_Stripe_Billing', 'render_webhook_secret_test_field' ),
                    'ktp-general',
                    'stripe_billing_setting_section'
                );

                add_settings_field(
                    'stripe_webhook_secret_live',
                    __( 'Webhook Secret（本番）', 'kantanpro' ),
                    array( 'KTPWP_Stripe_Billing', 'render_webhook_secret_live_field' ),
                    'ktp-general',
                    'stripe_billing_setting_section'
                );

                add_settings_field(
                    'stripe_days_until_due',
                    __( '支払期日', 'kantanpro' ),
                    array( 'KTPWP_Stripe_Billing', 'render_days_until_due_field' ),
                    'ktp-general',
                    'stripe_billing_setting_section'
                );

                add_settings_field(
                    'stripe_invoice_issuer_name',
                    __( '請求元名（Stripe）', 'kantanpro' ),
                    array( 'KTPWP_Stripe_Billing', 'render_invoice_issuer_name_field' ),
                    'ktp-general',
                    'stripe_billing_setting_section'
                );
            }

            add_settings_field(
                'contract_invoice_auto_enabled',
                __( '定期請求メール自動送信', 'kantanpro' ),
                array( 'KTPWP_Stripe_Billing', 'render_contract_invoice_auto_field' ),
                'ktp-general',
                'stripe_billing_setting_section'
            );
        }

        // プラグイン削除時の動作（エンドユーザー向け・一般設定ページに配置）
        add_settings_section(
            'uninstall_setting_section',
            __( 'プラグイン削除時のデータ保持設定', 'kantanpro' ),
            array( $this, 'print_uninstall_section_info' ),
            'ktp-general'
        );

        add_settings_field(
            'uninstall_mode',
            __( 'プラグイン削除時の動作', 'kantanpro' ),
            array( $this, 'uninstall_mode_callback' ),
            'ktp-general',
            'uninstall_setting_section'
        );

        // メール設定セクション
        add_settings_section(
            'email_setting_section',
            __( 'メール設定', 'kantanpro' ),
            array( $this, 'print_section_info' ),
            'ktp-settings'
        );

        // 自社メールアドレス
        add_settings_field(
            'email_address',
            __( '自社メールアドレス', 'kantanpro' ),
            array( $this, 'email_address_callback' ),
            'ktp-settings',
            'email_setting_section'
        );

        // SMTP設定セクション
        add_settings_section(
            'smtp_setting_section',
            __( 'SMTP設定', 'kantanpro' ),
            array( $this, 'print_smtp_section_info' ),
            'ktp-settings'
        );

        // デザイン設定セクション
        add_settings_section(
            'design_setting_section',
            __( 'デザイン設定', 'kantanpro' ),
            array( $this, 'print_design_section_info' ),
            'ktp-design'
        );

        // SMTPホスト
        add_settings_field(
            'smtp_host',
            __( 'SMTPホスト', 'kantanpro' ),
            array( $this, 'smtp_host_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        // SMTPポート
        add_settings_field(
            'smtp_port',
            __( 'SMTPポート', 'kantanpro' ),
            array( $this, 'smtp_port_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        // SMTPユーザー
        add_settings_field(
            'smtp_user',
            __( 'SMTPユーザー', 'kantanpro' ),
            array( $this, 'smtp_user_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        // SMTPパスワード
        add_settings_field(
            'smtp_pass',
            __( 'SMTPパスワード', 'kantanpro' ),
            array( $this, 'smtp_pass_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        // 暗号化方式
        add_settings_field(
            'smtp_secure',
            __( '暗号化方式', 'kantanpro' ),
            array( $this, 'smtp_secure_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        // 送信者名
        add_settings_field(
            'smtp_from_name',
            __( '送信者名', 'kantanpro' ),
            array( $this, 'smtp_from_name_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        add_settings_section(
            'japanpost_api_setting_section',
            __( '日本郵便 郵便番号・デジタルアドレスAPI', 'kantanpro' ),
            array( $this, 'print_japanpost_api_section_info' ),
            'ktp-general'
        );

        add_settings_field(
            'japanpost_api_enabled',
            __( '日本郵便APIを使う', 'kantanpro' ),
            array( $this, 'japanpost_api_enabled_callback' ),
            'ktp-general',
            'japanpost_api_setting_section'
        );

        add_settings_field(
            'japanpost_api_environment',
            __( '接続先', 'kantanpro' ),
            array( $this, 'japanpost_api_environment_callback' ),
            'ktp-general',
            'japanpost_api_setting_section'
        );

        add_settings_field(
            'japanpost_api_client_id',
            __( 'クライアントID', 'kantanpro' ),
            array( $this, 'japanpost_api_client_id_callback' ),
            'ktp-general',
            'japanpost_api_setting_section'
        );

        add_settings_field(
            'japanpost_api_secret_key',
            __( 'クライアントシークレット', 'kantanpro' ),
            array( $this, 'japanpost_api_secret_key_callback' ),
            'ktp-general',
            'japanpost_api_setting_section'
        );

        // デザイン設定フィールド
        // タブのアクティブ時の色
        add_settings_field(
            'tab_active_color',
            __( 'タブのアクティブ時の色', 'kantanpro' ),
            array( $this, 'tab_active_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // タブの非アクティブ時の色（背景色として設定）
        add_settings_field(
            'tab_inactive_color',
            __( 'タブの非アクティブ時の背景色', 'kantanpro' ),
            array( $this, 'tab_inactive_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // タブの下線色
        add_settings_field(
            'tab_border_color',
            __( 'タブの下線色', 'kantanpro' ),
            array( $this, 'tab_border_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // 奇数行の色
        add_settings_field(
            'odd_row_color',
            __( '奇数行の背景色', 'kantanpro' ),
            array( $this, 'odd_row_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // 偶数行の色
        add_settings_field(
            'even_row_color',
            __( '偶数行の背景色', 'kantanpro' ),
            array( $this, 'even_row_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // 公開商品カードの背景色
        add_settings_field(
            'public_product_card_bg_color',
            __( '公開商品カードの背景色', 'kantanpro' ),
            array( $this, 'public_product_card_bg_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // ヘッダー背景画像
        add_settings_field(
            'header_bg_image',
            __( 'ヘッダー背景画像', 'kantanpro' ),
            array( $this, 'header_bg_image_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // 固定ページごとのコンテンツ幅
        add_settings_field(
            'page_content_widths',
            __( '固定ページの幅', 'kantanpro' ),
            array( $this, 'page_content_widths_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // カスタムCSS入力は廃止した。
        // WordPress.org のガイドラインで、プラグインが任意の CSS/JS/PHP を保存・出力する
        // ことは認められていない（2026-09-02 のレビュー指摘）。
        // 外観の調整は WordPress 標準の「追加CSS」（カスタマイザー）を使ってもらう。
    }

    /**
     * テキストフィールドのサニタイズ
     *
     * @since 1.0.0
     * @param string $input 入力値
     * @return string サニタイズされた値
     */
    public function sanitize_text_field( $input ) {
        return sanitize_text_field( $input );
    }

    /**
     * テキストエリアフィールドのサニタイズ
     *
     * @since 1.0.0
     * @param string $input 入力値
     * @return string サニタイズされた値
     */
    public function sanitize_textarea_field( $input ) {
        return sanitize_textarea_field( $input );
    }

    public function sanitize( $input ) {
        $new_input = array();

        if ( isset( $input['email_address'] ) ) {
            $new_input['email_address'] = sanitize_email( $input['email_address'] );
        }

        if ( isset( $input['smtp_host'] ) ) {
            $new_input['smtp_host'] = sanitize_text_field( $input['smtp_host'] );
        }

        if ( isset( $input['smtp_port'] ) ) {
            $new_input['smtp_port'] = sanitize_text_field( $input['smtp_port'] );
        }

        if ( isset( $input['smtp_user'] ) ) {
            $new_input['smtp_user'] = sanitize_text_field( $input['smtp_user'] );
        }

        if ( isset( $input['smtp_pass'] ) ) {
            $new_input['smtp_pass'] = $input['smtp_pass'];
        }

        if ( isset( $input['smtp_secure'] ) ) {
            $new_input['smtp_secure'] = sanitize_text_field( $input['smtp_secure'] );
        }

        if ( isset( $input['smtp_from_name'] ) ) {
            $new_input['smtp_from_name'] = sanitize_text_field( $input['smtp_from_name'] );
        }

        return $new_input;
    }

    /**
     * デザイン設定のサニタイズ
     *
     * @since 1.0.0
     * @param array $input 入力データ
     * @return array サニタイズされたデータ
     */
    public function sanitize_design_settings( $input ) {
        $new_input = array();

        if ( isset( $input['tab_active_color'] ) ) {
            $new_input['tab_active_color'] = sanitize_hex_color( $input['tab_active_color'] );
        }

        if ( isset( $input['tab_inactive_color'] ) ) {
            $new_input['tab_inactive_color'] = sanitize_hex_color( $input['tab_inactive_color'] );
        }

        if ( isset( $input['tab_border_color'] ) ) {
            $new_input['tab_border_color'] = sanitize_hex_color( $input['tab_border_color'] );
        }

        if ( isset( $input['odd_row_color'] ) ) {
            $new_input['odd_row_color'] = sanitize_hex_color( $input['odd_row_color'] );
        }

        if ( isset( $input['even_row_color'] ) ) {
            $new_input['even_row_color'] = sanitize_hex_color( $input['even_row_color'] );
        }

        if ( isset( $input['public_product_card_bg_color'] ) ) {
            $new_input['public_product_card_bg_color'] = sanitize_hex_color( $input['public_product_card_bg_color'] );
        }

        if ( isset( $input['header_bg_image'] ) ) {
            // 数値（添付ファイルID）または文字列（画像パス）に対応
            if ( is_numeric( $input['header_bg_image'] ) ) {
                $new_input['header_bg_image'] = absint( $input['header_bg_image'] );
            } else {
                $new_input['header_bg_image'] = sanitize_text_field( $input['header_bg_image'] );
            }
        }

        // custom_css は廃止したため保存しない（既存の値も次回保存で消える）。

        $existing = get_option( 'ktp_design_settings', array() );
        $raw_widths = array();
        if ( isset( $input['page_content_widths'] ) && is_array( $input['page_content_widths'] ) ) {
            $raw_widths = $input['page_content_widths'];
        } elseif ( isset( $existing['page_content_widths'] ) && is_array( $existing['page_content_widths'] ) ) {
            $raw_widths = $existing['page_content_widths'];
        }

        $custom_widths = array();
        if ( isset( $input['page_content_width_custom'] ) && is_array( $input['page_content_width_custom'] ) ) {
            $custom_widths = $input['page_content_width_custom'];
        }

        $sanitized_widths = array();
        foreach ( $raw_widths as $page_id => $preset ) {
            $page_id = absint( $page_id );
            if ( $page_id <= 0 || ! self::page_has_ktpwp_shortcode( $page_id ) ) {
                continue;
            }

            $preset = sanitize_text_field( (string) $preset );
            if ( $preset === '' || $preset === 'default' ) {
                continue;
            }

            if ( $preset === 'custom' ) {
                $custom_raw = isset( $custom_widths[ $page_id ] ) ? trim( (string) $custom_widths[ $page_id ] ) : '';
                $width      = self::sanitize_page_content_width_value( $custom_raw );
                if ( $width === '' ) {
                    continue;
                }
                $sanitized_widths[ $page_id ] = $width;
                continue;
            }

            $width = self::sanitize_page_content_width_value( $preset );
            if ( $width !== '' ) {
                $sanitized_widths[ $page_id ] = $width;
            }
        }

        $new_input['page_content_widths'] = $sanitized_widths;

        return $new_input;
    }

    public function print_section_info() {
        echo esc_html__( 'メール送信に関する基本設定を行います。', 'kantanpro' );
    }

    public function print_smtp_section_info() {
        echo esc_html__( 'SMTPサーバーを使用したメール送信の設定を行います。SMTPを利用しない場合は空欄のままにしてください。', 'kantanpro' );
    }

    /**
     * デザイン設定セクションの説明
     *
     * @since 1.0.0
     * @return void
     */
    public function print_design_section_info() {
        echo esc_html__( 'プラグインの外観とデザインに関する設定を行います。', 'kantanpro' );
    }

    public function email_address_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="email" id="email_address" name="<?php echo esc_attr( $this->option_name ); ?>[email_address]" 
               value="<?php echo isset( $options['email_address'] ) ? esc_attr( $options['email_address'] ) : ''; ?>" 
               style="width:320px;max-width:100%;" required 
               pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" 
               placeholder="info@example.com">
        <div style="font-size:12px;color:#555;margin-top:4px;"><?php echo esc_html__( '※ サイトから届くメールが迷惑メールと認識されないよう、サイトのドメインと同じメールアドレスをご入力ください。', 'kantanpro' ); ?></div>
        <?php
    }

    public function smtp_host_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" id="smtp_host" name="<?php echo esc_attr( $this->option_name ); ?>[smtp_host]" 
               value="<?php echo isset( $options['smtp_host'] ) ? esc_attr( $options['smtp_host'] ) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               placeholder="smtp.example.com">
        <?php
    }

    public function smtp_port_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" id="smtp_port" name="<?php echo esc_attr( $this->option_name ); ?>[smtp_port]" 
               value="<?php echo isset( $options['smtp_port'] ) ? esc_attr( $options['smtp_port'] ) : ''; ?>" 
               style="width:80px;max-width:100%;" 
               placeholder="587">
        <?php
    }

    public function smtp_user_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" id="smtp_user" name="<?php echo esc_attr( $this->option_name ); ?>[smtp_user]" 
               value="<?php echo isset( $options['smtp_user'] ) ? esc_attr( $options['smtp_user'] ) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               placeholder="user@example.com">
        <?php
    }

    public function smtp_pass_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="password" id="smtp_pass" name="<?php echo esc_attr( $this->option_name ); ?>[smtp_pass]" 
               value="<?php echo isset( $options['smtp_pass'] ) ? esc_attr( $options['smtp_pass'] ) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               autocomplete="off">
        <?php
    }

    public function smtp_secure_callback() {
        $options = get_option( $this->option_name );
        $selected = isset( $options['smtp_secure'] ) ? $options['smtp_secure'] : '';
        ?>
        <select id="smtp_secure" name="<?php echo $this->option_name; ?>[smtp_secure]">
            <option value="" <?php selected( $selected, '' ); ?>><?php echo esc_html__( 'なし', 'kantanpro' ); ?></option>
            <option value="ssl" <?php selected( $selected, 'ssl' ); ?>>SSL</option>
            <option value="tls" <?php selected( $selected, 'tls' ); ?>>TLS</option>
        </select>
        <?php
    }

    public function smtp_from_name_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" id="smtp_from_name" name="<?php echo esc_attr( $this->option_name ); ?>[smtp_from_name]" 
               value="<?php echo isset( $options['smtp_from_name'] ) ? esc_attr( $options['smtp_from_name'] ) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               placeholder="<?php echo esc_attr__( '会社名や担当者名', 'kantanpro' ); ?>">
        <?php
    }

    /**
     * 日本郵便APIセクションの説明
     */
    public function print_japanpost_api_section_info() {
        echo '<p>' . esc_html__( '顧客タブの郵便番号から住所を自動入力する際、日本郵便の公式APIを利用できます。郵便番号・デジタルアドレス for Biz で発行したクライアントID・シークレットを入力してください。未設定または無効のときは従来どおり zipcloud の公開APIを利用します。', 'kantanpro' ) . '</p>';
        echo '<p>' . esc_html__( '検証（スタブ）は API v2（/api/v2/）です。テスト用ドキュメントのとおり、郵便番号の例: 1020072・1020082・1010032・1010047 などが検索できます。', 'kantanpro' ) . '</p>';
        echo '<p><a href="https://lp-api.da.pf.japanpost.jp/" target="_blank" rel="noopener noreferrer">' . esc_html__( '郵便番号・デジタルアドレスAPI（日本郵便）', 'kantanpro' ) . '</a></p>';
    }

    /**
     * @return void
     */
    public function japanpost_api_enabled_callback() {
        $options = get_option( 'ktp_japanpost_api_settings', array() );
        $on      = ! empty( $options['enabled'] );
        ?>
        <label>
            <input type="checkbox" name="ktp_japanpost_api_settings[enabled]" value="1" <?php checked( $on ); ?> />
            <?php echo esc_html__( '有効にする（オフのときは zipcloud で郵便番号から住所を取得）', 'kantanpro' ); ?>
        </label>
        <?php
    }

    /**
     * @return void
     */
    public function japanpost_api_environment_callback() {
        $options = get_option( 'ktp_japanpost_api_settings', array() );
        $env     = isset( $options['environment'] ) && $options['environment'] === 'stub' ? 'stub' : 'production';
        ?>
        <select name="ktp_japanpost_api_settings[environment]" id="ktp_japanpost_api_environment">
            <option value="production" <?php selected( $env, 'production' ); ?>><?php echo esc_html__( '本番', 'kantanpro' ); ?></option>
            <option value="stub" <?php selected( $env, 'stub' ); ?>><?php echo esc_html__( '検証（スタブ）', 'kantanpro' ); ?></option>
        </select>
        <p class="description" style="margin-top:8px;">
            <strong><?php echo esc_html__( '本番', 'kantanpro' ); ?>:</strong> <code>api.da.pf.japanpost.jp</code>（<?php echo esc_html__( 'API v1', 'kantanpro' ); ?>）<br />
            <strong><?php echo esc_html__( '検証（スタブ）', 'kantanpro' ); ?>:</strong> <code>stub-qz73x.da.pf.japanpost.jp</code>（<?php echo esc_html__( 'API v2（テスト用リファレンス準拠）', 'kantanpro' ); ?>）
        </p>
        <?php
    }

    /**
     * @return void
     */
    public function japanpost_api_client_id_callback() {
        $options = get_option( 'ktp_japanpost_api_settings', array() );
        $val     = isset( $options['client_id'] ) ? (string) $options['client_id'] : '';
        ?>
        <input type="text" name="ktp_japanpost_api_settings[client_id]" id="ktp_japanpost_api_client_id" value="<?php echo esc_attr( $val ); ?>" class="regular-text" autocomplete="off" />
        <?php
    }

    /**
     * @return void
     */
    public function japanpost_api_secret_key_callback() {
        ?>
        <input type="password" name="ktp_japanpost_api_settings[secret_key]" id="ktp_japanpost_api_secret_key" value="" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr__( '変更する場合のみ入力', 'kantanpro' ); ?>" />
        <p class="description"><?php echo esc_html__( '登録済みのシークレットを維持する場合は空のまま保存してください。', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * 日本郵便API設定のサニタイズ
     *
     * @param array|null $input 入力
     * @return array
     */
    public function sanitize_japanpost_api_settings( $input ) {
        $prev = get_option( 'ktp_japanpost_api_settings', array() );
        if ( ! is_array( $prev ) ) {
            $prev = array();
        }
        $out = array_merge(
            array(
                'enabled'     => false,
                'environment' => 'production',
                'client_id'   => '',
                'secret_key'  => '',
            ),
            $prev
        );
        if ( ! is_array( $input ) ) {
            return $out;
        }
        $out['enabled'] = ! empty( $input['enabled'] );
        if ( isset( $input['environment'] ) && in_array( $input['environment'], array( 'production', 'stub' ), true ) ) {
            $out['environment'] = $input['environment'];
        }
        if ( isset( $input['client_id'] ) ) {
            $out['client_id'] = sanitize_text_field( wp_unslash( (string) $input['client_id'] ) );
        }
        if ( isset( $input['secret_key'] ) ) {
            $sk = trim( wp_unslash( (string) $input['secret_key'] ) );
            if ( $sk !== '' ) {
                $out['secret_key'] = $sk;
            }
        }
        return $out;
    }

    /**
     * PHPMailer の HELO / Message-ID 用ホスト名を送信ドメインに揃える。
     * home_url が localhost の環境でも SMTP 送信元ドメインを優先する。
     *
     * @param object $phpmailer PHPMailer インスタンス。
     */
    public static function apply_phpmailer_identity( $phpmailer ) {
        if ( ! is_object( $phpmailer ) ) {
            return;
        }

        $host = self::resolve_mail_ehlo_hostname();
        if ( $host !== '' ) {
            $phpmailer->Hostname = $host;
            if ( property_exists( $phpmailer, 'Helo' ) ) {
                $phpmailer->Helo = $host;
            }
        }

        $phpmailer->CharSet = 'UTF-8';
    }

    /**
     * SMTP EHLO / Message-ID に使うホスト名を決定する。
     */
    private static function resolve_mail_ehlo_hostname() {
        $blocked_hosts = array( 'localhost', '127.0.0.1', '::1' );
        $from_domain   = '';

        $is_usable = static function ( $host ) use ( $blocked_hosts ) {
            if ( ! is_string( $host ) || $host === '' ) {
                return false;
            }
            return ! in_array( strtolower( $host ), $blocked_hosts, true );
        };

        $smtp_settings = get_option( 'ktp_smtp_settings', array() );
        $from_email    = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : '';
        if ( $from_email !== '' && is_email( $from_email ) ) {
            $domain_parts = explode( '@', $from_email );
            $from_domain  = strtolower( (string) end( $domain_parts ) );
            if ( $is_usable( $from_domain ) ) {
                return $from_domain;
            }
        }

        foreach ( array( home_url(), site_url() ) as $url ) {
            $site_host = wp_parse_url( $url, PHP_URL_HOST );
            if ( $is_usable( $site_host ) ) {
                return strtolower( (string) $site_host );
            }
        }

        return $from_domain;
    }

    public function setup_smtp_settings( $phpmailer ) {
        try {
            self::apply_phpmailer_identity( $phpmailer );

            $options = get_option( $this->option_name );

            if ( ! empty( $options['smtp_host'] ) && ! empty( $options['smtp_port'] ) && ! empty( $options['smtp_user'] ) && ! empty( $options['smtp_pass'] ) ) {
                $phpmailer->isSMTP();
                $phpmailer->Host = $options['smtp_host'];
                $phpmailer->Port = $options['smtp_port'];
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $options['smtp_user'];
                $phpmailer->Password = $options['smtp_pass'];

                if ( ! empty( $options['smtp_secure'] ) ) {
                    $phpmailer->SMTPSecure = $options['smtp_secure'];
                }

                $phpmailer->CharSet = 'UTF-8';

                if ( ! empty( $options['email_address'] ) ) {
                    $phpmailer->setFrom(
                        $options['email_address'],
                        ! empty( $options['smtp_from_name'] ) ? $options['smtp_from_name'] : $options['email_address'],
                        false
                    );
                }
            }
        } catch ( Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( $e->getMessage() ); }
        }
    }

    private function send_test_email() {
        $options = get_option( $this->option_name );
        $to = $options['email_address'];
        $subject = __( '【KTPWP】SMTPテストメール', 'kantanpro' );
        /* translators: %s: 送信元メールアドレス */
        $body = sprintf( __( "このメールはKTPWPプラグインのSMTPテスト送信です。\n\n送信元: %s", 'kantanpro' ), $options['email_address'] );
        $headers = array();

        if ( ! empty( $options['smtp_from_name'] ) ) {
            $headers[] = 'From: ' . $options['smtp_from_name'] . ' <' . $options['email_address'] . '>';
        } else {
            $headers[] = 'From: ' . $options['email_address'];
        }

        $sent = wp_mail( $to, $subject, $body, $headers );

        if ( $sent ) {
            $this->test_mail_message = __( 'テストメールを送信しました。メールボックスをご確認ください。', 'kantanpro' );
            $this->test_mail_status = 'success';

            if ( class_exists( 'KTPWP_Terms_Of_Service' ) ) {
                KTPWP_Terms_Of_Service::get_instance()->flush_pending_notifications();
            }

            // 成功通知を表示
            $this->show_notification( __( 'テストメールを送信しました。メールボックスをご確認ください。', 'kantanpro' ), true );

            add_settings_error(
                'ktp_settings',
                'test_mail_success',
                __( 'テストメールを送信しました。メールボックスをご確認ください。', 'kantanpro' ),
                'updated'
            );
        } else {
            global $phpmailer;
            $error_message = '';
            if ( isset( $phpmailer ) && is_object( $phpmailer ) ) {
                $error_message = $phpmailer->ErrorInfo;
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP SMTPテストメール送信失敗: ' . $error_message ); }
            } else {
                $error_message = __( 'PHPMailerインスタンスが取得できませんでした', 'kantanpro' );
                error_log( 'KTPWP SMTPテストメール送信失敗: ' . $error_message );
            }

            $this->test_mail_message = __( 'テストメールの送信に失敗しました。SMTP設定をご確認ください。', 'kantanpro' );
            $this->test_mail_status = 'error';

            // エラー通知を表示
            $this->show_notification( __( 'テストメールの送信に失敗しました。SMTP設定をご確認ください。', 'kantanpro' ), false );

            add_settings_error(
                'ktp_settings',
                'test_mail_error',
                __( 'テストメールの送信に失敗しました。SMTP設定をご確認ください。', 'kantanpro' ),
                'error'
            );
        }
    }

    /**
     * 新しいフローティング通知システムを使用して通知を表示する
     *
     * @param string $message 表示するメッセージ
     * @param bool   $success 成功メッセージかどうか（true=成功、false=エラー）
     */
    private function show_notification( $message, $success = true ) {
        // 生の <script> を出さず、共通の通知ヘルパーに寄せる（wp.org ガイドライン）。
        ktpwp_add_inline_notice( $success ? 'success' : 'error', $message );
    }

    /**
     * 一般設定のサニタイズ処理
     *
     * @since 1.0.0
     * @param array $input 入力値
     * @return array サニタイズされた値
     */
    public function sanitize_general_settings( $input ) {
        $new_input = array();

        if ( isset( $input['work_list_range'] ) ) {
            $range = intval( $input['work_list_range'] );
            // 最小5件、最大500件に制限
            $new_input['work_list_range'] = max( 5, min( 500, $range ) );
        }

        if ( isset( $input['delivery_warning_days'] ) ) {
            $warning_days = intval( $input['delivery_warning_days'] );
            // 最小1日、最大365日に制限
            $new_input['delivery_warning_days'] = max( 1, min( 365, $warning_days ) );
        }

        if ( isset( $input['qualified_invoice_number'] ) ) {
            // 適格請求書番号のサニタイズ：半角英数字、ハイフン、スペースのみ許可
            $qualified_invoice_number = sanitize_text_field( $input['qualified_invoice_number'] );
            // 英数字、ハイフン、スペースのみ許可（全角文字は半角に変換）
            $qualified_invoice_number = preg_replace( '/[^a-zA-Z0-9\-\s]/', '', $qualified_invoice_number );
            $new_input['qualified_invoice_number'] = $qualified_invoice_number;
        }

        if ( isset( $input['currency_code'] ) ) {
            $currency_code = strtoupper( sanitize_text_field( $input['currency_code'] ) );
            $supported_currencies = self::get_supported_currencies();
            $new_input['currency_code'] = isset( $supported_currencies[ $currency_code ] ) ? $currency_code : 'JPY';
        }

        if ( isset( $input['ktp_page_id'] ) ) {
            $page_id = absint( $input['ktp_page_id'] );
            if ( $page_id === 0 ) {
                $new_input['ktp_page_id'] = 0;
            } elseif ( self::page_has_ktpwp_shortcode( $page_id ) ) {
                $new_input['ktp_page_id'] = $page_id;
            } else {
                $existing = get_option( 'ktp_general_settings', array() );
                $new_input['ktp_page_id'] = isset( $existing['ktp_page_id'] ) ? (int) $existing['ktp_page_id'] : 0;
                add_settings_error(
                    'ktp_general_settings',
                    'invalid_ktp_page_id',
                    __( '業務画面ページには、[ktpwp_all_tab] または [kantanpro_ex] が設置された公開中の固定ページを選択してください。', 'kantanpro' ),
                    'error'
                );
            }
        }

        // 税制モード（明示的に選択: multiple | unified | abolished）
        if ( isset( $input['tax_mode'] ) ) {
            $ui_mode = sanitize_text_field( $input['tax_mode'] );
            if ( in_array( $ui_mode, array( 'multiple', 'unified', 'abolished' ), true ) ) {
                $new_input['tax_mode'] = $ui_mode;
            } else {
                // 後方互換: 'with_tax' が来た場合は multiple とみなす
                $new_input['tax_mode'] = ( $ui_mode === 'with_tax' ) ? 'multiple' : 'multiple';
            }
            // バリデーション: unified選択時は税率必須
            if ( $new_input['tax_mode'] === 'unified' ) {
                $rate_raw = isset( $input['unified_tax_rate'] ) ? trim( (string) $input['unified_tax_rate'] ) : '';
                if ( $rate_raw === '' ) {
                    add_settings_error( 'ktp_general_settings', 'unified_tax_rate_required', __( '一律税率モードでは、一律税率（%）の入力が必須です。', 'kantanpro' ), 'error' );
                }
            }
        }

        // 一律税率（空欄時はデフォルト5%を適用）
        if ( array_key_exists( 'unified_tax_rate', $input ) ) {
            $rate_raw = trim( (string) $input['unified_tax_rate'] );
            if ( $rate_raw === '' ) {
                $new_input['unified_tax_rate'] = 5.0;
            } else {
                $rate = floatval( $rate_raw );
                $new_input['unified_tax_rate'] = max( 0.0, $rate );
            }
        }

        // 税率/税額列の非表示
        if ( isset( $input['hide_tax_columns'] ) ) {
            $new_input['hide_tax_columns'] = (bool) $input['hide_tax_columns'];
        } else {
            $new_input['hide_tax_columns'] = false;
        }

        // 明細税率ロック
        if ( isset( $input['lock_line_tax_rate'] ) ) {
            $new_input['lock_line_tax_rate'] = (bool) $input['lock_line_tax_rate'];
        } else {
            $new_input['lock_line_tax_rate'] = false;
        }

        if ( isset( $input['contract_reminder_enabled'] ) ) {
            $new_input['contract_reminder_enabled'] = (bool) $input['contract_reminder_enabled'];
        } else {
            $new_input['contract_reminder_enabled'] = false;
        }

        if ( isset( $input['contract_reminder_days_before'] ) ) {
            $new_input['contract_reminder_days_before'] = max( 1, min( 30, absint( $input['contract_reminder_days_before'] ) ) );
        }

        if ( isset( $input['contract_reminder_subject'] ) ) {
            $new_input['contract_reminder_subject'] = sanitize_text_field( $input['contract_reminder_subject'] );
        }

        if ( isset( $input['contract_reminder_body'] ) ) {
            $new_input['contract_reminder_body'] = sanitize_textarea_field( $input['contract_reminder_body'] );
        }

        if ( isset( $input['company_info'] ) ) {
            // HTMLコンテンツを許可し、wp_ksesで安全なHTMLタグのみ保持
            $allowed_html = array(
                'br' => array(),
                'p' => array(),
                'strong' => array(),
                'b' => array(),
                'em' => array(),
                'i' => array(),
                'u' => array(),
                'a' => array(
                    'href' => array(),
                    'target' => array(),
                    'rel' => array(),
                ),
                'span' => array(
                    'style' => array(),
                ),
                'div' => array(
                    'style' => array(),
                ),
            );
            $new_input['company_info'] = wp_kses( $input['company_info'], $allowed_html );
        }

        if ( array_key_exists( 'bank_transfer_bank_branch', $input ) ) {
            $new_input['bank_transfer_bank_branch'] = sanitize_text_field( wp_unslash( (string) $input['bank_transfer_bank_branch'] ) );
        }

        if ( isset( $input['bank_transfer_account_type'] ) ) {
            $t = sanitize_text_field( wp_unslash( (string) $input['bank_transfer_account_type'] ) );
            $new_input['bank_transfer_account_type'] = ( $t === 'current' ) ? 'current' : 'ordinary';
        }

        if ( array_key_exists( 'bank_transfer_account_number', $input ) ) {
            $raw = wp_unslash( (string) $input['bank_transfer_account_number'] );
            $new_input['bank_transfer_account_number'] = sanitize_text_field( preg_replace( '/[^\d\-]/', '', $raw ) );
        }

        if ( array_key_exists( 'bank_transfer_account_holder_kana', $input ) ) {
            $new_input['bank_transfer_account_holder_kana'] = sanitize_text_field( wp_unslash( (string) $input['bank_transfer_account_holder_kana'] ) );
        }

        $existing = get_option( 'ktp_general_settings', array() );
        if ( ! is_array( $existing ) ) {
            $existing = array();
        }

        $new_input['stripe_enabled'] = ! empty( $input['stripe_enabled'] );
        $new_input['stripe_test_mode'] = ! empty( $input['stripe_test_mode'] );
        $new_input['contract_invoice_auto_enabled'] = ! empty( $input['contract_invoice_auto_enabled'] );

        if ( isset( $input['stripe_days_until_due'] ) ) {
            $new_input['stripe_days_until_due'] = max( 1, min( 90, absint( $input['stripe_days_until_due'] ) ) );
        } elseif ( isset( $existing['stripe_days_until_due'] ) ) {
            $new_input['stripe_days_until_due'] = max( 1, min( 90, absint( $existing['stripe_days_until_due'] ) ) );
        }

        if ( array_key_exists( 'stripe_invoice_issuer_name', $input ) ) {
            $new_input['stripe_invoice_issuer_name'] = sanitize_text_field( wp_unslash( (string) $input['stripe_invoice_issuer_name'] ) );
        } elseif ( isset( $existing['stripe_invoice_issuer_name'] ) ) {
            $new_input['stripe_invoice_issuer_name'] = $existing['stripe_invoice_issuer_name'];
        }

        $stripe_secret_fields = array(
            'stripe_secret_key_test',
            'stripe_secret_key_live',
            'stripe_webhook_secret_test',
            'stripe_webhook_secret_live',
        );
        foreach ( $stripe_secret_fields as $field ) {
            if ( array_key_exists( $field, $input ) ) {
                $value = trim( sanitize_text_field( wp_unslash( (string) $input[ $field ] ) ) );
                if ( $value !== '' ) {
                    $new_input[ $field ] = $value;
                } elseif ( isset( $existing[ $field ] ) ) {
                    $new_input[ $field ] = $existing[ $field ];
                }
            } elseif ( isset( $existing[ $field ] ) ) {
                $new_input[ $field ] = $existing[ $field ];
            }
        }

        if ( ! $new_input['stripe_enabled'] ) {
            $test_key = trim( (string) ( $new_input['stripe_secret_key_test'] ?? '' ) );
            $live_key = trim( (string) ( $new_input['stripe_secret_key_live'] ?? '' ) );
            if ( $test_key !== '' || $live_key !== '' ) {
                $new_input['stripe_enabled'] = true;
            }
        }

        return $new_input;
    }

    /**
     * 税制モードフィールド
     */
    public function tax_mode_callback() {
        $options = get_option( 'ktp_general_settings' );
        $saved_mode = isset( $options['tax_mode'] ) ? $options['tax_mode'] : 'multiple';
        ?>
        <label style="margin-right:16px;">
            <input type="radio" name="ktp_general_settings[tax_mode]" value="multiple" <?php checked( $saved_mode, 'multiple' ); ?> /> <?php echo esc_html__( '消費税あり（行ごと税率）', 'kantanpro' ); ?>
        </label>
        <label style="margin-right:16px;">
            <input type="radio" name="ktp_general_settings[tax_mode]" value="unified" <?php checked( $saved_mode, 'unified' ); ?> /> <?php echo esc_html__( '一律税率（全明細に同一税率）', 'kantanpro' ); ?>
        </label>
        <label>
            <input type="radio" name="ktp_general_settings[tax_mode]" value="abolished" <?php checked( $saved_mode, 'abolished' ); ?> /> <?php echo esc_html__( '消費税なし（税列非表示）', 'kantanpro' ); ?>
        </label>
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ 「一律税率」を選択すると入力した税率が全明細に適用され、行ごとの税率編集はできません。「消費税なし」を選択すると税率/税額列は非表示になります。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * 一律税率フィールド
     */
    public function unified_tax_rate_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['unified_tax_rate'] ) ? $options['unified_tax_rate'] : 5.00;
        $mode = class_exists('KTPWP_Tax_Policy') ? KTPWP_Tax_Policy::get_mode() : ( isset($options['tax_mode']) ? $options['tax_mode'] : 'multiple' );
        $disabled = ( $mode !== 'unified' ) ? 'disabled' : '';
        ?>
        <input type="number" id="unified_tax_rate" name="ktp_general_settings[unified_tax_rate]" value="<?php echo esc_attr( $value ); ?>" step="1" min="0" style="width:100px;text-align:right;" placeholder="<?php echo esc_attr__( '例：10', 'kantanpro' ); ?>" <?php echo $disabled; ?>> %
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ この設定は「一律税率」モード選択時のみ有効です。未入力時は既定の5%が適用されます。0を入力した場合は0%で固定されます。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * 税率/税額列の非表示
     */
    public function hide_tax_columns_callback() { /* 自動化により非表示（互換のため残置） */ }

    /**
     * 明細税率ロック
     */
    public function lock_line_tax_rate_callback() { /* 自動化により非表示（互換のため残置） */ }

    /**
     * 一般設定セクションの説明
     *
     * @since 1.0.0
     * @return void
     */
    public function print_general_section_info() {
        echo esc_html__( 'プラグインの基本設定を行います。', 'kantanpro' );
    }

    /**
     * ロゴマークのデフォルト値を確実に設定
     *
     * @since 1.0.0
     * @return void
     */
    public function ensure_logo_default_value() {
        $default_logo = function_exists( 'ktpwp_plugin_asset_url' )
            ? ktpwp_plugin_asset_url( 'images/default/icon.png' )
            : plugins_url( 'images/default/icon.png', KANTANPRO_PLUGIN_FILE );
        $current_logo = get_option( 'ktp_logo_image' );

        if ( ! is_string( $current_logo ) || '' === trim( $current_logo ) || $current_logo !== $default_logo ) {
            update_option( 'ktp_logo_image', $default_logo, false );
        }
    }

    /**
     * ロゴマークフィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function logo_image_callback() {
        $value = $this->get_fixed_logo_image();
        ?>
        <input type="hidden" id="ktp_logo_image" name="ktp_logo_image" value="<?php echo esc_attr( $value ); ?>" />
        <div class="logo-preview" style="margin-bottom: 10px;">
            <img src="<?php echo esc_url( $value ); ?>" alt="<?php echo esc_attr__( 'ロゴマーク', 'kantanpro' ); ?>" style="max-width: 200px; max-height: 100px; display: block;" />
        </div>
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ ロゴマークは固定です（変更できません）。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * システム名フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function system_name_callback() {
        $value = $this->get_fixed_system_name();
        ?>
        <input type="text" id="ktp_system_name" name="ktp_system_name" value="<?php echo esc_attr( $value ); ?>" class="regular-text" readonly />
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ システム名は固定です（変更できません）。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * システムの説明フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function system_description_callback() {
        $value = $this->get_fixed_system_description();
        ?>
        <textarea id="ktp_system_description" name="ktp_system_description" rows="3" cols="50" class="large-text" readonly><?php echo esc_textarea( $value ); ?></textarea>
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ システムの説明は固定です（変更できません）。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * 固定ロゴ画像URLを取得。
     *
     * @return string
     */
    private function get_fixed_logo_image() {
        return function_exists( 'ktpwp_plugin_asset_url' )
            ? ktpwp_plugin_asset_url( 'images/default/icon.png' )
            : plugins_url( 'images/default/icon.png', KANTANPRO_PLUGIN_FILE );
    }

    /**
     * 固定システム名を取得。
     *
     * @return string
     */
    private function get_fixed_system_name() {
        return defined( 'KANTANPRO_PLUGIN_NAME' ) ? KANTANPRO_PLUGIN_NAME : 'KantanProEX';
    }

    /**
     * 固定システム説明を取得。
     *
     * @return string
     */
    private function get_fixed_system_description() {
        return defined( 'KANTANPRO_PLUGIN_DESCRIPTION' )
            ? KANTANPRO_PLUGIN_DESCRIPTION
            : __( 'スモールビジネスのための販売支援ツール', 'kantanpro' );
    }

    /**
     * ロゴ画像設定を固定値へ強制。
     *
     * @param mixed $value 保存値（未使用）。
     * @return string
     */
    public function sanitize_fixed_logo_image( $value ) {
        return esc_url_raw( $this->get_fixed_logo_image() );
    }

    /**
     * システム名設定を固定値へ強制。
     *
     * @param mixed $value 保存値（未使用）。
     * @return string
     */
    public function sanitize_fixed_system_name( $value ) {
        return sanitize_text_field( $this->get_fixed_system_name() );
    }

    /**
     * システム説明設定を固定値へ強制。
     *
     * @param mixed $value 保存値（未使用）。
     * @return string
     */
    public function sanitize_fixed_system_description( $value ) {
        return sanitize_textarea_field( $this->get_fixed_system_description() );
    }

    /**
     * 業務画面ページフィールドのコールバック。
     *
     * @return void
     */
    public function ktp_page_id_callback() {
        $selected   = self::get_configured_ktpwp_business_page_id();
        $candidates = self::get_ktpwp_shortcode_pages();
        ?>
        <select id="ktp_page_id" name="ktp_general_settings[ktp_page_id]">
            <option value="0" <?php selected( $selected, 0 ); ?>>
                <?php echo esc_html__( '自動検出（ショートコード設置ページのうち ID が最小の公開ページ）', 'kantanpro' ); ?>
            </option>
            <?php foreach ( $candidates as $page ) : ?>
                <option value="<?php echo esc_attr( (string) (int) $page->ID ); ?>" <?php selected( $selected, (int) $page->ID ); ?>>
                    <?php echo esc_html( $page->post_title . ' (ID: ' . (int) $page->ID . ')' ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php echo esc_html__( '受注通知メール等の「受注書へのリンク」に使う KantanPro 業務画面ページです。デモページが選ばれる場合は本番ページを指定してください。', 'kantanpro' ); ?>
        </p>
        <?php
        $effective_page_id = self::get_ktpwp_business_page_id();
        if ( $effective_page_id > 0 ) {
            $effective_url = self::get_ktpwp_business_page_url();
            if ( $effective_url !== '' ) {
                ?>
                <p class="description">
                    <?php
                    printf(
                        /* translators: 1: page ID */
                        esc_html__( '現在のリンク先: ページ ID %1$d', 'kantanpro' ),
                        (int) $effective_page_id
                    );
                    ?>
                    —
                    <a href="<?php echo esc_url( $effective_url ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html__( 'ページを開く', 'kantanpro' ); ?>
                    </a>
                </p>
                <?php
            }
        }
    }

    /**
     * 仕事リスト表示件数フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function work_list_range_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['work_list_range'] ) ? $options['work_list_range'] : 20;
        ?>
        <select id="work_list_range" name="ktp_general_settings[work_list_range]">
            <option value="5" <?php selected( $value, 5 ); ?>><?php echo esc_html( /* translators: %d: 件数 */ sprintf( __( '%d件', 'kantanpro' ), 5 ) ); ?></option>
            <option value="10" <?php selected( $value, 10 ); ?>><?php echo esc_html( /* translators: %d: 件数 */ sprintf( __( '%d件', 'kantanpro' ), 10 ) ); ?></option>
            <option value="20" <?php selected( $value, 20 ); ?>><?php echo esc_html( /* translators: %d: 件数 */ sprintf( __( '%d件', 'kantanpro' ), 20 ) ); ?></option>
            <option value="30" <?php selected( $value, 30 ); ?>><?php echo esc_html( /* translators: %d: 件数 */ sprintf( __( '%d件', 'kantanpro' ), 30 ) ); ?></option>
            <option value="50" <?php selected( $value, 50 ); ?>><?php echo esc_html( /* translators: %d: 件数 */ sprintf( __( '%d件', 'kantanpro' ), 50 ) ); ?></option>
            <option value="100" <?php selected( $value, 100 ); ?>><?php echo esc_html( /* translators: %d: 件数 */ sprintf( __( '%d件', 'kantanpro' ), 100 ) ); ?></option>
            <option value="200" <?php selected( $value, 200 ); ?>><?php echo esc_html( /* translators: %d: 件数 */ sprintf( __( '%d件', 'kantanpro' ), 200 ) ); ?></option>
            <option value="500" <?php selected( $value, 500 ); ?>><?php echo esc_html( /* translators: %d: 件数 */ sprintf( __( '%d件', 'kantanpro' ), 500 ) ); ?></option>
        </select>
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ リストで一度に表示する件数を設定してください。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * 納期警告日数フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function delivery_warning_days_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['delivery_warning_days'] ) ? $options['delivery_warning_days'] : 3;
        ?>
        <select id="delivery_warning_days" name="ktp_general_settings[delivery_warning_days]">
            <option value="1" <?php selected( $value, 1 ); ?>><?php echo esc_html( /* translators: %d: 日数 */ sprintf( __( '%d日', 'kantanpro' ), 1 ) ); ?></option>
            <option value="3" <?php selected( $value, 3 ); ?>><?php echo esc_html( /* translators: %d: 日数 */ sprintf( __( '%d日', 'kantanpro' ), 3 ) ); ?></option>
            <option value="7" <?php selected( $value, 7 ); ?>><?php echo esc_html( /* translators: %d: 日数 */ sprintf( __( '%d日', 'kantanpro' ), 7 ) ); ?></option>
            <option value="14" <?php selected( $value, 14 ); ?>><?php echo esc_html( /* translators: %d: 日数 */ sprintf( __( '%d日', 'kantanpro' ), 14 ) ); ?></option>
            <option value="30" <?php selected( $value, 30 ); ?>><?php echo esc_html( /* translators: %d: 日数 */ sprintf( __( '%d日', 'kantanpro' ), 30 ) ); ?></option>
        </select>
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ 納期警告日数を設定してください。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * 適格請求書番号フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function qualified_invoice_number_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['qualified_invoice_number'] ) ? $options['qualified_invoice_number'] : '';
        $mode = class_exists('KTPWP_Tax_Policy') ? KTPWP_Tax_Policy::get_mode() : ( isset($options['tax_mode']) ? $options['tax_mode'] : 'multiple' );
        if ( $mode === 'abolished' ) {
            echo '<div style="color:#666;font-size:12px;">' . esc_html__( '消費税なし（税廃止）設定のため、適格請求書番号は使用しません。', 'kantanpro' ) . '</div>';
            echo '<input type="hidden" name="ktp_general_settings[qualified_invoice_number]" value="" />';
            return;
        }
        ?>
        <input type="text" id="qualified_invoice_number" name="ktp_general_settings[qualified_invoice_number]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ 適格請求書発行事業者の登録番号を入力してください。（例：T1234567890123）', 'kantanpro' ); ?>
        </div>
        <?php
    }


    /**
     * 通貨フィールドのコールバック
     *
     * @return void
     */
    public function currency_code_callback() {
        $options = get_option( 'ktp_general_settings', array() );
        $value = isset( $options['currency_code'] ) ? strtoupper( (string) $options['currency_code'] ) : 'JPY';
        $currencies = self::get_supported_currencies();
        ?>
        <select id="currency_code" name="ktp_general_settings[currency_code]">
            <?php foreach ( $currencies as $code => $currency ) : ?>
                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $value, $code ); ?>>
                    <?php echo esc_html( $currency['label'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ 金額の表示通貨です。為替換算は行わず、入力済みの数値を選択した通貨として表示します。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * 会社情報フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function company_info_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['company_info'] ) ? $options['company_info'] : '';
        // nullや非文字列の場合は空文字列に変換
        $value = is_string( $value ) ? $value : '';
        // WordPress Visual Editor (TinyMCE) を表示
        $editor_id = 'company_info_editor';
        $settings = array(
            'textarea_name' => 'ktp_general_settings[company_info]',
            'media_buttons' => true,
            'tinymce' => array(
                'height' => 200,
                'toolbar1' => 'formatselect bold italic underline | alignleft aligncenter alignright alignjustify | removeformat',
                'toolbar2' => 'styleselect | forecolor backcolor | table | charmap | pastetext | code',
                'toolbar3' => '',
                'wp_adv' => false,
            ),
            'default_editor' => 'tinymce',
        );
        wp_editor( $value, $editor_id, $settings );
        ?>
        <div style="font-size:12px;color:#555;margin-top:8px;">
            <?php echo esc_html__( '※ メール送信時に署名として使用される会社情報です。HTMLタグが使用できます。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * 旧システムから新システムへのデータ移行処理
     *
     * @since 1.0.0
     */
    private static function migrate_company_info_from_old_system() {
        global $wpdb;

        // 移行済みフラグをチェック
        if ( get_option( 'ktp_company_info_migrated' ) ) {
            return; // 既に移行済み
        }

        // 旧設定テーブルから会社情報を取得
        $setting_table = $wpdb->prefix . 'ktp_setting';
        $old_setting = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT my_company_content FROM {$setting_table} WHERE id = %d",
                1
            )
        );

        if ( $old_setting && ! empty( $old_setting->my_company_content ) ) {
            // 現在の一般設定を取得
            $general_settings = get_option( 'ktp_general_settings', array() );

            // 会社情報が未設定の場合のみ移行
            if ( empty( $general_settings['company_info'] ) ) {
                $general_settings['company_info'] = $old_setting->my_company_content;
                update_option( 'ktp_general_settings', $general_settings );
            }
        }

        // 移行完了フラグを設定
        update_option( 'ktp_company_info_migrated', true );
    }

    /**
     * タブのアクティブ時の色フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function tab_active_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['tab_active_color'] ) ? $options['tab_active_color'] : '#cdcccc';
        ?>
        <input type="color" id="tab_active_color" name="ktp_design_settings[tab_active_color]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ アクティブなタブの背景色を設定してください。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * タブの非アクティブ時の背景色フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function tab_inactive_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['tab_inactive_color'] ) ? $options['tab_inactive_color'] : '#bbbbbb';
        ?>
        <input type="color" id="tab_inactive_color" name="ktp_design_settings[tab_inactive_color]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ 非アクティブなタブの背景色を設定してください。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * タブの下線色フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function tab_border_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['tab_border_color'] ) ? $options['tab_border_color'] : '#cdcccc';
        ?>
        <input type="color" id="tab_border_color" name="ktp_design_settings[tab_border_color]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ タブの下線（border-bottom）の色を設定してください。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * 奇数行の背景色フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function odd_row_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['odd_row_color'] ) ? $options['odd_row_color'] : '#ffffff';
        ?>
        <input type="color" id="odd_row_color" name="ktp_design_settings[odd_row_color]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ リスト表示で奇数行（1行目、3行目など）の背景色を設定してください。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * 偶数行の背景色フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function even_row_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['even_row_color'] ) ? $options['even_row_color'] : '#f9f9f9';
        ?>
        <input type="color" id="even_row_color" name="ktp_design_settings[even_row_color]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ リスト表示で偶数行（2行目、4行目など）の背景色を設定してください。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * 公開商品カードの背景色フィールドのコールバック
     *
     * @since 1.3.60
     * @return void
     */
    public function public_product_card_bg_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value   = isset( $options['public_product_card_bg_color'] ) ? $options['public_product_card_bg_color'] : '#e2e8f0';
        ?>
        <input type="color" id="public_product_card_bg_color" name="ktp_design_settings[public_product_card_bg_color]"
               value="<?php echo esc_attr( $value ); ?>"
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ [ktpwp_public_products] のグリッド型・カード型一覧で、各商品カードの背景色を設定します。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * ヘッダー背景画像フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function header_bg_image_callback() {
        $options = get_option( 'ktp_design_settings' );
        $image_value = isset( $options['header_bg_image'] ) ? $options['header_bg_image'] : 'images/default/header_bg_image.png';
        $image_url = '';

        // 数値の場合は添付ファイルID、文字列の場合は画像パス
        // デフォルト値がある場合は常に画像URLを設定
        if ( is_numeric( $image_value ) ) {
            // 添付ファイルIDの場合
            $image_url = wp_get_attachment_image_url( $image_value, 'full' );
        } else {
            // 文字列パスの場合
            $image_path = $image_value;
            if ( strpos( $image_path, 'http' ) !== 0 ) {
                // 相対パスの場合は、プラグインディレクトリからの絶対URLに変換
                $image_url = plugin_dir_url( __DIR__ ) . $image_path;
            } else {
                $image_url = $image_path;
            }
        } ?>
        <div class="ktp-image-upload-field">
            <input type="hidden" id="header_bg_image" name="ktp_design_settings[header_bg_image]" value="<?php echo esc_attr( $image_value ); ?>" data-default-url="<?php echo esc_url( plugin_dir_url( __DIR__ ) . 'images/default/header_bg_image.png' ); ?>" />
            
            <div class="ktp-image-preview" style="margin-bottom: 10px;">
                <img id="header_bg_image_preview" src="<?php echo esc_url( $image_url ); ?>" style="max-width: 300px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;" />
            </div>
            
            <button type="button" class="button ktp-upload-image">
                <?php echo esc_html__( '画像を変更', 'kantanpro' ); ?>
            </button>
            
            <div style="font-size:12px;color:#555;margin-top:4px;">
                <?php echo esc_html__( '※ ヘッダーの背景画像として使用されます。推奨サイズ: 1920×100px', 'kantanpro' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * 固定ページごとのコンテンツ幅フィールドのコールバック。
     *
     * @return void
     */
    public function page_content_widths_callback() {
        $options  = self::get_design_settings();
        $widths   = isset( $options['page_content_widths'] ) && is_array( $options['page_content_widths'] )
            ? $options['page_content_widths']
            : array();
        $pages    = self::get_ktpwp_shortcode_pages();
        $presets  = self::get_page_content_width_presets();
        $preset_keys = array_keys( $presets );
        ?>
        <p class="description" style="margin-top:0;">
            <?php echo esc_html__( 'ショートコードを設置した固定ページごとに、KantanPro 業務画面の表示幅を変更できます。未設定のページは標準（1400px）で表示されます。', 'kantanpro' ); ?>
        </p>
        <?php if ( empty( $pages ) ) : ?>
            <p><?php echo esc_html__( 'ショートコードが設置された公開中の固定ページがありません。', 'kantanpro' ); ?></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:760px;">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__( '固定ページ', 'kantanpro' ); ?></th>
                        <th scope="col"><?php echo esc_html__( '表示幅', 'kantanpro' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $pages as $page ) :
                        $page_id       = (int) $page->ID;
                        $saved_width   = isset( $widths[ $page_id ] ) ? self::sanitize_page_content_width_value( (string) $widths[ $page_id ] ) : '';
                        $selected      = '';
                        $custom_value  = '';

                        if ( $saved_width !== '' ) {
                            if ( in_array( $saved_width, $preset_keys, true ) ) {
                                $selected = $saved_width;
                            } else {
                                $selected     = 'custom';
                                $custom_value = preg_replace( '/[^0-9.]/', '', $saved_width );
                            }
                        } else {
                            $selected = isset( $widths[ $page_id ] ) ? '' : self::get_default_page_content_width();
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $page->post_title ); ?></strong><br>
                                <span class="description"><?php echo esc_html( /* translators: %d: ID番号 */ sprintf( __( 'ID: %d', 'kantanpro' ), $page_id ) ); ?></span>
                            </td>
                            <td>
                                <select
                                    id="ktp_page_content_width_<?php echo esc_attr( (string) $page_id ); ?>"
                                    name="ktp_design_settings[page_content_widths][<?php echo esc_attr( (string) $page_id ); ?>]"
                                    class="ktp-page-content-width-select"
                                    data-page-id="<?php echo esc_attr( (string) $page_id ); ?>"
                                >
                                    <?php foreach ( $presets as $value => $label ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input
                                    type="number"
                                    class="ktp-page-content-width-custom"
                                    data-page-id="<?php echo esc_attr( (string) $page_id ); ?>"
                                    name="ktp_design_settings[page_content_width_custom][<?php echo esc_attr( (string) $page_id ); ?>]"
                                    value="<?php echo esc_attr( $custom_value ); ?>"
                                    min="320"
                                    max="3840"
                                    step="1"
                                    placeholder="<?php echo esc_attr__( '例: 1350', 'kantanpro' ); ?>"
                                    style="width:120px;max-width:100%;margin-left:8px;<?php echo $selected === 'custom' ? '' : 'display:none;'; ?>"
                                >
                                <span class="description ktp-page-content-width-custom-suffix" data-page-id="<?php echo esc_attr( (string) $page_id ); ?>" style="<?php echo $selected === 'custom' ? '' : 'display:none;'; ?>">px</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>
            (function () {
                function toggleCustomWidthInput(select) {
                    var pageId = select.getAttribute('data-page-id');
                    var customInput = document.querySelector('.ktp-page-content-width-custom[data-page-id="' + pageId + '"]');
                    var suffix = document.querySelector('.ktp-page-content-width-custom-suffix[data-page-id="' + pageId + '"]');
                    var showCustom = select.value === 'custom';
                    if (customInput) {
                        customInput.style.display = showCustom ? '' : 'none';
                    }
                    if (suffix) {
                        suffix.style.display = showCustom ? '' : 'none';
                    }
                }

                document.querySelectorAll('.ktp-page-content-width-select').forEach(function (select) {
                    select.addEventListener('change', function () {
                        toggleCustomWidthInput(select);
                    });
                });
            })();
            </script>
        <?php endif; ?>
        <?php
    }


    /**
     * Output custom styles to frontend
     *
     * @since 1.0.0
     * @return void
     */
    public function output_custom_styles() {
        $design_options = get_option( 'ktp_design_settings', array() );

        // デザイン設定が存在しない場合は何もしない
        if ( empty( $design_options ) ) {
            return;
        }

        $custom_css = '';

        // div.ktp_headerの基本スタイル
        $custom_css .= '
div.ktp_header {
    border: none !important;
    margin-bottom: 10px;
    position: relative;
}';

        // タブを手前に表示するためのz-index設定
        $custom_css .= '
.tabs.ktp_plugin_container {
    z-index: 200;
    position: relative;
}';

        // ヘッダー背景画像の設定
        $header_bg_image = ! empty( $design_options['header_bg_image'] ) ? $design_options['header_bg_image'] : 'images/default/header_bg_image.png';
        $image_url = '';

        // 数値の場合は添付ファイルID、文字列の場合は画像パス
        if ( is_numeric( $header_bg_image ) ) {
            // 添付ファイルIDの場合
            $image_url = wp_get_attachment_image_url( $header_bg_image, 'full' );
        } else {
            // 文字列パスの場合
            $image_path = $header_bg_image;
            if ( strpos( $image_path, 'http' ) !== 0 ) {
                // 相対パスの場合は、プラグインディレクトリからの絶対URLに変換
                $image_url = plugin_dir_url( __DIR__ ) . $image_path;
            } else {
                $image_url = $image_path;
            }
        }

        if ( $image_url ) {
                $custom_css .= '
div.ktp_header {
    background-image: url(' . esc_url( $image_url ) . ');
    background-size: cover;
    background-position: center center;
    background-repeat: no-repeat;
    border: none !important;
    width: 100%;
    height: 100px;
    max-width: 1920px;
    margin: 0 auto 10px auto;
    position: relative;
    overflow: hidden;
}

div.ktp_header::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: transparent;
    z-index: 1;
}

div.ktp_header > * {
    position: relative;
    z-index: 2;
}';
        }

        // タブのアクティブ時の色設定
        if ( ! empty( $design_options['tab_active_color'] ) ) {
            $tab_active_color = sanitize_hex_color( $design_options['tab_active_color'] );
            if ( $tab_active_color ) {
                $custom_css .= '
.tabs.ktp_plugin_container input:checked + .tab_item,
.tabs.ktp_plugin_container .tab_item.active {
    background-color: ' . esc_attr( $tab_active_color ) . ' !important;
}';
            }
        }

        // タブの非アクティブ時の色設定（背景色として設定）
        if ( ! empty( $design_options['tab_inactive_color'] ) ) {
            $tab_inactive_color = sanitize_hex_color( $design_options['tab_inactive_color'] );
            if ( $tab_inactive_color ) {
                $custom_css .= '
.tabs.ktp_plugin_container .tab_item {
    background-color: ' . esc_attr( $tab_inactive_color ) . ' !important;
}';
            }
        }

        // タブの下線色設定
        if ( ! empty( $design_options['tab_border_color'] ) ) {
            $tab_border_color = sanitize_hex_color( $design_options['tab_border_color'] );
            if ( $tab_border_color ) {
                $custom_css .= '
.tabs.ktp_plugin_container .tab_item {
    border-bottom-color: ' . esc_attr( $tab_border_color ) . ' !important;
}';

                // コントローラーの背景色設定（タブの下線色を使用）
                $custom_css .= '
/* 各タブのコントローラー背景色設定 - PC/タブレット/モバイル共通 */
.controller {
    background-color: ' . esc_attr( $tab_border_color ) . ' !important;
    padding: 10px 10px 0 10px !important;
    border-radius: 0 0 4px 4px !important;
    margin-bottom: 10px !important;
}';
            }
        }

        // 奇数行の背景色設定
        if ( ! empty( $design_options['odd_row_color'] ) ) {
            $odd_row_color = sanitize_hex_color( $design_options['odd_row_color'] );
            if ( $odd_row_color ) {
                $custom_css .= '
/* KTPWPプラグイン用奇数行色設定 - 固有プレフィックス付きでテーマとの競合を防止 */
.ktp_data_list_box .ktp_list_item:nth-child(odd),
.ktp_data_list_box > a:nth-of-type(odd) .ktp_data_list_item,
.ktp_data_list_box > .ktp_data_list_item:nth-of-type(odd),
.ktp_data_skill_list_box > .ktp_data_list_item:nth-child(odd),
.ktp_work_list_box .ktp_work_list_item:nth-child(odd),
.ktp_work_list_box ul li:nth-child(odd),
.ktp_work_list_item:nth-child(odd),
.ktp_list_item:nth-child(odd),
.ktp_plugin_container ul li:nth-child(odd),
.ktp_data_contents .ktp_data_list_box > a:nth-of-type(odd) .ktp_data_list_item,
.ktp_data_list_box .ktp-service-related-list__rows > a:nth-of-type(odd) .ktp_data_list_item,
.ktp_search_list_box ul li:nth-child(odd),
.ktp_search_list_box > a:nth-of-type(odd) .ktp_data_list_item,
.ktp_plugin_container tr:nth-child(odd),
.ktp_plugin_container tbody tr:nth-child(odd) {
    background-color: ' . esc_attr( $odd_row_color ) . ' !important;
}';
            }
        }

        // 偶数行の背景色設定
        if ( ! empty( $design_options['even_row_color'] ) ) {
            $even_row_color = sanitize_hex_color( $design_options['even_row_color'] );
            if ( $even_row_color ) {
                $custom_css .= '
/* KTPWPプラグイン用偶数行色設定 - 固有プレフィックス付きでテーマとの競合を防止 */
.ktp_data_list_box .ktp_list_item:nth-child(even),
.ktp_data_list_box > a:nth-of-type(even) .ktp_data_list_item,
.ktp_data_list_box > .ktp_data_list_item:nth-of-type(even),
.ktp_data_skill_list_box > .ktp_data_list_item:nth-child(even),
.ktp_work_list_box .ktp_work_list_item:nth-child(even),
.ktp_work_list_box ul li:nth-child(even),
.ktp_work_list_item:nth-child(even),
.ktp_list_item:nth-child(even),
.ktp_plugin_container ul li:nth-child(even),
.ktp_data_contents .ktp_data_list_box > a:nth-of-type(even) .ktp_data_list_item,
.ktp_data_list_box .ktp-service-related-list__rows > a:nth-of-type(even) .ktp_data_list_item,
.ktp_search_list_box ul li:nth-child(even),
.ktp_search_list_box > a:nth-of-type(even) .ktp_data_list_item,
.ktp_plugin_container tr:nth-child(even),
.ktp_plugin_container tbody tr:nth-child(even) {
    background-color: ' . esc_attr( $even_row_color ) . ' !important;
}';
            }
        }

        // 公開商品カードの背景色設定
        if ( ! empty( $design_options['public_product_card_bg_color'] ) ) {
            $public_product_card_bg_color = sanitize_hex_color( $design_options['public_product_card_bg_color'] );
            if ( $public_product_card_bg_color ) {
                $custom_css .= '
.ktpwp-public-products {
    --ktpwp-public-product-card-bg: ' . esc_attr( $public_product_card_bg_color ) . ';
}
.ktpwp-public-products-grid__item,
.ktpwp-public-products-card {
    background: ' . esc_attr( $public_product_card_bg_color ) . ' !important;
}
.ktpwp-public-products-grid__image-wrap,
.ktpwp-public-products-card__image-wrap {
    background: ' . esc_attr( $public_product_card_bg_color ) . ' !important;
}';
            }
        }

        // 生の <style> を書き出さず、WordPress のスタイルキューに乗せる
        // （wp.org ガイドライン: 静的CSSは wp_enqueue_style / インラインは wp_add_inline_style）。
        if ( empty( $custom_css ) ) {
            return;
        }
        $handle = wp_style_is( 'ktp-css', 'registered' ) ? 'ktp-css' : 'ktp-admin-settings';
        if ( wp_style_is( $handle, 'registered' ) ) {
            wp_add_inline_style( $handle, $custom_css );
        }
    }

    /**
     * 固定ページの幅設定に応じた body_class を付与する。
     *
     * @param string[] $classes body クラス一覧。
     * @return string[]
     */
    public function filter_body_class_for_page_content_width( $classes ) {
        $page_id = self::get_current_ktpwp_page_id();
        $width   = self::get_page_content_width_for_page( $page_id );
        if ( $width === '' ) {
            return $classes;
        }

        $classes[] = 'ktpwp-has-page-width';
        $classes[] = 'ktpwp-page-width-' . preg_replace( '/[^0-9]/', '', $width );

        return $classes;
    }

    /**
     * 固定ページごとのコンテンツ幅スタイルを出力する。
     *
     * @return void
     */
    public function output_page_content_width_styles() {
        if ( is_admin() ) {
            return;
        }

        $page_id = self::get_current_ktpwp_page_id();
        if ( $page_id <= 0 ) {
            return;
        }

        $width = self::get_page_content_width_for_page( $page_id );
        if ( $width === '' ) {
            return;
        }

        $custom_css = self::build_page_content_width_css( $page_id, $width );
        if ( $custom_css === '' ) {
            return;
        }

        // 生の <style> を出さず WordPress のスタイルキューに載せる（wp.org ガイドライン）
        $handle = wp_style_is( 'ktp-css', 'registered' ) ? 'ktp-css' : 'ktp-admin-settings';
        if ( wp_style_is( $handle, 'registered' ) ) {
            wp_add_inline_style( $handle, $custom_css );
        }
    }

    /**
     * デフォルト設定管理のアクションを処理
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_default_settings_actions() {
        // 管理者権限チェック
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // デザイン設定ページでのみ実行
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'ktp-design-settings' ) {
            return;
        }

        // 設定をデフォルト値にリセット
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'reset_to_default' ) {
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ktp_reset_to_default_nonce'] ) ), 'ktp_reset_to_default' ) ) {
                wp_die( esc_html__( 'セキュリティチェックに失敗しました。', 'kantanpro' ) );
            }

            // システムデフォルト値を使用
            $system_defaults = array(
                'tab_active_color' => '#B7CBFB',
                'tab_inactive_color' => '#E6EDFF',
                'tab_border_color' => '#B7CBFB',
                'odd_row_color' => '#E7EEFD',
                'even_row_color' => '#FFFFFF',
                'public_product_card_bg_color' => '#e2e8f0',
                'header_bg_image' => 'images/default/header_bg_image.png',
                'page_content_widths' => self::build_default_page_content_widths(),
                );
            update_option( 'ktp_design_settings', $system_defaults );
            add_settings_error(
                'ktp_design_settings',
                'reset_to_default',
                __( 'デザイン設定をデフォルト値にリセットしました。', 'kantanpro' ),
                'updated'
            );

            // リダイレクトでページを再読み込みし、フォームの再送信を防ぐ
            wp_redirect( admin_url( 'admin.php?page=ktp-design-settings&settings-updated=true' ) );
            exit;
        }
    }

    /**
     * 決済設定の初期値を確実に保存
     */


    /**
     * 管理画面メニューの追加（デバッグログとREST API設定用）
     *
     * @since 1.3.0
     */
    public static function add_admin_menu() {
        add_options_page(
            __( 'KTPWP設定', 'kantanpro' ),
            __( 'KTPWP設定', 'kantanpro' ),
            'manage_options',
            'ktpwp-settings',
            array( __CLASS__, 'admin_page' )
        );
    }

    /**
     * 管理画面ページの表示
     *
     * @since 1.3.0
     */
    public static function admin_page() {
        // 設定の保存処理
        if ( isset( $_POST['submit'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ktpwp_settings_nonce'] ) ), 'ktpwp_settings' ) ) {
            self::save_settings();
        }

        $current_settings = self::get_all_settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'KTPWP設定', 'kantanpro' ); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'ktpwp_settings', 'ktpwp_settings_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'デバッグログ設定', 'kantanpro' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="ktpwp_debug_log_enabled" value="1" 
                                           <?php checked( $current_settings['debug_log_enabled'], '1' ); ?> />
                                    <?php echo esc_html__( 'デバッグログを有効にする', 'kantanpro' ); ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__( 'デバッグログは安全な場所（wp-content/logs/）に保存されます。', 'kantanpro' ); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'REST API制限', 'kantanpro' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="ktpwp_rest_api_restricted" value="1" 
                                           <?php checked( $current_settings['rest_api_restricted'], '1' ); ?> />
                                    <?php echo esc_html__( 'フロントエンドでのREST APIをログインユーザーのみに制限する', 'kantanpro' ); ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__( '管理画面やブロックエディターは常に許可されます。', 'kantanpro' ); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'トラブルシューティング', 'kantanpro' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="ktpwp_disable_rest_api_restriction" value="1" 
                                           <?php checked( $current_settings['disable_rest_api_restriction'], '1' ); ?> />
                                    <?php echo esc_html__( 'REST API制限を完全に無効化する（サイトヘルスエラーが解決されない場合）', 'kantanpro' ); ?>
                                </label>
                                <p class="description">
                                    <strong><?php echo esc_html__( '注意:', 'kantanpro' ); ?></strong> <?php echo esc_html__( 'この設定を有効にすると、セキュリティが低下する可能性があります。', 'kantanpro' ); ?>
                                    <?php echo esc_html__( 'サイトヘルスエラーが解決されない場合のみ使用してください。', 'kantanpro' ); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php echo esc_html__( '管理画面 IP 制限', 'kantanpro' ); ?></th>
                        <td>
                            <?php
                            $current_ip = class_exists( 'KTPWP_Security' )
                                ? KTPWP_Security::get_instance()->get_client_ip()
                                : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
                            ?>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="ktpwp_admin_ip_restriction_enabled" value="1"
                                           <?php checked( $current_settings['admin_ip_restriction_enabled'], '1' ); ?> />
                                    <?php echo esc_html__( '許可 IP 以外から wp-admin / wp-login.php へのアクセスを拒否する', 'kantanpro' ); ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__( '現在のアクセス元 IP:', 'kantanpro' ); ?>
                                    <code><?php echo esc_html( $current_ip !== '' ? $current_ip : __( '取得できません', 'kantanpro' ) ); ?></code>
                                </p>
                                <label for="ktpwp_admin_allowed_ips"><?php echo esc_html__( '許可 IP アドレス', 'kantanpro' ); ?></label><br>
                                <textarea name="ktpwp_admin_allowed_ips" id="ktpwp_admin_allowed_ips" rows="5" class="large-text code"><?php echo esc_textarea( $current_settings['admin_allowed_ips'] ); ?></textarea>
                                <p class="description">
                                    <?php echo esc_html__( '1 行に 1 件。IPv4 / IPv6、CIDR（例: 203.0.113.0/24）に対応。有効化する前に自分の IP を必ず追加してください。', 'kantanpro' ); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php echo esc_html__( '管理画面 Basic 認証', 'kantanpro' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="ktpwp_admin_basic_auth_enabled" value="1"
                                           <?php checked( $current_settings['admin_basic_auth_enabled'], '1' ); ?> />
                                    <?php echo esc_html__( 'IP 制限に該当しないアクセス、または IP 制限が使えない環境向けに Basic 認証を要求する', 'kantanpro' ); ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__( '許可 IP からのアクセスは Basic 認証なしで通過します。IP 制限を無効にした場合は、管理画面全体で Basic 認証が必要になります。', 'kantanpro' ); ?>
                                </p>
                                <p>
                                    <label for="ktpwp_admin_basic_auth_user"><?php echo esc_html__( 'ユーザー名', 'kantanpro' ); ?></label><br>
                                    <input type="text" name="ktpwp_admin_basic_auth_user" id="ktpwp_admin_basic_auth_user" class="regular-text"
                                           value="<?php echo esc_attr( $current_settings['admin_basic_auth_user'] ); ?>" autocomplete="off">
                                </p>
                                <p>
                                    <label for="ktpwp_admin_basic_auth_pass"><?php echo esc_html__( 'パスワード', 'kantanpro' ); ?></label><br>
                                    <input type="password" name="ktpwp_admin_basic_auth_pass" id="ktpwp_admin_basic_auth_pass" class="regular-text" value="" autocomplete="new-password">
                                </p>
                                <p class="description">
                                    <?php echo esc_html__( 'パスワードを空欄のまま保存すると、現在のパスワードを維持します。', 'kantanpro' ); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php echo esc_html__( '現在の設定状況', 'kantanpro' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'プラグインバージョン', 'kantanpro' ); ?></th>
                    <td><?php echo esc_html( $current_settings['version'] ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__( 'インストール日', 'kantanpro' ); ?></th>
                    <td><?php echo esc_html( $current_settings['installed_date'] ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__( 'デバッグモード', 'kantanpro' ); ?></th>
                    <td><?php echo esc_html( $current_settings['debug_mode'] ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__( 'サイトURL', 'kantanpro' ); ?></th>
                    <td><?php echo esc_html( home_url() ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__( '開発環境', 'kantanpro' ); ?></th>
                    <td><?php echo ( strpos( home_url(), 'localhost' ) !== false || strpos( home_url(), '127.0.0.1' ) !== false ) ? esc_html__( 'はい', 'kantanpro' ) : esc_html__( 'いいえ', 'kantanpro' ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__( 'REST API制限の状態', 'kantanpro' ); ?></th>
                    <td>
                        <?php
                        $rest_api_status = esc_html__( '有効', 'kantanpro' );
                        if ( class_exists( 'KTPWP_Settings' ) ) {
                            $rest_api_restricted = self::get_setting( 'rest_api_restricted', '1' );
                            $disable_rest_api_restriction = self::get_setting( 'disable_rest_api_restriction', '0' );

                            if ( $disable_rest_api_restriction === '1' ) {
                                $rest_api_status = '<span style="color: red;">' . esc_html__( '完全無効化', 'kantanpro' ) . '</span>';
                            } elseif ( $rest_api_restricted !== '1' ) {
                                $rest_api_status = '<span style="color: orange;">' . esc_html__( '無効', 'kantanpro' ) . '</span>';
                            } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG && ( strpos( home_url(), 'localhost' ) !== false || strpos( home_url(), '127.0.0.1' ) !== false ) ) {
                                $rest_api_status = '<span style="color: blue;">' . esc_html__( '開発環境で緩和', 'kantanpro' ) . '</span>';
                            }
                        }
                        echo wp_kses_post( $rest_api_status );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__( '管理画面アクセス保護', 'kantanpro' ); ?></th>
                    <td>
                        <?php
                        $admin_protection = array();
                        if ( $current_settings['admin_ip_restriction_enabled'] === '1' ) {
                            $admin_protection[] = esc_html__( 'IP 制限: 有効', 'kantanpro' );
                        }
                        if ( $current_settings['admin_basic_auth_enabled'] === '1' ) {
                            $admin_protection[] = esc_html__( 'Basic 認証: 有効', 'kantanpro' );
                        }
                        echo ! empty( $admin_protection )
                            ? esc_html( implode( ' / ', $admin_protection ) )
                            : esc_html__( '無効', 'kantanpro' );
                        ?>
                    </td>
                </tr>
            </table>
            
            <h2><?php echo esc_html__( '推奨設定（wp-config.php）', 'kantanpro' ); ?></h2>
            <div class="notice notice-info">
                <p><strong><?php echo esc_html__( 'デバッグログの安全な設定:', 'kantanpro' ); ?></strong></p>
                <pre><code><?php echo esc_html__( 'デバッグモードを有効化', 'kantanpro' ); ?>
define( 'WP_DEBUG', true );

<?php echo esc_html__( 'デバッグログを安全な場所に保存', 'kantanpro' ); ?>
define( 'WP_DEBUG_LOG', WP_CONTENT_DIR . '/logs/debug.log' );

<?php echo esc_html__( 'デバッグ表示を無効化（本番環境では必須）', 'kantanpro' ); ?>
define( 'WP_DEBUG_DISPLAY', false );

<?php echo esc_html__( 'スクリプトエラーの表示を無効化', 'kantanpro' ); ?>
@ini_set( 'display_errors', 0 );</code></pre>
            </div>
        </div>
        <?php
    }

    /**
     * 設定の保存
     *
     * @since 1.3.0
     */
    private static function save_settings() {
        // デバッグログ設定
        $debug_log_enabled = isset( $_POST['ktpwp_debug_log_enabled'] ) ? '1' : '0';
        update_option( 'ktpwp_debug_log_enabled', $debug_log_enabled );

        // REST API制限設定
        $rest_api_restricted = isset( $_POST['ktpwp_rest_api_restricted'] ) ? '1' : '0';
        update_option( 'ktpwp_rest_api_restricted', $rest_api_restricted );

        // REST API制限の完全無効化設定
        $disable_rest_api_restriction = isset( $_POST['ktpwp_disable_rest_api_restriction'] ) ? '1' : '0';
        update_option( 'ktpwp_disable_rest_api_restriction', $disable_rest_api_restriction );

        // 管理画面 IP 制限
        $admin_ip_restriction_enabled = isset( $_POST['ktpwp_admin_ip_restriction_enabled'] ) ? '1' : '0';
        $admin_allowed_ips            = isset( $_POST['ktpwp_admin_allowed_ips'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['ktpwp_admin_allowed_ips'] ) )
            : '';
        update_option( 'ktpwp_admin_ip_restriction_enabled', $admin_ip_restriction_enabled );
        update_option( 'ktpwp_admin_allowed_ips', $admin_allowed_ips );

        // 管理画面 Basic 認証
        $admin_basic_auth_enabled = isset( $_POST['ktpwp_admin_basic_auth_enabled'] ) ? '1' : '0';
        $admin_basic_auth_user    = isset( $_POST['ktpwp_admin_basic_auth_user'] )
            ? sanitize_user( wp_unslash( $_POST['ktpwp_admin_basic_auth_user'] ), true )
            : '';
        update_option( 'ktpwp_admin_basic_auth_enabled', $admin_basic_auth_enabled );
        update_option( 'ktpwp_admin_basic_auth_user', $admin_basic_auth_user );

        $admin_basic_auth_pass_raw = isset( $_POST['ktpwp_admin_basic_auth_pass'] )
            ? (string) wp_unslash( $_POST['ktpwp_admin_basic_auth_pass'] )
            : '';
        if ( $admin_basic_auth_pass_raw !== '' ) {
            update_option( 'ktpwp_admin_basic_auth_pass', wp_hash_password( $admin_basic_auth_pass_raw ) );
        }

        // 設定保存メッセージ
        add_action(
            'admin_notices',
            function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '設定を保存しました。', 'kantanpro' ) . '</p></div>';
			}
        );
    }

    /**
     * すべての設定を取得
     *
     * @since 1.3.0
     * @return array
     */
    public static function get_all_settings() {
        return array(
            'version' => get_option( 'ktpwp_version', KANTANPRO_PLUGIN_VERSION ),
            'installed_date' => get_option( 'ktpwp_installed_date', __( '不明', 'kantanpro' ) ),
            'debug_mode' => get_option( 'ktpwp_debug_mode', 'disabled' ),
            'debug_log_enabled' => get_option( 'ktpwp_debug_log_enabled', '0' ),
            'rest_api_restricted' => get_option( 'ktpwp_rest_api_restricted', '1' ),
            'disable_rest_api_restriction' => get_option( 'ktpwp_disable_rest_api_restriction', '0' ),
            'admin_ip_restriction_enabled' => get_option( 'ktpwp_admin_ip_restriction_enabled', '0' ),
            'admin_allowed_ips' => get_option( 'ktpwp_admin_allowed_ips', '' ),
            'admin_basic_auth_enabled' => get_option( 'ktpwp_admin_basic_auth_enabled', '0' ),
            'admin_basic_auth_user' => get_option( 'ktpwp_admin_basic_auth_user', '' ),
        );
    }

    /**
     * プラグインの設定を取得
     *
     * @since 1.3.0
     * @param string $key 設定キー
     * @param mixed  $default デフォルト値
     * @return mixed
     */
    public static function get_setting( $key, $default = null ) {
        return get_option( 'ktpwp_' . $key, $default );
    }

    /**
     * プラグインの設定を保存
     *
     * @since 1.3.0
     * @param string $key 設定キー
     * @param mixed  $value 設定値
     * @return bool
     */
    public static function save_setting( $key, $value ) {
        return update_option( 'ktpwp_' . $key, $value );
    }

    /**
     * デバッグログの書き込み（安全な方法）
     *
     * @since 1.3.0
     * @param string $message ログメッセージ
     * @param array  $context コンテキスト情報
     * @param string $level ログレベル (debug, info, warning, error)
     */
    public static function log_debug( $message, $context = array(), $level = 'debug' ) {
        // 本番環境では重要なエラーのみログ出力
        if ( self::is_production_environment() && ! in_array( $level, array( 'error', 'warning' ) ) ) {
            return;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = '[' . date( 'Y-m-d H:i:s' ) . '] KTPWP [' . strtoupper( $level ) . ']: ' . $message;

            if ( ! empty( $context ) ) {
                $log_message .= ' | Context: ' . wp_json_encode( $context );
            }

            // 安全なログファイルパスを使用
            $log_file = defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/logs/debug.log';

            // ログディレクトリが存在しない場合は作成
            $log_dir = dirname( $log_file );
            if ( ! is_dir( $log_dir ) ) {
                wp_mkdir_p( $log_dir );
            }

            // ログローテーションをチェック
            self::check_log_rotation( $log_file );

            // ログファイルに書き込み
            error_log( $log_message );
        }
    }

    /**
     * 本番環境かどうかを判定
     *
     * @since 1.3.0
     * @return bool
     */
    private static function is_production_environment() {
        // 環境変数で判定
        if ( defined( 'WP_ENV' ) && WP_ENV === 'production' ) {
            return true;
        }

        // ドメインで判定（本番ドメインの例）
        $site_url = get_site_url();
        $production_domains = array(
            'kantanpro.com',
            'www.kantanpro.com',
            // 他の本番ドメインを追加
        );

        foreach ( $production_domains as $domain ) {
            if ( strpos( $site_url, $domain ) !== false ) {
                return true;
            }
        }

        // デバッグモードが無効の場合は本番環境とみなす
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return true;
        }

        return false;
    }

    /**
     * ログローテーション機能
     *
     * @since 1.3.0
     * @param string $log_file ログファイルパス
     */
    private static function check_log_rotation( $log_file ) {
        if ( ! file_exists( $log_file ) ) {
            return;
        }

        // ログファイルサイズをチェック（5MB）
        $max_size = 5 * 1024 * 1024; // 5MB
        $current_size = filesize( $log_file );

        if ( $current_size > $max_size ) {
            // ローテーション実行
            $backup_file = $log_file . '.' . date( 'Y-m-d-H-i-s' ) . '.bak';
            
            if ( rename( $log_file, $backup_file ) ) {
                // 古いログファイルを削除（7日以上前のもの）
                self::cleanup_old_logs( dirname( $log_file ) );
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP: Log rotated - ' . basename( $backup_file ) );
                }
            }
        }
    }

    /**
     * 古いログファイルのクリーンアップ
     *
     * @since 1.3.0
     * @param string $log_dir ログディレクトリ
     */
    private static function cleanup_old_logs( $log_dir ) {
        $files = glob( $log_dir . '/debug.log.*.bak' );
        $cutoff_time = time() - ( 7 * 24 * 60 * 60 ); // 7日前

        foreach ( $files as $file ) {
            if ( filemtime( $file ) < $cutoff_time ) {
                unlink( $file );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP: Cleaned up old log file - ' . basename( $file ) );
                }
            }
        }
    }

    /**
     * 寄付通知プレビューセクションの表示
     */
    public function display_donation_preview_section() {
        ?>
        <div class="ktp-settings-section">
            <h3><?php esc_html_e( '寄付通知プレビュー', 'kantanpro' ); ?></h3>
            <p><?php esc_html_e( 'フロントエンドで表示される寄付通知のプレビューを確認できます。', 'kantanpro' ); ?></p>
            <?php $this->donation_notice_preview_callback(); ?>
        </div>
        <?php
    }



    /**
     * 強固な暗号化（AES-256-CBC + サイト固有キー）
     */
    private function strong_encrypt( $plain_text ) {
        if ( empty( $plain_text ) ) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = self::get_encryption_iv();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Encryption: Using site-specific encryption key' );
        }
        
        return base64_encode( openssl_encrypt( $plain_text, 'AES-256-CBC', $key, 0, $iv ) );
    }

    /**
     * 強固な復号
     */
    private function strong_decrypt( $encrypted_text ) {
        if ( empty( $encrypted_text ) ) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = self::get_encryption_iv();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Decryption: Using site-specific encryption key' );
        }
        
        $decrypted = openssl_decrypt( base64_decode( $encrypted_text ), 'AES-256-CBC', $key, 0, $iv );
        return $decrypted === false ? '' : $decrypted;
    }

    // 静的に使える強固な暗号化
    public static function strong_encrypt_static( $plain_text ) {
        if ( empty( $plain_text ) ) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = self::get_encryption_iv();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Encryption Static: Using site-specific encryption key' );
        }
        
        return base64_encode( openssl_encrypt( $plain_text, 'AES-256-CBC', $key, 0, $iv ) );
    }
    
    public static function strong_decrypt_static( $encrypted_text ) {
        if ( empty( $encrypted_text ) ) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = self::get_encryption_iv();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Decryption Static: Using site-specific encryption key' );
        }
        
        $decrypted = openssl_decrypt( base64_decode( $encrypted_text ), 'AES-256-CBC', $key, 0, $iv );
        return $decrypted === false ? '' : $decrypted;
    }

    /**
     * 暗号化キーの取得（サイト固有）
     */
    private static function get_encryption_key() {
        // WordPressのSALT定数をチェック
        if ( defined( 'AUTH_KEY' ) && ! empty( AUTH_KEY ) ) {
            return AUTH_KEY;
        }
        
        // フォールバック：サイトURLとDBプレフィックスを使用
        $site_url = get_site_url();
        global $wpdb;
        $prefix = $wpdb->prefix;
        return hash( 'sha256', $site_url . $prefix . 'ktpwp_key' );
    }
    
    /**
     * 初期化ベクトル（IV）の取得（サイト固有）
     */
    private static function get_encryption_iv() {
        if ( defined( 'SECURE_AUTH_KEY' ) && ! empty( SECURE_AUTH_KEY ) ) {
            return substr( hash( 'sha256', SECURE_AUTH_KEY ), 0, 16 );
        }
        
        // フォールバック：サイトURLとDBプレフィックスを使用
        $site_url = get_site_url();
        global $wpdb;
        $prefix = $wpdb->prefix;
        return substr( hash( 'sha256', $site_url . $prefix . 'ktpwp_iv' ), 0, 16 );
    }

    /**
     * 消費税設定セクションの説明
     */
    public function print_tax_section_info() {
        echo '<p>' . esc_html__( '消費税の基本設定を行います。', 'kantanpro' ) . '</p>';
    }

    /**
     * 振込先口座セクションの説明
     */
    public function print_bank_transfer_section_info() {
        echo '<p>' . esc_html__( '請求書の下部（自社情報の直後）に自動で印字されます。いずれかを入力すると表示されます。', 'kantanpro' ) . '</p>';
    }

    /**
     * 銀行名・支店名
     */
    public function bank_transfer_bank_branch_callback() {
        $options = get_option( 'ktp_general_settings', array() );
        $value   = isset( $options['bank_transfer_bank_branch'] ) ? $options['bank_transfer_bank_branch'] : '';
        ?>
        <input type="text"
               id="bank_transfer_bank_branch"
               name="ktp_general_settings[bank_transfer_bank_branch]"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text"
               placeholder="<?php echo esc_attr__( '例：〇〇銀行 △△支店', 'kantanpro' ); ?>" />
        <?php
    }

    /**
     * 口座種別（普通 / 当座）
     */
    public function bank_transfer_account_type_callback() {
        $options = get_option( 'ktp_general_settings', array() );
        $saved   = isset( $options['bank_transfer_account_type'] ) ? $options['bank_transfer_account_type'] : 'ordinary';
        ?>
        <label style="margin-right:16px;">
            <input type="radio" name="ktp_general_settings[bank_transfer_account_type]" value="ordinary" <?php checked( $saved, 'ordinary' ); ?> />
            <?php echo esc_html__( '普通', 'kantanpro' ); ?>
        </label>
        <label>
            <input type="radio" name="ktp_general_settings[bank_transfer_account_type]" value="current" <?php checked( $saved, 'current' ); ?> />
            <?php echo esc_html__( '当座', 'kantanpro' ); ?>
        </label>
        <?php
    }

    /**
     * 口座番号
     */
    public function bank_transfer_account_number_callback() {
        $options = get_option( 'ktp_general_settings', array() );
        $value   = isset( $options['bank_transfer_account_number'] ) ? $options['bank_transfer_account_number'] : '';
        ?>
        <input type="text"
               id="bank_transfer_account_number"
               name="ktp_general_settings[bank_transfer_account_number]"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text"
               inputmode="numeric"
               autocomplete="off"
               placeholder="<?php echo esc_attr__( '半角数字（ハイフン可）', 'kantanpro' ); ?>" />
        <?php
    }

    /**
     * 口座名義（カタカナ）
     */
    public function bank_transfer_account_holder_kana_callback() {
        $options = get_option( 'ktp_general_settings', array() );
        $value   = isset( $options['bank_transfer_account_holder_kana'] ) ? $options['bank_transfer_account_holder_kana'] : '';
        ?>
        <input type="text"
               id="bank_transfer_account_holder_kana"
               name="ktp_general_settings[bank_transfer_account_holder_kana]"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text"
               placeholder="<?php echo esc_attr__( '例：カブシキガイシャ ヤマダ', 'kantanpro' ); ?>" />
        <p class="description"><?php echo esc_html__( '通帳記載の名義と同じ表記（カタカナ）を推奨します。', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * 基本税率フィールドのコールバック
     */
    public function default_tax_rate_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['default_tax_rate'] ) ? $options['default_tax_rate'] : 10.00;
        $mode = class_exists('KTPWP_Tax_Policy') ? KTPWP_Tax_Policy::get_mode() : ( isset($options['tax_mode']) ? $options['tax_mode'] : 'multiple' );
        $disabled = ( $mode === 'abolished' ) ? 'disabled' : '';
        ?>
        <input type="number" 
               id="default_tax_rate" 
               name="ktp_general_settings[default_tax_rate]" 
               value="<?php echo esc_attr( $value ); ?>" 
               step="1" 
               min="0" 
               max="100" 
               style="width: 100px;" <?php echo $disabled; ?> />
        <span>%</span>
        <p class="description">
            <?php esc_html_e( '基本税率を設定してください（消費税なしの場合は自動的に無効）。例：10', 'kantanpro' ); ?>
        </p>
        <?php
    }

    /**
     * 軽減税率フィールドのコールバック
     */
    public function reduced_tax_rate_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['reduced_tax_rate'] ) ? $options['reduced_tax_rate'] : 8.00;
        $mode = class_exists('KTPWP_Tax_Policy') ? KTPWP_Tax_Policy::get_mode() : ( isset($options['tax_mode']) ? $options['tax_mode'] : 'multiple' );
        $disabled = ( $mode === 'abolished' ) ? 'disabled' : '';
        ?>
        <input type="number" 
               id="reduced_tax_rate" 
               name="ktp_general_settings[reduced_tax_rate]" 
               value="<?php echo esc_attr( $value ); ?>" 
               step="1" 
               min="0" 
               max="100" 
               style="width: 100px;" <?php echo $disabled; ?> />
        <span>%</span>
        <p class="description">
            <?php esc_html_e( '軽減税率を設定してください（消費税なしの場合は自動的に無効）。例：8', 'kantanpro' ); ?>
        </p>
        <?php
    }

    /**
     * 更新通知設定セクション情報を表示
     */
    public function print_update_notification_section_info() {
        echo '<p>' . esc_html__( 'プラグインの更新通知に関する設定を行います。', 'kantanpro' ) . '</p>';
    }

    /**
     * 更新通知の有効化コールバック
     */
    public function enable_notifications_callback() {
        $options = get_option( 'ktp_update_notification_settings', array() );
        $value = isset( $options['enable_notifications'] ) ? $options['enable_notifications'] : true;
        ?>
        <label>
            <input type="checkbox" 
                   id="enable_notifications" 
                   name="ktp_update_notification_settings[enable_notifications]" 
                   value="1" 
                   <?php checked( $value, true ); ?> />
            <?php esc_html_e( '更新通知を有効にする', 'kantanpro' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'この設定を無効にすると、すべての更新通知が表示されなくなります。', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * 管理画面通知の有効化コールバック
     */
    public function enable_admin_notifications_callback() {
        $options = get_option( 'ktp_update_notification_settings', array() );
        $value = isset( $options['enable_admin_notifications'] ) ? $options['enable_admin_notifications'] : true;
        ?>
        <label>
            <input type="checkbox" 
                   id="enable_admin_notifications" 
                   name="ktp_update_notification_settings[enable_admin_notifications]" 
                   value="1" 
                   <?php checked( $value, true ); ?> />
            <?php esc_html_e( '管理画面での更新通知を有効にする', 'kantanpro' ); ?>
        </label>
        <p class="description"><?php esc_html_e( '管理画面のプラグインリストページとKantanPro設置ページで更新通知を表示します。', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * フロントエンド通知の有効化コールバック
     */
    public function enable_frontend_notifications_callback() {
        $options = get_option( 'ktp_update_notification_settings', array() );
        $value = isset( $options['enable_frontend_notifications'] ) ? $options['enable_frontend_notifications'] : true;
        ?>
        <label>
            <input type="checkbox" 
                   id="enable_frontend_notifications" 
                   name="ktp_update_notification_settings[enable_frontend_notifications]" 
                   value="1" 
                   <?php checked( $value, true ); ?> />
            <?php esc_html_e( 'フロントエンドでの更新通知を有効にする', 'kantanpro' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'KantanProが表示されているページで更新通知を表示します。', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * チェック間隔コールバック
     */
    public function check_interval_callback() {
        $options = get_option( 'ktp_update_notification_settings', array() );
        $value = isset( $options['check_interval'] ) ? $options['check_interval'] : 24;
        ?>
        <input type="number" 
               id="check_interval" 
               name="ktp_update_notification_settings[check_interval]" 
               value="<?php echo esc_attr( $value ); ?>" 
               min="1" 
               max="168" 
               style="width: 100px;" />
        <p class="description"><?php esc_html_e( '更新チェックの間隔を時間単位で設定してください（1-168時間）。', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * 通知対象ユーザー権限コールバック
     */
    public function notification_roles_callback() {
        $options = get_option( 'ktp_update_notification_settings', array() );
        $selected_roles = isset( $options['notification_roles'] ) ? $options['notification_roles'] : array( 'administrator' );
        
        $available_roles = array(
            'administrator' => __( '管理者', 'kantanpro' ),
            'editor' => __( '編集者', 'kantanpro' ),
            'author' => __( '投稿者', 'kantanpro' ),
            'contributor' => __( '寄稿者', 'kantanpro' ),
            'subscriber' => __( '購読者', 'kantanpro' )
        );
        
        foreach ( $available_roles as $role => $label ) {
            ?>
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" 
                       name="ktp_update_notification_settings[notification_roles][]" 
                       value="<?php echo esc_attr( $role ); ?>" 
                       <?php checked( in_array( $role, $selected_roles ), true ); ?> />
                <?php echo esc_html( $label ); ?>
            </label>
            <?php
        } ?>
        <p class="description"><?php esc_html_e( '更新通知を表示するユーザー権限を選択してください。', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * GitHubトークンコールバック
     */
    public function github_token_callback() {
        $options = get_option( 'ktp_update_notification_settings', array() );
        $value = isset( $options['github_token'] ) ? $options['github_token'] : '';
        ?>
        <input type="password" 
               id="github_token" 
               name="ktp_update_notification_settings[github_token]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width: 400px;" />
        <p class="description">
            <strong style="color: #28a745;"><?php echo esc_html__( '公開リポジトリ用に最適化済み', 'kantanpro' ); ?></strong><br>
            <?php esc_html_e( '現在のプラグインは公開リポジトリ用に設定されています。', 'kantanpro' ); ?>
            <br>
            <?php esc_html_e( '非公開リポジトリを使用する場合のみ、GitHub Personal Access Tokenを設定してください。', 'kantanpro' ); ?>
            <br>
            <a href="https://github.com/settings/tokens" target="_blank"><?php echo esc_html__( 'GitHub Personal Access Tokenの作成', 'kantanpro' ); ?></a>
            <br>
            <?php esc_html_e( '必要な権限: repo (プライベートリポジトリへのアクセス)', 'kantanpro' ); ?>
            <br><br>
            <strong><?php echo esc_html__( '現在の設定:', 'kantanpro' ); ?></strong> <?php echo esc_html__( '公開リポジトリ「KantanPro/KantanPro-a-」を使用', 'kantanpro' ); ?>
        </p>
        <?php
    }

    /**
     * プラグイン削除時データ保持セクションの説明
     */
    public function print_uninstall_section_info() {
        ?>
        <p><?php esc_html_e( 'プラグインをアンインストール（削除）する時に、登録されたデータをどう扱うかを選択できます。', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * プラグイン削除時の動作モード選択コールバック
     */
    public function uninstall_mode_callback() {
        $options = get_option( 'ktp_uninstall_settings', array() );
        $current = isset( $options['uninstall_mode'] ) ? $options['uninstall_mode'] : 'keep_data';
        ?>
        <fieldset>
            <label style="display:block;margin-bottom:10px;line-height:1.6;">
                <input type="radio"
                       name="ktp_uninstall_settings[uninstall_mode]"
                       value="keep_data"
                       <?php checked( $current, 'keep_data' ); ?> />
                <strong><?php esc_html_e( 'データを残す（推奨）', 'kantanpro' ); ?></strong><br />
                <span style="color:#555;margin-left:24px;display:inline-block;">
                    <?php esc_html_e( 'プラグインを削除しても、顧客・サービス・協力会社・受注書などのデータはデータベースに残ります。後からプラグインを再インストールしたとき、以前のデータをそのまま引き続き利用できます。', 'kantanpro' ); ?>
                </span>
            </label>
            <label style="display:block;line-height:1.6;">
                <input type="radio"
                       name="ktp_uninstall_settings[uninstall_mode]"
                       value="full_delete"
                       <?php checked( $current, 'full_delete' ); ?> />
                <strong style="color:#d63638;"><?php esc_html_e( '完全削除（すべてのデータを消す）', 'kantanpro' ); ?></strong><br />
                <span style="color:#555;margin-left:24px;display:inline-block;">
                    <?php esc_html_e( 'プラグインを削除すると同時に、KantanProに保存されている全データ（顧客・サービス・協力会社・受注書・請求書・設定など）をデータベースから完全に削除します。', 'kantanpro' ); ?>
                </span>
            </label>
        </fieldset>
        <div style="margin-top:14px;padding:10px 14px;background:#fef2f2;border-left:4px solid #d63638;border-radius:3px;">
            <strong style="color:#d63638;">⚠ <?php esc_html_e( '重要な注意:', 'kantanpro' ); ?></strong>
            <?php esc_html_e( '「完全削除」を選んだ状態でプラグインを削除すると、すべてのデータが失われ、元に戻せません。必ず事前にバックアップを取ってから実行してください。', 'kantanpro' ); ?>
        </div>
        <?php
    }

    /**
     * アンインストール設定のサニタイズ
     *
     * @param array $input
     * @return array
     */
    public function sanitize_uninstall_settings( $input ) {
        $sanitized = array( 'uninstall_mode' => 'keep_data' );
        if ( is_array( $input ) && isset( $input['uninstall_mode'] ) ) {
            $mode = sanitize_key( (string) $input['uninstall_mode'] );
            if ( in_array( $mode, array( 'keep_data', 'full_delete' ), true ) ) {
                $sanitized['uninstall_mode'] = $mode;
            }
        }
        return $sanitized;
    }

    /**
     * 更新通知設定のサニタイズ
     */
    public function sanitize_update_notification_settings( $input ) {
        $sanitized = array();
        
        // 更新通知の有効化
        $sanitized['enable_notifications'] = isset( $input['enable_notifications'] ) ? true : false;
        
        // 管理画面通知の有効化
        $sanitized['enable_admin_notifications'] = isset( $input['enable_admin_notifications'] ) ? true : false;
        
        // フロントエンド通知の有効化
        $sanitized['enable_frontend_notifications'] = isset( $input['enable_frontend_notifications'] ) ? true : false;
        
        // チェック間隔
        $sanitized['check_interval'] = isset( $input['check_interval'] ) ? intval( $input['check_interval'] ) : 24;
        if ( $sanitized['check_interval'] < 1 ) {
            $sanitized['check_interval'] = 1;
        } elseif ( $sanitized['check_interval'] > 168 ) {
            $sanitized['check_interval'] = 168;
        }
        
        // 通知対象ユーザー権限
        $sanitized['notification_roles'] = isset( $input['notification_roles'] ) && is_array( $input['notification_roles'] ) 
            ? array_map( 'sanitize_text_field', $input['notification_roles'] ) 
            : array( 'administrator' );
        
        // GitHubトークン
        $sanitized['github_token'] = isset( $input['github_token'] ) ? sanitize_text_field( $input['github_token'] ) : '';
        
        return $sanitized;
    }

    /**
     * 開発環境の判定
     *
     * @return bool
     */
    private function is_development_environment() {
        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

        return in_array( $host, array( 'localhost', '127.0.0.1' ), true )
            || strpos( $host, '.local' ) !== false
            || strpos( $host, '.test' ) !== false
            || strpos( $host, '.dev' ) !== false
            || strpos( $host, 'localhost:' ) !== false
            || strpos( $host, '127.0.0.1:' ) !== false
            || ( defined( 'WP_ENV' ) && WP_ENV === 'development' )
            || ( defined( 'KTPWP_DEVELOPMENT_MODE' ) && KTPWP_DEVELOPMENT_MODE === true );
    }

    /**
     * 開発者設定画面を表示可能か判定
     *
     * @return bool
     */
    private function is_developer_settings_enabled() {
        return ( defined( 'KTPWP_DEVELOPMENT_MODE' ) && KTPWP_DEVELOPMENT_MODE )
            || ( defined( 'KANTANPRO_DEV_MODE' ) && KANTANPRO_DEV_MODE )
            || $this->is_development_environment();
    }

    /**
     * 中央バナー設定セクションの説明
     *
     * @return void
     */
    public function print_central_banner_section_info() {
        return;
    }

    /**
     * 中央バナー設定の取得
     *
     * @return array
     */
    private function get_central_banner_settings() {
        $defaults = array(
            'enabled'    => 0,
            'source_url' => '',
            'image_url'  => '',
            'link_url'   => '',
            'alt_text'   => '',
        );

        $settings = get_option( 'ktp_central_banner_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * 中央バナー設定のサニタイズ
     *
     * @param array $input 入力値
     * @return array
     */
    public function sanitize_central_banner_settings( $input ) {
        if ( ! is_array( $input ) ) {
            $input = array();
        }

        $sanitized = array();
        $sanitized['enabled']    = isset( $input['enabled'] ) ? 1 : 0;
        $sanitized['source_url'] = isset( $input['source_url'] ) ? esc_url_raw( $input['source_url'] ) : '';
        $sanitized['image_url']  = isset( $input['image_url'] ) ? esc_url_raw( $input['image_url'] ) : '';
        $sanitized['link_url']   = isset( $input['link_url'] ) ? esc_url_raw( $input['link_url'] ) : '';
        $sanitized['alt_text']   = isset( $input['alt_text'] ) ? sanitize_text_field( $input['alt_text'] ) : '';

        return $sanitized;
    }

    /**
     * 中央バナー配信有効化フィールド
     *
     * @return void
     */
    public function central_banner_enabled_callback() {
        $settings = $this->get_central_banner_settings();
        ?>
        <label>
            <input type="checkbox" name="ktp_central_banner_settings[enabled]" value="1" <?php checked( 1, (int) $settings['enabled'] ); ?> />
            <?php esc_html_e( '中央バナー配信を有効にする', 'kantanpro' ); ?>
        </label>
        <?php
    }

    /**
     * 中央バナー外部参照URLフィールド
     *
     * @return void
     */
    public function central_banner_source_url_callback() {
        $settings = $this->get_central_banner_settings();
        ?>
        <input type="url" class="regular-text" name="ktp_central_banner_settings[source_url]" value="<?php echo esc_attr( $settings['source_url'] ); ?>" placeholder="https://example.com/banner.json" />
        <p class="description">
            <?php esc_html_e( '設定すると、このURLのJSONを優先してバナー表示に使用します。JSON例: {"enabled":true,"image_url":"https://...","link_url":"https://...","alt_text":"..."}', 'kantanpro' ); ?>
        </p>
        <?php
    }

    /**
     * 配布用バナー画像URL（KTP Banner 未使用の配布元向け）
     *
     * @return void
     */
    public function central_banner_image_url_callback() {
        $settings = $this->get_central_banner_settings();
        ?>
        <input type="url" class="regular-text" name="ktp_central_banner_settings[image_url]" value="<?php echo esc_attr( $settings['image_url'] ); ?>" placeholder="https://example.com/banner.png" />
        <p class="description"><?php esc_html_e( 'KTP Banner プラグインで画像を設定している場合はそちらが優先されます。未使用のときは REST 配信にこの URL が使われます。', 'kantanpro' ); ?></p>
        <?php
    }

    /**
     * 配布用バナーリンクURL
     *
     * @return void
     */
    public function central_banner_link_url_callback() {
        $settings = $this->get_central_banner_settings();
        ?>
        <input type="url" class="regular-text" name="ktp_central_banner_settings[link_url]" value="<?php echo esc_attr( $settings['link_url'] ); ?>" placeholder="https://example.com/" />
        <?php
    }

    /**
     * 配布用代替テキスト
     *
     * @return void
     */
    public function central_banner_alt_text_callback() {
        $settings = $this->get_central_banner_settings();
        ?>
        <input type="text" class="regular-text" name="ktp_central_banner_settings[alt_text]" value="<?php echo esc_attr( $settings['alt_text'] ); ?>" />
        <?php
    }

    /**
     * 中央バナー配信用 REST API ルートを登録
     *
     * @return void
     */
    public function register_central_banner_rest_route() {
        register_rest_route(
            'kantanpro/v1',
            '/central-banner',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_central_banner_rest_response' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * 中央バナー設定を REST で返す
     *
     * @return WP_REST_Response
     */
    public function get_central_banner_rest_response() {
        $settings = $this->get_central_banner_settings();
        $legacy_banner = get_option( 'ktp_banner_options', array() );

        $image_url = '';
        $link_url  = '';
        $alt_text  = '';

        // EX 本体では広告バナーを表示しないが、配布先 KantanPro 向けの API 配信は許可する。
        // 公式サイト側に KTP Banner がある場合は、その設定を優先して配信する。
        // KTP Banner プラグインは複数バナー形式（banners配列）に移行済みのため、
        // 旧フラット形式（image_url直下）と新形式（banners[].image_url）の両方に対応する。
        $legacy_active_banner = array();
        if ( is_array( $legacy_banner ) && ! empty( $legacy_banner['enabled'] ) ) {
            if ( ! empty( $legacy_banner['banners'] ) && is_array( $legacy_banner['banners'] ) ) {
                foreach ( $legacy_banner['banners'] as $banner_item ) {
                    if ( ! is_array( $banner_item ) || empty( $banner_item['enabled'] ) || empty( $banner_item['image_url'] ) ) {
                        continue;
                    }
                    $legacy_active_banner = $banner_item;
                    break;
                }
            } elseif ( ! empty( $legacy_banner['image_url'] ) ) {
                $legacy_active_banner = $legacy_banner;
            }
        }

        if ( ! empty( $legacy_active_banner['image_url'] ) ) {
            $image_url = esc_url_raw( $legacy_active_banner['image_url'] );
            $link_url  = isset( $legacy_active_banner['link_url'] ) ? esc_url_raw( $legacy_active_banner['link_url'] ) : '';
            $alt_text  = isset( $legacy_active_banner['alt_text'] ) ? sanitize_text_field( $legacy_active_banner['alt_text'] ) : '';
        } elseif ( ! empty( $settings['enabled'] ) && ! empty( $settings['image_url'] ) ) {
            $image_url = esc_url_raw( $settings['image_url'] );
            $link_url  = isset( $settings['link_url'] ) ? esc_url_raw( $settings['link_url'] ) : '';
            $alt_text  = isset( $settings['alt_text'] ) ? sanitize_text_field( $settings['alt_text'] ) : '';
        }

        $payload = array(
            'enabled'    => ( '' !== $image_url ),
            'image_url'  => $image_url,
            'link_url'   => $link_url,
            'alt_text'   => $alt_text,
            'updated_at' => current_time( 'mysql' ),
        );

        return rest_ensure_response( $payload );
    }

}

// インスタンスを初期化
KTPWP_Settings::get_instance();
