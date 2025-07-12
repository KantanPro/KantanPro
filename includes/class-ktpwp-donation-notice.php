<?php
/**
 * KantanPro 寄付通知クラス
 * 
 * @package KantanPro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KTPWP_Donation_Notice クラス
 * 
 * フロントエンドでの寄付通知表示を管理するクラス
 */
class KTPWP_Donation_Notice {

    /**
     * インスタンス
     * 
     * @var KTPWP_Donation_Notice
     */
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     * 
     * @return KTPWP_Donation_Notice
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
        add_action( 'wp_footer', array( $this, 'display_donation_notice' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_ktpwp_dismiss_donation_notice', array( $this, 'ajax_dismiss_notice' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_dismiss_donation_notice', array( $this, 'ajax_dismiss_notice' ) );
    }

    /**
     * 寄付通知を表示するかどうかをチェック
     * 
     * @return bool
     */
    private function should_display_notice() {
        // 管理画面では表示しない
        if ( is_admin() ) {
            return false;
        }

        // ログインしていない場合は表示しない
        if ( ! is_user_logged_in() ) {
            return false;
        }

        // KantanPro管理権限がない場合は表示しない
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        // 寄付設定を取得
        $donation_settings = get_option( 'ktp_donation_settings', array() );

        // フロントエンド通知が無効の場合は表示しない
        if ( empty( $donation_settings['frontend_notice_enabled'] ) ) {
            return false;
        }

        // ユーザーが通知を拒否しているかチェック
        $user_id = get_current_user_id();
        $dismissed_until = get_user_meta( $user_id, 'ktpwp_donation_notice_dismissed_until', true );
        
        if ( ! empty( $dismissed_until ) ) {
            $dismissed_timestamp = strtotime( $dismissed_until );
            if ( $dismissed_timestamp > current_time( 'timestamp' ) ) {
                return false;
            }
        }

        // 表示間隔をチェック
        $interval = isset( $donation_settings['notice_display_interval'] ) ? intval( $donation_settings['notice_display_interval'] ) : 7;
        
        if ( $interval > 0 ) {
            $last_displayed = get_user_meta( $user_id, 'ktpwp_donation_notice_last_displayed', true );
            if ( ! empty( $last_displayed ) ) {
                $last_timestamp = strtotime( $last_displayed );
                $days_since_last = ( current_time( 'timestamp' ) - $last_timestamp ) / DAY_IN_SECONDS;
                if ( $days_since_last < $interval ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 寄付通知を表示
     */
    public function display_donation_notice() {
        if ( ! $this->should_display_notice() ) {
            return;
        }

        $donation_settings = get_option( 'ktp_donation_settings', array() );
        $message = isset( $donation_settings['notice_message'] ) ? $donation_settings['notice_message'] : 'このサイトの運営にご協力いただける方は、寄付をお願いいたします。';
        
        // 寄付URLを取得（空欄の場合はデフォルトURLを使用）
        $donation_url = ! empty( $donation_settings['donation_url'] ) ? esc_url( $donation_settings['donation_url'] ) : 'https://www.kantanpro.com/donation';

        ?>
        <div id="ktpwp-donation-notice" class="ktpwp-donation-notice" style="display: none;">
            <div class="ktpwp-notice-content">
                <span class="ktpwp-notice-icon">💝</span>
                <span class="ktpwp-notice-message"><?php echo esc_html( $message ); ?></span>
                <div class="ktpwp-notice-actions">
                    <a href="<?php echo esc_url( $donation_url ); ?>" class="ktpwp-notice-donate-btn" target="_blank" rel="noopener"><?php esc_html_e( '寄付する', 'ktpwp' ); ?></a>
                    <button type="button" class="ktpwp-notice-dismiss-btn" aria-label="<?php esc_attr_e( '閉じる', 'ktpwp' ); ?>">×</button>
                </div>
            </div>
        </div>

        <style>
        .ktpwp-donation-notice {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            max-width: 400px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .ktpwp-notice-content {
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ktpwp-notice-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .ktpwp-notice-message {
            flex: 1;
            font-size: 14px;
            line-height: 1.4;
            color: #333;
        }

        .ktpwp-notice-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .ktpwp-notice-donate-btn {
            background: #0073aa;
            color: #fff;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .ktpwp-notice-donate-btn:hover {
            background: #005a87;
            color: #fff;
        }

        .ktpwp-notice-dismiss-btn {
            background: none;
            border: none;
            color: #666;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .ktpwp-notice-dismiss-btn:hover {
            background: #f0f0f0;
            color: #333;
        }

        @media (max-width: 768px) {
            .ktpwp-donation-notice {
                bottom: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
            
            .ktpwp-notice-content {
                padding: 12px;
            }
            
            .ktpwp-notice-message {
                font-size: 13px;
            }
        }
        </style>
        <?php
    }

    /**
     * スクリプトとスタイルを読み込み
     */
    public function enqueue_scripts() {
        if ( ! $this->should_display_notice() ) {
            return;
        }

        wp_enqueue_script(
            'ktpwp-donation-notice',
            plugin_dir_url( dirname( __FILE__ ) ) . 'js/ktpwp-donation-notice.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_localize_script(
            'ktpwp-donation-notice',
            'ktpwp_donation_notice',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ktpwp_donation_notice_nonce' ),
            )
        );
    }

    /**
     * AJAXで通知を拒否
     */
    public function ajax_dismiss_notice() {
        // ノンスをチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_donation_notice_nonce' ) ) {
            wp_die( 'Invalid nonce' );
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_die( 'User not logged in' );
        }

        // 拒否期間を設定（月に1回表示）
        $dismissed_until = date( 'Y-m-d H:i:s', strtotime( '+1 month' ) );
        update_user_meta( $user_id, 'ktpwp_donation_notice_dismissed_until', $dismissed_until );

        // 最後に表示した日時を記録
        update_user_meta( $user_id, 'ktpwp_donation_notice_last_displayed', current_time( 'mysql' ) );

        wp_send_json_success();
    }
}

// インスタンスを初期化
KTPWP_Donation_Notice::get_instance(); 