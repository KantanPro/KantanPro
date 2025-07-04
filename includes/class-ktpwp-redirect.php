<?php
/**
 * KTPWP リダイレクト管理クラス
 *
 * @package KTPWP
 * @since 0.1.0
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * リダイレクト管理クラス
 */
class KTPWP_Redirect {

    /**
     * シングルトンインスタンス
     *
     * @var KTPWP_Redirect|null
     */
    private static $instance = null;

    /**
     * 許可されたホスト一覧
     *
     * @var array
     */
    private $allowed_hosts = array();

    /**
     * リダイレクト対象スラッグ
     *
     * @var array
     */
    private $redirect_slugs = array();

    /**
     * リダイレクト対象カテゴリ
     *
     * @var array
     */
    private $redirect_categories = array();

    /**
     * シングルトンインスタンス取得
     *
     * @return KTPWP_Redirect
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
        $this->init_config();
        $this->init_hooks();
    }

    /**
     * 設定初期化
     */
    private function init_config() {
        // 許可されたホスト設定
        $this->allowed_hosts = array(
            'ktpwp.com',
            parse_url( home_url(), PHP_URL_HOST ),
        );

        // リダイレクト対象スラッグ
        $this->redirect_slugs = array(
            'redirect-to-ktpwp',
            'external-link',
        );

        // リダイレクト対象カテゴリ
        $this->redirect_categories = array(
            'blog',
            'news',
            'column',
        );
    }

    /**
     * フック初期化
     */
    private function init_hooks() {
        // テンプレートリダイレクト処理
        add_action( 'template_redirect', array( $this, 'handle_redirect' ) );

        // パーマリンクフィルタ
        add_filter( 'post_link', array( $this, 'custom_post_link' ), 10, 2 );
        add_filter( 'page_link', array( $this, 'custom_page_link' ), 10, 2 );

        // フォームリダイレクト処理
        add_action( 'wp_loaded', array( $this, 'handle_form_redirect' ), 1 );
    }

    /**
     * リダイレクト処理
     */
    public function handle_redirect() {
        // KTPWPショートコードが含まれるページはリダイレクトしない
        if ( isset( $_GET['tab_name'] ) || $this->has_ktpwp_shortcode() ) {
            return;
        }

        if ( is_single() || is_page() ) {
            $post = get_queried_object();

            if ( $post && $this->should_redirect( $post ) ) {
                $external_url = $this->get_external_url( $post );
                if ( $external_url && $this->is_url_safe( $external_url ) ) {
                    wp_redirect( $external_url, 301 );
                    exit;
                }
            }
        }
    }

    /**
     * 現在のページにKTPWPショートコードが含まれているかチェック
     *
     * @return bool
     */
    private function has_ktpwp_shortcode() {
        $post = get_queried_object();
        if ( ! $post || ! isset( $post->post_content ) ) {
            return false;
        }

        return (
            has_shortcode( $post->post_content, 'kantanAllTab' ) ||
            has_shortcode( $post->post_content, 'ktpwp_all_tab' )
        );
    }

    /**
     * リダイレクト対象かどうかを判定
     *
     * @param WP_Post $post 投稿オブジェクト
     * @return bool
     */
    private function should_redirect( $post ) {
        if ( ! $post ) {
            return false;
        }

        // ショートコードが含まれるページはリダイレクトしない
        if ( $this->has_ktpwp_shortcode() ) {
            return false;
        }

        // KTPWPのクエリパラメータがある場合はリダイレクトしない
        $ktpwp_params = array(
            'tab_name',
			'from_client',
			'order_id',
			'client_id',
            'customer_name',
			'user_name',
			'delete_order',
			'data_id',
            'view_mode',
			'query_post',
        );

        foreach ( $ktpwp_params as $param ) {
            if ( isset( $_GET[ $param ] ) ) {
                return false;
            }
        }

        // external_urlが設定されている投稿
        $external_url = get_post_meta( $post->ID, 'external_url', true );
        if ( ! empty( $external_url ) ) {
            return true;
        }

        // カスタム投稿タイプ「blog」で特定条件
        if ( $post->post_type === 'blog' ) {
            return in_array( $post->post_name, $this->redirect_slugs, true );
        }

        return false;
    }

    /**
     * 外部URL取得
     *
     * @param WP_Post $post 投稿オブジェクト
     * @return string|false
     */
    private function get_external_url( $post ) {
        if ( ! $post ) {
            return false;
        }

        $external_url = get_post_meta( $post->ID, 'external_url', true );

        if ( empty( $external_url ) ) {
            $base_url = 'https://ktpwp.com/blog/';

            if ( $post->post_type === 'blog' ) {
                $external_url = $base_url;
            } elseif ( $post->post_type === 'post' ) {
                $categories = wp_get_post_categories( $post->ID, array( 'fields' => 'slugs' ) );

                if ( in_array( 'blog', $categories, true ) ) {
                    $external_url = $base_url;
                } elseif ( in_array( 'news', $categories, true ) ) {
                    $external_url = $base_url . 'news/';
                } elseif ( in_array( 'column', $categories, true ) ) {
                    $external_url = $base_url . 'column/';
                }
            }
        }

        // URLからクエリパラメータを除去
        if ( $external_url ) {
            $external_url = strtok( $external_url, '?' );
        }

        return $external_url;
    }

    /**
     * URLの安全性確認
     *
     * @param string $url チェックするURL
     * @return bool
     */
    private function is_url_safe( $url ) {
        $parsed = wp_parse_url( $url );

        if ( ! isset( $parsed['host'] ) ) {
            return false;
        }

        $host = $parsed['host'];

        if ( ! in_array( $host, $this->allowed_hosts, true ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Redirect: Unsafe host blocked - ' . $host );
            }
            return false;
        }

        // クリーンなURLを構築
        $clean_url = $parsed['scheme'] . '://' . $host;
        if ( isset( $parsed['path'] ) ) {
            $clean_url .= $parsed['path'];
        }

        return $clean_url === $url;
    }

    /**
     * カスタム投稿パーマリンク
     *
     * @param string  $permalink パーマリンク
     * @param WP_Post $post 投稿オブジェクト
     * @return string
     */
    public function custom_post_link( $permalink, $post ) {
        if ( $post->post_type === 'blog' ) {
            $external_url = $this->get_external_url( $post );
            if ( $external_url && $this->is_url_safe( $external_url ) ) {
                return $external_url;
            }
        }

        if ( $post->post_type === 'post' ) {
            $categories = wp_get_post_categories( $post->ID, array( 'fields' => 'slugs' ) );

            if ( ! empty( array_intersect( $categories, $this->redirect_categories ) ) ) {
                $external_url = $this->get_external_url( $post );
                if ( $external_url && $this->is_url_safe( $external_url ) ) {
                    return $external_url;
                }
            }
        }

        return $permalink;
    }

    /**
     * カスタムページパーマリンク
     *
     * @param string $permalink パーマリンク
     * @param int    $post_id 投稿ID
     * @return string
     */
    public function custom_page_link( $permalink, $post_id ) {
        $post = get_post( $post_id );

        if ( $post && $this->should_redirect( $post ) ) {
            $external_url = $this->get_external_url( $post );
            if ( $external_url && $this->is_url_safe( $external_url ) ) {
                return $external_url;
            }
        }

        return $permalink;
    }

    /**
     * フォームリダイレクト処理
     */
    public function handle_form_redirect() {
        // 受注フォームのPOSTからGETへの変換処理
        if ( isset( $_POST['tab_name'] ) && $_POST['tab_name'] === 'order' && isset( $_POST['from_client'] ) ) {
            $redirect_params = $this->sanitize_form_data( $_POST );
            $clean_url = $this->get_clean_base_url();
            $redirect_url = add_query_arg( $redirect_params, $clean_url );

            wp_redirect( $redirect_url, 302 );
            exit;
        }
    }

    /**
     * フォームデータのサニタイズ
     *
     * @param array $post_data POSTデータ
     * @return array サニタイズされたデータ
     */
    private function sanitize_form_data( $post_data ) {
        $redirect_params = array();

        // 基本パラメータ
        if ( isset( $post_data['tab_name'] ) ) {
            $redirect_params['tab_name'] = sanitize_text_field( $post_data['tab_name'] );
        }

        if ( isset( $post_data['from_client'] ) ) {
            $redirect_params['from_client'] = sanitize_text_field( $post_data['from_client'] );
        }

        // オプションパラメータ
        $optional_params = array(
            'customer_name',
			'user_name',
        );

        foreach ( $optional_params as $param ) {
            if ( isset( $post_data[ $param ] ) ) {
                $redirect_params[ $param ] = sanitize_text_field( $post_data[ $param ] );
            }
        }

        // クライアントID（数値のみ）
        if ( isset( $post_data['client_id'] ) ) {
            $client_id = intval( $post_data['client_id'] );
            if ( $client_id > 0 ) {
                $redirect_params['client_id'] = $client_id;
            }
        }

        return $redirect_params;
    }

    /**
     * クリーンなベースURL取得
     *
     * @return string
     */
    private function get_clean_base_url() {
        $current_url = add_query_arg( null, null );

        $remove_params = array(
            'tab_name',
			'from_client',
			'customer_name',
			'user_name',
            'client_id',
			'order_id',
			'delete_order',
			'data_id',
            'view_mode',
			'query_post',
        );

        return remove_query_arg( $remove_params, $current_url );
    }

    /**
     * 許可されたホストの追加
     *
     * @param string $host ホスト名
     */
    public function add_allowed_host( $host ) {
        if ( ! in_array( $host, $this->allowed_hosts, true ) ) {
            $this->allowed_hosts[] = $host;
        }
    }

    /**
     * 許可されたホスト一覧取得
     *
     * @return array
     */
    public function get_allowed_hosts() {
        return $this->allowed_hosts;
    }

    /**
     * リダイレクト対象スラッグの追加
     *
     * @param string $slug スラッグ
     */
    public function add_redirect_slug( $slug ) {
        if ( ! in_array( $slug, $this->redirect_slugs, true ) ) {
            $this->redirect_slugs[] = $slug;
        }
    }

    /**
     * リダイレクト対象カテゴリの追加
     *
     * @param string $category カテゴリ
     */
    public function add_redirect_category( $category ) {
        if ( ! in_array( $category, $this->redirect_categories, true ) ) {
            $this->redirect_categories[] = $category;
        }
    }
}
