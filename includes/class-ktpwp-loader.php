<?php
/**
 * クラスローダークラス
 * 
 * プラグインの各クラスファイルの読み込みを管理
 *
 * @package KTPWP
 * @since 1.0.0
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * クラスローダークラス
 */
class KTPWP_Loader {
    
    /**
     * シングルトンインスタンス
     * 
     * @var KTPWP_Loader|null
     */
    private static $instance = null;
    
    /**
     * 自動読み込み対象クラスマップ
     * 
     * @var array
     */
    private $class_map = array();
    
    /**
     * 必須ファイルリスト
     * 
     * @var array
     */
    private $required_files = array();
    
    /**
     * シングルトンインスタンス取得
     * 
     * @return KTPWP_Loader
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
        $this->setup_class_map();
        $this->setup_required_files();
    }
    
    /**
     * 初期化
     */
    public function init() {
        $this->autoload_classes();
        $this->load_required_files();
    }
    
    /**
     * クラスマップの設定
     */
    private function setup_class_map() {
        $this->class_map = array(
            // タブ関連クラス
            'Kntan_Client_Class'     => 'includes/class-tab-client.php',
            'Kntan_Service_Class'    => 'includes/class-tab-service.php',
            'KTPWP_Supplier_Class'   => 'includes/class-tab-supplier.php',
            'KTPWP_Supplier_Security' => 'includes/class-supplier-security.php',
            'KTPWP_Supplier_Data'    => 'includes/class-supplier-data.php',
            'KTPWP_Supplier_Skills'  => 'includes/class-ktpwp-supplier-skills.php',
            'KTPWP_Report_Class'     => 'includes/class-tab-report.php',
            'Kntan_Order_Class'      => 'includes/class-tab-order.php',
            'KTPWP_Setting_Class'    => 'includes/class-tab-setting.php',
            'Kantan_List_Class'      => 'includes/class-tab-list.php',
            
            // ビュー関連クラス
            'view_tabs_Class'        => 'includes/class-view-tab.php',
            'Kantan_Login_Error'     => 'includes/class-login-error.php',
            
            // 管理機能クラス
            'KTPWP_Plugin_Reference' => 'includes/class-plugin-reference.php',
            
            // 新しい分離クラス
            'KTPWP_Shortcodes'       => 'includes/class-ktpwp-shortcodes.php',
            'KTPWP_Ajax'             => 'includes/class-ktpwp-ajax.php',
            'KTPWP_Redirect'         => 'includes/class-ktpwp-redirect.php',
            'KTPWP_Contact_Form'     => 'includes/class-ktpwp-contact-form.php',
            'KTPWP_GitHub_Updater'   => 'includes/class-ktpwp-github-updater.php',
            'KTPWP_Security'         => 'includes/class-ktpwp-security.php',
            'KTPWP_Database'         => 'includes/class-ktpwp-database.php',
            'KTPWP_Assets'           => 'includes/class-ktpwp-assets.php',
        );
    }
    
    /**
     * 必須ファイルリストの設定
     */
    private function setup_required_files() {
        $this->required_files = array(
            'includes/class-ktp-settings.php',
        );
    }
    
    /**
     * クラス自動読み込み
     */
    private function autoload_classes() {
        foreach ( $this->class_map as $class_name => $file_path ) {
            if ( ! class_exists( $class_name ) ) {
                $full_path = KANTANPRO_PLUGIN_DIR . $file_path;
                if ( file_exists( $full_path ) ) {
                    require_once $full_path;
                }
            }
        }
    }
    
    /**
     * 必須ファイルの読み込み
     */
    private function load_required_files() {
        foreach ( $this->required_files as $file ) {
            $file_path = KANTANPRO_PLUGIN_DIR . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * 特定のクラスファイルを読み込み
     * 
     * @param string $class_name クラス名
     * @return bool 読み込み成功の可否
     */
    public function load_class( $class_name ) {
        if ( class_exists( $class_name ) ) {
            return true;
        }
        
        if ( isset( $this->class_map[ $class_name ] ) ) {
            $file_path = KANTANPRO_PLUGIN_DIR . $this->class_map[ $class_name ];
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
                return class_exists( $class_name );
            }
        }
        
        return false;
    }
    
    /**
     * 特定のファイルを読み込み
     * 
     * @param string $file_path ファイルパス（プラグインディレクトリからの相対パス）
     * @return bool 読み込み成功の可否
     */
    public function load_file( $file_path ) {
        $full_path = KANTANPRO_PLUGIN_DIR . $file_path;
        if ( file_exists( $full_path ) ) {
            require_once $full_path;
            return true;
        }
        return false;
    }
    
    /**
     * クラスマップにクラスを追加
     * 
     * @param string $class_name クラス名
     * @param string $file_path ファイルパス
     */
    public function add_class( $class_name, $file_path ) {
        $this->class_map[ $class_name ] = $file_path;
    }
    
    /**
     * 必須ファイルリストにファイルを追加
     * 
     * @param string $file_path ファイルパス
     */
    public function add_required_file( $file_path ) {
        if ( ! in_array( $file_path, $this->required_files, true ) ) {
            $this->required_files[] = $file_path;
        }
    }
    
    /**
     * 読み込み済みクラス一覧を取得
     * 
     * @return array
     */
    public function get_loaded_classes() {
        $loaded_classes = array();
        foreach ( $this->class_map as $class_name => $file_path ) {
            if ( class_exists( $class_name ) ) {
                $loaded_classes[] = $class_name;
            }
        }
        return $loaded_classes;
    }
}
