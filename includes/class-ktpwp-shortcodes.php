<?php
/**
 * KTPWP ショートコード管理クラス
 *
 * @package KTPWP
 * @since 0.1.0
 */

// セキュリティ: 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ショートコード管理クラス
 */
class KTPWP_Shortcodes {

    /**
     * シングルトンインスタンス
     *
     * @var KTPWP_Shortcodes|null
     */
    private static $instance = null;

    /**
     * ユーザーログイン状況キャッシュ
     *
     * @var array
     */
    private $logged_in_users_cache = null;

    /**
     * 登録されたショートコード一覧
     *
     * @var array
     */
    private $registered_shortcodes = array();

    /**
     * シングルトンインスタンス取得
     *
     * @return KTPWP_Shortcodes
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * フック初期化
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'register_shortcodes' ), 15 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_products_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_banner_rotator_assets' ) );
        // ※Ajaxハンドラの登録は class-ktpwp-ajax.php 側でのみ行う
    }

    /**
     * 中央バナーが複数件ある場合のみ、ローテーション用CSS/JSをWordPressの
     * 正規のenqueue APIで登録する。
     *
     * 本文中に直接 <style>/<script> タグを出力すると、一部のキャッシュ/軽量化
     * プラグイン（例: WP-Optimizeの「インラインCSS/JSの結合」機能）がタグごと
     * 除去してしまうことがあるため、必ずこの経路で読み込ませること。
     *
     * @return void
     */
    public function maybe_enqueue_banner_rotator_assets() {
        if ( ! is_user_logged_in() ) {
            return;
        }
        if ( function_exists( 'ktpwp_should_hide_ktp_banner' ) && ktpwp_should_hide_ktp_banner() ) {
            return;
        }

        $options = $this->get_central_banner_options();
        if ( empty( $options ) ) {
            $options = $this->get_legacy_banner_options();
        }
        $banners = ! empty( $options['banners'] ) && is_array( $options['banners'] ) ? $options['banners'] : array();
        if ( count( $banners ) < 2 ) {
            return;
        }

        $css = '.ktp-banner-rotator-item{width:100%;max-width:100%;box-sizing:border-box;transition:opacity .5s ease;}'
            . '.ktp-banner-rotator-item:not(.is-active){position:absolute;top:0;left:0;opacity:0;visibility:hidden;pointer-events:none;}'
            . '.ktp-banner-rotator-item.is-active{position:relative;opacity:1;visibility:visible;pointer-events:auto;}';

        $js = 'function initKtpBannerRotator(el){'
            . 'var items=el.querySelectorAll(".ktp-banner-rotator-item");'
            . 'if(items.length<2){return;}'
            . 'var interval=parseInt(el.getAttribute("data-interval"),10);'
            . 'if(!isFinite(interval)||interval<2){interval=5;}'
            . 'var i=0;'
            . 'setInterval(function(){'
            . 'items[i].classList.remove("is-active");'
            . 'i=(i+1)%items.length;'
            . 'items[i].classList.add("is-active");'
            . '},interval*1000);'
            . '}'
            . 'document.querySelectorAll(".ktp-banner-rotator").forEach(initKtpBannerRotator);';

        wp_register_style( 'ktp-banner-rotator', false, array(), KANTANPRO_PLUGIN_VERSION );
        wp_enqueue_style( 'ktp-banner-rotator' );
        wp_add_inline_style( 'ktp-banner-rotator', $css );

        wp_register_script( 'ktp-banner-rotator', false, array(), KANTANPRO_PLUGIN_VERSION, true );
        wp_enqueue_script( 'ktp-banner-rotator' );
        wp_add_inline_script( 'ktp-banner-rotator', $js );
    }

    /**
     * ショートコード登録
     */
    public function register_shortcodes() {
        $map = array(
            'kantanAllTab'          => 'render_all_tabs',
            'ktpwp_all_tab'         => 'render_all_tabs',
            'kantanpro_ex'          => 'render_all_tabs',
            'ktpwp_public_products' => 'render_public_products',
        );

        foreach ( $map as $tag => $method ) {
            if ( shortcode_exists( $tag ) ) {
                continue;
            }

            add_shortcode( $tag, array( $this, $method ) );
            $this->registered_shortcodes[] = $tag;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Shortcodes: Registered shortcodes - ' . implode( ', ', $this->registered_shortcodes ) );
        }
    }

    /**
     * 全タブショートコードの描画
     *
     * @param array $atts ショートコード属性
     * @return string 描画されたHTML
     */
    public function render_all_tabs($atts = array()) {
        // Ajaxリクエスト中（特に投稿保存時など）は、ショートコードの出力を抑制する
        // これにより、JSONレスポンスが壊れるのを防ぐ
        // ただし、このショートコード自体がAjaxでコンテンツを返すことを意図している場合は、この条件分岐は見直す必要がある
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // WordPressの投稿保存処理など、特定のAjaxアクションを判定して分岐することも検討できる
            // 例: if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'editpost')
            return '';
        }

        // ログイン状態と権限チェック
        if (!is_user_logged_in()) { // ログインしているかチェック
            return $this->render_login_error();
        }
        // 権限チェックを削除し、ログインしていれば誰でも表示するように変更
        // if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) { // 次に権限をチェック
        //     return $this->render_permission_error(); // 権限がない場合は専用のエラーメッセージ
        // }

        // 属性のデフォルト値設定
        $atts = shortcode_atts(array(
            'debug' => 'false',
            'cache' => 'true',
        ), $atts, 'kantanpro_ex');

        ob_start(); // 出力バッファリングを開始
        $layout_attrs = class_exists( 'KTPWP_Settings' )
            ? KTPWP_Settings::get_page_layout_wrapper_attributes( 0, array( 'ktpwp-shortcode-container' ) )
            : 'class="ktpwp-page-layout ktpwp-shortcode-container"';
        echo '<div ' . $layout_attrs . '>'; // コンテナ開始

        try {
            // 各種コンテンツの取得
            $header_content = $this->get_header_content();
            $tab_content = $this->get_tab_content();

            // 有料版では KTP banner を表示しない（free では表示）
            $hide_banner = function_exists( 'ktpwp_should_hide_ktp_banner' ) && ktpwp_should_hide_ktp_banner();
            if ( ! $hide_banner ) {
                // KantanProロゴヘッダーの上に外部プラグイン（例: ktp-banner）差し込み領域を用意
                ob_start();
                do_action( 'ktpwp_between_pagination_footer' );
                $before_header_content = ob_get_clean();

                // フック未登録時の互換フォールバック: ショートコード直接描画
                if ( empty( $before_header_content ) && shortcode_exists( 'ktp_banner' ) ) {
                    $before_header_content = do_shortcode( '[ktp_banner]' );
                }
                // さらに空の場合はオプション値から直接描画（最終フォールバック）
                if ( empty( $before_header_content ) ) {
                    $before_header_content = $this->render_ktp_banner_from_option();
                }

                if ( ! empty( $before_header_content ) ) {
                    echo '<div class="ktp-before-header-banner" style="width:100%;max-width:100%;margin:0;text-align:center;box-sizing:border-box;">';
                    echo wp_kses_post( $before_header_content );
                    echo '</div>';
                }
            }

            echo $header_content . $tab_content; // バッファに出力

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP Shortcode Error: ' . $e->getMessage());
            }
            echo '<div class="ktpwp-error">' . esc_html__('エラーが発生しました。', 'kantanpro') . '</div>'; // バッファに出力
        }

        echo '</div>'; // コンテナ終了
        return ob_get_clean(); // バッファの内容を取得して返す
    }

    /**
     * フック・[ktp_banner] 経由が空のときのバナー HTML（ktpwp.php の kantanAllTab からも利用）
     *
     * @return string
     */
    public function get_banner_fallback_html_after_hooks() {
        if ( function_exists( 'ktpwp_should_hide_ktp_banner' ) && ktpwp_should_hide_ktp_banner() ) {
            return '';
        }
        return $this->render_ktp_banner_from_option();
    }

    /**
     * 設定値からバナーHTMLを生成する最終フォールバック。
     * 優先順位:
     * 1) KantanPro本体の中央バナー設定
     * 2) 互換のための ktp-banner プラグイン設定
     *
     * @return string
     */
    private function render_ktp_banner_from_option() {
        if ( function_exists( 'ktpwp_should_hide_ktp_banner' ) && ktpwp_should_hide_ktp_banner() ) {
            return '';
        }
        $options = $this->get_central_banner_options();
        if ( empty( $options ) ) {
            $options = $this->get_legacy_banner_options();
        }

        if ( empty( $options ) || empty( $options['enabled'] ) ) {
            return '';
        }

        $banners = ! empty( $options['banners'] ) && is_array( $options['banners'] ) ? $options['banners'] : array();
        if ( count( $banners ) > 1 ) {
            $rotation_interval = isset( $options['rotation_interval'] ) ? max( 2, min( 60, (int) $options['rotation_interval'] ) ) : 5;
            return $this->build_banner_rotator_html( $banners, $rotation_interval );
        }

        $image_url = isset( $options['image_url'] ) ? esc_url( $options['image_url'] ) : '';
        if ( '' === $image_url ) {
            return '';
        }

        $link_url = isset( $options['link_url'] ) ? esc_url( $options['link_url'] ) : '';
        $alt_text = isset( $options['alt_text'] ) ? esc_attr( $options['alt_text'] ) : '';
        $target   = ! empty( $options['open_new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';

        $image_tag = '<img src="' . $image_url . '" alt="' . $alt_text . '" style="width:100%;max-width:100%;height:auto;display:block;vertical-align:top;" />';
        if ( '' !== $link_url ) {
            return '<div class="ktp-banner ktp-banner-fallback" style="width:100%;max-width:100%;box-sizing:border-box;"><a href="' . $link_url . '"' . $target . ' style="display:block;width:100%;line-height:0;">' . $image_tag . '</a></div>';
        }

        return '<div class="ktp-banner ktp-banner-fallback" style="width:100%;max-width:100%;box-sizing:border-box;">' . $image_tag . '</div>';
    }

    /**
     * 複数バナーのローテーション表示HTMLを生成する。
     * CSS/JSは maybe_enqueue_banner_rotator_assets() で別途enqueueする
     * （本文中に直接 <style>/<script> を出力すると、一部のキャッシュ/軽量化
     * プラグインにタグごと除去されることがあるため）。
     *
     * @param array $banners           バナー配列（image_url/link_url/alt_text）
     * @param int   $rotation_interval ローテーション間隔（秒）
     * @return string
     */
    private function build_banner_rotator_html( $banners, $rotation_interval ) {
        $items = '';
        foreach ( $banners as $index => $banner ) {
            $image_url = isset( $banner['image_url'] ) ? esc_url( $banner['image_url'] ) : '';
            if ( '' === $image_url ) {
                continue;
            }
            $link_url  = isset( $banner['link_url'] ) ? esc_url( $banner['link_url'] ) : '';
            $alt_text  = isset( $banner['alt_text'] ) ? esc_attr( $banner['alt_text'] ) : '';
            $item_class = 'ktp-banner-rotator-item' . ( 0 === $index ? ' is-active' : '' );

            $image_tag = '<img src="' . $image_url . '" alt="' . $alt_text . '" style="width:100%;max-width:100%;height:auto;display:block;vertical-align:top;" />';
            if ( '' !== $link_url ) {
                $image_tag = '<a href="' . $link_url . '" target="_blank" rel="noopener noreferrer" style="display:block;width:100%;line-height:0;">' . $image_tag . '</a>';
            }
            $items .= '<div class="' . $item_class . '">' . $image_tag . '</div>';
        }

        if ( '' === $items ) {
            return '';
        }

        return '<div class="ktp-banner-rotator" data-interval="' . (int) $rotation_interval . '" style="position:relative;width:100%;max-width:100%;box-sizing:border-box;overflow:hidden;">' . $items . '</div>';
    }

    /**
     * KantanPro本体の中央バナー設定を取得（リモート・公式フォールバック含む）
     *
     * 配布先で source_url を空のまま配布する場合、公式サイトの既定 JSON を自動取得する。
     * 無効化: add_filter( 'kantanpro_auto_fetch_official_central_banner', '__return_false' );
     * URL 差し替え: add_filter( 'kantanpro_official_central_banner_json_url', function () { return 'https://...'; } );
     *
     * @return array
     */
    private function get_central_banner_options() {
        $options = get_option( 'ktp_central_banner_settings', array() );
        if ( ! is_array( $options ) ) {
            $options = array();
        }

        // 1) 明示の外部参照 URL（配布元の REST 等）
        if ( ! empty( $options['source_url'] ) ) {
            $remote_options = $this->fetch_remote_central_banner_options( $options['source_url'] );
            if ( ! empty( $remote_options['image_url'] ) ) {
                return $remote_options;
            }
        }

        // 2) ローカルに画像があれば（配布先個別・配布元の直接保存）
        // 画像が設定済みなら、無料版の広告仕様として enabled チェックの状態に関わらず表示する。
        if ( ! empty( $options['image_url'] ) ) {
            return array(
                'enabled'      => 1,
                'image_url'    => $options['image_url'],
                'link_url'     => isset( $options['link_url'] ) ? $options['link_url'] : '',
                'alt_text'     => isset( $options['alt_text'] ) ? $options['alt_text'] : '',
                'open_new_tab' => 1,
            );
        }

        // 3) 公式既定 JSON（不特定多数配布で各サイトに source_url を設定できない場合）
        if ( $this->should_fetch_official_central_banner_feed( $options ) ) {
            $official_url = apply_filters(
                'kantanpro_official_central_banner_json_url',
                'https://www.kantanpro.com/wp-json/kantanpro/v1/central-banner'
            );
            if ( is_string( $official_url ) && '' !== $official_url ) {
                $remote_options = $this->fetch_remote_central_banner_options( $official_url );
                if ( ! empty( $remote_options['image_url'] ) ) {
                    return $remote_options;
                }
            }
        }

        return array(
            'enabled'      => ! empty( $options['enabled'] ) ? 1 : 0,
            'image_url'    => isset( $options['image_url'] ) ? $options['image_url'] : '',
            'link_url'     => isset( $options['link_url'] ) ? $options['link_url'] : '',
            'alt_text'     => isset( $options['alt_text'] ) ? $options['alt_text'] : '',
            'open_new_tab' => 1,
        );
    }

    /**
     * 公式サイトの既定バナー JSON を取りに行くか
     *
     * @param array $options ktp_central_banner_settings
     * @return bool
     */
    private function should_fetch_official_central_banner_feed( $options ) {
        if ( function_exists( 'ktpwp_should_hide_ktp_banner' ) && ktpwp_should_hide_ktp_banner() ) {
            return false;
        }
        if ( ! apply_filters( 'kantanpro_auto_fetch_official_central_banner', true ) ) {
            return false;
        }
        // WordPress.org 配布版では、開発元が配信するバナーを一切取得・表示しない
        // （WordPress.org プラグインガイドライン第 11 項: サイトへの広告の注入）。
        // 有料版への導線は、機能ロック時の文脈内の案内に一本化する。
        if ( function_exists( 'ktpwp_uses_self_hosted_updates' ) && ! ktpwp_uses_self_hosted_updates() ) {
            return false;
        }

        // 自社配布版の無料版広告バナーは、配布先の管理画面設定に関わらず表示する。
        return true;
    }

    /**
     * 外部URLから中央バナー設定を取得
     *
     * @param string $source_url JSON取得先URL
     * @return array
     */
    private function fetch_remote_central_banner_options( $source_url ) {
        $source_url = esc_url_raw( $source_url );
        if ( '' === $source_url ) {
            return array();
        }

        // v2: enabled と image_url の不整合でキャッシュされた古い値を使わない
        $cache_key = 'ktp_central_banner_remote_v2_' . md5( $source_url );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        // 他プラグインが session_start() したまま外部HTTPリクエストを行うと、
        // セッションファイルのロックにより wp_remote_get() がブロック・タイムアウトすることがあるため、
        // リクエスト直前でセッションを解放する（$_SESSION の内容自体は保持される）。
        if ( function_exists( 'session_status' ) && session_status() === PHP_SESSION_ACTIVE ) {
            session_write_close();
        }

        $response = wp_remote_get(
            $source_url,
            array(
                'timeout' => 5,
            )
        );
        if ( is_wp_error( $response ) ) {
            return array();
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        if ( 200 !== (int) $status_code || '' === $body ) {
            return array();
        }

        $json = json_decode( $body, true );
        if ( ! is_array( $json ) ) {
            return array();
        }

        $image_raw    = isset( $json['image_url'] ) ? esc_url_raw( $json['image_url'] ) : '';
        $has_image    = '' !== $image_raw;
        // 配信JSONで enabled が false でも image_url があれば表示する（REST・キャッシュの不整合対策）
        $enabled_flag = ( ! empty( $json['enabled'] ) || $has_image ) ? 1 : 0;

        // 複数バナー（ローテーション表示用）
        $banners = array();
        if ( ! empty( $json['banners'] ) && is_array( $json['banners'] ) ) {
            foreach ( $json['banners'] as $banner_item ) {
                if ( ! is_array( $banner_item ) || empty( $banner_item['image_url'] ) ) {
                    continue;
                }
                $banners[] = array(
                    'image_url' => esc_url_raw( $banner_item['image_url'] ),
                    'link_url'  => isset( $banner_item['link_url'] ) ? esc_url_raw( $banner_item['link_url'] ) : '',
                    'alt_text'  => isset( $banner_item['alt_text'] ) ? sanitize_text_field( $banner_item['alt_text'] ) : '',
                );
            }
        }
        $rotation_interval = isset( $json['rotation_interval'] ) ? absint( $json['rotation_interval'] ) : 5;
        $rotation_interval = max( 2, min( 60, $rotation_interval ) );

        $normalized = array(
            'enabled'           => $enabled_flag,
            'image_url'         => $image_raw,
            'link_url'          => isset( $json['link_url'] ) ? esc_url_raw( $json['link_url'] ) : '',
            'alt_text'          => isset( $json['alt_text'] ) ? sanitize_text_field( $json['alt_text'] ) : '',
            'open_new_tab'      => 1,
            'banners'           => $banners,
            'rotation_interval' => $rotation_interval,
        );

        // 短時間キャッシュして配布先へのHTTP負荷を抑える
        set_transient( $cache_key, $normalized, 5 * MINUTE_IN_SECONDS );

        return $normalized;
    }

    /**
     * 互換目的で ktp-banner プラグイン設定を取得
     *
     * @return array
     */
    private function get_legacy_banner_options() {
        $options = get_option( 'ktp_banner_options', array() );
        if ( ! is_array( $options ) ) {
            return array();
        }
        return $options;
    }

    /**
     * ヘッダーコンテンツ取得
     *
     * @return string ヘッダーHTML
     */
    private function get_header_content() {
        global $current_user;

        // 基本情報の取得
        $plugin_name = esc_html(KANTANPRO_PLUGIN_NAME);
        $plugin_version = esc_html(KANTANPRO_PLUGIN_VERSION);
        $icon_img = $this->get_plugin_icon();

        // ナビゲーション要素の生成
        $logged_in_raw = $this->get_logged_in_users_display();

        // 閲覧専用モーダルは user-avatars 内（flex 子）に置かないよう、ヘッダー直下へ分離
        $logged_in_users_html     = $logged_in_raw;
        $readonly_profile_suffix = '';
        $modal_pos                = strpos( $logged_in_raw, '<div id="ktp-readonly-profile-modal"' );
        if ( false !== $modal_pos ) {
            $logged_in_users_html     = substr( $logged_in_raw, 0, $modal_pos );
            $readonly_profile_suffix = substr( $logged_in_raw, $modal_pos );
        }

        $navigation_links = $this->get_navigation_links();

        // ヘッダーHTML構築（PC・タブレット表示用）
        $header_html = '<div class="ktp_header">';
        $header_html .= '<div class="parent">';
        $header_html .= '<div class="title ktp-header-title">' . $icon_img . $plugin_name . '</div>';
        $header_html .= '<div class="version">v' . $plugin_version . '</div>';
        $header_html .= '</div>';
        $header_html .= '<div class="header-right-section">';
        $header_html .= '<div class="navigation-links">' . $navigation_links . '</div>';
        $header_html .= '<div class="user-avatars-section">' . $logged_in_users_html . '</div>';
        $header_html .= '</div>';
        $header_html .= '</div>';

        return $header_html . $readonly_profile_suffix;
    }

    /**
     * プラグインアイコン取得
     *
     * @return string アイコンIMGタグ
     */
    private function get_plugin_icon() {
        $icon_url = function_exists( 'ktpwp_plugin_asset_url' )
            ? ktpwp_plugin_asset_url( 'images/default/icon.png' )
            : plugins_url( 'images/default/icon.png', KANTANPRO_PLUGIN_FILE );
        $alt      = esc_attr(KANTANPRO_PLUGIN_NAME);

        return '<button type="button" class="ktp-header-plugin-reload" title="' . esc_attr__( 'リロード', 'kantanpro' ) . '" aria-label="' . esc_attr__( 'リロード', 'kantanpro' ) . '" onclick="window.location.reload();">'
            . '<img src="' . esc_url($icon_url) . '" alt="' . $alt . '" class="ktp-header-plugin-icon" width="40" height="40" decoding="async" loading="eager">'
            . '</button>';
    }

    /**
     * ログイン中ユーザー表示の取得
     *
     * @return string ユーザー表示HTML
     */
    private function get_logged_in_users_display() {
        global $current_user;

        // 厳密なログイン状態確認
        if (!is_user_logged_in() || !$current_user || $current_user->ID <= 0) {
            return '';
        }

        // 全てのログイン中のスタッフを取得
        $logged_in_staff = $this->get_logged_in_staff_users();
        
        if (empty($logged_in_staff)) {
            return '';
        }

        $logged_in_users_html = '<div class="logged-in-staff-avatars">';
        $current_user_id = get_current_user_id();
        $can_edit_users = current_user_can( 'edit_users' );
        
        foreach ($logged_in_staff as $user) {
            $nickname = get_user_meta($user->ID, 'nickname', true);
            if (empty($nickname)) {
                $nickname = $user->display_name ? $user->display_name : $user->user_login;
            }
            $nickname_esc = esc_attr($nickname);
            $display_name_esc = esc_attr($user->display_name ? $user->display_name : $user->user_login);
            $email_esc = esc_attr($user->user_email);
            
            // 現在のユーザーかどうかで表示を変更
            $is_current = ( $current_user_id === (int) $user->ID );
            $class = $is_current ? 'user_icon user_icon--current' : 'user_icon user_icon--staff';

            // 自分は常に profile.php、他人は edit_users 権限がある場合のみ編集画面へ
            if ( $is_current ) {
                $profile_url = esc_url( admin_url( 'profile.php' ) );
                $logged_in_users_html .= '<a class="ktp-avatar-trigger" href="' . $profile_url . '" title="' . $nickname_esc . '">'
                    . get_avatar($user->ID, 32, '', '', array('class' => $class))
                    . '</a>';
            } elseif ( $can_edit_users ) {
                $profile_link = get_edit_user_link( $user->ID );
                if ( empty( $profile_link ) ) {
                    $profile_link = admin_url( 'user-edit.php?user_id=' . (int) $user->ID );
                }
                $profile_url = esc_url( $profile_link );
                $logged_in_users_html .= '<a class="ktp-avatar-trigger" href="' . $profile_url . '" title="' . $nickname_esc . '">'
                    . get_avatar($user->ID, 32, '', '', array('class' => $class))
                    . '</a>';
            } else {
                $role_label = in_array('administrator', (array) $user->roles, true) ? '管理者' : 'スタッフ';
                $role_label_esc = esc_attr($role_label);
                $logged_in_users_html .= '<button type="button" class="ktp-avatar-trigger ktp-avatar-trigger--other"'
                    . ' title="' . $nickname_esc . '"'
                    . ' data-name="' . $display_name_esc . '"'
                    . ' data-email="' . $email_esc . '"'
                    . ' data-role="' . $role_label_esc . '"'
                    . ' onclick="return window.ktpwpOpenReadonlyProfile ? window.ktpwpOpenReadonlyProfile(this, event) : false;">'
                    . get_avatar($user->ID, 32, '', '', array('class' => $class))
                    . '</button>';
            }
        }

        $logged_in_users_html .= '</div>';

        // 編集権限がないユーザー向けに、他人アバターは閲覧専用プロフィールを表示
        if ( ! $can_edit_users ) {
            $logged_in_users_html .= '<div id="ktp-readonly-profile-modal" class="ktp-readonly-profile-modal" hidden>'
                . '<div class="ktp-readonly-profile-backdrop" data-close="1"></div>'
                . '<div class="ktp-readonly-profile-panel" role="dialog" aria-modal="true" aria-label="スタッフプロフィール">'
                . '<button type="button" class="ktp-readonly-profile-close" data-close="1" aria-label="閉じる">×</button>'
                . '<div class="ktp-readonly-profile-title">スタッフプロフィール</div>'
                . '<div class="ktp-readonly-profile-name"></div>'
                . '<div class="ktp-readonly-profile-email"></div>'
                . '<div class="ktp-readonly-profile-role"></div>'
                . '<div class="ktp-readonly-profile-note">このプロフィールは表示専用です（編集不可）。</div>'
                . '</div>'
                . '</div>';

            // 生の <script> を出さずフッターのスクリプトに載せる（wp.org ガイドライン）
            ktpwp_add_inline_script( '(function(){'
                . 'if(window.__ktpReadonlyProfileInit){return;} window.__ktpReadonlyProfileInit=true;'
                . 'function ktpwpRelocateReadonlyModal(){'
                . 'var m=document.getElementById("ktp-readonly-profile-modal");'
                . 'if(m&&m.parentNode!==document.body){document.body.appendChild(m);}'
                . '}'
                . 'window.ktpwpRelocateReadonlyModal=ktpwpRelocateReadonlyModal;'
                . 'if(document.readyState==="loading"){'
                . 'document.addEventListener("DOMContentLoaded",ktpwpRelocateReadonlyModal);'
                . '}else{ktpwpRelocateReadonlyModal();}'
                . 'window.ktpwpCloseReadonlyProfile=function(){'
                . 'var modal=document.getElementById("ktp-readonly-profile-modal");'
                . 'if(modal){modal.hidden=true;}'
                . '};'
                . 'window.ktpwpOpenReadonlyProfile=function(trigger,e){'
                . 'if(e){e.preventDefault();e.stopPropagation();}'
                . 'ktpwpRelocateReadonlyModal();'
                . 'var modal=document.getElementById("ktp-readonly-profile-modal");'
                . 'if(!modal){return false;}'
                . 'var name=trigger.getAttribute("data-name")||"";'
                . 'var email=trigger.getAttribute("data-email")||"";'
                . 'var role=trigger.getAttribute("data-role")||"";'
                . 'var nameEl=modal.querySelector(".ktp-readonly-profile-name");'
                . 'var emailEl=modal.querySelector(".ktp-readonly-profile-email");'
                . 'var roleEl=modal.querySelector(".ktp-readonly-profile-role");'
                . 'if(nameEl){nameEl.textContent=name;}'
                . 'if(emailEl){emailEl.textContent=email;}'
                . 'if(roleEl){roleEl.textContent=role;}'
                . 'modal.hidden=false;'
                . 'return false;'
                . '};'
                . 'document.addEventListener("click",function(e){'
                . 'var closeTarget=e.target.closest("[data-close=\\"1\\"]");'
                . 'if(closeTarget){window.ktpwpCloseReadonlyProfile();}'
                . '});'
                . 'document.addEventListener("keydown",function(e){if(e.key==="Escape"){window.ktpwpCloseReadonlyProfile();}});'
                . '})();' );
        }

        return $logged_in_users_html;
    }

    /**
     * ログイン中のスタッフユーザーを取得
     *
     * @return array ログイン中のスタッフユーザー配列
     */
    private function get_logged_in_staff_users() {
        // アクティブなセッションを持つユーザーを取得
        $users_with_sessions = get_users(array(
            'meta_key' => 'session_tokens',
            'meta_compare' => 'EXISTS',
            'fields' => 'all'
        ));
        
        $logged_in_staff = array();
        
        foreach ($users_with_sessions as $user) {
            // セッションが有効かチェック
            $sessions = get_user_meta($user->ID, 'session_tokens', true);
            if (empty($sessions)) {
                continue;
            }
            
            $has_valid_session = false;
            foreach ($sessions as $session) {
                if (isset($session['expiration']) && $session['expiration'] > time()) {
                    $has_valid_session = true;
                    break;
                }
            }
            
            if (!$has_valid_session) {
                continue;
            }
            
            // スタッフ権限をチェック（ktpwp_access または管理者権限）
            if ($this->is_staff_user($user)) {
                $logged_in_staff[] = $user;
            }
        }

        // 現在のユーザーはセッションメタやスタッフ権限検出に依存せず、ヘッダーアバターとして必ず含める
        $current_user_id = get_current_user_id();
        if ($current_user_id > 0) {
            $exists_current = false;
            foreach ($logged_in_staff as $staff_user) {
                if ((int) $staff_user->ID === (int) $current_user_id) {
                    $exists_current = true;
                    break;
                }
            }
            if (!$exists_current) {
                $current_user = get_userdata($current_user_id);
                if ($current_user) {
                    $logged_in_staff[] = $current_user;
                }
            }
        }
        
        // 現在のユーザーを末尾（右端）に並べ替え
        usort($logged_in_staff, function($a, $b) {
            $current_user_id = get_current_user_id();
            if ($a->ID === $current_user_id) return 1;
            if ($b->ID === $current_user_id) return -1;
            return strcmp($a->display_name, $b->display_name);
        });
        
        return $logged_in_staff;
    }

    /**
     * スタッフユーザーかどうかを判定
     *
     * @param WP_User $user ユーザーオブジェクト
     * @return bool スタッフかどうか
     */
    private function is_staff_user($user) {
        // 管理者は常にスタッフ扱い
        if (in_array('administrator', $user->roles)) {
            return true;
        }
        
        // ktpwp_access権限を持つユーザーをスタッフとして判定
        return user_can($user->ID, 'ktpwp_access');
    }

    /**
     * ナビゲーションリンク取得
     *
     * @return string ナビゲーションHTML
     */
    private function get_navigation_links() {
        global $current_user;

        // ログイン状態とセッション確認 (権限チェック部分を削除)
        if (!is_user_logged_in() || !$current_user || $current_user->ID <= 0) {
            return '';
        }

        // セッション有効性確認の改善
        $user_sessions = WP_Session_Tokens::get_instance($current_user->ID);
        if (!$user_sessions) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP Debug: get_navigation_links - WP_Session_Tokens::get_instance returned null for user ID: ' . $current_user->ID);
            }
            return '';
        }

        $all_sessions = $user_sessions->get_all();
        if (empty($all_sessions)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP Debug: get_navigation_links - No active sessions found for user ID: ' . $current_user->ID);
            }
            return '';
        }

        // 各種リンクの生成
        $logout_url = esc_url(wp_logout_url());
        $current_page_id = get_queried_object_id();
        $update_url = esc_url(get_permalink($current_page_id));
        $activation_key = esc_html($this->check_activation_key());

        $links = array();
        
        // 外部リンクの定数化とセキュリティ強化
        $external_links = array(
            'official_site' => 'https://www.kantanpro.com/',
            'features' => 'https://www.kantanpro.com/features/',
            'community' => 'https://www.kantanpro.com/community/'
        );

        // 公式サイト（KantanPro）
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($external_links['official_site']),
            esc_html( KANTANPRO_PLUGIN_NAME )
        );
        
        // 詳細を表示（公式サイトの機能紹介ページ等。なければトップページ）
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($external_links['features']),
            esc_html__('詳細を表示', 'kantanpro')
        );
        
        // コミュニティ
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($external_links['community']),
            esc_html__('コミュニティ', 'kantanpro')
        );
        
        // ログアウト
        $links[] = sprintf(
            '<a href="%s" title="%s" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;">%s</a>',
            $logout_url,
            esc_attr__('ログアウト', 'kantanpro'),
            '<span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">logout</span>'
        );
        
        // 更新
        $links[] = sprintf(
            '<a href="%s" title="%s" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;">%s</a>',
            $update_url,
            esc_attr__('更新', 'kantanpro'),
            '<span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">refresh</span>'
        );
        // アクティベーションキー（空文字列）
        if (!empty($activation_key)) {
            $links[] = $activation_key;
        }
        // ヘルプ（外部リンク）
        $links[] = '<a href="https://www.kantanpro.com/docs" target="_blank" title="' . esc_attr__( 'ヘルプ', 'kantanpro' ) . '" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;">' . KTPWP_SVG_Icons::get_icon('help', array('style' => 'font-size: 20px; vertical-align: middle;')) . '<span>' . esc_html__( 'ヘルプ', 'kantanpro' ) . '</span></a>';
        // 設定（右端）
        $links[] = sprintf(
            '<a href="%s" title="%s" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;">%s<span>%s</span></a>',
            esc_url( admin_url( 'admin.php?page=ktp-settings' ) ),
            esc_attr__( '設定', 'kantanpro' ),
            KTPWP_SVG_Icons::get_icon('settings', array('style' => 'font-size: 20px; vertical-align: middle;')),
            esc_html__( '設定', 'kantanpro' )
        );

        return ' ' . implode(' ', $links);
    }

    /**
     * アクティベーションキー確認
     *
     * @return string アクティベーションキー状態
     */
    private function check_activation_key() {
        $activation_key = get_site_option('ktp_activation_key');
        return empty($activation_key) ? '' : '';
    }

    /**
     * タブコンテンツ取得
     *
     * @return string タブHTML
     */
    private function get_tab_content() {
        $tab_name = $this->get_current_tab();

        // 各タブコンテンツの初期化
        $tab_contents = array(
            'list' => '',
            'order' => '',
            'client' => '',
            'service' => '',
            'supplier' => '',
            'info' => ''
        );

        // 現在のタブに応じてコンテンツを生成
        switch ($tab_name) {
            case 'list':
                $tab_contents['list'] = $this->get_list_content($tab_name);
                break;

            case 'order':
                $tab_contents['order'] = $this->get_order_content($tab_name);
                break;

            case 'client':
                $tab_contents['client'] = $this->get_client_content($tab_name);
                break;

            case 'service':
                $tab_contents['service'] = $this->get_service_content($tab_name);
                break;

            case 'supplier':
                $tab_contents['supplier'] = $this->get_supplier_content($tab_name);
                break;

            case 'info':
                $tab_contents['info'] = $this->get_info_content();
                break;

            default:
                // デフォルトでリストタブを表示
                $tab_name = 'list';
                $tab_contents['list'] = $this->get_list_content($tab_name);
                break;
        }

        // タブビューの生成
        return $this->render_tabs_view(
            $tab_contents['list'],
            $tab_contents['order'],
            $tab_contents['client'],
            $tab_contents['service'],
            $tab_contents['supplier'],
            $tab_contents['info']
        );
    }

    /**
     * 現在のタブ名取得
     *
     * @return string タブ名
     */
    private function get_current_tab() {
        // TabsView / kantanAllTab と同様に POST の tab_name を優先（フォーム送信後も正しいタブのコンテンツを生成する）
        $tab_name = 'list';
        if (isset($_POST['tab_name']) && is_string($_POST['tab_name'])) {
            $tab_name = sanitize_text_field(wp_unslash($_POST['tab_name']));
        } elseif (isset($_GET['tab_name'])) {
            $tab_name = sanitize_text_field(wp_unslash($_GET['tab_name']));
        }

        // 許可されたタブ名のホワイトリスト
        $allowed_tabs = array('list', 'order', 'client', 'service', 'supplier', 'report');

        if (!in_array($tab_name, $allowed_tabs, true)) {
            $tab_name = 'list';
        }

        return $tab_name;
    }

    /**
     * リストコンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_list_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('KTPWP_List_Class')) {
            $this->load_required_class('class-ktpwp-tab-list.php');
        }

        if (class_exists('KTPWP_List_Class')) {
            $list = new KTPWP_List_Class();
            return $list->List_Tab_View($tab_name);
        }

        return $this->get_error_content('KTPWP_List_Class');
    }

    /**
     * 受注コンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_order_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('KTPWP_Order_Class')) {
            $this->load_required_class('class-ktpwp-order-main.php');
        }

        if (class_exists('KTPWP_Order_Class')) {
            $order = new KTPWP_Order_Class();
            $content = $order->Order_Tab_View($tab_name);
            return $content ?? '';
        }

        return $this->get_error_content('KTPWP_Order_Class');
    }

    /**
     * 顧客コンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_client_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('KTPWP_Client_Class')) {
            $this->load_required_class('class-ktpwp-client.php');
        }

        if (class_exists('KTPWP_Client_Class')) {
            $client = new KTPWP_Client_Class();

            // 管理者権限がある場合のみテーブル操作 -> 編集者権限に変更
            if (current_user_can('edit_posts') || current_user_can('ktpwp_access')) {
                $client->Create_Table($tab_name);
                $client->Update_Table($tab_name);
            }

            return $client->View_Table($tab_name);
        }

        return $this->get_error_content('KTPWP_Client_Class');
    }

    /**
     * サービスコンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_service_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('KTPWP_Service_Class')) {
            $this->load_required_class('class-ktpwp-service-main.php');
        }

        if (class_exists('KTPWP_Service_Class')) {
            $service = new KTPWP_Service_Class();

            // 管理者権限がある場合のみテーブル操作
            if (current_user_can('manage_options')) {
                $service->Create_Table($tab_name);
                $service->Update_Table($tab_name);
            }

            return $service->View_Table($tab_name);
        }

        return $this->get_error_content('KTPWP_Service_Class');
    }

    /**
     * 仕入先コンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_supplier_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('KTPWP_Supplier_Class')) {
            $this->load_required_class('class-ktpwp-tab-supplier.php');
        }

        if (class_exists('KTPWP_Supplier_Class')) {
            $supplier = new KTPWP_Supplier_Class();

            // 編集者権限がある場合のみテーブル操作
            if (current_user_can('edit_posts') || current_user_can('ktpwp_access')) {
                $supplier->Create_Table($tab_name);
                
                if (!empty($_POST)) {
                    $supplier->Update_Table($tab_name);
                }
            }

            return $supplier->View_Table($tab_name);
        }

        return $this->get_error_content('KTPWP_Supplier_Class');
    }

    /**
     * 情報タブのコンテンツ取得
     *
     * @return string コンテンツHTML
     */
    private function get_info_content() {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('KTPWP_Tab_Info')) {
            $this->load_required_class('class-ktpwp-tab-info.php');
        }
        if (class_exists('KTPWP_Tab_Info')) {
            $info = new KTPWP_Tab_Info();
            return $info->render();
        }
        return '';
    }

    /**
     * 設定コンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_setting_content($tab_name) {
        // 設定タブは廃止されたため、廃止メッセージを返す
        return '<div class="ktpwp-notice" style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0; color: #6c757d;">設定タブについて</h3>
            <p style="margin: 0; color: #6c757d;">設定タブは廃止されました。ヘッダー画像などの設定は管理画面の「KantanPro設定」から行ってください。</p>
        </div>';
    }

    /**
     * 必要なクラスファイルを読み込み
     *
     * @param string $filename ファイル名
     */
    private function load_required_class($filename) {
        $file_path = KANTANPRO_PLUGIN_DIR . 'includes/' . $filename;

        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP Error: Required class file not found - ' . $filename);
            }
        }
    }

    /**
     * エラーコンテンツ取得
     *
     * @param string $class_name クラス名
     * @return string エラーHTML
     */
    private function get_error_content($class_name) {
        $message = sprintf(
            /* translators: %s: クラス名 */
            esc_html__('クラス %s が見つかりません。', 'kantanpro'),
            esc_html($class_name)
        );

        return '<div class="ktpwp-error">' . $message . '</div>';
    }

    /**
     * タブビューレンダリング
     *
     * @param string $list_content リストコンテンツ
     * @param string $order_content 受注コンテンツ
     * @param string $client_content 顧客コンテンツ
     * @param string $service_content サービスコンテンツ
     * @param string $supplier_content 仕入先コンテンツ
     * @param string $info_content 情報タブのコンテンツ
     * @return string タブビューHTML
     */
    private function render_tabs_view($list_content, $order_content, $client_content, $service_content, $supplier_content, $info_content) {
        if (!class_exists('KTPWP_View_Tabs_Class')) {
            $this->load_required_class('class-ktpwp-view-tab.php');
        }

        if (class_exists('KTPWP_View_Tabs_Class')) {
            $view = new KTPWP_View_Tabs_Class();
            return $view->TabsView($list_content, $order_content, $client_content, $service_content, $supplier_content, $report_content);
        }

        return $this->get_error_content('KTPWP_View_Tabs_Class');
    }

    /**
     * ログインエラー表示
     *
     * @return string ログインエラーHTML
     */
    private function render_login_error() {
        return '<div class="ktpwp-error">' . esc_html__('このコンテンツを表示するにはログインが必要です。', 'kantanpro') . '</div>';
    }

    /**
     * 権限エラーメッセージ描画
     *
     * @return string エラーHTML
     */
    private function render_permission_error() {
        return '<div class="ktpwp-error">' . esc_html__('このコンテンツを表示する権限がありません。', 'kantanpro') . '</div>';
    }

    /**
     * Ajax: ログイン中ユーザー取得
     */
    public function ajax_get_logged_in_users() {
        // Ajax以外からのアクセスは何も返さない
        if (
            !defined('DOING_AJAX') ||
            !DOING_AJAX ||
            (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest')
        ) {
            wp_die();
        }

        // キャッシュがある場合は使用
        if ($this->logged_in_users_cache !== null) {
            wp_send_json($this->logged_in_users_cache);
        }

        // ログイン中スタッフを取得
        $logged_in_staff = $this->get_logged_in_staff_users();

        $users_data = array();
        foreach ($logged_in_staff as $user) {
            $nickname = get_user_meta($user->ID, 'nickname', true);
            if (empty($nickname)) {
                $nickname = $user->display_name ? $user->display_name : $user->user_login;
            }
            
            $users_data[] = array(
                'id' => $user->ID,
                'name' => esc_html($nickname) . 'さん',
                'is_current' => (get_current_user_id() === $user->ID),
                'avatar_url' => get_avatar_url($user->ID, array('size' => 32))
            );
        }

        // キャッシュに保存（30秒）
        $this->logged_in_users_cache = $users_data;
        wp_cache_set('ktpwp_logged_in_staff', $users_data, '', 30);

        wp_send_json($users_data);
    }

    /**
     * 公開商品ショートコード用アセットを読み込む。
     *
     * @return void
     */
    public function enqueue_public_products_assets() {
        if ( is_admin() ) {
            return;
        }

        global $post;
        if ( ! $post instanceof WP_Post ) {
            return;
        }

        if ( ! has_shortcode( (string) $post->post_content, 'ktpwp_public_products' ) ) {
            return;
        }

        $plugin_root = dirname( __DIR__ );
        wp_enqueue_style(
            'ktpwp-public-products',
            plugin_dir_url( $plugin_root . '/ktpwp.php' ) . 'css/public-products.css',
            array(),
            defined( 'KTPWP_PLUGIN_VERSION' ) ? KTPWP_PLUGIN_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'ktpwp-public-products',
            plugin_dir_url( $plugin_root . '/ktpwp.php' ) . 'js/public-products.js',
            array(),
            defined( 'KTPWP_PLUGIN_VERSION' ) ? KTPWP_PLUGIN_VERSION : '1.0.0',
            true
        );

        $nonce_action = class_exists( 'KTPWP_Public_Product_Order' )
            ? KTPWP_Public_Product_Order::get_nonce_action()
            : 'ktpwp_public_product_order';

        wp_localize_script(
            'ktpwp-public-products',
            'ktpwpPublicProducts',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php', 'relative' ),
                'nonce'   => wp_create_nonce( $nonce_action ),
                'i18n'    => array(
                    'orderTitle'   => __( 'お問い合わせ', 'kantanpro' ),
                    'submit'       => __( '送信する', 'kantanpro' ),
                    'submitting'   => __( '送信中…', 'kantanpro' ),
                    'close'        => __( '閉じる', 'kantanpro' ),
                    'category'     => __( 'カテゴリ', 'kantanpro' ),
                    'price'        => __( '単価', 'kantanpro' ),
                    'unit'         => __( '単位', 'kantanpro' ),
                    'tax'          => __( '税率', 'kantanpro' ),
                    'memo'         => __( 'メモ', 'kantanpro' ),
                    'quantity'     => __( '数量', 'kantanpro' ),
                    'companyName'  => __( '会社名', 'kantanpro' ),
                    'contactName'  => __( 'お名前', 'kantanpro' ),
                    'email'        => __( 'メールアドレス', 'kantanpro' ),
                    'phone'        => __( '電話番号', 'kantanpro' ),
                    'message'      => __( 'ご要望・備考', 'kantanpro' ),
                    'requiredMark' => __( '必須', 'kantanpro' ),
                    'networkError' => __( '通信エラーが発生しました。時間をおいて再度お試しください。', 'kantanpro' ),
                    'sessionExpired' => __( 'セッションの有効期限が切れました。ページを再読み込みして再度お試しください。', 'kantanpro' ),
                    'filterLabel'  => __( 'カテゴリで絞り込み', 'kantanpro' ),
                    'filterPlaceholder' => __( 'カテゴリを入力または選択…', 'kantanpro' ),
                    'filterAll'    => __( 'すべて表示', 'kantanpro' ),
                    'filterEmpty'  => __( '該当する商品がありません。', 'kantanpro' ),
                    'enlargeImage' => __( '画像を拡大', 'kantanpro' ),
                    'initialFees'     => __( '初回費用', 'kantanpro' ),
                    'initialFeesNote' => __( '初回請求時のみ', 'kantanpro' ),
                    'recurringBadge'  => __( '定期', 'kantanpro' ),
                    'pendingBadge'    => __( '保留中', 'kantanpro' ),
                    'soldOutBadge'    => __( '完売御礼！', 'kantanpro' ),
                    'pendingNotice'   => __( '現在お問い合わせを受け付けておりません。', 'kantanpro' ),
                    'soldOutNotice'   => __( 'こちらの商品は完売しました。', 'kantanpro' ),
                    'inquire'         => __( '問い合わす', 'kantanpro' ),
                ),
            )
        );
    }

    /**
     * 一般公開用の商品一覧ショートコード。
     *
     * @param array $atts ショートコード属性。
     * @return string
     */
    public function render_public_products( $atts = array() ) {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return '';
        }
        if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'public_products' ) ) {
            if ( class_exists( 'KTPWP_Edition' ) ) {
                return KTPWP_Edition::get_upgrade_message_html( __( '公開商品機能', 'kantanpro' ) );
            }
            return '';
        }

        $atts = shortcode_atts(
            array(
                'layout'        => 'grid',
                'columns'       => '3',
                'category'      => '',
                'ids'           => '',
                'limit'         => '0',
                'order_by'      => 'id',
                'order'         => 'DESC',
                'show_image'    => 'yes',
                'show_price'    => 'yes',
                'show_unit'     => 'yes',
                'show_category' => 'yes',
                'show_tax'      => 'no',
                'show_memo'         => 'yes',
                'show_initial_fees' => 'yes',
                'show_filter'       => 'yes',
            ),
            $atts,
            'ktpwp_public_products'
        );

        $layout = sanitize_key( $atts['layout'] );
        if ( ! in_array( $layout, array( 'grid', 'table', 'cards' ), true ) ) {
            $layout = 'grid';
        }

        $columns = max( 1, min( 4, (int) $atts['columns'] ) );
        $categories = $this->parse_public_products_categories( $atts['category'] );
        $ids = $this->parse_public_products_ids( $atts['ids'] );
        $limit = max( 0, (int) $atts['limit'] );
        $order_by = sanitize_key( $atts['order_by'] );
        $order = strtoupper( sanitize_key( $atts['order'] ) );
        if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
            $order = 'DESC';
        }

        $display = array(
            'image'    => $this->is_shortcode_flag_enabled( $atts['show_image'] ),
            'price'    => $this->is_shortcode_flag_enabled( $atts['show_price'] ),
            'unit'     => $this->is_shortcode_flag_enabled( $atts['show_unit'] ),
            'category' => $this->is_shortcode_flag_enabled( $atts['show_category'] ),
            'tax'      => $this->is_shortcode_flag_enabled( $atts['show_tax'] ),
            'memo'         => $this->is_shortcode_flag_enabled( $atts['show_memo'] ),
            'initial_fees' => $this->is_shortcode_flag_enabled( $atts['show_initial_fees'] ),
        );

        if ( ! class_exists( 'KTPWP_Service_DB' ) ) {
            $service_db_file = dirname( __FILE__ ) . '/class-ktpwp-service-db.php';
            if ( file_exists( $service_db_file ) ) {
                require_once $service_db_file;
            }
        }

        if ( ! class_exists( 'KTPWP_Service_DB' ) ) {
            return '<p class="ktpwp-public-products ktpwp-public-products--empty">' . esc_html__( '商品データを読み込めませんでした。', 'kantanpro' ) . '</p>';
        }

        $query_args = array(
            'is_public' => true,
            'limit'     => $limit,
            'order_by'  => $order_by,
            'order'     => $order,
        );

        if ( ! empty( $categories ) ) {
            $query_args['category'] = count( $categories ) === 1 ? $categories[0] : $categories;
        }

        if ( ! empty( $ids ) ) {
            $query_args['ids'] = $ids;
        }

        $services = KTPWP_Service_DB::get_instance()->get_services( 'service', $query_args );

        if ( empty( $services ) ) {
            return '<p class="ktpwp-public-products ktpwp-public-products--empty">' . esc_html__( '公開中の商品がありません。', 'kantanpro' ) . '</p>';
        }

        $inner = '';
        if ( $layout === 'table' ) {
            $inner = $this->render_public_products_table( $services, $display );
        } elseif ( $layout === 'cards' ) {
            $inner = $this->render_public_products_cards( $services, $display, $columns );
        } else {
            $inner = $this->render_public_products_grid( $services, $display, $columns );
        }

        $service_db     = KTPWP_Service_DB::get_instance();
        $all_categories = $service_db->get_public_service_categories();
        $show_filter    = $this->is_shortcode_flag_enabled( $atts['show_filter'] );
        $filter_categories = ! empty( $categories ) ? $categories : $all_categories;
        $filter_initial = count( $categories ) === 1 ? $categories[0] : '';
        $filter_html    = ( $show_filter && ! empty( $filter_categories ) )
            ? $this->render_public_category_filter( $filter_categories, $filter_initial )
            : '';

        return '<div class="ktpwp-public-products ktpwp-public-products--' . esc_attr( $layout ) . '">'
            . $filter_html
            . '<div class="ktpwp-public-products-list">'
            . $inner
            . '<p class="ktpwp-public-products-filter__empty" hidden>' . esc_html__( '該当する商品がありません。', 'kantanpro' ) . '</p>'
            . '</div>'
            . $this->render_public_product_detail_shell()
            . $this->render_public_product_image_lightbox_shell()
            . '</div>';
    }

    /**
     * yes/no 属性を真偽値に変換する。
     *
     * @param string $value 属性値。
     * @return bool
     */
    private function is_shortcode_flag_enabled( $value ) {
        $value = strtolower( trim( (string) $value ) );
        return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
    }

    /**
     * カンマ区切り ID 属性を配列に変換する。
     *
     * @param string $ids_attr ID 属性。
     * @return array<int, int>
     */
    private function parse_public_products_ids( $ids_attr ) {
        if ( $ids_attr === '' ) {
            return array();
        }

        $parts = preg_split( '/\s*,\s*/', (string) $ids_attr );
        if ( ! is_array( $parts ) ) {
            return array();
        }

        return array_values( array_filter( array_map( 'absint', $parts ) ) );
    }

    /**
     * category 属性（カンマ区切り可）を配列に変換する。
     *
     * @param string $category_attr カテゴリー属性。
     * @return array<int, string>
     */
    private function parse_public_products_categories( $category_attr ) {
        if ( $category_attr === '' ) {
            return array();
        }

        $parts = preg_split( '/\s*,\s*/', (string) $category_attr );
        if ( ! is_array( $parts ) ) {
            return array();
        }

        $categories = array();
        foreach ( $parts as $part ) {
            $category = sanitize_text_field( $part );
            if ( $category !== '' ) {
                $categories[] = $category;
            }
        }

        return array_values( array_unique( $categories ) );
    }

    /**
     * 商品画像 URL を解決する。
     *
     * @param object $service 商品レコード。
     * @return string
     */
    private function resolve_public_product_image_url( $service ) {
        if ( ! class_exists( 'KTPWP_Service_DB' ) ) {
            require_once dirname( __DIR__ ) . '/includes/class-ktpwp-service-db.php';
        }

        $service_db = KTPWP_Service_DB::get_instance();
        $service_id = isset( $service->id ) ? (int) $service->id : 0;
        $image_url  = isset( $service->image_url ) ? (string) $service->image_url : '';

        return $service_db->resolve_image_url( $service_id, $image_url );
    }

    /**
     * 公開商品の表示用データを整形する。
     *
     * @param object $service 商品レコード。
     * @return array<string, string>
     */
    private function format_public_product_row( $service ) {
        $name = isset( $service->service_name ) ? (string) $service->service_name : '';
        $price = isset( $service->price ) ? (float) $service->price : 0.0;
        $unit = isset( $service->unit ) ? (string) $service->unit : '';
        $category = isset( $service->category ) ? (string) $service->category : '';
        $tax_rate = isset( $service->tax_rate ) && $service->tax_rate !== null && $service->tax_rate !== ''
            ? ( class_exists( 'KTPWP_Settings' )
                ? KTPWP_Settings::format_decimal_trimmed( $service->tax_rate )
                : (string) $service->tax_rate )
            : '';

        $price_display = class_exists( 'KTPWP_Settings' )
            ? KTPWP_Settings::format_money_trimmed( $price )
            : number_format( $price );
        $memo = isset( $service->memo ) ? (string) $service->memo : '';
        $public_html = class_exists( 'KTPWP_Service_DB' )
            ? KTPWP_Service_DB::format_public_html_for_display( $service->public_html ?? '' )
            : '';

        $contract_billing_cycle = class_exists( 'KTPWP_Contract_Billing_Cycle' ) && isset( $service->contract_billing_cycle )
            ? KTPWP_Contract_Billing_Cycle::sanitize( $service->contract_billing_cycle )
            : 'none';
        $is_recurring_contract = class_exists( 'KTPWP_Contract_Billing_Cycle' )
            && KTPWP_Contract_Billing_Cycle::is_recurring( $contract_billing_cycle );
        $contract_billing_cycle_label = class_exists( 'KTPWP_Contract_Billing_Cycle' )
            ? KTPWP_Contract_Billing_Cycle::get_label( $contract_billing_cycle )
            : '';

        $recurring_items = array();
        if ( $is_recurring_contract && class_exists( 'KTPWP_Contract_Recurring_Items' ) ) {
            $service_id = isset( $service->id ) ? (int) $service->id : 0;
            $item_rows  = KTPWP_Contract_Recurring_Items::get_by_service_id( $service_id );

            foreach ( $item_rows as $item_row ) {
                $item_amount = isset( $item_row->amount ) ? (float) $item_row->amount : 0.0;
                $recurring_items[] = array(
                    'item_name'      => isset( $item_row->item_name ) ? (string) $item_row->item_name : '',
                    'amount'         => $item_amount,
                    'amount_display' => class_exists( 'KTPWP_Settings' )
                        ? KTPWP_Settings::format_money_trimmed( $item_amount )
                        : number_format( $item_amount ),
                    'tax_rate'       => isset( $item_row->tax_rate ) && $item_row->tax_rate !== null && $item_row->tax_rate !== ''
                        ? ( class_exists( 'KTPWP_Settings' )
                            ? KTPWP_Settings::format_decimal_trimmed( $item_row->tax_rate )
                            : (string) $item_row->tax_rate )
                        : '',
                );
            }
        }

        $initial_fee_rows = array();
        if ( $is_recurring_contract && class_exists( 'KTPWP_Service_Initial_Fees' ) ) {
            $service_id = isset( $service->id ) ? (int) $service->id : 0;
            $fee_rows   = KTPWP_Service_Initial_Fees::get_by_service_id( $service_id );

            foreach ( $fee_rows as $fee_row ) {
                $initial_fee_rows[] = array(
                    'fee_name' => isset( $fee_row->fee_name ) ? (string) $fee_row->fee_name : '',
                    'amount'   => isset( $fee_row->amount ) ? (float) $fee_row->amount : 0.0,
                    'tax_rate' => isset( $fee_row->tax_rate ) && $fee_row->tax_rate !== null && $fee_row->tax_rate !== ''
                        ? $fee_row->tax_rate
                        : '',
                );
            }
        }

        $initial_fees = $this->normalize_public_product_initial_fees( $initial_fee_rows );

        $service_id = isset( $service->id ) ? (int) $service->id : 0;
        $availability = array(
            'acceptance_open'    => true,
            'availability_state' => 'open',
            'status_label'       => '',
        );
        if ( class_exists( 'KTPWP_Contract_Service_Public_Availability' ) ) {
            $availability = KTPWP_Contract_Service_Public_Availability::get_public_availability(
                $service_id,
                $service,
                $is_recurring_contract
            );
        }
        $acceptance_open = (bool) $availability['acceptance_open'];
        $availability_state = (string) $availability['availability_state'];
        $status_label = (string) $availability['status_label'];

        return array(
            'id'                           => isset( $service->id ) ? (int) $service->id : 0,
            'name'                         => $name,
            'price'                        => $price,
            'price_display'                => $price_display,
            'unit'                         => $unit,
            'category'                     => $category,
            'tax_rate'                     => $tax_rate,
            'memo'                         => $memo,
            'public_html'                  => $public_html,
            'image'                        => $this->resolve_public_product_image_url( $service ),
            'contract_billing_cycle'       => $contract_billing_cycle,
            'contract_billing_cycle_label' => $contract_billing_cycle_label,
            'is_recurring_contract'        => $is_recurring_contract,
            'is_recurring'                 => $is_recurring_contract,
            'recurring_items'              => $recurring_items,
            'initial_fees'                 => $initial_fees,
            'initial_fees_summary'         => $this->format_public_initial_fees_summary( $initial_fees ),
            'recurring_items_summary'      => $this->format_public_recurring_items_summary( $recurring_items, $unit ),
            'acceptance_open'              => $acceptance_open,
            'availability_state'           => $availability_state,
            'is_sold_out'                  => $availability_state === 'sold_out',
            'is_pending'                   => $availability_state === 'pending',
            'status_label'                 => $status_label,
            'quantity_fixed'               => class_exists( 'KTPWP_Service_DB' )
                ? KTPWP_Service_DB::is_public_quantity_fixed( $service )
                : false,
        );
    }

    /**
     * 公開一覧用の初回費用行を正規化する。
     *
     * @param mixed $raw 初回費用行。
     * @return array<int, array{fee_name: string, amount: float, amount_display: string, tax_rate: string}>
     */
    private function normalize_public_product_initial_fees( $raw ) {
        if ( ! is_array( $raw ) ) {
            return array();
        }

        $fees = array();
        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $name = isset( $row['fee_name'] ) ? trim( (string) $row['fee_name'] ) : '';
            if ( $name === '' ) {
                continue;
            }

            $amount = isset( $row['amount'] ) ? (float) $row['amount'] : 0.0;
            $tax_rate = isset( $row['tax_rate'] ) && $row['tax_rate'] !== null && $row['tax_rate'] !== ''
                ? ( class_exists( 'KTPWP_Settings' )
                    ? KTPWP_Settings::format_decimal_trimmed( $row['tax_rate'] )
                    : (string) $row['tax_rate'] )
                : '';

            $fees[] = array(
                'fee_name'       => $name,
                'amount'         => $amount,
                'amount_display' => class_exists( 'KTPWP_Settings' )
                    ? KTPWP_Settings::format_money_trimmed( $amount )
                    : number_format( $amount ),
                'tax_rate'       => $tax_rate,
            );
        }

        return $fees;
    }

    /**
     * @param array<int, array{fee_name: string, amount_display: string}> $initial_fees 初回費用。
     * @return string
     */
    private function format_public_initial_fees_summary( array $initial_fees ) {
        if ( empty( $initial_fees ) ) {
            return '';
        }

        $parts = array();
        foreach ( $initial_fees as $fee ) {
            $parts[] = $fee['fee_name'] . ' ' . $fee['amount_display'];
        }

        return implode( '、', $parts );
    }

    /**
     * @param array<int, array{item_name: string, amount_display: string, tax_rate: string}> $recurring_items 定期請求項目。
     * @param string                                                                          $unit            単位。
     * @return string
     */
    private function format_public_recurring_items_summary( array $recurring_items, $unit ) {
        if ( empty( $recurring_items ) ) {
            return '';
        }

        $parts = array();
        foreach ( $recurring_items as $item ) {
            $parts[] = $this->format_public_recurring_item_line(
                (string) $item['item_name'],
                (string) $item['amount_display'],
                $unit
            );
        }

        return implode( '、', $parts );
    }

    /**
     * @param string $item_name      項目名。
     * @param string $amount_display 金額表示。
     * @param string $unit           単位。
     * @return string
     */
    private function format_public_recurring_item_line( $item_name, $amount_display, $unit ) {
        $suffix = $unit !== '' ? '/' . $unit : '';

        return $item_name . ' ' . $amount_display . $suffix;
    }

    /**
     * 公開一覧の価格ブロック HTML を返す。
     *
     * @param array<string, mixed> $row     商品行データ。
     * @param array<string, bool>  $display 表示フラグ。
     * @param string               $prefix  CSS クラス接頭辞。
     * @return string
     */
    private function render_public_product_list_price_block_html( array $row, array $display, $prefix ) {
        if ( ! $display['price'] && ! ( $display['unit'] && $row['unit'] !== '' ) && ! ( $display['tax'] && $row['tax_rate'] !== '' ) ) {
            return '';
        }

        $unit              = (string) ( $row['unit'] ?? '' );
        $service_tax_rate  = (string) ( $row['tax_rate'] ?? '' );
        $price_html        = '';
        $has_recurring_items = ! empty( $row['recurring_items'] ) && is_array( $row['recurring_items'] );

        if ( $has_recurring_items ) {
            $lines = '';
            foreach ( $row['recurring_items'] as $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }

                $item_name      = (string) ( $item['item_name'] ?? '' );
                $amount_display = (string) ( $item['amount_display'] ?? '' );
                if ( $item_name === '' || $amount_display === '' ) {
                    continue;
                }

                $line_text = $this->format_public_recurring_item_line(
                    $item_name,
                    $amount_display,
                    $display['unit'] && $unit !== '' ? $unit : ''
                );
                $lines    .= '<div class="' . esc_attr( $prefix ) . '__recurring-item">'
                    . '<span class="' . esc_attr( $prefix ) . '__recurring-item-line">' . esc_html( $line_text ) . '</span>'
                    . '</div>';
            }

            if ( $lines !== '' ) {
                $price_html = '<div class="' . esc_attr( $prefix ) . '__recurring-items">' . $lines . '</div>';
            }
        }

        if ( $price_html === '' && $display['price'] ) {
            $price_part = '<span class="' . esc_attr( $prefix ) . '__price">' . esc_html( (string) $row['price_display'] ) . '</span>';
            $unit_part  = ( $display['unit'] && $unit !== '' )
                ? '<span class="' . esc_attr( $prefix ) . '__unit">/' . esc_html( $unit ) . '</span>'
                : '';
            $price_html = '<div class="' . esc_attr( $prefix ) . '__price-row">' . $price_part . $unit_part . '</div>';
        }

        $tax_html = ( $display['tax'] && $service_tax_rate !== '' && ! $has_recurring_items )
            ? '<span class="' . esc_attr( $prefix ) . '__tax">' . esc_html__( '税率', 'kantanpro' ) . ': ' . esc_html( $service_tax_rate ) . '%</span>'
            : '';

        if ( $price_html === '' && $tax_html === '' ) {
            return '';
        }

        return '<div class="' . esc_attr( $prefix ) . '__price-block">' . $price_html . $tax_html . '</div>';
    }

    /**
     * 公開一覧の初回費用 HTML を返す。
     *
     * @param array<string, mixed> $row       商品行データ。
     * @param string               $css_class CSS クラス名。
     * @return string
     */
    private function render_public_product_list_initial_fees_html( array $row, $css_class ) {
        if ( empty( $row['initial_fees'] ) || ! is_array( $row['initial_fees'] ) ) {
            return '';
        }

        $fee_lines = array();
        foreach ( $row['initial_fees'] as $fee ) {
            if ( ! is_array( $fee ) ) {
                continue;
            }

            $fee_lines[] = esc_html( (string) ( $fee['fee_name'] ?? '' ) ) . ' '
                . esc_html( (string) ( $fee['amount_display'] ?? '' ) );
        }

        if ( empty( $fee_lines ) ) {
            return '';
        }

        $items = '';
        $last  = count( $fee_lines ) - 1;
        foreach ( $fee_lines as $index => $line ) {
            $items .= '<span class="' . esc_attr( $css_class ) . '__item">' . $line;
            if ( $index === $last ) {
                $items .= '<span class="' . esc_attr( $css_class ) . '__note">' . esc_html__( '初回請求時のみ', 'kantanpro' ) . '</span>';
            }
            $items .= '</span>';
        }

        return '<div class="' . esc_attr( $css_class ) . '">'
            . '<span class="' . esc_attr( $css_class ) . '__label">' . esc_html__( '初回費用', 'kantanpro' ) . '</span>'
            . $items
            . '</div>';
    }

    /**
     * 一覧ブロック下部の「問い合わす」ボタン HTML。
     *
     * @param array<string, mixed> $row        整形済み商品行。
     * @param bool                 $table_cell テーブルセル内表示（フッター余白なし）。
     * @return string
     */
    private function render_public_product_inquiry_button_html( array $row, $table_cell = false ) {
        $acceptance_open = ! empty( $row['acceptance_open'] ) && empty( $row['is_pending'] ) && empty( $row['is_sold_out'] );
        $wrapper_class   = $table_cell
            ? 'ktpwp-public-product-item__inquire-wrap ktpwp-public-product-item__inquire-wrap--table'
            : 'ktpwp-public-product-item__footer';

        if ( $acceptance_open ) {
            return '<div class="' . esc_attr( $wrapper_class ) . '">'
                . '<button type="button" class="ktpwp-public-product-item__inquire-btn">'
                . esc_html__( '問い合わす', 'kantanpro' )
                . '</button></div>';
        }

        $label = (string) ( $row['status_label'] ?? '' );
        if ( $label === '' ) {
            $label = __( '受付停止', 'kantanpro' );
        }

        return '<div class="' . esc_attr( $wrapper_class ) . '">'
            . '<button type="button" class="ktpwp-public-product-item__inquire-btn" disabled>'
            . esc_html( $label )
            . '</button></div>';
    }

    /**
     * クリック可能な商品要素の data 属性文字列を返す。
     *
     * @param array<string, mixed> $payload 商品データ。
     * @return string
     */
    private function get_public_product_item_attrs( array $payload, $extra_class = '' ) {
        $classes  = trim( 'ktpwp-public-product-item ' . $extra_class );
        if ( ! empty( $payload['is_sold_out'] ) ) {
            $classes .= ' ktpwp-public-product-item--sold-out';
        } elseif ( ! empty( $payload['is_pending'] ) ) {
            $classes .= ' ktpwp-public-product-item--pending';
        }
        $category = isset( $payload['category'] ) ? (string) $payload['category'] : '';

        return ' class="' . esc_attr( $classes ) . '"'
            . ' data-category="' . esc_attr( $category ) . '"'
            . ' data-product="' . esc_attr( wp_json_encode( $payload ) ) . '"';
    }

    /**
     * 保留中バッジ HTML。
     *
     * @return string
     */
    private function render_public_product_pending_badge_html( $css_class = 'ktpwp-public-product-item__pending-badge' ) {
        return '<span class="' . esc_attr( $css_class ) . '">' . esc_html__( '保留中', 'kantanpro' ) . '</span>';
    }

    /**
     * 画像上に重ねる受付停止オーバーレイ HTML。
     *
     * @param string $label 表示ラベル（保留中 / 完売御礼！ 等）。
     * @return string
     */
    private function render_public_product_status_overlay_html( $label ) {
        $label = trim( (string) $label );
        if ( $label === '' ) {
            return '';
        }

        return '<span class="ktpwp-public-product-item__pending-overlay" aria-hidden="true">'
            . '<span class="ktpwp-public-product-item__pending-overlay-badge">' . esc_html( $label ) . '</span>'
            . '</span>';
    }

    /**
     * 一覧ブロック内の商品画像 HTML（クリックで拡大表示）。
     *
     * @param string $image_url  画像 URL。
     * @param string $name       商品名（alt・aria-label 用）。
     * @param string $image_class 画像要素の CSS クラス。
     * @param string $wrap_class  ラップ要素の CSS クラス（空なら省略）。
     * @param string $extra_attrs img 要素の追加属性（例: width/height）。
     * @param bool   $show_status_overlay 受付停止オーバーレイを画像上に表示するか。
     * @param string $status_label        オーバーレイ文言。
     * @return string
     */
    private function render_public_product_image_html( $image_url, $name, $image_class, $wrap_class = '', $extra_attrs = '', $show_status_overlay = false, $status_label = '' ) {
        $label = sprintf(
            /* translators: %s: product name */
            __( '%s の画像を拡大', 'kantanpro' ),
            $name
        );

        $image_markup = '<button type="button" class="ktpwp-public-product-item__image-btn" aria-label="' . esc_attr( $label ) . '">'
            . '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $name ) . '" class="' . esc_attr( trim( $image_class . ' ktpwp-public-product-item__image' ) ) . '" loading="lazy" decoding="async"'
            . ( $extra_attrs !== '' ? ' ' . $extra_attrs : '' )
            . ' /></button>';

        if ( $wrap_class === '' ) {
            return $image_markup;
        }

        $wrap_classes = trim( $wrap_class . ( $show_status_overlay ? ' ktpwp-public-product-item__image-wrap--pending' : '' ) );
        $overlay      = $show_status_overlay ? $this->render_public_product_status_overlay_html( $status_label ) : '';

        return '<div class="' . esc_attr( $wrap_classes ) . '">' . $image_markup . $overlay . '</div>';
    }

    /**
     * 画像拡大表示用ライトボックスの HTML を返す。
     *
     * @return string
     */
    private function render_public_product_image_lightbox_shell() {
        ob_start();
        ?>
        <div class="ktpwp-public-product-image-lightbox" id="ktpwp-public-product-image-lightbox" hidden>
            <button type="button" class="ktpwp-public-product-image-lightbox__backdrop" aria-label="<?php echo esc_attr__( '閉じる', 'kantanpro' ); ?>"></button>
            <figure class="ktpwp-public-product-image-lightbox__figure">
                <img class="ktpwp-public-product-image-lightbox__image" alt="" decoding="async" />
                <figcaption class="ktpwp-public-product-image-lightbox__caption"></figcaption>
            </figure>
            <button type="button" class="ktpwp-public-product-image-lightbox__close" aria-label="<?php echo esc_attr__( '閉じる', 'kantanpro' ); ?>">&times;</button>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * 一覧レイアウト用のメモ HTML を返す。
     *
     * @param string $memo      メモ本文。
     * @param string $css_class CSS クラス名。
     * @return string
     */
    private function render_public_product_list_memo_html( $memo, $css_class ) {
        if ( $memo === '' ) {
            return '';
        }

        return '<p class="' . esc_attr( $css_class ) . '">' . esc_html( $memo ) . '</p>';
    }

    /**
     * 一覧レイアウト用の公開HTMLブロックを返す。
     *
     * @param string $public_html 公開用HTML（表示用にサニタイズ済み）。
     * @param string $css_class   CSS クラス名。
     * @return string
     */
    private function render_public_product_public_html_block( $public_html, $css_class ) {
        if ( $public_html === '' ) {
            return '';
        }

        return '<div class="' . esc_attr( $css_class ) . '">' . $public_html . '</div>';
    }

    /**
     * カテゴリー絞り込み UI を返す。
     *
     * @param array<int, string> $categories  カテゴリー一覧。
     * @param string             $initial     初期選択カテゴリー。
     * @return string
     */
    private function render_public_category_filter( array $categories, $initial = '' ) {
        $list_id = 'ktpwp-public-products-categories-' . wp_rand( 1000, 9999 );

        $options = '';
        foreach ( $categories as $cat ) {
            $options .= '<option value="' . esc_attr( $cat ) . '"></option>';
        }

        $initial_attr = $initial !== '' ? ' value="' . esc_attr( $initial ) . '"' : '';

        return '<div class="ktpwp-public-products-filter">'
            . '<label class="ktpwp-public-products-filter__label" for="' . esc_attr( $list_id ) . '-input">'
            . esc_html__( 'カテゴリで絞り込み', 'kantanpro' )
            . '</label>'
            . '<input type="search" class="ktpwp-public-products-filter__input" id="' . esc_attr( $list_id ) . '-input" list="' . esc_attr( $list_id ) . '"'
            . ' placeholder="' . esc_attr__( 'カテゴリを入力または選択…', 'kantanpro' ) . '" autocomplete="off"' . $initial_attr . ' />'
            . '<datalist id="' . esc_attr( $list_id ) . '">' . $options . '</datalist>'
            . '<button type="button" class="ktpwp-public-products-filter__clear">' . esc_html__( 'すべて表示', 'kantanpro' ) . '</button>'
            . '</div>';
    }

    /**
     * 商品詳細・お問い合わせフォームのシェルを返す。
     *
     * @return string
     */
    private function render_public_product_detail_shell() {
        ob_start();
        ?>
        <div class="ktpwp-public-product-detail" id="ktpwp-public-product-detail" hidden>
            <button type="button" class="ktpwp-public-product-detail__backdrop" aria-label="<?php echo esc_attr__( '閉じる', 'kantanpro' ); ?>"></button>
            <div class="ktpwp-public-product-detail__panel" role="dialog" aria-modal="true" aria-labelledby="ktpwp-public-product-detail-title">
                <div class="ktpwp-public-product-detail__header">
                    <button type="button" class="ktpwp-public-product-detail__close" aria-label="<?php echo esc_attr__( '閉じる', 'kantanpro' ); ?>">&times;</button>
                </div>
                <div class="ktpwp-public-product-detail__scroll">
                <div class="ktpwp-public-product-detail__content"></div>
                <form class="ktpwp-public-product-order-form" novalidate>
                    <h4 class="ktpwp-public-product-order-form__title" id="ktpwp-public-product-detail-title"><?php echo esc_html__( 'お問い合わせ', 'kantanpro' ); ?></h4>
                    <input type="hidden" name="service_id" value="" />
                    <p class="ktpwp-public-product-order-form__field">
                        <label for="ktpwp-pp-company"><?php echo esc_html__( '会社名', 'kantanpro' ); ?> <span class="ktpwp-public-product-order-form__optional"><?php echo esc_html__( '任意', 'kantanpro' ); ?></span></label>
                        <input type="text" id="ktpwp-pp-company" name="company_name" autocomplete="organization" />
                    </p>
                    <p class="ktpwp-public-product-order-form__field">
                        <label for="ktpwp-pp-contact"><?php echo esc_html__( 'お名前', 'kantanpro' ); ?> <span class="required">*</span></label>
                        <input type="text" id="ktpwp-pp-contact" name="contact_name" required autocomplete="name" />
                    </p>
                    <p class="ktpwp-public-product-order-form__field">
                        <label for="ktpwp-pp-email"><?php echo esc_html__( 'メールアドレス', 'kantanpro' ); ?> <span class="required">*</span></label>
                        <input type="email" id="ktpwp-pp-email" name="email" required autocomplete="email" />
                    </p>
                    <p class="ktpwp-public-product-order-form__field">
                        <label for="ktpwp-pp-phone"><?php echo esc_html__( '電話番号', 'kantanpro' ); ?></label>
                        <input type="tel" id="ktpwp-pp-phone" name="phone" autocomplete="tel" />
                    </p>
                    <p class="ktpwp-public-product-order-form__field ktpwp-public-product-order-form__field--quantity">
                        <label for="ktpwp-pp-quantity"><?php echo esc_html__( '数量', 'kantanpro' ); ?></label>
                        <input type="number" id="ktpwp-pp-quantity" name="quantity" min="1" step="1" value="1" />
                    </p>
                    <p class="ktpwp-public-product-order-form__field">
                        <label for="ktpwp-pp-message"><?php echo esc_html__( 'ご要望・備考', 'kantanpro' ); ?></label>
                        <textarea id="ktpwp-pp-message" name="message" rows="4"></textarea>
                    </p>
                    <p class="ktpwp-public-product-order-form__honeypot" aria-hidden="true">
                        <label for="ktpwp-pp-company-url">URL</label>
                        <input type="text" id="ktpwp-pp-company-url" name="company_url" tabindex="-1" autocomplete="off" />
                    </p>
                    <p class="ktpwp-public-product-order-form__actions">
                        <button type="submit" class="ktpwp-public-product-order-form__submit"><?php echo esc_html__( '送信する', 'kantanpro' ); ?></button>
                        <button type="button" class="ktpwp-public-product-order-form__close"><?php echo esc_html__( '閉じる', 'kantanpro' ); ?></button>
                    </p>
                    <div class="ktpwp-public-product-order-form__message" role="status" aria-live="polite" hidden></div>
                </form>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * テーブルレイアウトを描画する。
     *
     * @param array<int, object> $services 商品一覧。
     * @param array<string, bool> $display 表示フラグ。
     * @return string
     */
    private function render_public_products_table( $services, $display ) {
        $headers = array();
        $rows = '';

        if ( $display['image'] ) {
            $headers[] = '<th scope="col">' . esc_html__( '画像', 'kantanpro' ) . '</th>';
        }
        $headers[] = '<th scope="col">' . esc_html__( '商品名', 'kantanpro' ) . '</th>';
        if ( $display['category'] ) {
            $headers[] = '<th scope="col">' . esc_html__( 'カテゴリ', 'kantanpro' ) . '</th>';
        }
        if ( $display['price'] ) {
            $headers[] = '<th scope="col">' . esc_html__( '単価', 'kantanpro' ) . '</th>';
        }
        if ( $display['unit'] ) {
            $headers[] = '<th scope="col">' . esc_html__( '単位', 'kantanpro' ) . '</th>';
        }
        if ( $display['tax'] ) {
            $headers[] = '<th scope="col">' . esc_html__( '税率（%）', 'kantanpro' ) . '</th>';
        }
        if ( $display['memo'] ) {
            $headers[] = '<th scope="col">' . esc_html__( 'メモ', 'kantanpro' ) . '</th>';
        }
        if ( $display['initial_fees'] ) {
            $headers[] = '<th scope="col">' . esc_html__( '初回費用', 'kantanpro' ) . '</th>';
        }
        $headers[] = '<th scope="col">' . esc_html__( 'お問い合わせ', 'kantanpro' ) . '</th>';

        foreach ( $services as $service ) {
            $row     = $this->format_public_product_row( $service );
            $payload = $row;
            $cells   = array();

            if ( $display['image'] ) {
                $show_overlay = empty( $row['acceptance_open'] );
                $cells[] = '<td>' . $this->render_public_product_image_html(
                    $row['image'],
                    $row['name'],
                    'ktpwp-public-products-thumb',
                    'ktpwp-public-products-table__image-wrap',
                    'width="48" height="48"',
                    $show_overlay,
                    (string) ( $row['status_label'] ?? '' )
                ) . '</td>';
            }
            $cells[] = '<td>' . esc_html( $row['name'] ) . '</td>';
            if ( $display['category'] ) {
                $cells[] = '<td>' . esc_html( $row['category'] ) . '</td>';
            }
            if ( $display['price'] ) {
                $price_cell = ! empty( $row['recurring_items_summary'] )
                    ? (string) $row['recurring_items_summary']
                    : (string) $row['price_display'];
                $cells[] = '<td>' . esc_html( $price_cell ) . '</td>';
            }
            if ( $display['unit'] ) {
                $cells[] = '<td>' . esc_html( $row['unit'] ) . '</td>';
            }
            if ( $display['tax'] ) {
                $cells[] = '<td>' . esc_html( $row['tax_rate'] ) . '</td>';
            }
            if ( $display['memo'] ) {
                $cells[] = '<td class="ktpwp-public-products-table__memo">' . esc_html( $row['memo'] ) . '</td>';
            }
            if ( $display['initial_fees'] ) {
                $cells[] = '<td class="ktpwp-public-products-table__initial-fees">' . esc_html( (string) $row['initial_fees_summary'] ) . '</td>';
            }

            $cells[] = '<td class="ktpwp-public-products-table__inquire">' . $this->render_public_product_inquiry_button_html( $row, true ) . '</td>';

            $rows .= '<tr' . $this->get_public_product_item_attrs( $payload ) . '>' . implode( '', $cells ) . '</tr>';
        }

        return '<table class="ktpwp-public-products-table"><thead><tr>' . implode( '', $headers ) . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /**
     * グリッドレイアウトを描画する。
     *
     * @param array<int, object> $services 商品一覧。
     * @param array<string, bool> $display 表示フラグ。
     * @param int $columns 列数。
     * @return string
     */
    private function render_public_products_grid( $services, $display, $columns ) {
        $items = '';

        foreach ( $services as $service ) {
            $row     = $this->format_public_product_row( $service );
            $payload = $row;
            $image_html = '';
            if ( $display['image'] ) {
                $show_overlay = empty( $row['acceptance_open'] );
                $status_label = (string) ( $row['status_label'] ?? '' );
                if ( $row['image'] !== '' ) {
                    $image_html = $this->render_public_product_image_html(
                        $row['image'],
                        $row['name'],
                        'ktpwp-public-products-grid__image',
                        'ktpwp-public-products-grid__image-wrap',
                        '',
                        $show_overlay,
                        $status_label
                    );
                } elseif ( $show_overlay && $status_label !== '' ) {
                    $image_html = '<div class="ktpwp-public-products-grid__image-wrap ktpwp-public-product-item__image-wrap--pending">'
                        . $this->render_public_product_status_overlay_html( $status_label )
                        . '</div>';
                }
            }

            $category_html = ( $display['category'] && $row['category'] !== '' )
                ? '<p class="ktpwp-public-products-grid__category">' . esc_html( $row['category'] ) . '</p>'
                : '';
            $price_block = $this->render_public_product_list_price_block_html( $row, $display, 'ktpwp-public-products-grid' );
            $memo_html = $display['memo']
                ? $this->render_public_product_list_memo_html( $row['memo'], 'ktpwp-public-products-grid__memo' )
                : '';
            $public_html_block = $this->render_public_product_public_html_block(
                (string) ( $row['public_html'] ?? '' ),
                'ktpwp-public-products-grid__public-html'
            );
            $initial_fees_html = $display['initial_fees']
                ? $this->render_public_product_list_initial_fees_html( $row, 'ktpwp-public-products-grid__initial-fees' )
                : '';

            $inquire_html = $this->render_public_product_inquiry_button_html( $row );

            $items .= '<article' . $this->get_public_product_item_attrs( $payload, 'ktpwp-public-products-grid__item' ) . '>'
                . $image_html
                . '<div class="ktpwp-public-products-grid__body">'
                . '<h3 class="ktpwp-public-products-grid__name">' . esc_html( $row['name'] ) . '</h3>'
                . $category_html
                . $price_block
                . $initial_fees_html
                . $memo_html
                . $public_html_block
                . '</div>'
                . $inquire_html
                . '</article>';
        }

        return '<div class="ktpwp-public-products-grid ktpwp-public-products-grid--cols-' . esc_attr( (string) $columns ) . '">' . $items . '</div>';
    }

    /**
     * カードレイアウトを描画する。
     *
     * @param array<int, object> $services 商品一覧。
     * @param array<string, bool> $display 表示フラグ。
     * @param int $columns 列数。
     * @return string
     */
    private function render_public_products_cards( $services, $display, $columns ) {
        $items = '';

        foreach ( $services as $service ) {
            $row     = $this->format_public_product_row( $service );
            $payload = $row;
            $image_html = '';
            if ( $display['image'] ) {
                $show_overlay = empty( $row['acceptance_open'] );
                $status_label = (string) ( $row['status_label'] ?? '' );
                if ( $row['image'] !== '' ) {
                    $image_html = $this->render_public_product_image_html(
                        $row['image'],
                        $row['name'],
                        'ktpwp-public-products-card__image',
                        'ktpwp-public-products-card__image-wrap',
                        '',
                        $show_overlay,
                        $status_label
                    );
                } elseif ( $show_overlay && $status_label !== '' ) {
                    $image_html = '<div class="ktpwp-public-products-card__image-wrap ktpwp-public-product-item__image-wrap--pending">'
                        . $this->render_public_product_status_overlay_html( $status_label )
                        . '</div>';
                }
            }

            $category_html = ( $display['category'] && $row['category'] !== '' )
                ? '<p class="ktpwp-public-products-card__category">' . esc_html( $row['category'] ) . '</p>'
                : '';
            $price_block = $this->render_public_product_list_price_block_html( $row, $display, 'ktpwp-public-products-card' );
            $memo_html = $display['memo']
                ? $this->render_public_product_list_memo_html( $row['memo'], 'ktpwp-public-products-card__memo' )
                : '';
            $public_html_block = $this->render_public_product_public_html_block(
                (string) ( $row['public_html'] ?? '' ),
                'ktpwp-public-products-card__public-html'
            );
            $initial_fees_html = $display['initial_fees']
                ? $this->render_public_product_list_initial_fees_html( $row, 'ktpwp-public-products-card__initial-fees' )
                : '';

            $inquire_html = $this->render_public_product_inquiry_button_html( $row );

            $items .= '<article' . $this->get_public_product_item_attrs( $payload, 'ktpwp-public-products-card' ) . '>'
                . $image_html
                . '<div class="ktpwp-public-products-card__body">'
                . $category_html
                . '<h3 class="ktpwp-public-products-card__name">' . esc_html( $row['name'] ) . '</h3>'
                . $price_block
                . $initial_fees_html
                . $memo_html
                . $public_html_block
                . '</div>'
                . $inquire_html
                . '</article>';
        }

        return '<div class="ktpwp-public-products-cards ktpwp-public-products-cards--cols-' . esc_attr( (string) $columns ) . '">' . $items . '</div>';
    }

    /**
     * 登録済みショートコード一覧取得
     *
     * @return array ショートコード名配列
     */
    public function get_registered_shortcodes() {
        return $this->registered_shortcodes;
    }

    /**
     * ショートコード存在チェック
     *
     * @param string $shortcode_name ショートコード名
     * @return bool 存在するかどうか
     */
    public function shortcode_exists($shortcode_name) {
        return in_array($shortcode_name, $this->registered_shortcodes, true);
    }

    /**
     * ログイン中スタッフアバター表示の公開メソッド
     *
     * @return string ユーザー表示HTML
     */
    public function get_staff_avatars_display() {
        return $this->get_logged_in_users_display();
    }

    /**
     * デストラクタ
     */
    public function __destruct() {
        // キャッシュクリア（必要に応じて）
        $this->logged_in_users_cache = null;
    }
}
