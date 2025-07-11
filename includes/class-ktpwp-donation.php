<?php
/**
 * Donation management class for KTPWP plugin
 *
 * Handles donation functionality including Stripe integration,
 * donation tracking, and frontend display.
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
 * Donation management class
 *
 * @since 1.0.0
 */
class KTPWP_Donation {
    
    /**
     * Single instance of the class
     *
     * @var KTPWP_Donation
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return KTPWP_Donation
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // 寄付データテーブル作成
        add_action( 'ktpwp_upgrade', array( $this, 'create_donation_tables' ) );
        
        // ショートコード登録
        add_shortcode( 'ktpwp_donation', array( $this, 'render_donation_form' ) );
        
        // AJAX処理
        add_action( 'wp_ajax_ktpwp_create_payment_intent', array( $this, 'create_payment_intent' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_create_payment_intent', array( $this, 'create_payment_intent' ) );
        
        add_action( 'wp_ajax_ktpwp_confirm_donation', array( $this, 'confirm_donation' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_confirm_donation', array( $this, 'confirm_donation' ) );
        
        add_action( 'wp_ajax_ktpwp_get_donation_progress', array( $this, 'get_donation_progress' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_get_donation_progress', array( $this, 'get_donation_progress' ) );
        
        // フロントエンド通知のAJAX処理
        add_action( 'wp_ajax_ktpwp_dismiss_donation_notice', array( $this, 'dismiss_donation_notice' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_dismiss_donation_notice', array( $this, 'dismiss_donation_notice' ) );
        
        // 寄付完了確認のAJAX処理
        add_action( 'wp_ajax_ktpwp_check_donation_completion', array( $this, 'check_donation_completion' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_check_donation_completion', array( $this, 'check_donation_completion' ) );
        
        // 寄付完了後のフック
        add_action( 'ktpwp_donation_completed', array( $this, 'send_thank_you_email' ) );
        
        // スクリプトとスタイルの読み込み
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // フロントエンド通知の表示
        add_action( 'wp_footer', array( $this, 'display_frontend_notice' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_notice_scripts' ) );
    }
    
    /**
     * 寄付関連テーブルの作成
     *
     * @since 1.0.0
     */
    public function create_donation_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ktp_donations';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'JPY',
            donor_name varchar(255) DEFAULT '',
            donor_email varchar(255) DEFAULT '',
            donor_message text,
            stripe_payment_intent_id varchar(255) DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        // テーブル作成ログ
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Created donations table' );
        }
    }
    
    /**
     * スクリプトとスタイルの読み込み
     *
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        // 寄付フォームがあるページでのみ読み込み
        if ( $this->has_donation_shortcode() ) {
            wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
            wp_enqueue_script( 
                'ktpwp-donation', 
                plugin_dir_url( __DIR__ ) . 'js/ktpwp-donation.js', 
                array( 'jquery', 'stripe-js' ), 
                KTPWP_PLUGIN_VERSION, 
                true 
            );
            
            // Ajax URLとnonceを渡す
            wp_localize_script( 'ktpwp-donation', 'ktpwp_donation', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ktpwp_donation_nonce' ),
                'stripe_publishable_key' => $this->get_stripe_publishable_key(),
                'currency' => 'jpy'
            ) );
            
            wp_enqueue_style( 
                'ktpwp-donation', 
                plugin_dir_url( __DIR__ ) . 'css/ktpwp-donation.css', 
                array(), 
                KTPWP_PLUGIN_VERSION 
            );
        }
    }

    /**
     * フロントエンド通知用スクリプトとスタイルの読み込み
     *
     * @since 1.0.0
     */
    public function enqueue_frontend_notice_scripts() {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Donation: enqueue_frontend_notice_scripts() called' );
            error_log( 'KTPWP Donation: is_admin() = ' . ( is_admin() ? 'true' : 'false' ) );
            error_log( 'KTPWP Donation: should_show_frontend_notice() = ' . ( $this->should_show_frontend_notice() ? 'true' : 'false' ) );
        }
        
        // 管理画面以外で寄付通知が有効な場合のみ読み込み
        $should_enqueue = ! is_admin() && $this->should_show_frontend_notice();
        
        if ( $should_enqueue ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: enqueuing frontend notice scripts' );
            }
            
            // 寄付通知用スクリプト
            wp_enqueue_script( 
                'ktpwp-donation-notice', 
                plugin_dir_url( __DIR__ ) . 'js/ktpwp-donation-notice.js', 
                array( 'jquery' ), 
                KTPWP_PLUGIN_VERSION, 
                true 
            );
            
            // Ajax URLとnonceを渡す
            wp_localize_script( 'ktpwp-donation-notice', 'ktpwp_donation_notice', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ktpwp_donation_notice_nonce' ),
                'dismiss_text' => __( '閉じる', 'ktpwp' ),
                'donate_text' => __( '寄付する', 'ktpwp' ),
                'admin_only' => true
            ) );
            
            // 寄付通知用スタイル
            wp_enqueue_style( 
                'ktpwp-donation-notice', 
                plugin_dir_url( __DIR__ ) . 'css/ktpwp-donation-notice.css', 
                array(), 
                KTPWP_PLUGIN_VERSION 
            );
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: not enqueuing frontend notice scripts' );
            }
        }
    }
    
    /**
     * 管理画面用スクリプトとスタイルの読み込み
     *
     * @since 1.0.0
     */
    public function enqueue_admin_scripts( $hook ) {
        // KantanProの設定ページでのみ読み込み
        if ( strpos( $hook, 'ktp-settings' ) !== false || strpos( $hook, 'ktp-' ) !== false ) {
            wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
            wp_enqueue_script( 
                'ktpwp-donation', 
                plugin_dir_url( __DIR__ ) . 'js/ktpwp-donation.js', 
                array( 'jquery', 'stripe-js' ), 
                KTPWP_PLUGIN_VERSION, 
                true 
            );
            
            // Ajax URLとnonceを渡す
            wp_localize_script( 'ktpwp-donation', 'ktpwp_donation', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ktpwp_donation_nonce' ),
                'stripe_publishable_key' => $this->get_stripe_publishable_key(),
                'currency' => 'jpy'
            ) );
            
            wp_enqueue_style( 
                'ktpwp-donation', 
                plugin_dir_url( __DIR__ ) . 'css/ktpwp-donation.css', 
                array(), 
                KTPWP_PLUGIN_VERSION 
            );
        }
    }

    /**
     * 現在のページに寄付ショートコードがあるかチェック
     *
     * @since 1.0.0
     * @return bool
     */
    private function has_donation_shortcode() {
        global $post;
        return is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ktpwp_donation' );
    }
    
    /**
     * 寄付フォームのレンダリング
     *
     * @since 1.0.0
     * @param array $atts ショートコード属性
     * @return string HTML出力
     */
    public function render_donation_form( $atts = array() ) {
        $atts = shortcode_atts( array(
            'title' => 'KantanProの開発を支援する',
            'description' => 'このプラグインが役に立った場合、継続的な開発のためにご寄付をお願いします。',
            'amounts' => '500,1000,3000,5000',
            'show_progress' => 'true'
        ), $atts );
        
        // 寄付設定を取得
        $donation_settings = get_option( 'ktp_donation_settings', array() );
        if ( empty( $donation_settings['enabled'] ) ) {
            return '';
        }
        
        $stripe_settings = get_option( 'ktp_payment_settings', array() );
        if ( empty( $stripe_settings['stripe_publishable_key'] ) ) {
            return '<p>寄付機能の設定が完了していません。</p>';
        }
        
        ob_start();
        ?>
        <div id="ktpwp-donation-form" class="ktpwp-donation-container">
            <div class="ktpwp-donation-header">
                <h3><?php echo esc_html( $atts['title'] ); ?></h3>
                <p><?php echo esc_html( $atts['description'] ); ?></p>
            </div>
            
            <?php if ( $atts['show_progress'] === 'true' ): ?>
            <div class="ktpwp-donation-progress">
                <h4>今月の目標達成状況</h4>
                <div class="ktpwp-progress-bar">
                    <div class="ktpwp-progress-fill" style="width: <?php echo esc_attr( $this->get_monthly_progress() ); ?>%"></div>
                </div>
                <p>¥<?php echo number_format( $this->get_monthly_total() ); ?> / ¥<?php echo number_format( $donation_settings['monthly_goal'] ?? 10000 ); ?></p>
            </div>
            <?php endif; ?>
            
            <form id="ktpwp-donation-form-element">
                <div class="ktpwp-donation-amounts">
                    <?php 
                    $amounts = explode( ',', $atts['amounts'] );
                    foreach ( $amounts as $amount ): 
                        $amount = intval( trim( $amount ) );
                        if ( $amount > 0 ):
                    ?>
                    <button type="button" class="ktpwp-amount-btn" data-amount="<?php echo esc_attr( $amount ); ?>">
                        ¥<?php echo number_format( $amount ); ?>
                    </button>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                
                <div class="ktpwp-custom-amount">
                    <label for="ktpwp-custom-amount">カスタム金額：</label>
                    <input type="number" id="ktpwp-custom-amount" min="100" step="100" placeholder="100">
                </div>
                
                <div class="ktpwp-donor-info">
                    <input type="text" id="ktpwp-donor-name" placeholder="お名前（任意）">
                    <input type="email" id="ktpwp-donor-email" placeholder="メールアドレス（任意）">
                    <textarea id="ktpwp-donor-message" placeholder="メッセージ（任意）"></textarea>
                </div>
                
                <div id="ktpwp-card-element">
                    <!-- Stripe Elements will create form elements here -->
                </div>
                
                <button type="submit" id="ktpwp-donate-btn" class="ktpwp-btn-primary">
                    寄付する
                </button>
                
                <div id="ktpwp-donation-messages"></div>
            </form>
            
            <div class="ktpwp-donation-usage">
                <h4>寄付金の使途</h4>
                <ul>
                    <li>サーバー運営費</li>
                    <li>開発・保守費用</li>
                    <li>新機能追加</li>
                    <li>セキュリティアップデート</li>
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Stripe Payment Intent作成
     *
     * @since 1.0.0
     */
    public function create_payment_intent() {
        try {
            // セキュリティチェック
            if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_donation_nonce' ) ) {
                throw new Exception( 'セキュリティ検証に失敗しました。' );
            }
            
            $amount = intval( $_POST['amount'] );
            $donor_name = sanitize_text_field( $_POST['donor_name'] ?? '' );
            $donor_email = sanitize_email( $_POST['donor_email'] ?? '' );
            $donor_message = sanitize_textarea_field( $_POST['donor_message'] ?? '' );
            
            if ( $amount < 100 ) {
                throw new Exception( '最小寄付額は100円です。' );
            }
            
            // Stripe設定を取得
            $stripe_settings = get_option( 'ktp_payment_settings', array() );
            $secret_key = $this->decrypt_api_key( $stripe_settings['stripe_secret_key'] ?? '' );
            
            if ( empty( $secret_key ) ) {
                throw new Exception( 'Stripe設定が完了していません。' );
            }
            
            // Stripe SDK初期化
            if ( file_exists( KTPWP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
                require_once KTPWP_PLUGIN_DIR . 'vendor/autoload.php';
            } else {
                throw new Exception( 'Stripe SDKが見つかりません。' );
            }
            
            \Stripe\Stripe::setApiKey( $secret_key );
            
            // Payment Intent作成
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => 'jpy',
                'description' => 'KantanPro開発支援寄付',
                'metadata' => [
                    'donor_name' => $donor_name,
                    'donor_email' => $donor_email,
                    'plugin' => 'KantanPro',
                    'type' => 'donation'
                ]
            ]);
            
            // 寄付レコードを作成（pending状態）
            $donation_id = $this->create_donation_record( $amount, $donor_name, $donor_email, $donor_message, $intent->id );
            
            wp_send_json_success([
                'client_secret' => $intent->client_secret,
                'donation_id' => $donation_id
            ]);
            
        } catch ( Exception $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }
    
    /**
     * 寄付完了確認
     *
     * @since 1.0.0
     */
    public function confirm_donation() {
        try {
            if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_donation_nonce' ) ) {
                throw new Exception( 'セキュリティ検証に失敗しました。' );
            }
            
            $donation_id = intval( $_POST['donation_id'] );
            $payment_intent_id = sanitize_text_field( $_POST['payment_intent_id'] );
            
            // 寄付レコードを更新
            $this->update_donation_status( $donation_id, 'completed' );
            
            // 寄付完了フック実行
            do_action( 'ktpwp_donation_completed', $donation_id );
            
            wp_send_json_success([
                'message' => 'ご寄付ありがとうございます！'
            ]);
            
        } catch ( Exception $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }
    
    /**
     * 寄付進捗の取得（AJAX用）
     *
     * @since 1.0.0
     */
    public function get_donation_progress() {
        try {
            if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_donation_nonce' ) ) {
                throw new Exception( 'セキュリティ検証に失敗しました。' );
            }
            
            $donation_settings = get_option( 'ktp_donation_settings', array() );
            $monthly_goal = intval( $donation_settings['monthly_goal'] ?? 10000 );
            $monthly_total = $this->get_monthly_total();
            $progress = $this->get_monthly_progress();
            
            wp_send_json_success([
                'total' => $monthly_total,
                'goal' => $monthly_goal,
                'progress' => $progress
            ]);
            
        } catch ( Exception $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }
    
    /**
     * 寄付完了確認（AJAX用）
     *
     * @since 1.0.0
     */
    public function check_donation_completion() {
        try {
            if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_donation_notice_nonce' ) ) {
                throw new Exception( 'セキュリティ検証に失敗しました。' );
            }
            
            // ログインユーザーのみ対象
            if ( ! is_user_logged_in() ) {
                wp_send_json_error( 'ゲストユーザーは対象外です。' );
                return;
            }
            
            $user_id = get_current_user_id();
            $user = get_userdata( $user_id );
            
            // KantanPro管理権限または管理者権限がない場合はエラー
            if ( ! $user->has_cap( 'ktpwp_access' ) && ! $user->has_cap( 'manage_options' ) ) {
                wp_send_json_error( '権限がありません。' );
                return;
            }
            
            // Stripeでの実際の入金確認を実行
            $has_donated = $this->verify_stripe_payment( $user_id );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: check_donation_completion for user ' . $user_id . ', has_donated = ' . ( $has_donated ? 'true' : 'false' ) );
            }
            
            if ( $has_donated ) {
                wp_send_json_success([
                    'message' => 'ご寄付ありがとうございました！',
                    'has_donated' => true
                ]);
            } else {
                wp_send_json_success([
                    'message' => '',
                    'has_donated' => false
                ]);
            }
            
        } catch ( Exception $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }
    
    /**
     * 寄付レコードの作成
     *
     * @since 1.0.0
     * @param int $amount 寄付金額
     * @param string $donor_name 寄付者名
     * @param string $donor_email 寄付者メール
     * @param string $donor_message 寄付者メッセージ
     * @param string $payment_intent_id Stripe Payment Intent ID
     * @return int|false 寄付ID
     */
    private function create_donation_record( $amount, $donor_name, $donor_email, $donor_message, $payment_intent_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ktp_donations';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'amount' => $amount,
                'donor_name' => $donor_name,
                'donor_email' => $donor_email,
                'donor_message' => $donor_message,
                'stripe_payment_intent_id' => $payment_intent_id,
                'status' => 'pending'
            ),
            array( '%f', '%s', '%s', '%s', '%s', '%s' )
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * 寄付ステータスの更新
     *
     * @since 1.0.0
     * @param int $donation_id 寄付ID
     * @param string $status 新しいステータス
     * @return bool
     */
    private function update_donation_status( $donation_id, $status ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ktp_donations';
        
        return $wpdb->update(
            $table_name,
            array( 'status' => $status ),
            array( 'id' => $donation_id ),
            array( '%s' ),
            array( '%d' )
        );
    }
    
    /**
     * 今月の寄付合計を取得
     *
     * @since 1.0.0
     * @return int
     */
    public function get_monthly_total() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ktp_donations';
        $start_of_month = date( 'Y-m-01' );
        
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM $table_name WHERE status = 'completed' AND created_at >= %s",
                $start_of_month
            )
        );
        
        return intval( $total );
    }
    
    /**
     * 今月の目標達成率を取得
     *
     * @since 1.0.0
     * @return float
     */
    public function get_monthly_progress() {
        $donation_settings = get_option( 'ktp_donation_settings', array() );
        $monthly_goal = intval( $donation_settings['monthly_goal'] ?? 10000 );
        $monthly_total = $this->get_monthly_total();
        
        if ( $monthly_goal <= 0 ) {
            return 0;
        }
        
        return min( 100, ( $monthly_total / $monthly_goal ) * 100 );
    }
    
    /**
     * Stripe公開キーを取得
     *
     * @since 1.0.0
     * @return string
     */
    private function get_stripe_publishable_key() {
        $payment_settings = get_option( 'ktp_payment_settings', array() );
        return $payment_settings['stripe_publishable_key'] ?? '';
    }
    
    /**
     * API キーの復号化
     *
     * @since 1.0.0
     * @param string $encrypted_key 暗号化されたキー
     * @return string
     */
    private function decrypt_api_key( $encrypted_key ) {
        if ( empty( $encrypted_key ) ) {
            return '';
        }
        // 強固な暗号化方式で復号を試行
        $decrypted = KTP_Settings::strong_decrypt_static( $encrypted_key );
        if ( $decrypted !== false && ! empty( $decrypted ) ) {
            return $decrypted;
        }
        // 古いbase64方式の場合は自動移行
        $maybe_old = base64_decode( $encrypted_key );
        if ( $maybe_old && strpos($maybe_old, 'sk_') === 0 ) {
            // 新しい強固な暗号化で再保存
            $new_encrypted = KTP_Settings::strong_encrypt_static( $maybe_old );
            $stripe_settings = get_option( 'ktp_payment_settings', array() );
            $stripe_settings['stripe_secret_key'] = $new_encrypted;
            update_option( 'ktp_payment_settings', $stripe_settings );
            return $maybe_old;
        }
        return '';
    }
    
    /**
     * お礼メール送信
     *
     * @since 1.0.0
     * @param int $donation_id 寄付ID
     */
    public function send_thank_you_email( $donation_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ktp_donations';
        $donation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $donation_id
            )
        );
        
        if ( ! $donation || empty( $donation->donor_email ) ) {
            return;
        }
        
        $subject = 'KantanPro開発支援へのご寄付ありがとうございます';
        $message = "
{$donation->donor_name} 様

この度は、KantanProの開発継続にご支援いただき、心から感謝申し上げます。

寄付金額：¥" . number_format( $donation->amount ) . "

いただいたご支援は、以下の用途に大切に使わせていただきます：
- サーバー運営費
- 開発・保守作業
- 新機能の追加
- セキュリティアップデート

今後ともKantanProをよろしくお願いいたします。

KantanPro開発チーム
";
        
        wp_mail( $donation->donor_email, $subject, $message );
    }

    /**
     * 現在のページにKantanProが設置されているかチェック
     *
     * @since 1.0.0
     * @return bool
     */
    private function has_ktpwp_content() {
        global $post;
        
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return false;
        }
        
        // KantanProのショートコードが含まれているかチェック
        $ktpwp_shortcodes = array(
            'ktpwp_all_tab',
            'ktpwp_client_tab',
            'ktpwp_order_tab',
            'ktpwp_service_tab',
            'ktpwp_supplier_tab',
            'ktpwp_report_tab',
            'ktpwp_donation'
        );
        
        foreach ( $ktpwp_shortcodes as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Donation: found shortcode ' . $shortcode . ' in post ' . $post->ID );
                }
                return true;
            }
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Donation: no KantanPro shortcodes found in post ' . $post->ID );
        }
        
        return false;
    }

    /**
     * フロントエンド通知を表示すべきかどうかを判定
     *
     * @since 1.0.0
     * @return bool
     */
    private function should_show_frontend_notice() {
        // デバッグログ
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Donation: should_show_frontend_notice() called' );
        }
        
        // 寄付設定を取得
        $donation_settings = get_option( 'ktp_donation_settings', array() );
        
        // デバッグログ
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Donation: donation_settings = ' . wp_json_encode( $donation_settings ) );
        }
        
        // 寄付機能が無効またはフロントエンド通知が無効の場合
        if ( empty( $donation_settings['enabled'] ) || empty( $donation_settings['frontend_notice_enabled'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: donation disabled or frontend notice disabled' );
                error_log( 'KTPWP Donation: enabled = ' . ( isset( $donation_settings['enabled'] ) ? $donation_settings['enabled'] : 'not set' ) );
                error_log( 'KTPWP Donation: frontend_notice_enabled = ' . ( isset( $donation_settings['frontend_notice_enabled'] ) ? $donation_settings['frontend_notice_enabled'] : 'not set' ) );
            }
            return false;
        }
        
        // Stripe設定を確認
        if ( empty( $this->get_stripe_publishable_key() ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: Stripe publishable key not found' );
                $stripe_settings = get_option( 'ktp_payment_settings', array() );
                error_log( 'KTPWP Donation: stripe_settings = ' . wp_json_encode( $stripe_settings ) );
            }
            return false;
        }
        
        // ログインユーザーのみ対象
        if ( ! is_user_logged_in() ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: user not logged in' );
            }
            return false;
        }
        
        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );
        
        // KantanPro管理権限（ktpwp_access）または管理者権限を持つユーザーのみ対象
        if ( ! $user->has_cap( 'ktpwp_access' ) && ! $user->has_cap( 'manage_options' ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: user does not have required capabilities' );
                error_log( 'KTPWP Donation: user_id = ' . $user_id . ', user_login = ' . $user->user_login );
                error_log( 'KTPWP Donation: has ktpwp_access = ' . ( $user->has_cap( 'ktpwp_access' ) ? 'true' : 'false' ) );
                error_log( 'KTPWP Donation: has manage_options = ' . ( $user->has_cap( 'manage_options' ) ? 'true' : 'false' ) );
            }
            return false;
        }
        
        // フロントエンド通知が有効でない場合は表示しない
        if ( empty( $donation_settings['frontend_notice_enabled'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: frontend notice disabled' );
            }
            return false;
        }
        
        // ユーザーが寄付したことがある場合は表示しない
        if ( $this->user_has_donated( $user_id ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: user has already donated' );
            }
            return false;
        }
        
        // ユーザーが通知を拒否している場合は表示しない
        if ( $this->user_has_dismissed_notice( $user_id ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: user has dismissed notice' );
            }
            return false;
        }
        
        // 現在のページにKantanProが設置されているかチェック
        if ( ! $this->has_ktpwp_content() ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: no KantanPro content found on current page' );
            }
            return false;
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Donation: should show frontend notice = true' );
        }
        
        return true;
    }

    /**
     * ユーザーが寄付したことがあるかチェック
     *
     * @since 1.0.0
     * @param int $user_id User ID
     * @return bool
     */
    private function user_has_donated( $user_id ) {
        global $wpdb;
        
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: user not found for user_id = ' . $user_id );
            }
            return false;
        }
        
        $table_name = $wpdb->prefix . 'ktp_donations';
        $count = $wpdb->get_var( $wpdb->prepare( 
            "SELECT COUNT(*) FROM $table_name WHERE donor_email = %s AND status = 'completed'",
            $user->user_email
        ) );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Donation: donation count for user ' . $user->user_email . ' = ' . $count );
        }
        
        return $count > 0;
    }

    /**
     * Stripeでの実際の入金確認を実行
     *
     * @since 1.0.0
     * @param int $user_id User ID
     * @return bool
     */
    private function verify_stripe_payment( $user_id ) {
        global $wpdb;
        
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: user not found for user_id = ' . $user_id );
            }
            return false;
        }
        
        // 最近の寄付レコードを取得（過去1時間以内）
        $table_name = $wpdb->prefix . 'ktp_donations';
        $recent_donation = $wpdb->get_row( $wpdb->prepare( 
            "SELECT * FROM $table_name WHERE donor_email = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY created_at DESC LIMIT 1",
            $user->user_email
        ) );
        
        if ( ! $recent_donation ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: no recent donation found for user ' . $user->user_email );
            }
            return false;
        }
        
        // Stripe設定を取得
        $stripe_settings = get_option( 'ktp_payment_settings', array() );
        $secret_key = $this->decrypt_api_key( $stripe_settings['stripe_secret_key'] ?? '' );
        
        if ( empty( $secret_key ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: Stripe secret key not found' );
            }
            return false;
        }
        
        try {
            // Stripe SDK初期化
            if ( file_exists( KTPWP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
                require_once KTPWP_PLUGIN_DIR . 'vendor/autoload.php';
            } else {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Donation: Stripe SDK not found' );
                }
                return false;
            }
            
            \Stripe\Stripe::setApiKey( $secret_key );
            
            // Payment Intentの状態を確認
            $payment_intent = \Stripe\PaymentIntent::retrieve( $recent_donation->stripe_payment_intent_id );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: Payment Intent status = ' . $payment_intent->status );
            }
            
            // 支払いが成功している場合
            if ( $payment_intent->status === 'succeeded' ) {
                // データベースのステータスも更新
                if ( $recent_donation->status !== 'completed' ) {
                    $this->update_donation_status( $recent_donation->id, 'completed' );
                    
                    // 寄付完了フック実行
                    do_action( 'ktpwp_donation_completed', $recent_donation->id );
                }
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Donation: Payment confirmed for user ' . $user->user_email );
                }
                
                return true;
            }
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: Payment not succeeded, status = ' . $payment_intent->status );
            }
            
            return false;
            
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: Error verifying Stripe payment: ' . $e->getMessage() );
            }
            return false;
        }
    }
    
    /**
     * ユーザーが通知を拒否しているかチェック
     *
     * @since 1.0.0
     * @param int $user_id User ID
     * @return bool
     */
    private function user_has_dismissed_notice( $user_id ) {
        $dismissed_time = get_user_meta( $user_id, 'ktpwp_donation_notice_dismissed', true );
        if ( empty( $dismissed_time ) ) {
            return false;
        }
        $donation_settings = get_option( 'ktp_donation_settings', array() );
        $interval_days = isset( $donation_settings['notice_display_interval'] ) ? intval( $donation_settings['notice_display_interval'] ) : 30;
        // --- ここからカスタム分岐 ---
        // 通知表示間隔が0かつ寄付していない場合は30日ごとに再表示
        if ( $interval_days === 0 && ! $this->user_has_donated( $user_id ) ) {
            $interval_days = 30;
        }
        // --- ここまでカスタム分岐 ---
        if ( $interval_days === 0 ) {
            return false;
        }
        $time_since_dismissed = time() - $dismissed_time;
        $interval_seconds = $interval_days * DAY_IN_SECONDS;
        $has_dismissed = $time_since_dismissed < $interval_seconds;
        return $has_dismissed;
    }

    /**
     * フロントエンド通知の表示
     *
     * @since 1.0.0
     */
    public function display_frontend_notice() {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Donation: display_frontend_notice() called' );
        }
        
        $should_display = $this->should_show_frontend_notice();
        
        if ( ! $should_display ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Donation: should_show_frontend_notice() returned false' );
            }
            return;
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Donation: displaying frontend notice' );
        }
        
        $donation_settings = get_option( 'ktp_donation_settings', array() );
        $message = isset( $donation_settings['notice_message'] ) ? $donation_settings['notice_message'] : 'このサイトの運営にご協力いただける方は、寄付をお願いいたします。';
        
        // 寄付URLを取得（設定されていない場合はデフォルトURLを使用）
        $donation_url = isset( $donation_settings['donation_url'] ) && ! empty( $donation_settings['donation_url'] ) 
            ? $donation_settings['donation_url'] 
            : 'https://www.kantanpro.com/donation';
        
        ?>
        <div id="ktpwp-donation-notice" class="ktpwp-donation-notice" style="display: none;">
            <div class="ktpwp-notice-content">
                <span class="ktpwp-notice-icon">💝</span>
                <span class="ktpwp-notice-message"><?php echo esc_html( $message ); ?></span>
                <div class="ktpwp-notice-actions">
                    <a href="<?php echo esc_url( $donation_url ); ?>" class="ktpwp-notice-donate-btn" target="_blank" rel="noopener">
                        <?php esc_html_e( '寄付する', 'ktpwp' ); ?>
                    </a>
                    <button type="button" class="ktpwp-notice-dismiss-btn" aria-label="<?php esc_attr_e( '閉じる', 'ktpwp' ); ?>">
                        ×
                    </button>
                </div>
            </div>
        </div>
        
        <!-- 寄付完了メッセージ -->
        <div id="ktpwp-donation-thanks" class="ktpwp-donation-thanks" style="display: none;">
            <div class="ktpwp-thanks-content">
                <span class="ktpwp-thanks-icon">🎉</span>
                <span class="ktpwp-thanks-message"><?php esc_html_e( 'ご寄付ありがとうございました！', 'ktpwp' ); ?></span>
                <button type="button" class="ktpwp-thanks-close" aria-label="<?php esc_attr_e( '閉じる', 'ktpwp' ); ?>">
                    ×
                </button>
            </div>
        </div>
        
        <!-- 確認中のアニメーション -->
        <div id="ktpwp-donation-checking" class="ktpwp-donation-checking" style="display: none;">
            <div class="ktpwp-checking-content">
                <div class="ktpwp-checking-spinner"></div>
                <span class="ktpwp-checking-message">確認中・・・</span>
            </div>
        </div>
        

        <?php
    }





    /**
     * 通知拒否のAJAX処理
     *
     * @since 1.0.0
     */
    public function dismiss_donation_notice() {
        // nonce検証
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_donation_notice_nonce' ) ) {
            wp_die( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
        }
        
        // ログインユーザーのみ対象
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'ゲストユーザーは対象外です。', 'ktpwp' ) ) );
            return;
        }
        
        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );
        
        // KantanPro管理権限または管理者権限がない場合はエラー
        if ( ! $user->has_cap( 'ktpwp_access' ) && ! $user->has_cap( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '権限がありません。', 'ktpwp' ) ) );
            return;
        }
        
        // ユーザーメタに拒否履歴を保存
        update_user_meta( $user_id, 'ktpwp_donation_notice_dismissed', time() );
        
        wp_send_json_success( array( 'message' => __( '通知を非表示にしました。', 'ktpwp' ) ) );
    }
} 