<?php
/**
 * KTPWP GitHub Updater Class
 *
 * GitHubからのプラグイン更新機能を管理するクラス
 *
 * @package KTPWP
 * @since 1.0.0
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KTPWP_GitHub_Updaterクラス
 *
 * GitHubリポジトリからのプラグイン自動更新機能を提供
 */
class KTPWP_GitHub_Updater {

    /**
     * シングルトンインスタンス
     *
     * @var KTPWP_GitHub_Updater|null
     */
    private static $instance = null;

    /**
     * GitHubユーザー名
     *
     * @var string
     */
    private $github_user = 'aiojiipg';

    /**
     * GitHubリポジトリ名
     *
     * @var string
     */
    private $github_repo = 'ktpwp';

    /**
     * プラグインスラッグ
     *
     * @var string
     */
    private $plugin_slug = 'KTPWP/ktpwp.php';

    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * シングルトンインスタンスを取得
     *
     * @return KTPWP_GitHub_Updater
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * WordPressフックを初期化
     */
    private function init_hooks() {
        // GitHub Updaterフィルターを登録（現在はコメントアウト）
        // add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        // add_filter('plugins_api', array($this, 'get_plugin_info'), 10, 3);
    }

    /**
     * プラグイン更新をチェック
     *
     * @param mixed $transient 更新トランジェント
     * @return mixed
     */
    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // GitHubの最新リリース情報を取得
        $response = wp_remote_get(
            $this->get_github_api_url(),
            array(
				'headers' => array(
					'Accept' => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
				),
			)
        );

        if ( is_wp_error( $response ) ) {
            return $transient;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $release ) || empty( $release->tag_name ) ) {
            return $transient;
        }

        // 現在のバージョンを取得
        $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_slug );
        $current_version = $plugin_data['Version'];
        $latest_version = ltrim( $release->tag_name, 'v' );

        // 新しいバージョンがあればアップデート情報をセット
        if ( version_compare( $current_version, $latest_version, '<' ) ) {
            $package_url = $this->get_package_url( $release );

            if ( ! empty( $package_url ) ) {
                $transient->response[ $this->plugin_slug ] = (object) array(
                    'slug' => dirname( $this->plugin_slug ),
                    'plugin' => $this->plugin_slug,
                    'new_version' => $latest_version,
                    'url' => $release->html_url,
                    'package' => $package_url,
                );
            }
        }

        return $transient;
    }

    /**
     * プラグイン情報を取得
     *
     * @param mixed  $res レスポンス
     * @param string $action アクション
     * @param object $args 引数
     * @return mixed
     */
    public function get_plugin_info( $res, $action, $args ) {
        if ( $action !== 'plugin_information' || ! isset( $args->slug ) || $args->slug !== 'KTPWP' ) {
            return $res;
        }

        $response = wp_remote_get(
            $this->get_github_api_url(),
            array(
				'headers' => array(
					'Accept' => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
				),
			)
        );

        if ( is_wp_error( $response ) ) {
            return $res;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $release ) ) {
            return $res;
        }

        $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/KTPWP/ktpwp.php' );

        $res = new stdClass();
        $res->name = $plugin_data['Name'];
        $res->slug = 'KTPWP';
        $res->version = ltrim( $release->tag_name, 'v' );
        $res->tested = get_bloginfo( 'version' );
        $res->requires = '5.0';
        $res->author = $plugin_data['Author'];
        $res->author_profile = '';
        $res->download_link = isset( $release->zipball_url ) ? $release->zipball_url : '';
        $res->trunk = isset( $release->zipball_url ) ? $release->zipball_url : '';
        $res->last_updated = isset( $release->published_at ) ? $release->published_at : '';
        $res->sections = array(
            'description' => $plugin_data['Description'],
            'changelog' => isset( $release->body ) ? $release->body : __( 'No changelog provided.', 'ktpwp' ),
        );

        return $res;
    }

    /**
     * GitHub API URLを取得
     *
     * @return string
     */
    private function get_github_api_url() {
        return "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
    }

    /**
     * パッケージURLを取得
     *
     * @param object $release リリース情報
     * @return string
     */
    private function get_package_url( $release ) {
        $package_url = '';

        // ZIPファイルのURLを見つける
        if ( isset( $release->assets ) && is_array( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( isset( $asset->browser_download_url ) &&
                    strpos( $asset->browser_download_url, '.zip' ) !== false ) {
                    $package_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        // アセットがなければzipballを使用
        if ( empty( $package_url ) && isset( $release->zipball_url ) ) {
            $package_url = $release->zipball_url;
        }

        return $package_url;
    }

    /**
     * GitHub Updaterを有効化
     */
    public function enable_updater() {
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
        add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 10, 3 );
    }

    /**
     * GitHub Updaterを無効化
     */
    public function disable_updater() {
        remove_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
        remove_filter( 'plugins_api', array( $this, 'get_plugin_info' ) );
    }
}
