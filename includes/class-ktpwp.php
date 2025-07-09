<?php
/**
 * 後方互換性ファイル
 *
 * クラス名の互換性を保持するためのブリッジファイル
 *
 * @package KTPWP
 * @since 1.0.0
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// メインクラスファイルを読み込み
require_once KANTANPRO_PLUGIN_DIR . 'includes/class-ktpwp-main.php';

/**
 * 後方互換性のためのエイリアスクラス
 *
 * 旧KTPWP_Mainクラスとの互換性を維持するためのクラス
 */
class KTPWP {

    /**
     * シングルトンインスタンス
     *
     * @var KTPWP
     */
    private static $instance = null;

    /**
     * メインプラグインクラスのインスタンス
     *
     * @var KTPWP_Main
     */
    private $main;

    /**
     * インスタンス取得
     *
     * @return KTPWP (実際にはKTPWP_Mainクラスを返す)
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        // 新しいメインクラスをインスタンス化
        $this->main = KTPWP_Main::get_instance();
    }

    /**
     * マジックメソッド: 存在しないメソッドはメインクラスに移譲
     *
     * @param string $name メソッド名
     * @param array  $arguments 引数
     * @return mixed
     */
    public function __call( $name, $arguments ) {
        if ( method_exists( $this->main, $name ) ) {
            return call_user_func_array( array( $this->main, $name ), $arguments );
        }

        return null;
    }

    /**
     * マジックメソッド: 存在しないスタティックメソッドはメインクラスに移譲
     *
     * @param string $name メソッド名
     * @param array  $arguments 引数
     * @return mixed
     */
    public static function __callStatic( $name, $arguments ) {
        $instance = KTPWP_Main::get_instance();
        if ( method_exists( $instance, $name ) ) {
            return call_user_func_array( array( $instance, $name ), $arguments );
        }

        return null;
    }

    /**
     * メインクラスのプロパティにアクセスするマジックメソッド
     *
     * @param string $name プロパティ名
     * @return mixed
     */
    public function __get( $name ) {
        if ( property_exists( $this->main, $name ) ) {
            return $this->main->$name;
        }

        return null;
    }

    /**
     * メインインスタンスを直接取得
     *
     * @return KTPWP_Main
     */
    public function get_main_instance() {
        return $this->main;
    }

    /**
     * ローダーインスタンスを取得
     *
     * @return KTPWP_Loader
     */
    public function get_loader() {
        return $this->main->get_loader();
    }

    /**
     * セキュリティインスタンスを取得
     *
     * @return KTPWP_Security
     */
    public function get_security() {
        return $this->main->get_security();
    }

    /**
     * アセットインスタンスを取得
     *
     * @return KTPWP_Assets
     */
    public function get_assets() {
        return $this->main->get_assets();
    }

    /**
     * ショートコードインスタンスを取得
     *
     * @return KTPWP_Shortcodes
     */
    public function get_shortcodes() {
        return $this->main->get_shortcodes();
    }

    /**
     * Ajaxインスタンスを取得
     *
     * @return KTPWP_Ajax
     */
    public function get_ajax() {
        return $this->main->get_ajax();
    }

    /**
     * リダイレクトインスタンスを取得
     *
     * @return KTPWP_Redirect
     */
    public function get_redirect() {
        return $this->main->get_redirect();
    }

    /**
     * Contact Formインスタンス取得
     *
     * @return KTPWP_Contact_Form
     */
    public function get_contact_form() {
        return $this->main->get_contact_form();
    }

    /**
     * データベースインスタンスを取得
     *
     * @return KTPWP_Database
     */
    public function get_database() {
        return $this->main->get_database();
    }
}
